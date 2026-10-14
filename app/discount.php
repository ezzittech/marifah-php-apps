<?php
// ══════════════════════════════════════════════
//  تقرير التخفيضات الشامل — Marifah International School
//  (تفاعلي مع فلاتر ديناميكية وإحصائيات لحظية)
// ══════════════════════════════════════════════

$odoo_url = 'https://portal.marifah.edu.my';
$db       = 'Marifahdb';
$username = 'ezzittech@gmail.com';
$password = 'Abulez@2526';

function authenticate($url, $db, $login, $password) {
    $ch = curl_init($url . '/web/session/authenticate');
    $payload = json_encode(['jsonrpc'=>'2.0','method'=>'call',
        'params'=>['db'=>$db,'login'=>$login,'password'=>$password]]);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload,
        CURLOPT_HEADER=>true, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_TIMEOUT=>30]);
    $r = curl_exec($ch);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $h = substr($r, 0, $hs);
    curl_close($ch);
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $h, $m);
    $c = [];
    foreach ($m[1] as $i) { parse_str($i, $x); $c = array_merge($c, $x); }
    return $c['session_id'] ?? '';
}

function odoo($url, $sid, $model, $method, $args, $kwargs = []) {
    $ch = curl_init($url . '/web/dataset/call_kw');
    $payload = json_encode(['jsonrpc'=>'2.0','method'=>'call',
        'params'=>['model'=>$model,'method'=>$method,'args'=>$args,'kwargs'=>$kwargs]]);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Cookie: session_id='.$sid],
        CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload,
        CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_TIMEOUT=>90]);
    $r = curl_exec($ch);
    curl_close($ch);
    $d = json_decode($r, true);
    return $d['result'] ?? null;
}

$sid = authenticate($odoo_url, $db, $username, $password);
$error = $sid ? '' : 'فشل الاتصال بأودو';

$final_data = [];
$fx_rates = ['MYR' => 1.0];

