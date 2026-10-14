<?php
// ==========================================
// إعدادات الاتصال بنظام أودو
// ==========================================
$odoo_url = 'https://portal.marifah.edu.my';
$db       = 'Marifahdb';
$username = 'ezzittech@gmail.com';
$password = 'Abulez@2526';

// ==========================================
// دوال الاتصال
// ==========================================
function authenticate($url, $db, $login, $password) {
    $ch = curl_init($url . '/web/session/authenticate');
    $payload = json_encode([
        'jsonrpc' => '2.0', 'method' => 'call',
        'params' => ['db' => $db, 'login' => $login, 'password' => $password]
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response    = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header      = substr($response, 0, $header_size);
    curl_close($ch);
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $matches);
    $cookies = [];
    foreach ($matches[1] as $item) { parse_str($item, $c); $cookies = array_merge($cookies, $c); }
    return $cookies['session_id'] ?? '';
}

function odoo_call_kw($url, $session_id, $model, $method, $args, $kwargs = []) {
    $ch = curl_init($url . '/web/dataset/call_kw');
    $payload = json_encode([
        'jsonrpc' => '2.0', 'method' => 'call',
        'params' => ['model' => $model, 'method' => $method, 'args' => $args, 'kwargs' => $kwargs]
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Cookie: session_id=' . $session_id]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['result'] ?? null;
}

// ==========================================
// طباعة PDF الفاتورة من أودو
// ==========================================
if (isset($_GET['print_invoice_id'])) {
    $move_id    = (int)$_GET['print_invoice_id'];
    $session_id = authenticate($odoo_url, $db, $username, $password);
    if ($session_id) {
        $moves = odoo_call_kw($odoo_url, $session_id, 'account.move', 'read', [[$move_id]]);
        if (!empty($moves[0])) {
            $move     = $moves[0];
            $pdf_data = null;
            $file_name = 'Invoice_' . $move_id . '.pdf';
            if (!empty($move['invoice_pdf_report_file'])) {
                $pdf_data = $move['invoice_pdf_report_file'];
            } elseif (!empty($move['invoice_pdf_report_id'])) {
                $att_id = is_array($move['invoice_pdf_report_id']) ? $move['invoice_pdf_report_id'][0] : $move['invoice_pdf_report_id'];
                $att = odoo_call_kw($odoo_url, $session_id, 'ir.attachment', 'read', [[$att_id]], ['fields' => ['datas', 'name']]);
                if (!empty($att[0]['datas'])) { $pdf_data = $att[0]['datas']; $file_name = $att[0]['name'] ?: $file_name; }
            } elseif (!empty($move['message_main_attachment_id'])) {
                $att_id = is_array($move['message_main_attachment_id']) ? $move['message_main_attachment_id'][0] : $move['message_main_attachment_id'];
                $att = odoo_call_kw($odoo_url, $session_id, 'ir.attachment', 'read', [[$att_id]], ['fields' => ['datas', 'name']]);
                if (!empty($att[0]['datas'])) { $pdf_data = $att[0]['datas']; $file_name = $att[0]['name'] ?: $file_name; }
            }
            if ($pdf_data) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . $file_name . '"');
                echo base64_decode($pdf_data);
                exit;
            } else {
                die('لا يوجد PDF متاح لهذه الفاتورة. يرجى توليدها في أودو أولاً.');
            }
        } else { die('لم يتم العثور على الفاتورة.'); }
    } else { die('فشل الاتصال بأودو.'); }
}

// ==========================================
// جلب بيانات كشف الحساب
// ==========================================
$session_id = authenticate($odoo_url, $db, $username, $password);
$records    = [];
$error      = '';

if ($session_id) {
    $records = odoo_call_kw($odoo_url, $session_id, 'account.move.line', 'search_read', [[
        ['parent_state',    '=',  'posted'],
        ['partner_id',      '!=', false],
        ['account_id.account_type', 'in', ['asset_receivable', 'liability_payable']]
    ]], [
        'fields' => ['date', 'move_id', 'partner_id', 'name', 'debit', 'credit', 'balance', 'account_id'],
        'order'  => 'date asc',
        'limit'  => 5000
    ]) ?? [];
} else {
    $error = 'فشل الاتصال بأودو. يرجى التحقق من بيانات الدخول.';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>كشف حساب الشركاء — Marifah</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* ── Base ── */
    body { font-family: 'Tajawal', sans-serif; background: #f0f4f8; }

    /* ── Scrollbar ── */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

    /* ── Partner row ── */
    .partner-row { cursor: pointer; transition: background .15s; }
    .partner-row:hover { background: #eef2ff; }
    .partner-row.open  { background: #e0e7ff; }

    /* ── Detail row ── */
    .detail-row { display: none; }
    .detail-row.open { display: table-row-group; }

    /* ── Arrow ── */
    .arrow { transition: transform .25s; display: inline-block; }
    .partner-row.open .arrow { transform: rotate(90deg); }

    /* ── Status badges ── */
    .badge-recv { background:#dbeafe; color:#1e40af; }
    .badge-pay  { background:#fce7f3; color:#9d174d; }

    /* ── Balance colors ── */
    .bal-pos { color: #dc2626; }   /* مدين = أحمر */
    .bal-neg { color: #16a34a; }   /* دائن = أخضر */
    .bal-zero{ color: #6b7280; }

    /* ══════════════════════════════════
       PRINT STYLES
    ══════════════════════════════════ */
    @media print {
        body { background: white !important; font-family: 'Tajawal', sans-serif; }
        .no-print { display: none !important; }
        #print-area { display: block !important; }
        @page { margin: 1.5cm 2cm; size: A4; }
    }
    #print-area { display: none; }
</style>
</head>
<body class="min-h-screen antialiased">

<!-- ══════════════════════════════════════════════════
     SCREEN UI
══════════════════════════════════════════════════ -->
<div class="no-print max-w-screen-xl mx-auto px-4 py-6 space-y-5">

    <!-- ── Header ── -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-5">
        <div class="flex flex-wrap items-center justify-between gap-4">

            <!-- Title -->
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-slate-800 flex items-center justify-center shadow-md flex-shrink-0">
                    <i class="fas fa-book-open text-white text-lg"></i>
                </div>
                <div>
                    <h1 class="text-xl font-extrabold text-gray-900 leading-tight">كشف حساب الشركاء</h1>
                    <p class="text-gray-400 text-xs mt-0.5">Partner Ledger — Marifah International School — <?= date('d/m/Y H:i') ?></p>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2 flex-wrap">
                <?php if ($error): ?>
                    <span class="bg-red-50 text-red-600 border border-red-100 px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5">
                        <i class="fas fa-circle-xmark"></i> <?= htmlspecialchars($error) ?>
                    </span>
                <?php else: ?>
                    <span class="bg-emerald-50 text-emerald-700 border border-emerald-100 px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5">
                        <i class="fas fa-circle-check"></i> متصل بنجاح
                    </span>
                <?php endif; ?>
                <a href="index.html"
                   class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 rounded-xl text-sm font-bold transition-colors flex items-center gap-2 shadow-sm">
                    <i class="fas fa-home"></i> الرئيسية
                </a>
            </div>
        </div>
    </div>

    <!-- ── Stats bar ── -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4" id="statsBar">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
            <p class="text-xs font-semibold text-gray-400 mb-1">إجمالي الشركاء</p>
            <p class="text-2xl font-extrabold text-gray-900" id="st-partners">—</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
            <p class="text-xs font-semibold text-gray-400 mb-1">إجمالي الحركات</p>
            <p class="text-2xl font-extrabold text-gray-900" id="st-lines">—</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
            <p class="text-xs font-semibold text-gray-400 mb-1">إجمالي المدين</p>
            <p class="text-xl font-extrabold text-blue-700" id="st-debit" dir="ltr">—</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
            <p class="text-xs font-semibold text-gray-400 mb-1">إجمالي الدائن</p>
            <p class="text-xl font-extrabold text-emerald-600" id="st-credit" dir="ltr">—</p>
        </div>
    </div>

    <!-- ── Filters ── -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-6 py-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            <!-- Search -->
            <div>
                <label class="block text-xs font-bold text-gray-500 mb-1.5">بحث بالاسم</label>
                <div class="relative">
                    <input type="text" id="searchInput" placeholder="اكتب اسم الشريك..."
                        class="w-full border border-gray-200 rounded-xl py-2.5 pr-9 pl-3 text-sm focus:ring-2 focus:ring-slate-400 outline-none transition-all">
                    <i class="fas fa-magnifying-glass absolute top-3 right-3 text-gray-400 text-sm"></i>
                </div>
            </div>

            <!-- Type -->
            <div>
                <label class="block text-xs font-bold text-gray-500 mb-1.5">نوع الحساب</label>
                <div class="relative">
                    <select id="typeFilter"
                        class="w-full border border-gray-200 rounded-xl py-2.5 px-3 pl-8 text-sm appearance-none focus:ring-2 focus:ring-slate-400 outline-none bg-white">
                        <option value="all">الكل (مدين + دائن)</option>
                        <option value="receivable">ذمم مدينة (Receivable)</option>
                        <option value="payable">ذمم دائنة (Payable)</option>
                    </select>
                    <i class="fas fa-chevron-down absolute top-3.5 left-3 text-gray-400 text-xs pointer-events-none"></i>
                </div>
            </div>

            <!-- Date From -->
            <div>
                <label class="block text-xs font-bold text-gray-500 mb-1.5">من تاريخ</label>
                <input type="date" id="dateFrom"
                    class="w-full border border-gray-200 rounded-xl py-2.5 px-3 text-sm focus:ring-2 focus:ring-slate-400 outline-none">
            </div>

            <!-- Date To -->
            <div>
                <label class="block text-xs font-bold text-gray-500 mb-1.5">إلى تاريخ</label>
                <input type="date" id="dateTo"
                    class="w-full border border-gray-200 rounded-xl py-2.5 px-3 text-sm focus:ring-2 focus:ring-slate-400 outline-none">
            </div>
        </div>

        <div class="mt-3 flex items-center justify-between">
            <p class="text-xs text-gray-400">
                عرض <span id="visibleCount" class="font-bold text-gray-700">—</span> شريك
            </p>
            <button onclick="resetFilters()"
                class="text-xs text-slate-600 hover:text-slate-900 font-bold flex items-center gap-1 transition-colors">
                <i class="fas fa-rotate-right"></i> إعادة ضبط
            </button>
        </div>
    </div>

    <!-- ── Table ── -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto" style="max-height:65vh; overflow-y:auto;">
            <table class="w-full text-sm border-collapse" id="mainTable">
                <thead>
                    <tr class="bg-slate-800 text-white text-right">
                        <th class="py-4 px-4 w-10 bg-slate-800 sticky top-0"></th>
                        <th class="py-4 px-5 font-bold whitespace-nowrap bg-slate-800 sticky top-0 text-right">الشريك</th>
                        <th class="py-4 px-5 font-bold whitespace-nowrap bg-slate-800 sticky top-0 text-center">النوع</th>
                        <th class="py-4 px-5 font-bold whitespace-nowrap bg-slate-800 sticky top-0 text-center">الحركات</th>
                        <th class="py-4 px-5 font-bold whitespace-nowrap bg-slate-800 sticky top-0 text-left">إجمالي المدين</th>
                        <th class="py-4 px-5 font-bold whitespace-nowrap bg-slate-800 sticky top-0 text-left">إجمالي الدائن</th>
                        <th class="py-4 px-5 font-bold whitespace-nowrap bg-slate-800 sticky top-0 text-left">الرصيد</th>
                        <th class="py-4 px-4 font-bold whitespace-nowrap bg-slate-800 sticky top-0 text-center">إجراء</th>
                    </tr>
                </thead>
                <tbody id="ledgerBody"></tbody>
            </table>
        </div>
    </div>

</div><!-- /no-print -->


<!-- ══════════════════════════════════════════════════
     PRINT AREA  (كشف الحساب للطباعة)
══════════════════════════════════════════════════ -->
<div id="print-area">

    <!-- Header -->
    <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #1e293b; padding-bottom:20px; margin-bottom:24px;">
        <img src="https://cdn.prod.website-files.com/642e38eb59ef94ba3c2bf35b/68696ec0e6fe2cab7b377c39_724c268af6d0a34b14c1961f01f18585_Marifah_CMYK-p-500.png"
             alt="Marifah Logo" style="width:110px; object-fit:contain;">
        <div style="text-align:right; font-family:'Tajawal',sans-serif;">
            <p style="font-size:17px; font-weight:800; color:#0f172a; margin:0;">Marifah International School</p>
            <p style="font-size:11px; color:#475569; margin:4px 0 0; line-height:1.6;">
                Level 3 South City Plaza, Persiaran Serdang Perdana,<br>
                Seksyen 1, 43300 Seri Kembangan, Selangor
            </p>
            <p style="font-size:11px; color:#0f172a; margin:6px 0 0; font-weight:600;">info@marifah.edu.my | +60 18-988 9302</p>
        </div>
    </div>

    <!-- Title -->
    <div style="text-align:center; margin-bottom:24px;">
        <p style="font-size:10px; letter-spacing:3px; color:#64748b; text-transform:uppercase; font-family:'Tajawal',sans-serif; margin:0 0 4px;">
            Statement of Account
        </p>
        <h1 id="print-partner-name"
            style="font-size:24px; font-weight:800; color:#0f172a; margin:0; font-family:'Tajawal',sans-serif;"></h1>
    </div>

    <!-- Meta -->
    <div style="display:flex; justify-content:space-between; margin-bottom:20px; font-family:'Tajawal',sans-serif; font-size:12px; color:#475569;">
        <div>
            <span style="font-weight:700; color:#0f172a;">نوع الحساب: </span>
            <span id="print-acc-type">—</span>
        </div>
        <div>
            <span style="font-weight:700; color:#0f172a;">تاريخ الكشف: </span>
            <span id="print-date"></span>
        </div>
    </div>

    <!-- Table -->
    <table style="width:100%; border-collapse:collapse; font-family:'Tajawal',sans-serif; font-size:12px; direction:rtl;">
        <thead>
            <tr style="background:#1e293b; color:white;">
                <th style="padding:10px 12px; text-align:right; font-weight:700;">التاريخ</th>
                <th style="padding:10px 12px; text-align:right; font-weight:700;">رقم القيد</th>
                <th style="padding:10px 12px; text-align:right; font-weight:700;">الوصف</th>
                <th style="padding:10px 12px; text-align:left;  font-weight:700;">المدين</th>
                <th style="padding:10px 12px; text-align:left;  font-weight:700;">الدائن</th>
                <th style="padding:10px 12px; text-align:left;  font-weight:700;">الرصيد التراكمي</th>
            </tr>
        </thead>
        <tbody id="print-tbody"></tbody>
        <tfoot id="print-tfoot"></tfoot>
    </table>

    <!-- Summary box -->
    <div id="print-summary"
         style="margin-top:24px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; font-family:'Tajawal',sans-serif;">
    </div>

    <!-- Footer -->
    <div style="margin-top:40px; display:flex; justify-content:space-between; align-items:flex-end;
                border-top:1px solid #cbd5e1; padding-top:16px; font-family:'Tajawal',sans-serif; font-size:11px; color:#64748b;">
        <p style="margin:0;">
            هذا المستند صادر إلكترونياً ولا يحتاج إلى توقيع يدوي.<br>
            <a href="https://marifah.edu.my" style="color:#3b82f6;">marifah.edu.my</a>
        </p>
        <div style="text-align:center;">
            <div style="border-bottom:1px solid #334155; width:160px; margin-bottom:6px;"></div>
            <p style="margin:0; font-weight:700; color:#0f172a;">التوقيع المعتمد</p>
        </div>
    </div>

</div><!-- /print-area -->


<!-- ══════════════════════════════════════════════════
     JAVASCRIPT
══════════════════════════════════════════════════ -->
<script>
const RAW = <?= json_encode($records, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE) ?>;

let grouped = {};

// ── تنسيق الأرقام ────────────────────────────────
const fmt = v => parseFloat(v || 0).toLocaleString('en-MY', { minimumFractionDigits:2, maximumFractionDigits:2 });

// ── لون الرصيد ───────────────────────────────────
function balClass(v) {
    if (v > 0.005)  return 'bal-pos';
    if (v < -0.005) return 'bal-neg';
    return 'bal-zero';
}
function balLabel(v) {
    if (v > 0.005)  return `<span class="bal-pos font-bold" dir="ltr">${fmt(v)}</span> <span class="text-xs text-red-400">مدين</span>`;
    if (v < -0.005) return `<span class="bal-neg font-bold" dir="ltr">${fmt(Math.abs(v))}</span> <span class="text-xs text-emerald-500">دائن</span>`;
    return `<span class="bal-zero font-bold" dir="ltr">0.00</span>`;
}

// ── نوع الحساب ───────────────────────────────────
function accType(accName) {
    if (!accName) return 'other';
    const n = accName.toLowerCase();
    if (n.includes('receivable')) return 'receivable';
    if (n.includes('payable'))    return 'payable';
    return 'other';
}
function accBadge(type) {
    if (type === 'receivable') return `<span class="badge-recv text-xs font-bold px-2 py-0.5 rounded-full">ذمم مدينة</span>`;
    if (type === 'payable')    return `<span class="badge-pay  text-xs font-bold px-2 py-0.5 rounded-full">ذمم دائنة</span>`;
    return `<span class="bg-gray-100 text-gray-500 text-xs font-bold px-2 py-0.5 rounded-full">أخرى</span>`;
}

// ── رسم الجدول الرئيسي ───────────────────────────
function renderTable() {
    const search   = document.getElementById('searchInput').value.trim().toLowerCase();
    const typeF    = document.getElementById('typeFilter').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo   = document.getElementById('dateTo').value;

    grouped = {};

    RAW.forEach(line => {
        const aType = accType(line.account_id?.[1] ?? '');
        if (typeF === 'receivable' && aType !== 'receivable') return;
        if (typeF === 'payable'    && aType !== 'payable')    return;
        if (dateFrom && line.date < dateFrom) return;
        if (dateTo   && line.date > dateTo)   return;

        const pName = line.partner_id?.[1] ?? 'Unknown';
        const pId   = line.partner_id?.[0] ?? 0;
        if (search && !pName.toLowerCase().includes(search)) return;

        if (!grouped[pId]) {
            grouped[pId] = { name:pName, accType:aType, debit:0, credit:0, lines:[] };
        }
        grouped[pId].lines.push(line);
        grouped[pId].debit  += line.debit  || 0;
        grouped[pId].credit += line.credit || 0;
    });

    const tbody = document.getElementById('ledgerBody');
    tbody.innerHTML = '';

    let totalPartners = 0, totalLines = 0, totalDebit = 0, totalCredit = 0;

    Object.entries(grouped).forEach(([pId, p]) => {
        totalPartners++;
        totalLines  += p.lines.length;
        totalDebit  += p.debit;
        totalCredit += p.credit;
        const bal = p.debit - p.credit;

        // ── صف الشريك ──
        const partnerTr = document.createElement('tr');
        partnerTr.className = 'partner-row border-b border-gray-100';
        partnerTr.dataset.pid = pId;
        partnerTr.innerHTML = `
            <td class="py-3 px-4 text-gray-400">
                <i class="fas fa-chevron-left arrow text-xs"></i>
            </td>
            <td class="py-3 px-5">
                <p class="font-bold text-gray-800">${p.name}</p>
                <p class="text-xs text-gray-400 mt-0.5">${p.lines.length} حركة</p>
            </td>
            <td class="py-3 px-5 text-center">${accBadge(p.accType)}</td>
            <td class="py-3 px-5 text-center font-bold text-slate-600">${p.lines.length}</td>
            <td class="py-3 px-5 text-left font-bold text-blue-700" dir="ltr">${fmt(p.debit)}</td>
            <td class="py-3 px-5 text-left font-bold text-emerald-600" dir="ltr">${fmt(p.credit)}</td>
            <td class="py-3 px-5 text-left">${balLabel(bal)}</td>
            <td class="py-3 px-4 text-center">
                <button onclick="event.stopPropagation(); printStatement(${pId})"
                    class="bg-slate-700 hover:bg-slate-900 text-white px-3 py-1.5 rounded-lg text-xs font-bold transition-colors flex items-center gap-1.5 mx-auto">
                    <i class="fas fa-print"></i> كشف
                </button>
            </td>
        `;
        partnerTr.addEventListener('click', () => toggleDetails(pId, partnerTr));
        tbody.appendChild(partnerTr);

        // ── صفوف التفاصيل ──
        const detailGroup = document.createElement('tbody');
        detailGroup.id = `det-${pId}`;
        detailGroup.className = 'detail-row';

        // رأس التفاصيل
        const headerDetail = document.createElement('tr');
        headerDetail.className = 'bg-slate-50';
        headerDetail.innerHTML = `
            <td colspan="8" class="pt-3 pb-1 px-10">
                <div class="flex items-center gap-2 text-xs font-bold text-slate-500 uppercase tracking-wide">
                    <i class="fas fa-list-ul text-slate-400"></i>
                    تفاصيل حركات ${p.name}
                </div>
            </td>`;
        detailGroup.appendChild(headerDetail);

        // صف العناوين الداخلية
        const innerHead = document.createElement('tr');
        innerHead.className = 'bg-slate-50 text-xs text-slate-500 border-b border-slate-200';
        innerHead.innerHTML = `
            <td class="pb-2 px-10 text-right" colspan="2">التاريخ / القيد</td>
            <td class="pb-2 px-3 text-right" colspan="2">الوصف</td>
            <td class="pb-2 px-3 text-left">المدين</td>
            <td class="pb-2 px-3 text-left">الدائن</td>
            <td class="pb-2 px-3 text-left" colspan="2">الرصيد التراكمي</td>`;
        detailGroup.appendChild(innerHead);

        let running = 0;
        p.lines.forEach(l => {
            running += (l.debit || 0) - (l.credit || 0);
            const moveId   = l.move_id?.[0] ?? 0;
            const moveName = l.move_id?.[1] ?? '—';
            const isInv    = moveName.toUpperCase().includes('INV');
            const pdfBtn   = (isInv && moveId)
                ? `<a href="?print_invoice_id=${moveId}" target="_blank"
                      onclick="event.stopPropagation()"
                      class="inline-flex items-center gap-1 text-xs bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 px-2 py-0.5 rounded transition-colors">
                       <i class="fas fa-file-pdf text-xs"></i> PDF
                   </a>` : '';

            const lineTr = document.createElement('tr');
            lineTr.className = 'bg-slate-50 hover:bg-indigo-50 transition-colors text-xs border-b border-slate-100';
            lineTr.innerHTML = `
                <td class="py-2.5 px-10 text-gray-500 whitespace-nowrap" colspan="2">
                    <span class="font-semibold text-slate-700">${l.date}</span><br>
                    <span class="text-blue-600 font-bold">${moveName}</span>
                    ${pdfBtn}
                </td>
                <td class="py-2.5 px-3 text-gray-500 max-w-xs" colspan="2">${l.name || '—'}</td>
                <td class="py-2.5 px-3 text-left text-blue-700 font-semibold" dir="ltr">${fmt(l.debit)}</td>
                <td class="py-2.5 px-3 text-left text-emerald-600 font-semibold" dir="ltr">${fmt(l.credit)}</td>
                <td class="py-2.5 px-3 text-left font-bold ${balClass(running)}" dir="ltr" colspan="2">${fmt(Math.abs(running))} ${running>0.005?'<span class="text-xs font-normal text-red-400">مدين</span>':running<-0.005?'<span class="text-xs font-normal text-emerald-500">دائن</span>':''}</td>
            `;
            detailGroup.appendChild(lineTr);
        });

        // تذييل التفاصيل
        const footDetail = document.createElement('tr');
        footDetail.className = 'bg-indigo-50 text-xs font-bold border-b-2 border-indigo-200';
        footDetail.innerHTML = `
            <td colspan="4" class="py-3 px-10 text-right text-indigo-800">إجمالي: ${p.name}</td>
            <td class="py-3 px-3 text-left text-blue-700"    dir="ltr">${fmt(p.debit)}</td>
            <td class="py-3 px-3 text-left text-emerald-600" dir="ltr">${fmt(p.credit)}</td>
            <td class="py-3 px-3 text-left ${balClass(p.debit-p.credit)}" dir="ltr" colspan="2">
                ${fmt(Math.abs(p.debit-p.credit))}
                ${p.debit-p.credit>0.005?'<span class="font-normal text-red-400"> مدين</span>':p.debit-p.credit<-0.005?'<span class="font-normal text-emerald-500"> دائن</span>':''}
            </td>`;
        detailGroup.appendChild(footDetail);

        tbody.appendChild(detailGroup);
    });

    // ── إجماليات البطاقات ──
    document.getElementById('st-partners').textContent = totalPartners.toLocaleString('en');
    document.getElementById('st-lines').textContent    = totalLines.toLocaleString('en');
    document.getElementById('st-debit').textContent    = fmt(totalDebit);
    document.getElementById('st-credit').textContent   = fmt(totalCredit);
    document.getElementById('visibleCount').textContent = totalPartners;

    if (!Object.keys(grouped).length) {
        tbody.innerHTML = `<tr><td colspan="8" class="py-16 text-center text-gray-400">
            <i class="fas fa-magnifying-glass text-3xl mb-3 block opacity-30"></i>
            لا توجد سجلات مطابقة</td></tr>`;
    }
}

// ── فتح / إغلاق التفاصيل ─────────────────────────
function toggleDetails(pId, row) {
    const det = document.getElementById(`det-${pId}`);
    const isOpen = det.classList.contains('open');
    det.classList.toggle('open', !isOpen);
    row.classList.toggle('open', !isOpen);
}

// ── طباعة كشف الحساب ─────────────────────────────
function printStatement(pId) {
    const p = grouped[pId];
    if (!p) return;

    document.getElementById('print-partner-name').textContent = p.name;
    document.getElementById('print-date').textContent         = new Date().toLocaleDateString('ar-MY');
    document.getElementById('print-acc-type').textContent     =
        p.accType === 'receivable' ? 'ذمم مدينة (Receivable)' :
        p.accType === 'payable'    ? 'ذمم دائنة (Payable)'    : 'أخرى';

    const tbody = document.getElementById('print-tbody');
    tbody.innerHTML = '';

    let running = 0;
    p.lines.forEach((l, i) => {
        running += (l.debit||0) - (l.credit||0);
        const moveName = l.move_id?.[1] ?? '—';
        const bgColor  = i % 2 === 0 ? '#ffffff' : '#f8fafc';
        tbody.innerHTML += `
            <tr style="background:${bgColor};">
                <td style="padding:9px 12px; border-bottom:1px solid #e2e8f0; color:#475569;">${l.date}</td>
                <td style="padding:9px 12px; border-bottom:1px solid #e2e8f0; font-weight:700; color:#1e40af;">${moveName}</td>
                <td style="padding:9px 12px; border-bottom:1px solid #e2e8f0; color:#64748b; font-size:11px;">${l.name||'—'}</td>
                <td style="padding:9px 12px; border-bottom:1px solid #e2e8f0; text-align:left; color:#1d4ed8; font-weight:600;" dir="ltr">${fmt(l.debit)}</td>
                <td style="padding:9px 12px; border-bottom:1px solid #e2e8f0; text-align:left; color:#16a34a; font-weight:600;" dir="ltr">${fmt(l.credit)}</td>
                <td style="padding:9px 12px; border-bottom:1px solid #e2e8f0; text-align:left; font-weight:700;
                    color:${running>0.005?'#dc2626':running<-0.005?'#16a34a':'#6b7280'};" dir="ltr">
                    ${fmt(Math.abs(running))}
                    <span style="font-size:10px; font-weight:400; color:${running>0.005?'#ef4444':running<-0.005?'#22c55e':'#9ca3af'};">
                        ${running>0.005?' مدين':running<-0.005?' دائن':''}
                    </span>
                </td>
            </tr>`;
    });

    // تذييل الجدول
    const bal = p.debit - p.credit;
    document.getElementById('print-tfoot').innerHTML = `
        <tr style="background:#1e293b; color:white;">
            <td colspan="3" style="padding:11px 12px; font-weight:800; text-align:right;">الإجمالي</td>
            <td style="padding:11px 12px; text-align:left; font-weight:800;" dir="ltr">${fmt(p.debit)}</td>
            <td style="padding:11px 12px; text-align:left; font-weight:800;" dir="ltr">${fmt(p.credit)}</td>
            <td style="padding:11px 12px; text-align:left; font-weight:800;" dir="ltr">${fmt(Math.abs(bal))}
                <span style="font-size:10px; font-weight:400;">${bal>0.005?' مدين':bal<-0.005?' دائن':''}</span>
            </td>
        </tr>`;

    // ملخص جانبي
    document.getElementById('print-summary').innerHTML = `
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; text-align:center;">
            <div>
                <p style="font-size:10px; color:#64748b; margin:0 0 4px;">إجمالي المدين</p>
                <p style="font-size:18px; font-weight:800; color:#1d4ed8; margin:0;" dir="ltr">${fmt(p.debit)}</p>
            </div>
            <div>
                <p style="font-size:10px; color:#64748b; margin:0 0 4px;">إجمالي الدائن</p>
                <p style="font-size:18px; font-weight:800; color:#16a34a; margin:0;" dir="ltr">${fmt(p.credit)}</p>
            </div>
            <div style="background:${bal>0.005?'#fee2e2':bal<-0.005?'#dcfce7':'#f1f5f9'}; border-radius:8px; padding:8px;">
                <p style="font-size:10px; color:#64748b; margin:0 0 4px;">الرصيد النهائي</p>
                <p style="font-size:18px; font-weight:800; color:${bal>0.005?'#dc2626':bal<-0.005?'#16a34a':'#475569'}; margin:0;" dir="ltr">
                    ${fmt(Math.abs(bal))} ${bal>0.005?'مدين':bal<-0.005?'دائن':''}
                </p>
            </div>
        </div>`;

    window.print();
}

// ── Reset ─────────────────────────────────────────
function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('typeFilter').value  = 'all';
    document.getElementById('dateFrom').value    = '';
    document.getElementById('dateTo').value      = '';
    renderTable();
}

// ── Listeners ─────────────────────────────────────
document.getElementById('searchInput').addEventListener('input',  renderTable);
document.getElementById('typeFilter') .addEventListener('change', renderTable);
document.getElementById('dateFrom')   .addEventListener('change', renderTable);
document.getElementById('dateTo')     .addEventListener('change', renderTable);

renderTable();
</script>
</body>
</html>