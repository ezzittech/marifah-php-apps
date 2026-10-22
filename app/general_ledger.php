<?php
// ===================================================================
// ملف: general_ledger.php
// دفتر الأستاذ العام — شركة واحدة فقط (Company ID = 1)
// الإصدار: 2.0
// ===================================================================
require_once 'odoo_api.php';

// ── مسح الكاش يدوياً ──
if (isset($_GET['refresh']) && $_GET['refresh'] === '1') {
    cache_clear_all();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ── إعدادات الشركة المستهدفة ──
const TARGET_COMPANY_ID = 1;

// ── المصادقة ──
$session_id = authenticate($odoo_url, $db, $username, $password);

$accounts_summary = [];
$error            = '';
$grand_debit      = 0.0;
$grand_credit     = 0.0;
$from_cache       = false;
$cached_at_label  = '';

if (!$session_id) {
    $error = 'فشل الاتصال بأودو. تحقق من بيانات المصادقة.';
} else {

    // ── domain الفلترة: الشركة 1 فقط، القيود المرحّلة، بدون أسطر القسم والملاحظة ──
    $domain = [
        ['parent_state', '=',      'posted'],
        ['display_type', 'not in', ['line_section', 'line_note']],
        ['company_id',   '=',      TARGET_COMPANY_ID],
    ];

    // ── kwargs مع context صريح يحدد الشركة 1 فقط ──
    $kwargs = [
        'fields'  => [
            'date', 'move_id', 'account_id', 'partner_id',
            'name', 'ref', 'debit', 'credit', 'balance',
            'currency_id', 'amount_currency', 'company_id',
        ],
        'order'   => 'account_id asc, date asc',
        'limit'   => 10000,
        'context' => [
            // ✅ هذان الحقلان هما اللذان يمنعان أودو من إرجاع بيانات شركة أخرى
            'allowed_company_ids' => [TARGET_COMPANY_ID],
            'company_id'          => TARGET_COMPANY_ID,
        ],
    ];

    $response = odoo_cached($odoo_url, $session_id, 'account.move.line', 'search_read',
                            [$domain], $kwargs, 60);
    

    $raw        = $response['data'];
    $from_cache = $response['from_cache'];
    if ($response['cached_at']) {
        $cached_at_label = date('d/m/Y H:i', $response['cached_at']);
    }

    if (!is_array($raw)) {
        $error = 'فشل جلب البيانات من أودو.';
    } else {
        foreach ($raw as $l) {

            // ── فحص صارم: تجاهل أي سطر لا يتبع الشركة 1 (خط دفاع ثالث) ──
            $line_comp_id = is_array($l['company_id'] ?? null)
                ? (int)$l['company_id'][0]
                : (int)($l['company_id'] ?? 0);
            if ($line_comp_id !== TARGET_COMPANY_ID) {
                continue;
            }

            $acc_id   = is_array($l['account_id']) ? $l['account_id'][0] : $l['account_id'];
            $acc_full = is_array($l['account_id']) ? $l['account_id'][1] : '—';

            if (!isset($accounts_summary[$acc_id])) {
                $accounts_summary[$acc_id] = [
                    'code'   => '',
                    'name'   => $acc_full,
                    'debit'  => 0.0,
                    'credit' => 0.0,
                    'lines'  => [],
                ];
                // فصل الكود عن الاسم إذا كان الشكل "101000 اسم الحساب"
                if (preg_match('/^(\d+)\s+(.+)$/', $acc_full, $m)) {
                    $accounts_summary[$acc_id]['code'] = $m[1];
                    $accounts_summary[$acc_id]['name'] = $m[2];
                }
            }

            $accounts_summary[$acc_id]['debit']  += (float)$l['debit'];
            $accounts_summary[$acc_id]['credit'] += (float)$l['credit'];
            $accounts_summary[$acc_id]['lines'][] = $l;

            $grand_debit  += (float)$l['debit'];
            $grand_credit += (float)$l['credit'];
        }

        ksort($accounts_summary);
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>دفتر الأستاذ العام — Marifah</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<style>
  body { font-family: 'Tajawal', sans-serif; background: #f0f4f8; }
  ::-webkit-scrollbar { width: 8px; height: 8px; }
  ::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 4px; }

  .stat-card { transition: transform .15s, box-shadow .15s; }
  .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.09); }

  .acc-header { cursor: pointer; transition: background .12s; }
  .acc-header:hover { background: #f8fafc; }
  .acc-header.open { background: #eef2ff; }

  .acc-detail-group { display: none; }
  .acc-detail-group.open { display: table-row; }

  .arrow { display: inline-block; transition: transform .2s; }
  .acc-header.open .arrow { transform: rotate(-90deg); }

  .cache-badge-live   { background:#dcfce7; color:#15803d; border-color:#bbf7d0; }
  .cache-badge-cached { background:#fef9c3; color:#854d0e; border-color:#fde68a; }

  @media print {
    .no-print { display: none !important; }
    #print-area { display: block !important; }
    body { background: white; }
    @page { size: A4 landscape; margin: 1cm 1.5cm; }
  }
  #print-area { display: none; }
</style>
</head>
<body class="min-h-screen p-4 md:p-6 antialiased">
<div class="max-w-screen-xl mx-auto space-y-5 no-print">

<!-- ══ Header ══ -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
  <div class="flex flex-wrap items-center justify-between gap-4">

    <div class="flex items-center gap-3">
      <div class="w-11 h-11 rounded-xl bg-slate-800 flex items-center justify-center shadow-md flex-shrink-0">
        <i class="fas fa-book text-white text-lg"></i>
      </div>
      <div>
        <h1 class="text-xl font-extrabold text-gray-900">دفتر الأستاذ العام</h1>
        <p class="text-gray-400 text-xs mt-0.5">
          General Ledger · Company ID <?= TARGET_COMPANY_ID ?> · <?= date('d/m/Y H:i') ?>
        </p>
      </div>
    </div>

    <div class="flex items-center gap-2 flex-wrap">

      <?php if ($error): ?>
        <span class="bg-red-50 text-red-600 border border-red-100 px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5">
          <i class="fas fa-circle-xmark"></i> <?= htmlspecialchars($error) ?>
        </span>
      <?php else: ?>
        <span class="bg-emerald-50 text-emerald-700 border border-emerald-100 px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5">
          <i class="fas fa-circle-check"></i> متصل | <?= count($accounts_summary) ?> حساب
        </span>
      <?php endif; ?>

      <!-- ── شارة حالة الكاش ── -->
      <?php if (!$error && $cached_at_label): ?>
        <span class="border px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5
          <?= $from_cache ? 'cache-badge-cached' : 'cache-badge-live' ?>">
          <i class="fas <?= $from_cache ? 'fa-clock' : 'fa-bolt' ?>"></i>
          <?php if ($from_cache): ?>
            بيانات مؤقتة · آخر تحديث <?= $cached_at_label ?>
          <?php else: ?>
            بيانات مباشرة من أودو
          <?php endif; ?>
        </span>
      <?php endif; ?>

      <a href="?refresh=1"
         class="bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 px-3 py-2 rounded-xl text-xs font-bold transition-colors flex items-center gap-1.5"
         title="مسح الكاش وجلب أحدث البيانات من أودو مباشرة">
        <i class="fas fa-arrows-rotate"></i> تحديث البيانات
      </a>
      <button onclick="expandAll()"
              class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-xl text-xs font-bold transition-colors flex items-center gap-1.5">
        <i class="fas fa-expand-alt"></i> فتح الكل
      </button>
      <button onclick="collapseAll()"
              class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-xl text-xs font-bold transition-colors flex items-center gap-1.5">
        <i class="fas fa-compress-alt"></i> إغلاق الكل
      </button>
      <button onclick="printAll()"
              class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-xl text-xs font-bold transition-colors flex items-center gap-1.5">
        <i class="fas fa-print"></i> طباعة الكل
      </button>
      <a href="index.html"
         class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 rounded-xl text-sm font-bold transition-colors flex items-center gap-2 shadow-sm">
        <i class="fas fa-home"></i> الرئيسية
      </a>
    </div>

  </div>
</div>

<!-- ══ Stats ══ -->
<?php if (!$error):
    $grand_balance = $grand_debit - $grand_credit;
?>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
  <div class="stat-card bg-white rounded-2xl border border-gray-100 p-4 shadow-sm">
    <div class="flex items-center gap-2 mb-2">
      <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center">
        <i class="fas fa-layer-group text-slate-600 text-sm"></i>
      </div>
      <span class="text-xs font-semibold text-gray-500">الحسابات</span>
    </div>
    <p class="text-2xl font-extrabold text-gray-900"><?= number_format(count($accounts_summary)) ?></p>
    <p class="text-xs text-gray-400 mt-1" id="visibleAccStat">كل الحسابات</p>
  </div>

  <div class="stat-card bg-white rounded-2xl border border-gray-100 p-4 shadow-sm">
    <div class="flex items-center gap-2 mb-2">
      <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center">
        <i class="fas fa-list text-slate-500 text-sm"></i>
      </div>
      <span class="text-xs font-semibold text-gray-500">إجمالي الحركات</span>
    </div>
    <p class="text-2xl font-extrabold text-gray-900">
      <?= number_format(array_sum(array_map(fn($a) => count($a['lines']), $accounts_summary))) ?>
    </p>
  </div>

  <div class="stat-card bg-white rounded-2xl border border-gray-100 p-4 shadow-sm">
    <div class="flex items-center gap-2 mb-2">
      <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
        <i class="fas fa-arrow-down text-blue-600 text-sm"></i>
      </div>
      <span class="text-xs font-semibold text-gray-500">إجمالي المدين</span>
    </div>
    <p class="text-lg font-extrabold text-blue-700" dir="ltr"><?= number_format($grand_debit, 2) ?></p>
  </div>

  <div class="stat-card bg-white rounded-2xl border border-gray-100 p-4 shadow-sm">
    <div class="flex items-center gap-2 mb-2">
      <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center">
        <i class="fas fa-arrow-up text-emerald-600 text-sm"></i>
      </div>
      <span class="text-xs font-semibold text-gray-500">إجمالي الدائن</span>
    </div>
    <p class="text-lg font-extrabold text-emerald-600" dir="ltr"><?= number_format($grand_credit, 2) ?></p>
  </div>
</div>
<?php endif; ?>

<!-- ══ Filters ══ -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div>
      <label class="block text-xs font-bold text-gray-500 mb-1.5">بحث (حساب / شريك / وصف)</label>
      <div class="relative">
        <input type="text" id="searchInput" placeholder="اكتب للبحث الفوري..."
          class="w-full border border-gray-200 rounded-xl py-2.5 pr-9 pl-3 text-sm focus:ring-2 focus:ring-slate-400 outline-none">
        <i class="fas fa-magnifying-glass absolute top-3 right-3 text-gray-400 text-sm"></i>
      </div>
    </div>
    <div>
      <label class="block text-xs font-bold text-gray-500 mb-1.5">من تاريخ</label>
      <input type="date" id="dateFrom"
        class="w-full border border-gray-200 rounded-xl py-2.5 px-3 text-sm focus:ring-2 focus:ring-slate-400 outline-none">
    </div>
    <div>
      <label class="block text-xs font-bold text-gray-500 mb-1.5">إلى تاريخ</label>
      <input type="date" id="dateTo"
        class="w-full border border-gray-200 rounded-xl py-2.5 px-3 text-sm focus:ring-2 focus:ring-slate-400 outline-none">
    </div>
    <div>
      <label class="block text-xs font-bold text-gray-500 mb-1.5">عرض الأرصدة</label>
      <div class="relative">
        <select id="balanceFilter"
          class="w-full border border-gray-200 rounded-xl py-2.5 px-3 pl-8 text-sm appearance-none focus:ring-2 focus:ring-slate-400 outline-none bg-white">
          <option value="all">الكل</option>
          <option value="debit">مدين فقط (رصيد موجب)</option>
          <option value="credit">دائن فقط (رصيد سالب)</option>
          <option value="zero">رصيد صفر</option>
        </select>
        <i class="fas fa-chevron-down absolute top-3.5 left-3 text-gray-400 text-xs pointer-events-none"></i>
      </div>
    </div>
  </div>
  <div class="mt-3 flex items-center justify-between">
    <p class="text-xs text-gray-400">
      عرض <span id="visibleCount" class="font-bold text-gray-700">—</span>
      من <span class="font-bold text-gray-700"><?= count($accounts_summary) ?></span> حساب
    </p>
    <button onclick="resetFilters()"
            class="text-xs text-slate-600 hover:text-slate-900 font-bold flex items-center gap-1">
      <i class="fas fa-rotate-right"></i> إعادة ضبط
    </button>
  </div>
</div>

<!-- ══ Ledger Table ══ -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
  <div class="overflow-x-auto pb-4">
    <table class="w-full text-sm border-collapse" id="ledgerTable">
      <thead>
        <tr class="bg-slate-800 text-white text-right">
          <th class="py-3.5 px-4 w-8"></th>
          <th class="py-3.5 px-3 whitespace-nowrap">كود</th>
          <th class="py-3.5 px-5 whitespace-nowrap">اسم الحساب</th>
          <th class="py-3.5 px-4 text-center whitespace-nowrap">حركات</th>
          <th class="py-3.5 px-5 text-left whitespace-nowrap">إجمالي المدين</th>
          <th class="py-3.5 px-5 text-left whitespace-nowrap">إجمالي الدائن</th>
          <th class="py-3.5 px-5 text-left whitespace-nowrap">الرصيد</th>
          <th class="py-3.5 px-4 text-center whitespace-nowrap">الإجراءات</th>
        </tr>
      </thead>
      <tbody id="glBody"></tbody>
      <tfoot class="bg-slate-800 text-white border-t-2 border-slate-600">
        <tr>
          <td colspan="4" class="py-4 px-5 font-extrabold text-right">
            <i class="fas fa-sigma ml-1 opacity-60"></i> الإجمالي العام:
          </td>
          <td class="py-4 px-5 font-extrabold text-left text-blue-300"    id="footDebit"  dir="ltr">—</td>
          <td class="py-4 px-5 font-extrabold text-left text-emerald-300" id="footCredit" dir="ltr">—</td>
          <td class="py-4 px-5 font-extrabold text-left text-yellow-300"  id="footBal"    dir="ltr">—</td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

</div><!-- /no-print -->

<!-- ══ Print Area ══ -->
<div id="print-area" class="p-8">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #1e293b;padding-bottom:20px;margin-bottom:24px;">
    <img src="https://cdn.prod.website-files.com/642e38eb59ef94ba3c2bf35b/68696ec0e6fe2cab7b377c39_724c268af6d0a34b14c1961f01f18585_Marifah_CMYK-p-500.png"
         alt="Marifah Logo" style="width:100px;object-fit:contain;">
    <div style="text-align:right;font-family:'Tajawal',sans-serif;">
      <p style="font-size:16px;font-weight:800;color:#0f172a;margin:0;">Marifah International School</p>
      <p style="font-size:11px;color:#475569;margin:4px 0 0;line-height:1.6;">
        Level 3 South City Plaza, Persiaran Serdang Perdana,<br>
        Seksyen 1, 43300 Seri Kembangan, Selangor
      </p>
      <p style="font-size:11px;font-weight:600;color:#0f172a;margin:5px 0 0;">
        info@marifah.edu.my | +60 18-988 9302
      </p>
    </div>
  </div>
  <div style="text-align:center;margin-bottom:20px;font-family:'Tajawal',sans-serif;">
    <p style="font-size:9px;letter-spacing:3px;color:#64748b;text-transform:uppercase;margin:0 0 4px;">General Ledger</p>
    <h1 id="print-title" style="font-size:20px;font-weight:800;color:#0f172a;margin:0;">دفتر الأستاذ العام</h1>
    <p id="print-subtitle" style="font-size:11px;color:#64748b;margin:4px 0 0;"></p>
  </div>
  <div id="print-content" style="font-family:'Tajawal',sans-serif;font-size:11px;direction:rtl;"></div>
  <div style="margin-top:30px;border-top:1px solid #cbd5e1;padding-top:12px;display:flex;justify-content:space-between;font-family:'Tajawal',sans-serif;font-size:10px;color:#64748b;">
    <p style="margin:0;">طُبع بتاريخ: <span id="print-date2"></span></p>
    <p style="margin:0;">marifah.edu.my</p>
  </div>
</div>

<!-- ══ JavaScript ══ -->
<script>
// ── تحويل بيانات PHP إلى JavaScript ──
const ACCOUNTS = <?= json_encode(
    array_values($accounts_summary),
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
) ?>;
<?php $i = 0; foreach ($accounts_summary as $acc_id => $acc): ?>
ACCOUNTS[<?= $i ?>]._id = <?= $acc_id ?>;
<?php $i++; endforeach; ?>

// ── حالة الفلترة الحالية ──
let filtered = [];

// ── تنسيق الأرقام بالإنجليزي مع فاصلة آلاف ──
const fmt = v => parseFloat(v || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// ── عرض الرصيد بألوان ──
function balDisplay(b) {
  if (b >  0.005) return `<span class="font-bold text-red-600" dir="ltr">${fmt(b)}</span> <span class="text-xs text-red-400">مدين</span>`;
  if (b < -0.005) return `<span class="font-bold text-emerald-600" dir="ltr">${fmt(Math.abs(b))}</span> <span class="text-xs text-emerald-500">دائن</span>`;
  return `<span class="font-bold text-gray-400" dir="ltr">0.00</span>`;
}

// ── الدالة الرئيسية للرسم بعد الفلترة ──
function render() {
  const search   = document.getElementById('searchInput').value.trim().toLowerCase();
  const dateFrom = document.getElementById('dateFrom').value;
  const dateTo   = document.getElementById('dateTo').value;
  const balF     = document.getElementById('balanceFilter').value;

  filtered = ACCOUNTS.filter(acc => {
    const bal = acc.debit - acc.credit;

    // فلتر الرصيد
    if (balF === 'debit'  && bal <=  0.005) return false;
    if (balF === 'credit' && bal >= -0.005) return false;
    if (balF === 'zero'   && Math.abs(bal) >  0.005) return false;

    // إذا ما في بحث أو تاريخ، اعرض الكل
    if (!search && !dateFrom && !dateTo) return true;

    // فلتر النص
    if (search) {
      const accStr   = `${acc.code || ''} ${acc.name || ''}`.toLowerCase();
      const matchAcc = accStr.includes(search);
      const matchLine = acc.lines.some(l => {
        const p = l.partner_id?.[1] ? String(l.partner_id[1]).toLowerCase() : '';
        const n = l.name            ? String(l.name).toLowerCase()           : '';
        const m = l.move_id?.[1]    ? String(l.move_id[1]).toLowerCase()     : '';
        return p.includes(search) || n.includes(search) || m.includes(search);
      });
      if (!matchAcc && !matchLine) return false;
    }

    // فلتر التاريخ
    if (dateFrom || dateTo) {
      const hasLine = acc.lines.some(l =>
        (!dateFrom || l.date >= dateFrom) && (!dateTo || l.date <= dateTo)
      );
      if (!hasLine) return false;
    }

    return true;
  });

  const tbody = document.getElementById('glBody');

  if (!filtered.length) {
    tbody.innerHTML = `<tr><td colspan="8" class="py-16 text-center text-gray-400">
      <i class="fas fa-magnifying-glass text-3xl mb-3 block opacity-30"></i>لا توجد حسابات مطابقة</td></tr>`;
    document.getElementById('visibleCount').textContent = '0';
    updateFooter(0, 0);
    return;
  }

  let sumD = 0, sumC = 0;
  let htmlStr = '';

  filtered.forEach((acc, ai) => {
    sumD += acc.debit;
    sumC += acc.credit;
    const bal = acc.debit - acc.credit;

    const accLines = acc.lines.filter(l =>
      (!dateFrom || l.date >= dateFrom) && (!dateTo || l.date <= dateTo)
    );

    // ── صف الحساب الرئيسي ──
    htmlStr += `
      <tr class="acc-header border-b border-gray-200 bg-white" onclick="toggleAcc(${ai}, this)">
        <td class="py-3.5 px-4 text-slate-400">
          <i class="fas fa-chevron-left arrow text-xs"></i>
        </td>
        <td class="py-3.5 px-3 font-mono text-xs text-slate-500 whitespace-nowrap">${acc.code || '—'}</td>
        <td class="py-3.5 px-5">
          <p class="font-bold text-slate-800">${acc.name}</p>
          <p class="text-xs text-gray-400 mt-0.5">${accLines.length} حركة</p>
        </td>
        <td class="py-3.5 px-4 text-center font-bold text-slate-600">${accLines.length}</td>
        <td class="py-3.5 px-5 font-bold text-blue-700 text-left" dir="ltr">${fmt(acc.debit)}</td>
        <td class="py-3.5 px-5 font-bold text-emerald-600 text-left" dir="ltr">${fmt(acc.credit)}</td>
        <td class="py-3.5 px-5 text-left">${balDisplay(bal)}</td>
        <td class="py-3.5 px-4 text-center whitespace-nowrap">
          <button onclick="event.stopPropagation(); printAccount(${ai})"
                  class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 px-2.5 py-1.5 rounded-lg text-xs font-bold transition-colors mx-0.5"
                  title="طباعة كشف الحساب">
            <i class="fas fa-file-pdf"></i> PDF
          </button>
          <button onclick="event.stopPropagation(); exportExcel(${ai})"
                  class="text-emerald-600 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1.5 rounded-lg text-xs font-bold transition-colors mx-0.5"
                  title="تنزيل Excel">
            <i class="fas fa-file-excel"></i> Excel
          </button>
        </td>
      </tr>`;

    // ── أسطر التفاصيل (مخفية بالافتراضي) ──
    let nestedRows = '';
    let running = 0;

    accLines.forEach((l, li) => {
      running += (l.debit || 0) - (l.credit || 0);
      const partner  = l.partner_id?.[1] ?? '';
      const moveName = l.move_id?.[1]    ?? '—';
      const cur      = l.currency_id?.[1] ?? '';
      const forCur   = (cur && cur !== 'MYR' && l.amount_currency && l.amount_currency !== 0)
        ? `<span class="text-xs text-amber-600 font-semibold ml-1">(${parseFloat(l.amount_currency).toLocaleString('en', { minimumFractionDigits: 2 })} ${cur})</span>`
        : '';

      const runColor = running >  0.005 ? 'text-red-600'
                     : running < -0.005 ? 'text-emerald-600'
                     : 'text-gray-400';
      const runLabel = running >  0.005 ? 'م' : running < -0.005 ? 'د' : '';

      nestedRows += `
        <tr class="${li % 2 ? 'bg-white' : 'bg-slate-50'} hover:bg-indigo-50 transition-colors border-b border-slate-100">
          <td class="py-2.5 px-4 whitespace-nowrap text-slate-500">${l.date}</td>
          <td class="py-2.5 px-4 font-bold text-slate-700">${moveName}</td>
          <td class="py-2.5 px-4 text-indigo-600 font-semibold">${partner || '—'}</td>
          <td class="py-2.5 px-4 text-slate-500">${l.name || '—'} ${forCur}</td>
          <td class="py-2.5 px-4 text-left text-blue-700 font-semibold" dir="ltr">${fmt(l.debit)}</td>
          <td class="py-2.5 px-4 text-left text-emerald-600 font-semibold" dir="ltr">${fmt(l.credit)}</td>
          <td class="py-2.5 px-4 text-left font-bold ${runColor}" dir="ltr">
            ${fmt(Math.abs(running))}
            <span class="text-xs font-normal">${runLabel}</span>
          </td>
        </tr>`;
    });

    htmlStr += `
      <tr id="det-${ai}" class="acc-detail-group">
        <td colspan="8" class="p-0 border-b-2 border-indigo-100 bg-slate-50">
          <div class="px-4 py-6 md:px-10">
            <table class="w-full text-xs text-right border border-gray-200 rounded-xl overflow-hidden shadow-sm bg-white">
              <thead class="bg-slate-200/70 text-slate-600">
                <tr>
                  <th class="py-3 px-4 font-bold">التاريخ</th>
                  <th class="py-3 px-4 font-bold">القيد</th>
                  <th class="py-3 px-4 font-bold">الشريك</th>
                  <th class="py-3 px-4 font-bold">الوصف</th>
                  <th class="py-3 px-4 font-bold text-left">مدين</th>
                  <th class="py-3 px-4 font-bold text-left">دائن</th>
                  <th class="py-3 px-4 font-bold text-left">الرصيد التراكمي</th>
                </tr>
              </thead>
              <tbody>${nestedRows}</tbody>
            </table>
          </div>
        </td>
      </tr>`;
  });

  tbody.innerHTML = htmlStr;
  updateFooter(sumD, sumC);
  document.getElementById('visibleCount').textContent   = filtered.length;
  document.getElementById('visibleAccStat').textContent = `${filtered.length} حساب ظاهر`;
}

function updateFooter(d, c) {
  const gb = d - c;
  document.getElementById('footDebit').textContent  = fmt(d);
  document.getElementById('footCredit').textContent = fmt(c);
  document.getElementById('footBal').textContent    =
    fmt(Math.abs(gb)) + (gb > 0.005 ? ' (مدين)' : gb < -0.005 ? ' (دائن)' : '');
}

// ── فتح / إغلاق تفاصيل الحساب ──
function toggleAcc(ai, row) {
  const det = document.getElementById(`det-${ai}`);
  if (!det) return;
  const isOpen = det.classList.contains('open');
  det.classList.toggle('open', !isOpen);
  row.classList.toggle('open', !isOpen);
}

function expandAll() {
  document.querySelectorAll('.acc-detail-group').forEach(d => d.classList.add('open'));
  document.querySelectorAll('.acc-header').forEach(r => r.classList.add('open'));
}
function collapseAll() {
  document.querySelectorAll('.acc-detail-group').forEach(d => d.classList.remove('open'));
  document.querySelectorAll('.acc-header').forEach(r => r.classList.remove('open'));
}

// ── تصدير Excel لحساب واحد ──
function exportExcel(ai) {
  const acc      = filtered[ai];
  if (!acc) return;
  const dateFrom = document.getElementById('dateFrom').value;
  const dateTo   = document.getElementById('dateTo').value;
  const accLines = acc.lines.filter(l =>
    (!dateFrom || l.date >= dateFrom) && (!dateTo || l.date <= dateTo)
  );

  const data = [
    ['تقرير كشف حساب'],
    ['اسم الحساب:', acc.name, 'كود الحساب:', acc.code || '—'],
    ['من تاريخ:', dateFrom || 'البداية', 'إلى تاريخ:', dateTo || 'النهاية'],
    [],
    ['التاريخ', 'رقم القيد', 'الشريك', 'الوصف', 'مدين', 'دائن', 'الرصيد التراكمي'],
  ];

  let running = 0;
  accLines.forEach(l => {
    running += (l.debit || 0) - (l.credit || 0);
    data.push([
      l.date,
      l.move_id?.[1]    ?? '',
      l.partner_id?.[1] ?? '',
      l.name ?? '',
      l.debit  || 0,
      l.credit || 0,
      running,
    ]);
  });

  const ws = XLSX.utils.aoa_to_sheet(data);
  ws['!dir'] = 'rtl';
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'كشف حساب');
  XLSX.writeFile(wb, `Account_${acc.code || 'Ledger'}.xlsx`);
}

// ── طباعة حساب واحد ──
function printAccount(ai) {
  const acc      = filtered[ai];
  if (!acc) return;
  const dateFrom = document.getElementById('dateFrom').value;
  const dateTo   = document.getElementById('dateTo').value;
  const accLines = acc.lines.filter(l =>
    (!dateFrom || l.date >= dateFrom) && (!dateTo || l.date <= dateTo)
  );

  document.getElementById('print-title').textContent    = acc.name + (acc.code ? ` (${acc.code})` : '');
  document.getElementById('print-subtitle').textContent =
    `${accLines.length} حركة | مدين: ${fmt(acc.debit)} | دائن: ${fmt(acc.credit)}`;
  document.getElementById('print-date2').textContent    = new Date().toLocaleDateString('ar-MY');

  let running = 0, rows = '';
  accLines.forEach((l, i) => {
    running += (l.debit || 0) - (l.credit || 0);
    const bg       = i % 2 ? '#f8fafc' : '#ffffff';
    const runColor = running >  0.005 ? '#dc2626' : running < -0.005 ? '#16a34a' : '#9ca3af';
    const runLabel = running >  0.005 ? 'م'       : running < -0.005 ? 'د'       : '';
    rows += `<tr style="background:${bg};">
      <td style="padding:7px 10px;border-bottom:1px solid #e2e8f0;color:#475569;">${l.date}</td>
      <td style="padding:7px 10px;border-bottom:1px solid #e2e8f0;font-weight:700;color:#1e40af;">${l.move_id?.[1] ?? '—'}</td>
      <td style="padding:7px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;">${l.partner_id?.[1] ?? '—'}</td>
      <td style="padding:7px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;">${l.name || '—'}</td>
      <td style="padding:7px 10px;border-bottom:1px solid #e2e8f0;text-align:left;color:#1d4ed8;font-weight:600;" dir="ltr">${fmt(l.debit)}</td>
      <td style="padding:7px 10px;border-bottom:1px solid #e2e8f0;text-align:left;color:#16a34a;font-weight:600;" dir="ltr">${fmt(l.credit)}</td>
      <td style="padding:7px 10px;border-bottom:1px solid #e2e8f0;text-align:left;font-weight:700;color:${runColor};" dir="ltr">
        ${fmt(Math.abs(running))} <span style="font-size:9px;font-weight:400;">${runLabel}</span>
      </td>
    </tr>`;
  });

  const bal = acc.debit - acc.credit;
  document.getElementById('print-content').innerHTML = `
    <table style="width:100%;border-collapse:collapse;direction:rtl;">
      <thead>
        <tr style="background:#1e293b;color:white;">
          <th style="padding:9px 10px;text-align:right;font-weight:700;">التاريخ</th>
          <th style="padding:9px 10px;text-align:right;font-weight:700;">رقم القيد</th>
          <th style="padding:9px 10px;text-align:right;font-weight:700;">الشريك</th>
          <th style="padding:9px 10px;text-align:right;font-weight:700;">الوصف</th>
          <th style="padding:9px 10px;text-align:left;font-weight:700;">المدين</th>
          <th style="padding:9px 10px;text-align:left;font-weight:700;">الدائن</th>
          <th style="padding:9px 10px;text-align:left;font-weight:700;">الرصيد التراكمي</th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
      <tfoot>
        <tr style="background:#1e293b;color:white;">
          <td colspan="4" style="padding:9px 10px;font-weight:800;text-align:right;">الإجمالي</td>
          <td style="padding:9px 10px;font-weight:800;text-align:left;color:#93c5fd;" dir="ltr">${fmt(acc.debit)}</td>
          <td style="padding:9px 10px;font-weight:800;text-align:left;color:#6ee7b7;" dir="ltr">${fmt(acc.credit)}</td>
          <td style="padding:9px 10px;font-weight:800;text-align:left;color:${bal > 0.005 ? '#fca5a5' : bal < -0.005 ? '#6ee7b7' : '#d1d5db'};" dir="ltr">
            ${fmt(Math.abs(bal))} ${bal > 0.005 ? 'مدين' : bal < -0.005 ? 'دائن' : ''}
          </td>
        </tr>
      </tfoot>
    </table>`;
  window.print();
}

// ── طباعة كل الحسابات ──
function printAll() {
  const dateFrom = document.getElementById('dateFrom').value;
  const dateTo   = document.getElementById('dateTo').value;

  document.getElementById('print-title').textContent    = 'دفتر الأستاذ العام';
  document.getElementById('print-subtitle').textContent =
    `${filtered.length} حساب | الفترة: ${dateFrom || '—'} → ${dateTo || '—'}`;
  document.getElementById('print-date2').textContent    = new Date().toLocaleDateString('ar-MY');

  let content = '';
  filtered.forEach(acc => {
    const lines = acc.lines.filter(l =>
      (!dateFrom || l.date >= dateFrom) && (!dateTo || l.date <= dateTo)
    );
    if (!lines.length) return;

    const bal = acc.debit - acc.credit;
    let rows = '', running = 0;

    lines.forEach((l, i) => {
      running += (l.debit || 0) - (l.credit || 0);
      const runColor = running >  0.005 ? '#dc2626' : running < -0.005 ? '#16a34a' : '#9ca3af';
      rows += `<tr style="background:${i % 2 ? '#f8fafc' : '#fff'};">
        <td style="padding:5px 8px;border-bottom:1px solid #e2e8f0;color:#475569;">${l.date}</td>
        <td style="padding:5px 8px;border-bottom:1px solid #e2e8f0;font-weight:700;color:#1e40af;">${l.move_id?.[1] ?? '—'}</td>
        <td style="padding:5px 8px;border-bottom:1px solid #e2e8f0;color:#64748b;">${l.partner_id?.[1] ?? '—'}</td>
        <td style="padding:5px 8px;border-bottom:1px solid #e2e8f0;color:#64748b;">${l.name || '—'}</td>
        <td style="padding:5px 8px;border-bottom:1px solid #e2e8f0;text-align:left;color:#1d4ed8;" dir="ltr">${fmt(l.debit)}</td>
        <td style="padding:5px 8px;border-bottom:1px solid #e2e8f0;text-align:left;color:#16a34a;" dir="ltr">${fmt(l.credit)}</td>
        <td style="padding:5px 8px;border-bottom:1px solid #e2e8f0;text-align:left;font-weight:700;color:${runColor};" dir="ltr">${fmt(Math.abs(running))}</td>
      </tr>`;
    });

    content += `
      <div style="margin-bottom:24px;page-break-inside:avoid;">
        <div style="background:#1e293b;color:white;padding:8px 12px;border-radius:4px 4px 0 0;display:flex;justify-content:space-between;">
          <span style="font-weight:800;">${acc.code ? acc.code + ' — ' : ''}${acc.name}</span>
          <span style="font-size:10px;opacity:.8;">
            مدين: ${fmt(acc.debit)} | دائن: ${fmt(acc.credit)} | رصيد: ${fmt(Math.abs(bal))} ${bal > 0.005 ? 'م' : bal < -0.005 ? 'د' : ''}
          </span>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:10px;direction:rtl;">
          <thead>
            <tr style="background:#f1f5f9;color:#475569;">
              <th style="padding:6px 8px;text-align:right;">تاريخ</th>
              <th style="padding:6px 8px;text-align:right;">قيد</th>
              <th style="padding:6px 8px;text-align:right;">شريك</th>
              <th style="padding:6px 8px;text-align:right;">وصف</th>
              <th style="padding:6px 8px;text-align:left;">مدين</th>
              <th style="padding:6px 8px;text-align:left;">دائن</th>
              <th style="padding:6px 8px;text-align:left;">رصيد</th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;
  });

  document.getElementById('print-content').innerHTML = content;
  window.print();
}

// ── إعادة ضبط الفلاتر ──
function resetFilters() {
  document.getElementById('searchInput').value    = '';
  document.getElementById('dateFrom').value       = '';
  document.getElementById('dateTo').value         = '';
  document.getElementById('balanceFilter').value  = 'all';
  render();
}

// ── ربط الأحداث بالفلاتر ──
['searchInput', 'dateFrom', 'dateTo', 'balanceFilter'].forEach(id => {
  const el = document.getElementById(id);
  el.addEventListener('input',  render);
  el.addEventListener('change', render);
});

// ── رسم أولي ──
render();
</script>
</body>
</html>