if ($sid) {
    // 1. جلب أسعار الصرف لتوحيد الإجماليات
    $cur_data = odoo($odoo_url, $sid, 'res.currency', 'search_read',
        [[['active', '=', true]]], ['fields' => ['name', 'rate']]);
    if (is_array($cur_data)) {
        foreach ($cur_data as $c) {
            if (!empty($c['name']) && !empty($c['rate']) && (float)$c['rate'] > 0) {
                $fx_rates[(string)$c['name']] = (float)$c['rate'];
            }
        }
    }

    // 2. جلب أسطر الفواتير التي تحتوي على أي تخفيض (نسبة أو مبلغ سالب أو الأصناف المحددة)
    $domain = [
        ['parent_state', '=', 'posted'],
        ['move_id.move_type', '=', 'out_invoice'],
        '|', '|', '|',
        ['discount', '>', 0],
        ['price_subtotal', '<', 0],
        ['price_unit', '<', 0],
        ['product_id', 'in', [41, 43, 2172]]
    ];

    $lines = odoo($odoo_url, $sid, 'account.move.line', 'search_read', [$domain], [
        'fields' => ['move_id', 'date', 'partner_id', 'name', 'product_id', 'discount', 'price_subtotal', 'price_unit', 'quantity', 'currency_id'],
        'order'  => 'date desc, id desc'
    ]);

    if (is_array($lines)) {
        // استخراج أرقام الطلاب
        $unique_pids = [];
        foreach ($lines as $line) {
            if (!empty($line['partner_id']) && is_array($line['partner_id'])) {
                $unique_pids[$line['partner_id'][0]] = $line['partner_id'][0];
            }
        }

        // جلب أنواع الدراسة للطلاب
        $pid_type_map = [];
        if (!empty($unique_pids)) {
            $partners = odoo($odoo_url, $sid, 'res.partner', 'search_read',
                [[['id', 'in', array_values($unique_pids)]]],
                ['fields' => ['id', 'x_student_type']]
            );
            if (is_array($partners)) {
                foreach ($partners as $p) {
                    $pid_type_map[$p['id']] = isset($p['x_student_type']) && is_string($p['x_student_type']) ? $p['x_student_type'] : '';
                }
            }
        }

        // معالجة البيانات وتصنيف التخفيضات
        foreach ($lines as $line) {
            $pid = is_array($line['partner_id']) ? $line['partner_id'][0] : null;
            $partner_name = is_array($line['partner_id']) ? $line['partner_id'][1] : '—';
            $invoice_num = is_array($line['move_id']) ? $line['move_id'][1] : '—';
            $prod_id = is_array($line['product_id']) ? $line['product_id'][0] : null;
            
            // تحديد نوع التخفيض حسب طلبك
            $discount_pct = (float)($line['discount'] ?? 0);
            if (in_array($prod_id, [41, 43])) {
                $disc_type = 'online';
                $disc_label = 'تخفيض أون لاين';
            } elseif ($prod_id == 2172) {
                $disc_type = 'onsite';
                $disc_label = 'تخفيض حضوري';
            } elseif ($discount_pct > 0) {
                $disc_type = 'percent';$disc_label = 'تخفيض نسبة';
            } else {
                $disc_type = 'other';$disc_label = 'أخرى (سالب)';
            }

            // حساب مبلغ التخفيض
            $subtotal = (float)($line['price_subtotal'] ?? 0);
            $price_unit = (float)($line['price_unit'] ?? 0);
            $qty = (float)($line['quantity'] ?? 0);

            $discount_amt = 0;
            if ($discount_pct > 0) {
                // إذا كان التخفيض نسبة، المبلغ هو (السعر * الكمية * النسبة)
                $discount_amt = abs($qty * $price_unit * ($discount_pct / 100));
            } else {
                // إذا كان خصم كمنتج، فالمبلغ هو الإجمالي السالب
                $discount_amt = abs($subtotal);
            }

            // تحويل للرنجت للإحصائيات الموحدة
            $cur = (!empty($line['currency_id']) && is_array($line['currency_id'])) ? (string)$line['currency_id'][1] : 'MYR';$rate = $fx_rates[$cur] ?? 1.0;
            $discount_amt_myr =$rate > 0 ? $discount_amt / $rate : 0;

            $final_data[] = [
                'date'             => $line['date'] ?? '',
                'invoice_num'      => $invoice_num,
                'partner_name'     => $partner_name,
                'student_type'     => $pid_type_map[$pid] ?? '',
                'desc'             => mb_substr(str_replace("\n", ' ', $line['name'] ?? ''), 0, 55) . (mb_strlen($line['name']??'')>55?'...':''),
                'disc_type'        => $disc_type,
                'disc_label'       => $disc_label,
                'discount_pct'     => $discount_pct,
                'discount_amt'     => $discount_amt,
                'currency'         => $cur,
                'discount_amt_myr' => $discount_amt_myr
            ];
        }
    } else {
        $error = 'فشل جلب البيانات من أودو أو لا توجد نتائج.';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تقرير التخفيضات الشامل — Marifah</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body { font-family: 'Tajawal', sans-serif; background: #f0f4f8; }
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.kpi-card { transition: transform 0.2s, box-shadow 0.2s; }
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
@media print { .no-print { display: none !important; } body { background: white; } }
</style>
</head>
<body class="min-h-screen p-4 md:p-6 antialiased">

<div class="max-w-screen-xl mx-auto space-y-5">
    
    <!-- ══ Header ══ -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 no-print">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-red-600 to-rose-700 flex items-center justify-center shadow-md flex-shrink-0">
            <i class="fas fa-tags text-white text-xl"></i>
          </div>
          <div>
            <h1 class="text-xl font-extrabold text-gray-900">تقرير التخفيضات الشامل</h1>
            <p class="text-gray-500 text-xs mt-0.5">لوحة تحليل الفواتير المخصومة ديناميكياً — <?=date('d/m/Y')?></p>
          </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <?php if($error):?>
            <span class="bg-red-50 text-red-600 border border-red-100 px-3 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5">
              <i class="fas fa-circle-xmark"></i> <?=htmlspecialchars($error)?>
            </span>
          <?php endif;?>
          <button onclick="window.print()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl text-sm font-bold transition-colors flex items-center gap-2">
            <i class="fas fa-print"></i> طباعة
          </button>
          <a href="index.html" class="bg-red-700 hover:bg-red-800 text-white px-4 py-2 rounded-xl text-sm font-bold transition-colors flex items-center gap-2 shadow-sm">
            <i class="fas fa-home"></i> الرئيسية
          </a>
        </div>
      </div>
    </div>

    <!-- ══ KPIs (الإحصائيات اللحظية) ══ -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 no-print">
      <div class="kpi-card bg-white rounded-2xl border border-gray-100 p-4 shadow-sm border-r-4 border-r-indigo-500">
        <p class="text-xs font-bold text-gray-400 mb-1">إجمالي المبالغ المخفضة</p>
        <p class="text-xl font-extrabold text-indigo-700" dir="ltr" id="kpi-total-myr">0.00</p>
        <p class="text-xs text-gray-400 mt-1">بالمعادل (MYR)</p>
      </div>
      <div class="kpi-card bg-white rounded-2xl border border-gray-100 p-4 shadow-sm border-r-4 border-r-blue-500">
        <p class="text-xs font-bold text-gray-400 mb-1">تخفيضات أون لاين (41,43)</p>
        <p class="text-xl font-extrabold text-blue-600" dir="ltr" id="kpi-online">0.00</p>
        <p class="text-xs text-gray-400 mt-1">بالمعادل (MYR)</p>
      </div>
      <div class="kpi-card bg-white rounded-2xl border border-gray-100 p-4 shadow-sm border-r-4 border-r-emerald-500">
        <p class="text-xs font-bold text-gray-400 mb-1">تخفيضات حضوري (2172)</p>
        <p class="text-xl font-extrabold text-emerald-600" dir="ltr" id="kpi-onsite">0.00</p>
        <p class="text-xs text-gray-400 mt-1">بالمعادل (MYR)</p>
      </div>
      <div class="kpi-card bg-white rounded-2xl border border-gray-100 p-4 shadow-sm border-r-4 border-r-amber-500">
        <p class="text-xs font-bold text-gray-400 mb-1">تخفيضات النسبة %</p>
        <p class="text-xl font-extrabold text-amber-600" dir="ltr" id="kpi-percent">0.00</p>
        <p class="text-xs text-gray-400 mt-1">بالمعادل (MYR)</p>
      </div>
      <div class="kpi-card bg-white rounded-2xl border border-gray-100 p-4 shadow-sm border-r-4 border-r-gray-400">
        <p class="text-xs font-bold text-gray-400 mb-1">عدد الفواتير / الأسطر</p>
        <p class="text-xl font-extrabold text-gray-700" id="kpi-count">0</p>
        <p class="text-xs text-gray-400 mt-1">سطر تخفيض ظاهر</p>
      </div>
    </div>

    <!-- ══ Filters (الفلاتر) ══ -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 no-print">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1.5">بحث (طالب أو فاتورة)</label>
          <div class="relative">
            <input type="text" id="filterSearch" placeholder="اكتب للبحث..."
              class="w-full border border-gray-200 rounded-xl py-2 pr-9 pl-3 text-sm focus:ring-2 focus:ring-red-400 outline-none">
            <i class="fas fa-search absolute top-3 right-3 text-gray-400 text-sm"></i>
          </div>
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1.5">نوع التخفيض</label>
          <div class="relative">
            <select id="filterDiscType" class="w-full border border-gray-200 rounded-xl py-2 px-3 pl-8 text-sm appearance-none focus:ring-2 focus:ring-red-400 outline-none bg-white">
              <option value="">جميع التخفيضات</option>
              <option value="online">تخفيض أون لاين (41, 43)</option>
              <option value="onsite">تخفيض حضوري (2172)</option>
              <option value="percent">تخفيض نسبة %</option>
              <option value="other">أخرى (مبالغ سالبة)</option>
            </select>
            <i class="fas fa-chevron-down absolute top-3 left-3 text-gray-400 text-xs pointer-events-none"></i>
          </div>
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1.5">نوع الدراسة (للطالب)</label>
          <div class="relative">
            <select id="filterStudyType" class="w-full border border-gray-200 rounded-xl py-2 px-3 pl-8 text-sm appearance-none focus:ring-2 focus:ring-red-400 outline-none bg-white">
              <option value="">الكل</option>
              <option value="physical">حضوري (On-site)</option>
              <option value="online">أون لاين (Online)</option>
              <option value="both">هجين (Both)</option>
            </select>
            <i class="fas fa-chevron-down absolute top-3 left-3 text-gray-400 text-xs pointer-events-none"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- ══ Table ══ -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="overflow-x-auto" style="max-height: 60vh; overflow-y: auto;">
        <table class="w-full text-right text-sm border-collapse">
          <thead>
            <tr class="bg-gray-800 text-white">
              <th class="py-3 px-4 sticky top-0 bg-gray-800 z-10">التاريخ</th>
              <th class="py-3 px-4 sticky top-0 bg-gray-800 z-10">رقم الفاتورة</th>
              <th class="py-3 px-4 sticky top-0 bg-gray-800 z-10">اسم الطالب / العميل</th>
              <th class="py-3 px-4 sticky top-0 bg-gray-800 z-10 text-center">نوع الدراسة</th>
              <th class="py-3 px-4 sticky top-0 bg-gray-800 z-10">وصف السطر (الصنف)</th>
              <th class="py-3 px-4 sticky top-0 bg-gray-800 z-10 text-center">نوع التخفيض</th>
              <th class="py-3 px-4 sticky top-0 bg-gray-800 z-10 text-center">النسبة</th>
              <th class="py-3 px-4 sticky top-0 bg-gray-800 z-10 text-left">المبلغ המخصوم</th>
            </tr>
          </thead>
          <tbody id="tbody" class="divide-y divide-gray-100">
            <!-- سيتم ملؤه بواسطة الجافاسكريبت -->
          </tbody>
          <tfoot id="tfoot" class="bg-gray-50 border-t-2 border-gray-300">
            <!-- سيتم ملؤه بواسطة الجافاسكريبت (الإجماليات) -->
          </tfoot>
        </table>
      </div>
    </div>

</div>

<script>
const RAW_DATA = <?=json_encode($final_data, JSON_UNESCAPED_UNICODE)?>;

// دوال مساعدة للتنسيق
function fmt(num) { return parseFloat(num).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); }
function fmtMYR(num) { return parseFloat(num).toLocaleString('en-MY', {minimumFractionDigits:2, maximumFractionDigits:2}); }

// الحصول على فئات CSS لنوع الدراسة والتخفيض
function getStudyBadge(type) {
    if (type === 'physical') return '<span class="px-2 py-0.5 rounded text-xs font-bold bg-sky-50 text-sky-700 border border-sky-200">On-site</span>';
    if (type === 'online') return '<span class="px-2 py-0.5 rounded text-xs font-bold bg-purple-50 text-purple-700 border border-purple-200">Online</span>';
    if (type === 'both') return '<span class="px-2 py-0.5 rounded text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">هجين</span>';
    return '<span class="px-2 py-0.5 rounded text-xs font-bold bg-gray-100 text-gray-500 border border-gray-200">غير محدد</span>';
}

function getDiscBadge(type, label) {
    if (type === 'online') return `<span class="px-2 py-0.5 rounded text-xs font-bold bg-blue-100 text-blue-700">${label}</span>`;
    if (type === 'onsite') return `<span class="px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-700">${label}</span>`;
    if (type === 'percent') return `<span class="px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-700">${label}</span>`;
    return `<span class="px-2 py-0.5 rounded text-xs font-bold bg-gray-100 text-gray-600">${label}</span>`;
}

function renderTable() {
    const searchVal = document.getElementById('filterSearch').value.toLowerCase().trim();
    const discVal = document.getElementById('filterDiscType').value;
    const studyVal = document.getElementById('filterStudyType').value;

    let sumTotalMyr = 0, sumOnlineMyr = 0, sumOnsiteMyr = 0, sumPercentMyr = 0;
    let totalsByCurrency = {};
    let count = 0;
    
    let html = '';

    RAW_DATA.forEach(row => {
        // فحص الفلاتر
        if (searchVal && !row.partner_name.toLowerCase().includes(searchVal) && !row.invoice_num.toLowerCase().includes(searchVal)) return;
        if (discVal && row.disc_type !== discVal) return;
        if (studyVal && row.student_type !== studyVal) return;

        // حساب الإجماليات
        count++;
        sumTotalMyr += row.discount_amt_myr;
        if (row.disc_type === 'online') sumOnlineMyr += row.discount_amt_myr;
        if (row.disc_type === 'onsite') sumOnsiteMyr += row.discount_amt_myr;
        if (row.disc_type === 'percent') sumPercentMyr += row.discount_amt_myr;

        if (!totalsByCurrency[row.currency]) totalsByCurrency[row.currency] = 0;
        totalsByCurrency[row.currency] += row.discount_amt;

        // بناء الصف
        html += `
        <tr class="hover:bg-gray-50 transition-colors">
            <td class="py-3 px-4 text-xs font-bold text-gray-500">${row.date}</td>
            <td class="py-3 px-4 font-extrabold text-red-700">${row.invoice_num}</td>
            <td class="py-3 px-4 font-bold text-gray-800">${row.partner_name}</td>
            <td class="py-3 px-4 text-center">${getStudyBadge(row.student_type)}</td>
            <td class="py-3 px-4 text-xs text-gray-600 font-medium">${row.desc}</td>
            <td class="py-3 px-4 text-center">${getDiscBadge(row.disc_type, row.disc_label)}</td>
            <td class="py-3 px-4 text-center font-bold ${row.discount_pct > 0 ? 'text-amber-600' : 'text-gray-300'}">
                ${row.discount_pct > 0 ? row.discount_pct + '%' : '—'}
            </td>
            <td class="py-3 px-4 text-left font-extrabold text-red-600 whitespace-nowrap" dir="ltr">
                ${fmt(row.discount_amt)} <span class="text-xs font-normal text-gray-400 ml-1">${row.currency}</span>
            </td>
        </tr>`;
    });

    if (count === 0) {
        html = `<tr><td colspan="8" class="py-16 text-center text-gray-400">
                <i class="fas fa-search text-3xl mb-3 block opacity-30"></i>لا توجد نتائج مطابقة للبحث</td></tr>`;
    }

    document.getElementById('tbody').innerHTML = html;

    // تحديث بطاقات الإحصائيات (KPIs)
    document.getElementById('kpi-total-myr').innerText = fmtMYR(sumTotalMyr);
    document.getElementById('kpi-online').innerText = fmtMYR(sumOnlineMyr);
    document.getElementById('kpi-onsite').innerText = fmtMYR(sumOnsiteMyr);
    document.getElementById('kpi-percent').innerText = fmtMYR(sumPercentMyr);
    document.getElementById('kpi-count').innerText = count;

    // تحديث التذييل (الإجماليات تحت الأعمدة)
    let footerHtml = '';
    Object.keys(totalsByCurrency).forEach(cur => {
        footerHtml += `
        <tr class="border-b border-gray-200">
            <td colspan="7" class="py-3 px-4 text-right font-bold text-gray-700">إجمالي مبالغ التخفيضات بالـ (${cur}) للبيانات الظاهرة</td>
            <td class="py-3 px-4 text-left font-extrabold text-red-700 text-base" dir="ltr">
                ${fmt(totalsByCurrency[cur])} <span class="text-xs text-gray-500">${cur}</span>
            </td>
        </tr>`;
    });
    
    // سطر الإجمالي الموحد
    footerHtml += `
    <tr class="bg-indigo-50">
        <td colspan="7" class="py-4 px-4 text-right font-extrabold text-indigo-900 text-base">الإجمالي الموحّد بالرنجت (المعادل لـ MYR)</td>
        <td class="py-4 px-4 text-left font-extrabold text-indigo-700 text-lg" dir="ltr">
            ${fmtMYR(sumTotalMyr)} <span class="text-xs text-indigo-400">MYR</span>
        </td>
    </tr>`;

    document.getElementById('tfoot').innerHTML = footerHtml;
}

// إضافة الأحداث (Events) للفلاتر لكي تعمل فوراً عند التغيير
document.getElementById('filterSearch').addEventListener('input', renderTable);
document.getElementById('filterDiscType').addEventListener('change', renderTable);
document.getElementById('filterStudyType').addEventListener('change', renderTable);

// تشغيل العرض لأول مرة
renderTable();
</script>
</body>
</html>