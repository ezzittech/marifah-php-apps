<?php
// ══════════════════════════════════════════════
//  كشف الرواتب — Marifah International School
// ══════════════════════════════════════════════

$odoo_url = 'https://portal.marifah.edu.my';
$db       = 'Marifahdb';
$username = 'ezzittech@gmail.com';
$password = 'Abulez@2526';

// ── JSON-RPC مع session cookie ─────────────────
function<?php
// ══════════════════════════════════════════════
//  كشف الرواتب الموحد — Marifah & Almunifi
// ══════════════════════════════════════════════

$odoo_url = 'https://portal.marifah.edu.my';
$db       = 'Marifahdb';
$username = 'ezzittech@gmail.com';
$password = 'Abulez@2526';

// ── JSON-RPC مع session cookie ─────────────────
function odoo_call($url, $db, $password, $model, $method, $args, $kwargs = []) {
    static $session_id = null;
    global $username;
    if (!$session_id) {
        $login_payload = json_encode([
            'jsonrpc'=>'2.0','method'=>'call','id'=>1,
            'params'=>['db'=>$db,'login'=>$username,'password'=>$password]
        ]);
        $ch = curl_init("$url/web/session/authenticate");
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>$login_payload,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_TIMEOUT=>15,
            CURLOPT_HEADER=>true,
            CURLOPT_COOKIEFILE=>'', CURLOPT_COOKIEJAR=>'/tmp/odoo_cookies.txt'
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        preg_match('/session_id=([^;]+)/', $res, $m);
        $session_id = $m[1] ?? '';
    }
    $payload = json_encode([
        'jsonrpc'=>'2.0','method'=>'call','id'=>1,
        'params'=>[
            'model'  => $model,
            'method' => $method,
            'args'   => $args,
            'kwargs' => array_merge(['context'=>['lang'=>'en_US']], $kwargs)
        ]
    ]);
    $ch = curl_init("$url/web/dataset/call_kw");
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>$payload,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json',"Cookie: session_id=$session_id"],
        CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_TIMEOUT=>30,
        CURLOPT_COOKIEFILE=>'/tmp/odoo_cookies.txt'
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode($res, true);
    return $decoded['result'] ?? [];
}

// ── الشهر المحدد ──────────────────────────────
$now       = new DateTime();
$sel_year  = intval($_GET['year']  ?? $now->format('Y'));
$sel_month = intval($_GET['month'] ?? $now->format('m'));
$sel_search= trim($_GET['search']  ?? '');
$sel_tag   = trim($_GET['tag']     ?? '');

$date_start  = sprintf('%04d-%02d-01', $sel_year, $sel_month);
$date_end    = date('Y-m-t', strtotime($date_start));
$month_label = (new DateTime($date_start))->format('F Y');

// ══════════════════════════════════════════════
//  جلب البيانات
// ══════════════════════════════════════════════
$lines    = [];
$all_tags = [];
$error    = '';

// 1) جلب الـ batches في الشهر (غير draft فقط)
$batches_raw = odoo_call($odoo_url, $db, $password,
    'marifah.fast.payroll', 'search_read',
    [[
        ['date_start', '>=', $date_start],
        ['date_start', '<=', $date_end],
        ['state', 'in', ['payslips_created', 'paid']]
    ]],
    ['fields' => ['id','name','date_start','date_end','state','company_id']]
);

if (empty($batches_raw)) {
    // لا يوجد batches
} else {
    $batch_ids = array_column($batches_raw, 'id');
    $batch_map = [];
    foreach ($batches_raw as $b) $batch_map[$b['id']] = $b;

    // 2) جلب كل سطور الموظفين من هذه الـ batches
    $raw_lines = odoo_call($odoo_url, $db, $password,
        'marifah.fast.payroll.line', 'search_read',
        [[['batch_id', 'in', $batch_ids]]],
        ['fields' => [
            'id','batch_id','employee_id','company_id',
            'basic_salary','additional_allowance','bonus_amount','gross_salary',
            'absent_days','late_hours','advance_ded','fees_ded',
            'epf_emp','socso_emp','eis_emp','pcb','other_ded',
            'total_deductions','net_salary'
        ]]
    );

    if (!empty($raw_lines)) {
        // 3) جلب تاجز الموظفين
        $emp_ids = array_unique(array_map(
            fn($l) => is_array($l['employee_id']) ? $l['employee_id'][0] : $l['employee_id'],
            $raw_lines
        ));

        $employees = odoo_call($odoo_url, $db, $password,
            'hr.employee', 'search_read',
            [[['id', 'in', array_values($emp_ids)]]],
            ['fields' => ['id','name','category_ids']]
        );
        $emp_map = [];
        foreach ($employees as $e) $emp_map[$e['id']] = $e;

        // 4) جلب أسماء التاجز
        $all_cat_ids = [];
        foreach ($employees as $e)
            foreach ($e['category_ids'] as $cid) $all_cat_ids[] = $cid;
        $all_cat_ids = array_unique($all_cat_ids);

        $categories = odoo_call($odoo_url, $db, $password,
            'hr.employee.category', 'search_read',
            [[['id', 'in', array_values($all_cat_ids)]]],
            ['fields' => ['id','name']]
        );
        $cat_map = [];
        foreach ($categories as $c) $cat_map[$c['id']] = $c['name'];

        // تجميع tags لكل موظف
        $emp_tags = [];
        foreach ($employees as $e) {
            $tags = [];
            foreach ($e['category_ids'] as $cid)
                if (isset($cat_map[$cid])) $tags[] = $cat_map[$cid];
            $emp_tags[$e['id']] = $tags;
            foreach ($tags as $t)
                if (!in_array($t, ['Payroll','Local','Redbytes','Regular']))
                    $all_tags[$t] = true;
        }
        ksort($all_tags);

        // 5) بناء قائمة الموظفين النهائية
        foreach ($raw_lines as $line) {
            $emp_id   = is_array($line['employee_id']) ? $line['employee_id'][0] : $line['employee_id'];
            $emp_name = is_array($line['employee_id']) ? $line['employee_id'][1] : '';
            $tags     = $emp_tags[$emp_id] ?? [];

            // فقط الذين عندهم تاج Payroll
            if (!in_array('Payroll', $tags)) continue;

            // فلتر البحث
            if ($sel_search && stripos($emp_name, $sel_search) === false) continue;

            // فلتر التاج
            if ($sel_tag && !in_array($sel_tag, $tags)) continue;

            // بيانات الـ batch
            $batch_id = is_array($line['batch_id']) ? $line['batch_id'][0] : $line['batch_id'];
            $batch    = $batch_map[$batch_id] ?? [];
            $is_local = in_array('Local', $tags);

            // Group classification - ordered: Administration, Student Services, Teachers, Services, Others
            $main_tag   = 'Others';
            $main_order = 99;
            $tag_priority = [
                'Administration'   => ['label'=>'Administration',   'order'=>1],
                'Student Services' => ['label'=>'Student Services', 'order'=>2],
                'Teachers'         => ['label'=>'Teachers',         'order'=>3],
                'Service'          => ['label'=>'Services',         'order'=>4],
            ];
            foreach ($tag_priority as $tk => $tv)
                if (in_array($tk, $tags)) {
                    $main_tag   = $tv['label'];
                    $main_order = $tv['order'];
                    break;
                }

            $line['_emp_name']    = $emp_name;
            $line['_tags']        = $tags;
            $line['_is_local']    = $is_local;
            $line['_batch_state'] = $batch['state'] ?? '';
            $line['_main_tag']    = $main_tag;
            $line['_main_order']  = $main_order;
            $lines[] = $line;
        }

        // ترتيب: تصنيف ← اسم (تم إلغاء الترتيب حسب الشركة)
        usort($lines, fn($a,$b) =>
            ($a['_main_order'] - $b['_main_order']) ?:
            strcmp($a['_emp_name'], $b['_emp_name'])
        );
    }
}

// ── دوال مساعدة ──────────────────────────────
function fmt($n) { return number_format((float)$n, 2); }

function totals($rows) {
    $t = array_fill_keys(['basic_salary','additional_allowance','bonus_amount','gross_salary',
        'absent_days','late_hours','advance_ded','fees_ded','epf_emp','socso_emp','eis_emp',
        'pcb','other_ded','total_deductions','net_salary'], 0);
    foreach ($rows as $r) foreach ($t as $k => &$v) $v += floatval($r[$k] ?? 0);
    return $t;
}

$months = ['01'=>'January','02'=>'February','03'=>'March','04'=>'April','05'=>'May',
           '06'=>'June','07'=>'July','08'=>'August','09'=>'September','10'=>'October',
           '11'=>'November','12'=>'December'];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Consolidated Payroll Report — Marifah</title>
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#f8f5f5;margin:0}

/* قفل */
#lock-screen{position:fixed;inset:0;z-index:9999;
  background:linear-gradient(135deg,#1a0608 0%,#3d0c14 50%,#1a0608 100%);
  display:flex;align-items:center;justify-content:center}
#lock-screen.hidden{display:none}
.lock-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);
  border-radius:24px;padding:48px 40px;width:100%;max-width:400px;
  text-align:center;backdrop-filter:blur(20px)}
.lock-input{width:100%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);
  border-radius:12px;padding:14px 18px;color:white;font-family:'Tajawal',sans-serif;
  font-size:20px;letter-spacing:6px;text-align:center;outline:none;
  transition:border-color .2s;margin:24px 0 20px}
.lock-input:focus{border-color:rgba(180,30,55,.6)}
.lock-input.error{border-color:#ef4444;animation:shake .4s ease}
@keyframes shake{0%,100%{transform:translateX(0)}20%{transform:translateX(-8px)}
  40%{transform:translateX(8px)}60%{transform:translateX(-5px)}80%{transform:translateX(5px)}}
.lock-btn{width:100%;background:linear-gradient(135deg,#8b1a2a,#b41e37);color:white;
  border:none;border-radius:12px;padding:14px;font-family:'Tajawal',sans-serif;
  font-size:16px;font-weight:700;cursor:pointer;transition:opacity .2s,transform .2s}
.lock-btn:hover{opacity:.9;transform:translateY(-1px)}
.lock-err{color:#f87171;font-size:13px;margin-top:12px;min-height:20px}

/* تطبيق */
#main-app{display:none}
#main-app.visible{display:block}
.navbar{background:rgba(26,6,8,.95);backdrop-filter:blur(16px);
  border-bottom:1px solid rgba(180,30,55,.2);position:sticky;top:0;z-index:100}
.hero{background:linear-gradient(160deg,#1a0608 0%,#3d0c14 40%,#6b1020 70%,#3d0c14 100%);
  padding:36px 24px 52px}
.filter-bar{background:white;border:1px solid rgba(139,26,42,.12);border-radius:16px;
  padding:20px 24px;box-shadow:0 4px 20px rgba(139,26,42,.06)}
.filter-input{background:#f8f5f5;border:1px solid rgba(139,26,42,.15);border-radius:10px;
  padding:10px 14px;font-family:'Tajawal',sans-serif;font-size:14px;outline:none;
  transition:border-color .2s;width:100%}
.filter-input:focus{border-color:rgba(139,26,42,.4)}
.btn-primary{background:linear-gradient(135deg,#8b1a2a,#b41e37);color:white;border:none;
  border-radius:10px;padding:10px 20px;font-family:'Tajawal',sans-serif;font-weight:700;
  cursor:pointer;font-size:14px;transition:opacity .2s,transform .2s}
.btn-primary:hover{opacity:.9;transform:translateY(-1px)}
.btn-secondary{background:white;color:#8b1a2a;border:1px solid rgba(139,26,42,.3);
  border-radius:10px;padding:10px 20px;font-family:'Tajawal',sans-serif;font-weight:700;
  cursor:pointer;font-size:14px;transition:all .2s;text-decoration:none;display:inline-block}
.btn-secondary:hover{background:rgba(139,26,42,.05)}

/* جدول */
.payroll-table{width:100%;border-collapse:collapse;font-size:12.5px}
.payroll-table th{background:linear-gradient(135deg,#1a0608,#3d0c14);color:white;
  padding:9px 7px;text-align:left;font-weight:700;white-space:nowrap}
.payroll-table td{padding:8px 7px;text-align:left;border-bottom:1px solid #f0e8e8;
  white-space:nowrap}
.payroll-table tr:hover td{background:rgba(139,26,42,.02)}
.payroll-table tr.grp-hdr td{background:rgba(139,26,42,.07);font-weight:800;
  color:#8b1a2a;border-top:2px solid rgba(139,26,42,.2);font-size:12px;padding:7px 10px;text-align:left;}
.payroll-table tr.sub-tot td{background:rgba(139,26,42,.04);font-weight:700;
  color:#3d0c14;border-top:1px solid rgba(139,26,42,.12)}
.payroll-table tr.grand-tot td{background:linear-gradient(135deg,#1a0608,#3d0c14);
  color:white;font-weight:800;border-top:2px solid #8b1a2a}
.col-name{text-align:left !important;min-width:150px}
.neg{color:#dc2626}
.pos{color:#16a34a;font-weight:700}
.net-val{color:#1d4ed8;font-weight:800}

.company-section{background:white;border:1px solid rgba(139,26,42,.12);border-radius:16px;
  overflow:hidden;box-shadow:0 4px 20px rgba(139,26,42,.06);margin-bottom:28px}
.company-header{background:linear-gradient(135deg,#3d0c14,#8b1a2a);color:white;
  padding:16px 24px;display:flex;align-items:center;justify-content:space-between}
.table-scroll{overflow-x:auto}

.contrib-box{background:white;border-radius:10px;padding:12px 16px;text-align:center;
  border:1px solid #e5e7eb}

.sign-line{height:1px;background:rgba(139,26,42,.25);margin-top:12px;margin-bottom:6px}

@media print{
  @page{size:A4 landscape;margin:8mm 6mm}
  #lock-screen,.navbar,.hero,.filter-bar,.no-print{display:none !important}
  #main-app{display:block !important}
  body{background:white !important;font-size:8px !important}
  .print-header{display:block !important}
  .company-section{box-shadow:none !important;border:1px solid #bbb !important;
    border-radius:0 !important;margin-bottom:8mm !important}
  .payroll-table tr { page-break-inside: avoid !important; }
  .company-header{background:#3d0c14 !important;
    -webkit-print-color-adjust:exact;print-color-adjust:exact;
    padding:5px 10px !important}
  .company-header h2{font-size:10px !important;margin:0}
  .company-header p{font-size:7.5px !important;margin:0}
  .company-header .text-2xl{font-size:11px !important}
  .table-scroll{overflow:visible !important}
  .payroll-table{font-size:7px !important;width:100% !important}
  .payroll-table th{background:#1a0608 !important;
    -webkit-print-color-adjust:exact;print-color-adjust:exact;
    padding:3px 3px !important;font-size:6.5px !important;text-align:left !important}
  .payroll-table td{padding:2.5px 3px !important;border-bottom:1px solid #eee;text-align:left !important}
  .payroll-table tr.grp-hdr td{background:#f0e8e8 !important;
    -webkit-print-color-adjust:exact;print-color-adjust:exact;
    padding:3px 6px !important;font-size:7px !important;font-weight:700;color:#6b1020}
  .payroll-table tr.sub-tot td{background:#fdf5f5 !important;
    -webkit-print-color-adjust:exact;print-color-adjust:exact;font-weight:600}
  .payroll-table tr.grand-tot td{background:#1a0608 !important;
    -webkit-print-color-adjust:exact;print-color-adjust:exact;font-size:7.5px !important}
  .col-name{min-width:80px !important;text-align:left !important}
  .p-4,.p-6{padding:4px 8px !important}
  .contrib-box{padding:3px 6px !important;min-width:60px !important;display:inline-block;margin:2px}
  .contrib-box p{font-size:6.5px !important;margin:0}
  .sign-line{margin-top:40px !important}
  .text-xs,.text-sm{font-size:7px !important}
  .bg-gray-50{background:#fafafa !important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .max-w-7xl{max-width:100% !important;padding:0 !important;margin:0 !important}
}
.print-header{display:none}
</style>
</head>
<body>

<!-- قفل -->
<div id="lock-screen">
  <div class="lock-card">
    <img src="https://cdn.prod.website-files.com/642e38eb59ef94ba3c2bf35b/68696ec0e6fe2cab7b377c39_724c268af6d0a34b14c1961f01f18585_Marifah_CMYK-p-500.png"
         alt="Marifah" class="h-20 object-contain mx-auto mb-2 brightness-0 invert opacity-90">
    <h2 class="text-white text-xl font-extrabold mt-4">Payroll Report</h2>
    <p class="text-slate-500 text-sm mt-1">Enter password to continue</p>
    <input type="password" id="pwInput" class="lock-input" placeholder="••••"
           maxlength="8" onkeydown="if(event.key==='Enter')checkPw()">
    <button class="lock-btn" onclick="checkPw()">
      <i class="fas fa-unlock-alt mr-2"></i> Unlock
    </button>
    <p class="lock-err" id="pwErr"></p>
  </div>
</div>

<!-- التطبيق -->
<div id="main-app">

<!-- Navbar -->
<nav class="navbar px-6 py-3 no-print">
  <div class="max-w-7xl mx-auto flex items-center justify-between">
    <div class="flex items-center gap-3">
      <img src="https://cdn.prod.website-files.com/642e38eb59ef94ba3c2bf35b/68696ec0e6fe2cab7b377c39_724c268af6d0a34b14c1961f01f18585_Marifah_CMYK-p-500.png"
           alt="Marifah" class="h-8 object-contain brightness-0 invert opacity-80">
      <span class="text-white/60 text-sm">Consolidated Payroll Report</span>
    </div>
    <div class="flex items-center gap-4">
      <span class="text-white/40 text-xs" id="navDate"></span>
      <a href="index.php" class="text-white/50 hover:text-white/80 text-xs transition-colors">
        <i class="fas fa-home mr-1"></i>Home
      </a>
      <button onclick="exportToExcel()" class="text-white/40 hover:text-green-400 text-xs transition-colors">
        <i class="fas fa-file-excel mr-1"></i>Excel
      </button>
      <button onclick="window.print()" class="text-white/40 hover:text-white/80 text-xs transition-colors">
        <i class="fas fa-print mr-1"></i>Print
      </button>
      <button onclick="lockApp()" class="text-white/40 hover:text-red-400 text-xs transition-colors">
        <i class="fas fa-lock mr-1"></i>Lock
      </button>
    </div>
  </div>
</nav>

<!-- Hero + Filter -->
<div class="hero no-print">
  <div class="max-w-7xl mx-auto">
    <h1 class="text-3xl font-extrabold text-white mb-1">
      <i class="fas fa-money-bill-wave mr-3 text-rose-400"></i>Consolidated Payroll Report
    </h1>
    <p class="text-white/40 text-sm mb-6">Marifah International School — Live data from Odoo</p>
    <div class="filter-bar">
      <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1">Month</label>
          <select name="month" class="filter-input" style="width:130px">
            <?php foreach($months as $m=>$ml): ?>
            <option value="<?=$m?>" <?=intval($m)==$sel_month?'selected':''?>><?=$ml?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1">Year</label>
          <select name="year" class="filter-input" style="width:100px">
            <?php for($y=2024;$y<=2027;$y++): ?>
            <option value="<?=$y?>" <?=$y==$sel_year?'selected':''?>><?=$y?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1">Group</label>
          <select name="tag" class="filter-input" style="width:170px">
            <option value="">— All —</option>
            <?php foreach(array_keys($all_tags) as $t): ?>
            <option value="<?=htmlspecialchars($t)?>" <?=$sel_tag===$t?'selected':''?>>
              <?=htmlspecialchars($t)?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="flex:1;min-width:180px">
          <label class="block text-xs font-bold text-gray-500 mb-1">Search Employee</label>
          <input type="text" name="search" value="<?=htmlspecialchars($sel_search)?>"
                 placeholder="Type employee name..." class="filter-input">
        </div>
        <div class="flex gap-2">
          <button type="submit" class="btn-primary">
            <i class="fas fa-filter mr-1"></i>Apply
          </button>
          <a href="payroll_report.php" class="btn-secondary">
            <i class="fas fa-undo mr-1"></i>Reset
          </a>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- رأس الطباعة -->
<div class="print-header text-center py-4 border-b border-gray-200 mb-4 px-6">
  <img src="https://cdn.prod.website-files.com/642e38eb59ef94ba3c2bf35b/68696ec0e6fe2cab7b377c39_724c268af6d0a34b14c1961f01f18585_Marifah_CMYK-p-500.png"
       alt="Marifah" class="h-14 object-contain mx-auto mb-2">
  <h1 class="text-xl font-extrabold">Marifah International School</h1>
  <h2 id="reportTitle" class="text-base font-bold text-gray-600">Monthly Payroll Report — <?=$month_label?></h2>
  <p class="text-xs text-gray-400 mt-1">Printed: <?=date('d M Y, H:i')?></p>
</div>

<!-- المحتوى -->
<div class="max-w-7xl mx-auto px-4 py-8" style="margin-top:-16px">

<?php if(empty($lines)): ?>
<div class="bg-white rounded-xl p-12 text-center border border-gray-100">
  <i class="fas fa-inbox text-4xl text-gray-300 mb-4 block"></i>
  <p class="text-gray-400 font-bold">No approved payroll data for this period</p>
  <p class="text-gray-300 text-sm mt-1"><?=$month_label?></p>
</div>
<?php else: ?>

<?php
// إعداد وتجميع البيانات الشاملة (بدون تقسيم بالشركة)
$by_tag = [];
foreach($lines as $l) $by_tag[$l['_main_tag']][] = $l;

// ترتيب المجموعات بناءً على الأولوية
uksort($by_tag, function($a,$b) {
    $order_map = ['Administration'=>1,'Student Services'=>2,'Teachers'=>3,'Services'=>4,'Others'=>99];
    return ($order_map[$a]??99) - ($order_map[$b]??99);
});

$grand_totals = totals($lines);
$has_contrib = array_sum(array_column($lines,'epf_emp')) > 0;
// عدد الأعمدة: الأساسية + عمود Contribution إذا وجد
$cols = $has_contrib ? 14 : 13;
$grand_ee_contrib = $grand_totals['epf_emp'] + $grand_totals['socso_emp'] + $grand_totals['eis_emp'];
?>

<div class="company-section">
  <div class="company-header">
    <div>
      <h2 class="text-lg font-extrabold">Consolidated Employees List</h2>
      <p class="text-white/50 text-sm mt-0.5"><?=$month_label?> — <?=count($lines)?> employees</p>
    </div>
    <div class="text-left">
      <p class="text-white/50 text-xs mb-0.5">Total Net Salary</p>
      <p class="text-2xl font-extrabold text-green-300"><?=fmt($grand_totals['net_salary'])?> MYR</p>
    </div>
  </div>

  <div class="table-scroll">
  <table class="payroll-table">
    <thead>
      <tr>
        <th rowspan="2" style="min-width:30px;background:#1a0608">#</th>
        <th class="col-name" rowspan="2" style="background:#1a0608">Employee</th>
        <th colspan="3" style="background:#1e3a5f">Earnings</th>
        <th rowspan="2" style="background:#1a365d">Gross</th>
        <th colspan="<?=$has_contrib ? 6 : 5?>" style="background:#5c1a1a">Deductions</th>
        <th rowspan="2" style="background:#4c1d95">Total Ded.</th>
        <th rowspan="2" style="background:#1e3a8a;min-width:90px">Net Salary</th>
      </tr>
      <tr>
        <th style="background:#1e3a5f">Basic</th>
        <th style="background:#1e3a5f">Allowance</th>
        <th style="background:#1e3a5f">Bonus</th>
        <th style="background:#5c1a1a">Absent</th>
        <th style="background:#5c1a1a">Late</th>
        <th style="background:#5c1a1a">Advance</th>
        <th style="background:#5c1a1a">Fees</th>
        <th style="background:#5c1a1a">Other</th>
        <?php if($has_contrib): ?>
        <th style="background:#5c1a1a">Contribution</th>
        <?php endif; ?>
      </tr>
    </thead>
    <tbody>
    <?php $rn=1; foreach($by_tag as $tag_label => $tag_lines):
        $tt = totals($tag_lines); ?>
      <tr class="grp-hdr">
        <td colspan="<?=$cols?>">
          <i class="fas fa-users mr-2"></i><?=$tag_label?> (<?=count($tag_lines)?> employees)
        </td>
      </tr>
      <?php foreach($tag_lines as $l): 
          $ee_contrib = $l['epf_emp'] + $l['socso_emp'] + $l['eis_emp'];
      ?>
      <tr>
        <td class="text-gray-400 text-xs"><?=$rn++?></td>
        <td class="col-name font-bold text-gray-800"><?=htmlspecialchars($l['_emp_name'])?></td>
        <td><?=fmt($l['basic_salary'])?></td>
        <td><?=$l['additional_allowance']>0?'<span class="pos">+'.fmt($l['additional_allowance']).'</span>':'—'?></td>
        <td><?=$l['bonus_amount']>0?'<span class="pos">+'.fmt($l['bonus_amount']).'</span>':'—'?></td>
        <td class="font-bold"><?=fmt($l['gross_salary'])?></td>
        <td><?=$l['absent_days']>0?'<span class="neg">'.fmt($l['absent_days']).'</span>':'—'?></td>
        <td><?=$l['late_hours']>0?'<span class="neg">'.fmt($l['late_hours']).'</span>':'—'?></td>
        <td><?=$l['advance_ded']>0?'<span class="neg">'.fmt($l['advance_ded']).'</span>':'—'?></td>
        <td><?=$l['fees_ded']>0?'<span class="neg">'.fmt($l['fees_ded']).'</span>':'—'?></td>
        <td><?=$l['other_ded']>0?'<span class="neg">'.fmt($l['other_ded']).'</span>':'—'?></td>
        <?php if($has_contrib): ?>
        <td><?=$ee_contrib>0?'<span class="neg">'.fmt($ee_contrib).'</span>':'—'?></td>
        <?php endif; ?>
        <td class="neg font-bold"><?=fmt($l['total_deductions'])?></td>
        <td class="net-val"><?=fmt($l['net_salary'])?></td>
      </tr>
      <?php endforeach; 
          $tt_ee_contrib = $tt['epf_emp'] + $tt['socso_emp'] + $tt['eis_emp'];
      ?>
      <!-- إجمالي المجموعة -->
      <tr class="sub-tot">
        <td colspan="2" style="text-align:left;padding-left:12px">Subtotal — <?=$tag_label?></td>
        <td><?=fmt($tt['basic_salary'])?></td>
        <td><?=fmt($tt['additional_allowance'])?></td>
        <td><?=fmt($tt['bonus_amount'])?></td>
        <td><?=fmt($tt['gross_salary'])?></td>
        <td><?=fmt($tt['absent_days'])?></td>
        <td><?=fmt($tt['late_hours'])?></td>
        <td><?=fmt($tt['advance_ded'])?></td>
        <td><?=fmt($tt['fees_ded'])?></td>
        <td><?=fmt($tt['other_ded'])?></td>
        <?php if($has_contrib): ?>
        <td><?=fmt($tt_ee_contrib)?></td>
        <?php endif; ?>
        <td><?=fmt($tt['total_deductions'])?></td>
        <td class="net-val"><?=fmt($tt['net_salary'])?></td>
      </tr>
    <?php endforeach; ?>
    <!-- الإجمالي الكلي -->
    <tr class="grand-tot">
      <td colspan="2" style="text-align:left;padding-left:12px">Grand Total</td>
      <td><?=fmt($grand_totals['basic_salary'])?></td>
      <td><?=fmt($grand_totals['additional_allowance'])?></td>
      <td><?=fmt($grand_totals['bonus_amount'])?></td>
      <td><?=fmt($grand_totals['gross_salary'])?></td>
      <td><?=fmt($grand_totals['absent_days'])?></td>
      <td><?=fmt($grand_totals['late_hours'])?></td>
      <td><?=fmt($grand_totals['advance_ded'])?></td>
      <td><?=fmt($grand_totals['fees_ded'])?></td>
      <td><?=fmt($grand_totals['other_ded'])?></td>
      <?php if($has_contrib): ?>
      <td><?=fmt($grand_ee_contrib)?></td>
      <?php endif; ?>
      <td><?=fmt($grand_totals['total_deductions'])?></td>
      <td style="color:#86efac;font-size:14px;font-weight:900"><?=fmt($grand_totals['net_salary'])?></td>
    </tr>
    </tbody>
  </table>
  </div>

  <?php if($has_contrib): ?>
  <!-- Contributions Summary -->
  <div class="p-4 bg-gray-50 border-t border-gray-100">
    <p class="text-xs font-bold text-gray-500 mb-3">Statutory Contributions Summary</p>
    <div class="flex flex-wrap gap-3">
      <div class="contrib-box"><p class="text-xs text-gray-400">EPF (Employee)</p><p class="font-bold text-red-600 mt-0.5"><?=fmt($grand_totals['epf_emp'])?></p></div>
      <div class="contrib-box"><p class="text-xs text-gray-400">SOCSO (Employee)</p><p class="font-bold text-red-600 mt-0.5"><?=fmt($grand_totals['socso_emp'])?></p></div>
      <div class="contrib-box"><p class="text-xs text-gray-400">EIS (Employee)</p><p class="font-bold text-red-600 mt-0.5"><?=fmt($grand_totals['eis_emp'])?></p></div>
      <div class="w-px bg-gray-200 self-stretch mx-1"></div>
      <div class="contrib-box"><p class="text-xs text-gray-400">Total Contributions</p><p class="font-bold text-red-800 mt-0.5"><?=fmt($grand_ee_contrib)?></p></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Signature Lines -->
  <div class="p-6 border-t border-gray-100 mt-2">
    <div class="grid grid-cols-3 gap-10">
      <?php foreach([
          ['title'=>'Accounting Manager', 'name'=>'AHMED ALMALJAMIE'],
          ['title'=>'Executive Chairman', 'name'=>'ABDULNOOR SALEH'],
          ['title'=>'General Manager', 'name'=>'ABDULQAWI AL-QADASI']
      ] as $sig): ?>
      <div class="text-center pt-2">
        <p class="text-xs text-gray-500 font-bold mb-14"><?=$sig['title']?></p>
        <div class="sign-line"></div>
        <p class="text-[11px] text-gray-800 font-extrabold uppercase"><?=$sig['name']?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Overall Summary (بطاقات الخلاصة) -->
<div class="bg-white border border-gray-200 rounded-xl p-6 mt-2 no-print">
  <h3 class="font-extrabold text-gray-800 mb-4 text-base">
    <i class="fas fa-sigma mr-2 text-rose-700"></i>Overall Summary
  </h3>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-gray-50 rounded-xl p-4 text-center">
      <p class="text-xs text-gray-400 font-bold">Total Basic Salary</p>
      <p class="text-xl font-extrabold text-gray-800 mt-1"><?=fmt($grand_totals['basic_salary'])?></p>
    </div>
    <div class="bg-red-50 rounded-xl p-4 text-center">
      <p class="text-xs text-gray-400 font-bold">Total Deductions</p>
      <p class="text-xl font-extrabold text-red-700 mt-1"><?=fmt($grand_totals['total_deductions'])?></p>
    </div>
    <div class="bg-blue-50 rounded-xl p-4 text-center">
      <p class="text-xs text-gray-400 font-bold">Total Net Salary</p>
      <p class="text-xl font-extrabold text-blue-700 mt-1"><?=fmt($grand_totals['net_salary'])?></p>
    </div>
    <div class="bg-green-50 rounded-xl p-4 text-center">
      <p class="text-xs text-gray-400 font-bold">Total Employees</p>
      <p class="text-xl font-extrabold text-green-700 mt-1"><?=count($lines)?></p>
    </div>
  </div>
</div>

<?php endif; ?>

<!-- Footer -->
<div class="mt-8 pt-6 flex items-center justify-between no-print"
     style="border-top:1px solid rgba(139,26,42,.12)">
  <div class="flex items-center gap-3">
    <img src="https://cdn.prod.website-files.com/642e38eb59ef94ba3c2bf35b/68696ec0e6fe2cab7b377c39_724c268af6d0a34b14c1961f01f18585_Marifah_CMYK-p-500.png"
         alt="Marifah" class="h-8 object-contain opacity-40">
    <span class="text-xs" style="color:rgba(139,26,42,.5)">Marifah International School</span>
  </div>
  <p class="text-xs text-gray-300"><i class="fas fa-shield-halved mr-1"></i>Live data from Odoo</p>
</div>
</div>
</div>

<script>
const PASS='2526', SK='marifah_payroll_auth';
function checkSession(){if(sessionStorage.getItem(SK)===PASS)showApp()}
function checkPw(){
  const v=document.getElementById('pwInput').value;
  if(v===PASS){sessionStorage.setItem(SK,PASS);showApp()}
  else{
    const inp=document.getElementById('pwInput');
    inp.classList.add('error');
    document.getElementById('pwErr').textContent='Incorrect password';
    inp.value='';
    setTimeout(()=>inp.classList.remove('error'),500);
  }
}
function showApp(){
  document.getElementById('lock-screen').classList.add('hidden');
  document.getElementById('main-app').classList.add('visible');
}
function lockApp(){
  sessionStorage.removeItem(SK);
  document.getElementById('lock-screen').classList.remove('hidden');
  document.getElementById('main-app').classList.remove('visible');
  document.getElementById('pwInput').value='';
  document.getElementById('pwErr').textContent='';
}

// دالة التصدير إلى إكسل بنفس التصميم
function exportToExcel(){
  let tableHTML = `
  <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
  <head>
    <meta charset="UTF-8">
    <style>
      table { border-collapse: collapse; width: 100%; font-family: sans-serif; font-size: 13px; margin-bottom: 30px; }
      th { color: #ffffff; font-weight: bold; text-align: left; padding: 8px; border: 1px solid #ddd; }
      td { text-align: left; padding: 8px; border: 1px solid #ddd; white-space: nowrap; }
      .grp-hdr td { background-color: #f0e8e8; font-weight: bold; color: #8b1a2a; border-top: 2px solid #8b1a2a; }
      .sub-tot td { background-color: #fdf5f5; font-weight: bold; color: #3d0c14; }
      .grand-tot td { background-color: #1a0608; color: #ffffff; font-weight: bold; }
      .neg { color: #dc2626; }
      .pos { color: #16a34a; font-weight: bold; }
      .net-val { color: #1d4ed8; font-weight: bold; }
      h2 { font-family: sans-serif; color: #333; margin-bottom: 5px; }
      h3 { font-family: sans-serif; color: #666; margin-top: 5px; margin-bottom: 20px; }
    </style>
  </head>
  <body>`;

  let reportTitle = document.getElementById('reportTitle') ? document.getElementById('reportTitle').innerText : 'Payroll Report';
  tableHTML += `<h2>Marifah International School</h2>`;
  tableHTML += `<h3>${reportTitle}</h3>`;

  // أخذ محتوى الجداول بدون الأيقونات
  document.querySelectorAll('.company-section').forEach(section => {
    let companyName = section.querySelector('h2').innerText;
    tableHTML += `<br><h2>${companyName}</h2>`;
    let table = section.querySelector('table');
    if (table) {
      let clonedTable = table.cloneNode(true);
      // إزالة جميع الأيقونات
      clonedTable.querySelectorAll('i').forEach(i => i.remove());
      tableHTML += clonedTable.outerHTML;
    }
  });

  tableHTML += '</body></html>';
  
  // إنشاء رابط التحميل
  let blob = new Blob([tableHTML], { type: 'application/vnd.ms-excel' });
  let link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  
  let y = document.querySelector('select[name="year"]').value;
  let m = document.querySelector('select[name="month"]').value;
  link.download = `Payroll_Report_${y}_${m}.xls`;
  
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

const d=new Date();
document.getElementById('navDate').textContent=
  d.toLocaleDateString('en-MY',{weekday:'short',year:'numeric',month:'short',day:'numeric'});
checkSession();
</script>
</body>
</html> odoo_call($url, $db, $password, $model, $method, $args, $kwargs = []) {
    static $session_id = null;
    global $username;
    if (!$session_id) {
        $login_payload = json_encode([
            'jsonrpc'=>'2.0','method'=>'call','id'=>1,
            'params'=>['db'=>$db,'login'=>$username,'password'=>$password]
        ]);
        $ch = curl_init("$url/web/session/authenticate");
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>$login_payload,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_TIMEOUT=>15,
            CURLOPT_HEADER=>true,
            CURLOPT_COOKIEFILE=>'', CURLOPT_COOKIEJAR=>'/tmp/odoo_cookies.txt'
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        preg_match('/session_id=([^;]+)/', $res, $m);
        $session_id = $m[1] ?? '';
    }
    $payload = json_encode([
        'jsonrpc'=>'2.0','method'=>'call','id'=>1,
        'params'=>[
            'model'  => $model,
            'method' => $method,
            'args'   => $args,
            'kwargs' => array_merge(['context'=>['lang'=>'en_US']], $kwargs)
        ]
    ]);
    $ch = curl_init("$url/web/dataset/call_kw");
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>$payload,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json',"Cookie: session_id=$session_id"],
        CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_TIMEOUT=>30,
        CURLOPT_COOKIEFILE=>'/tmp/odoo_cookies.txt'
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode($res, true);
    return $decoded['result'] ?? [];
}

// ── الشهر المحدد ──────────────────────────────
$now       = new DateTime();
$sel_year  = intval($_GET['year']  ?? $now->format('Y'));
$sel_month = intval($_GET['month'] ?? $now->format('m'));
$sel_search= trim($_GET['search']  ?? '');
$sel_tag   = trim($_GET['tag']     ?? '');

$date_start  = sprintf('%04d-%02d-01', $sel_year, $sel_month);
$date_end    = date('Y-m-t', strtotime($date_start));
$month_label = (new DateTime($date_start))->format('F Y');

// ══════════════════════════════════════════════
//  جلب البيانات
// ══════════════════════════════════════════════
$lines    = [];
$all_tags = [];
$error    = '';

// 1) جلب الـ batches في الشهر (غير draft فقط)
$batches_raw = odoo_call($odoo_url, $db, $password,
    'marifah.fast.payroll', 'search_read',
    [[
        ['date_start', '>=', $date_start],
        ['date_start', '<=', $date_end],
        ['state', 'in', ['payslips_created', 'paid']]
    ]],
    ['fields' => ['id','name','date_start','date_end','state','company_id']]
);

if (empty($batches_raw)) {
    // لا يوجد batches — الصفحة ستظهر رسالة فارغة
} else {
    $batch_ids = array_column($batches_raw, 'id');
    $batch_map = [];
    foreach ($batches_raw as $b) $batch_map[$b['id']] = $b;

    // 2) جلب كل سطور الموظفين من هذه الـ batches
    $raw_lines = odoo_call($odoo_url, $db, $password,
        'marifah.fast.payroll.line', 'search_read',
        [[['batch_id', 'in', $batch_ids]]],
        ['fields' => [
            'id','batch_id','employee_id','company_id',
            'basic_salary','additional_allowance','bonus_amount','gross_salary',
            'absent_days','late_hours','advance_ded','fees_ded',
            'epf_emp','socso_emp','eis_emp','pcb','other_ded',
            'total_deductions','net_salary',
            'epf_empr','socso_empr','eis_empr'
        ]]
    );

    if (!empty($raw_lines)) {
        // 3) جلب تاجز الموظفين
        $emp_ids = array_unique(array_map(
            fn($l) => is_array($l['employee_id']) ? $l['employee_id'][0] : $l['employee_id'],
            $raw_lines
        ));

        $employees = odoo_call($odoo_url, $db, $password,
            'hr.employee', 'search_read',
            [[['id', 'in', array_values($emp_ids)]]],
            ['fields' => ['id','name','category_ids']]
        );
        $emp_map = [];
        foreach ($employees as $e) $emp_map[$e['id']] = $e;

        // 4) جلب أسماء التاجز
        $all_cat_ids = [];
        foreach ($employees as $e)
            foreach ($e['category_ids'] as $cid) $all_cat_ids[] = $cid;
        $all_cat_ids = array_unique($all_cat_ids);

        $categories = odoo_call($odoo_url, $db, $password,
            'hr.employee.category', 'search_read',
            [[['id', 'in', array_values($all_cat_ids)]]],
            ['fields' => ['id','name']]
        );
        $cat_map = [];
        foreach ($categories as $c) $cat_map[$c['id']] = $c['name'];

        // تجميع tags لكل موظف
        $emp_tags = [];
        foreach ($employees as $e) {
            $tags = [];
            foreach ($e['category_ids'] as $cid)
                if (isset($cat_map[$cid])) $tags[] = $cat_map[$cid];
            $emp_tags[$e['id']] = $tags;
            foreach ($tags as $t)
                if (!in_array($t, ['Payroll','Local','Redbytes','Regular']))
                    $all_tags[$t] = true;
        }
        ksort($all_tags);

        // 5) بناء قائمة الموظفين النهائية
        foreach ($raw_lines as $line) {
            $emp_id   = is_array($line['employee_id']) ? $line['employee_id'][0] : $line['employee_id'];
            $emp_name = is_array($line['employee_id']) ? $line['employee_id'][1] : '';
            $tags     = $emp_tags[$emp_id] ?? [];

            // فقط الذين عندهم تاج Payroll
            if (!in_array('Payroll', $tags)) continue;

            // فلتر البحث
            if ($sel_search && stripos($emp_name, $sel_search) === false) continue;

            // فلتر التاج
            if ($sel_tag && !in_array($sel_tag, $tags)) continue;

            // بيانات الـ batch
            $batch_id = is_array($line['batch_id']) ? $line['batch_id'][0] : $line['batch_id'];
            $batch    = $batch_map[$batch_id] ?? [];
            $company  = is_array($batch['company_id']) ? $batch['company_id'][1] : ($batch['company_id'] ?? '');
            $is_local = in_array('Local', $tags);

            // Group classification - ordered: Administration, Student Services, Teachers, Services
            $main_tag   = 'Others';
            $main_order = 99;
            $tag_priority = [
                'Administration'   => ['label'=>'Administration',   'order'=>1],
                'Student Services' => ['label'=>'Student Services', 'order'=>2],
                'Teachers'         => ['label'=>'Teachers',         'order'=>3],
                'Service'          => ['label'=>'Services',         'order'=>4],
            ];
            foreach ($tag_priority as $tk => $tv)
                if (in_array($tk, $tags)) {
                    $main_tag   = $tv['label'];
                    $main_order = $tv['order'];
                    break;
                }

            $line['_emp_name']    = $emp_name;
            $line['_tags']        = $tags;
            $line['_company']     = $company;
            $line['_is_local']    = $is_local;
            $line['_batch_state'] = $batch['state'] ?? '';
            $line['_main_tag']    = $main_tag;
            $line['_main_order']  = $main_order;
            $lines[] = $line;
        }

        // ترتيب: شركة ← تصنيف ← اسم
        usort($lines, fn($a,$b) =>
            strcmp($a['_company'], $b['_company']) ?:
            ($a['_main_order'] - $b['_main_order']) ?:
            strcmp($a['_emp_name'], $b['_emp_name'])
        );
    }
}

// ── دوال مساعدة ──────────────────────────────
function fmt($n) { return number_format((float)$n, 2); }

function totals($rows) {
    $t = array_fill_keys(['basic_salary','additional_allowance','bonus_amount','gross_salary',
        'absent_days','late_hours','advance_ded','fees_ded','epf_emp','socso_emp','eis_emp',
        'pcb','other_ded','total_deductions','net_salary','epf_empr','socso_empr','eis_empr'], 0);
    foreach ($rows as $r) foreach ($t as $k => &$v) $v += floatval($r[$k] ?? 0);
    return $t;
}

$months = ['01'=>'January','02'=>'February','03'=>'March','04'=>'April','05'=>'May',
           '06'=>'June','07'=>'July','08'=>'August','09'=>'September','10'=>'October',
           '11'=>'November','12'=>'December'];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payroll Report — Marifah</title>
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#f8f5f5;margin:0}

/* قفل */
#lock-screen{position:fixed;inset:0;z-index:9999;
  background:linear-gradient(135deg,#1a0608 0%,#3d0c14 50%,#1a0608 100%);
  display:flex;align-items:center;justify-content:center}
#lock-screen.hidden{display:none}
.lock-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);
  border-radius:24px;padding:48px 40px;width:100%;max-width:400px;
  text-align:center;backdrop-filter:blur(20px)}
.lock-input{width:100%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);
  border-radius:12px;padding:14px 18px;color:white;font-family:'Tajawal',sans-serif;
  font-size:20px;letter-spacing:6px;text-align:center;outline:none;
  transition:border-color .2s;margin:24px 0 20px}
.lock-input:focus{border-color:rgba(180,30,55,.6)}
.lock-input.error{border-color:#ef4444;animation:shake .4s ease}
@keyframes shake{0%,100%{transform:translateX(0)}20%{transform:translateX(-8px)}
  40%{transform:translateX(8px)}60%{transform:translateX(-5px)}80%{transform:translateX(5px)}}
.lock-btn{width:100%;background:linear-gradient(135deg,#8b1a2a,#b41e37);color:white;
  border:none;border-radius:12px;padding:14px;font-family:'Tajawal',sans-serif;
  font-size:16px;font-weight:700;cursor:pointer;transition:opacity .2s,transform .2s}
.lock-btn:hover{opacity:.9;transform:translateY(-1px)}
.lock-err{color:#f87171;font-size:13px;margin-top:12px;min-height:20px}

/* تطبيق */
#main-app{display:none}
#main-app.visible{display:block}
.navbar{background:rgba(26,6,8,.95);backdrop-filter:blur(16px);
  border-bottom:1px solid rgba(180,30,55,.2);position:sticky;top:0;z-index:100}
.hero{background:linear-gradient(160deg,#1a0608 0%,#3d0c14 40%,#6b1020 70%,#3d0c14 100%);
  padding:36px 24px 52px}
.filter-bar{background:white;border:1px solid rgba(139,26,42,.12);border-radius:16px;
  padding:20px 24px;box-shadow:0 4px 20px rgba(139,26,42,.06)}
.filter-input{background:#f8f5f5;border:1px solid rgba(139,26,42,.15);border-radius:10px;
  padding:10px 14px;font-family:'Tajawal',sans-serif;font-size:14px;outline:none;
  transition:border-color .2s;width:100%}
.filter-input:focus{border-color:rgba(139,26,42,.4)}
.btn-primary{background:linear-gradient(135deg,#8b1a2a,#b41e37);color:white;border:none;
  border-radius:10px;padding:10px 20px;font-family:'Tajawal',sans-serif;font-weight:700;
  cursor:pointer;font-size:14px;transition:opacity .2s,transform .2s}
.btn-primary:hover{opacity:.9;transform:translateY(-1px)}
.btn-secondary{background:white;color:#8b1a2a;border:1px solid rgba(139,26,42,.3);
  border-radius:10px;padding:10px 20px;font-family:'Tajawal',sans-serif;font-weight:700;
  cursor:pointer;font-size:14px;transition:all .2s;text-decoration:none;display:inline-block}
.btn-secondary:hover{background:rgba(139,26,42,.05)}

/* جدول */
.payroll-table{width:100%;border-collapse:collapse;font-size:12.5px}
.payroll-table th{background:linear-gradient(135deg,#1a0608,#3d0c14);color:white;
  padding:9px 7px;text-align:left;font-weight:700;white-space:nowrap}
.payroll-table td{padding:8px 7px;text-align:left;border-bottom:1px solid #f0e8e8;
  white-space:nowrap}
.payroll-table tr:hover td{background:rgba(139,26,42,.02)}
.payroll-table tr.grp-hdr td{background:rgba(139,26,42,.07);font-weight:800;
  color:#8b1a2a;border-top:2px solid rgba(139,26,42,.2);font-size:12px;padding:7px 10px;text-align:left;}
.payroll-table tr.sub-tot td{background:rgba(139,26,42,.04);font-weight:700;
  color:#3d0c14;border-top:1px solid rgba(139,26,42,.12)}
.payroll-table tr.grand-tot td{background:linear-gradient(135deg,#1a0608,#3d0c14);
  color:white;font-weight:800;border-top:2px solid #8b1a2a}
.col-name{text-align:left !important;min-width:150px}
.neg{color:#dc2626}
.pos{color:#16a34a;font-weight:700}
.net-val{color:#1d4ed8;font-weight:800}

.company-section{background:white;border:1px solid rgba(139,26,42,.12);border-radius:16px;
  overflow:hidden;box-shadow:0 4px 20px rgba(139,26,42,.06);margin-bottom:28px}
.company-header{background:linear-gradient(135deg,#3d0c14,#8b1a2a);color:white;
  padding:16px 24px;display:flex;align-items:center;justify-content:space-between}
.table-scroll{overflow-x:auto}

.contrib-box{background:white;border-radius:10px;padding:12px 16px;text-align:center;
  border:1px solid #e5e7eb}
.contrib-box.er{border-color:#bbf7d0}

.sign-line{height:1px;background:rgba(139,26,42,.25);margin-top:12px;margin-bottom:6px}

@media print{
  @page{size:A4 landscape;margin:8mm 6mm}
  #lock-screen,.navbar,.hero,.filter-bar,.no-print{display:none !important}
  #main-app{display:block !important}
  body{background:white !important;font-size:8px !important}
  .print-header{display:block !important}
  /* إزالة page-break-inside:avoid للسماح ببدء الطباعة من الصفحة الأولى وتقسيم الجدول بشكل طبيعي */
  .company-section{box-shadow:none !important;border:1px solid #bbb !important;
    border-radius:0 !important;margin-bottom:8mm !important}
  /* منع انقسام صف الموظف الواحد بين صفحتين */
  .payroll-table tr { page-break-inside: avoid !important; }
  .company-header{background:#3d0c14 !important;
    -webkit-print-color-adjust:exact;print-color-adjust:exact;
    padding:5px 10px !important}
  .company-header h2{font-size:10px !important;margin:0}
  .company-header p{font-size:7.5px !important;margin:0}
  .company-header .text-2xl{font-size:11px !important}
  .table-scroll{overflow:visible !important}
  .payroll-table{font-size:7px !important;width:100% !important}
  .payroll-table th{background:#1a0608 !important;
    -webkit-print-color-adjust:exact;print-color-adjust:exact;
    padding:3px 3px !important;font-size:6.5px !important;text-align:left !important}
  .payroll-table td{padding:2.5px 3px !important;border-bottom:1px solid #eee;text-align:left !important}
  .payroll-table tr.grp-hdr td{background:#f0e8e8 !important;
    -webkit-print-color-adjust:exact;print-color-adjust:exact;
    padding:3px 6px !important;font-size:7px !important;font-weight:700;color:#6b1020}
  .payroll-table tr.sub-tot td{background:#fdf5f5 !important;
    -webkit-print-color-adjust:exact;print-color-adjust:exact;font-weight:600}
  .payroll-table tr.grand-tot td{background:#1a0608 !important;
    -webkit-print-color-adjust:exact;print-color-adjust:exact;font-size:7.5px !important}
  .col-name{min-width:80px !important;text-align:left !important}
  .p-4,.p-6{padding:4px 8px !important}
  .contrib-box{padding:3px 6px !important;min-width:60px !important;display:inline-block;margin:2px}
  .contrib-box p{font-size:6.5px !important;margin:0}
  .sign-line{margin-top:40px !important}
  .text-xs,.text-sm{font-size:7px !important}
  .bg-gray-50{background:#fafafa !important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .max-w-7xl{max-width:100% !important;padding:0 !important;margin:0 !important}
}
.print-header{display:none}
</style>
</head>
<body>

<!-- قفل -->
<div id="lock-screen">
  <div class="lock-card">
    <img src="https://cdn.prod.website-files.com/642e38eb59ef94ba3c2bf35b/68696ec0e6fe2cab7b377c39_724c268af6d0a34b14c1961f01f18585_Marifah_CMYK-p-500.png"
         alt="Marifah" class="h-20 object-contain mx-auto mb-2 brightness-0 invert opacity-90">
    <h2 class="text-white text-xl font-extrabold mt-4">Payroll Report</h2>
    <p class="text-slate-500 text-sm mt-1">Enter password to continue</p>
    <input type="password" id="pwInput" class="lock-input" placeholder="••••"
           maxlength="8" onkeydown="if(event.key==='Enter')checkPw()">
    <button class="lock-btn" onclick="checkPw()">
      <i class="fas fa-unlock-alt mr-2"></i> Unlock
    </button>
    <p class="lock-err" id="pwErr"></p>
  </div>
</div>

<!-- التطبيق -->
<div id="main-app">

<!-- Navbar -->
<nav class="navbar px-6 py-3 no-print">
  <div class="max-w-7xl mx-auto flex items-center justify-between">
    <div class="flex items-center gap-3">
      <img src="https://cdn.prod.website-files.com/642e38eb59ef94ba3c2bf35b/68696ec0e6fe2cab7b377c39_724c268af6d0a34b14c1961f01f18585_Marifah_CMYK-p-500.png"
           alt="Marifah" class="h-8 object-contain brightness-0 invert opacity-80">
      <span class="text-white/60 text-sm">Monthly Payroll Report</span>
    </div>
    <div class="flex items-center gap-4">
      <span class="text-white/40 text-xs" id="navDate"></span>
      <a href="index.php" class="text-white/50 hover:text-white/80 text-xs transition-colors">
        <i class="fas fa-home mr-1"></i>Home
      </a>
      <button onclick="window.print()" class="text-white/40 hover:text-white/80 text-xs transition-colors">
        <i class="fas fa-print mr-1"></i>Print
      </button>
      <button onclick="lockApp()" class="text-white/40 hover:text-red-400 text-xs transition-colors">
        <i class="fas fa-lock mr-1"></i>Lock
      </button>
    </div>
  </div>
</nav>

<!-- Hero + Filter -->
<div class="hero no-print">
  <div class="max-w-7xl mx-auto">
    <h1 class="text-3xl font-extrabold text-white mb-1">
      <i class="fas fa-money-bill-wave mr-3 text-rose-400"></i>Monthly Payroll Report
    </h1>
    <p class="text-white/40 text-sm mb-6">Marifah International School — Live data from Odoo</p>
    <div class="filter-bar">
      <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1">Month</label>
          <select name="month" class="filter-input" style="width:130px">
            <?php foreach($months as $m=>$ml): ?>
            <option value="<?=$m?>" <?=intval($m)==$sel_month?'selected':''?>><?=$ml?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1">Year</label>
          <select name="year" class="filter-input" style="width:100px">
            <?php for($y=2024;$y<=2027;$y++): ?>
            <option value="<?=$y?>" <?=$y==$sel_year?'selected':''?>><?=$y?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-500 mb-1">Group</label>
          <select name="tag" class="filter-input" style="width:170px">
            <option value="">— All —</option>
            <?php foreach(array_keys($all_tags) as $t): ?>
            <option value="<?=htmlspecialchars($t)?>" <?=$sel_tag===$t?'selected':''?>>
              <?=htmlspecialchars($t)?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="flex:1;min-width:180px">
          <label class="block text-xs font-bold text-gray-500 mb-1">Search Employee</label>
          <input type="text" name="search" value="<?=htmlspecialchars($sel_search)?>"
                 placeholder="Type employee name..." class="filter-input">
        </div>
        <div class="flex gap-2">
          <button type="submit" class="btn-primary">
            <i class="fas fa-filter mr-1"></i>Apply
          </button>
          <a href="payroll_report.php" class="btn-secondary">
            <i class="fas fa-undo mr-1"></i>Reset
          </a>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- رأس الطباعة -->
<div class="print-header text-center py-4 border-b border-gray-200 mb-4 px-6">
  <img src="https://cdn.prod.website-files.com/642e38eb59ef94ba3c2bf35b/68696ec0e6fe2cab7b377c39_724c268af6d0a34b14c1961f01f18585_Marifah_CMYK-p-500.png"
       alt="Marifah" class="h-14 object-contain mx-auto mb-2">
  <h1 class="text-xl font-extrabold">Marifah International School</h1>
  <h2 class="text-base font-bold text-gray-600">Monthly Payroll Report — <?=$month_label?></h2>
  <p class="text-xs text-gray-400 mt-1">Printed: <?=date('d M Y, H:i')?></p>
</div>

<!-- المحتوى -->
<div class="max-w-7xl mx-auto px-4 py-8" style="margin-top:-16px">

<?php if(empty($lines)): ?>
<div class="bg-white rounded-xl p-12 text-center border border-gray-100">
  <i class="fas fa-inbox text-4xl text-gray-300 mb-4 block"></i>
  <p class="text-gray-400 font-bold">No approved payroll data for this period</p>
  <p class="text-gray-300 text-sm mt-1"><?=$month_label?></p>
</div>
<?php else: ?>

<?php
// تجميع حسب الشركة
$by_company = [];
foreach ($lines as $l) $by_company[$l['_company']][] = $l;
ksort($by_company);
$grand_totals = totals($lines);
?>

<?php foreach($by_company as $company => $co_lines):
    $by_tag = [];
    foreach($co_lines as $l) $by_tag[$l['_main_tag']][] = $l;
    // Sort groups by defined order
    uksort($by_tag, function($a,$b) use ($lines) {
        $order_map = ['Administration'=>1,'Student Services'=>2,'Teachers'=>3,'Services'=>4];
        return ($order_map[$a]??99) - ($order_map[$b]??99);
    });
    $co_totals = totals($co_lines);
    $has_contrib = array_sum(array_column($co_lines,'epf_emp')) > 0;
    
    // عدد الأعمدة بعد إزالة عمود Group أصبح 17 إذا كان هناك اشتراكات و 11 إذا لم يكن
    $cols = $has_contrib ? 17 : 11;
?>

<div class="company-section">
  <div class="company-header">
    <div>
      <h2 class="text-lg font-extrabold"><?=htmlspecialchars($company)?></h2>
      <p class="text-white/50 text-sm mt-0.5"><?=$month_label?> — <?=count($co_lines)?> employees</p>
    </div>
    <div class="text-left">
      <p class="text-white/50 text-xs mb-0.5">Total Net Salary</p>
      <p class="text-2xl font-extrabold text-green-300"><?=fmt($co_totals['net_salary'])?> MYR</p>
    </div>
  </div>

  <div class="table-scroll">
  <table class="payroll-table">
    <thead>
      <tr>
        <th rowspan="2" style="min-width:30px">#</th>
        <th class="col-name" rowspan="2">Employee</th>
        <th colspan="3" style="background:#1e3a5f">Earnings</th>
        <th rowspan="2" style="background:#1a365d">Gross</th>
        <th colspan="5" style="background:#5c1a1a">Deductions</th>
        <?php if($has_contrib): ?>
        <th colspan="3" style="background:#14532d">EE Contributions</th>
        <?php endif; ?>
        <th rowspan="2" style="background:#4c1d95">Total Ded.</th>
        <th rowspan="2" style="background:#1e3a8a;min-width:90px">Net Salary</th>
        <?php if($has_contrib): ?>
        <th colspan="3" style="background:#1a3a2e">ER Contributions</th>
        <?php endif; ?>
      </tr>
      <tr>
        <th style="background:#1e3a5f">Basic</th>
        <th style="background:#1e3a5f">Allowance</th>
        <th style="background:#1e3a5f">Bonus</th>
        <th style="background:#5c1a1a">Absent</th>
        <th style="background:#5c1a1a">Late</th>
        <th style="background:#5c1a1a">Advance</th>
        <th style="background:#5c1a1a">Fees</th>
        <th style="background:#5c1a1a">Other</th>
        <?php if($has_contrib): ?>
        <th style="background:#14532d">EPF</th>
        <th style="background:#14532d">SOCSO</th>
        <th style="background:#14532d">EIS</th>
        <th style="background:#1a3a2e">EPF</th>
        <th style="background:#1a3a2e">SOCSO</th>
        <th style="background:#1a3a2e">EIS</th>
        <?php endif; ?>
      </tr>
    </thead>
    <tbody>
    <?php $rn=1; foreach($by_tag as $tag_label => $tag_lines):
        $tt = totals($tag_lines); ?>
      <tr class="grp-hdr">
        <td colspan="<?=$cols?>">
          <i class="fas fa-users mr-2"></i><?=$tag_label?> (<?=count($tag_lines)?> employees)
        </td>
      </tr>
      <?php foreach($tag_lines as $l): ?>
      <tr>
        <td class="text-gray-400 text-xs"><?=$rn++?></td>
        <td class="col-name font-bold text-gray-800"><?=htmlspecialchars($l['_emp_name'])?></td>
        <td><?=fmt($l['basic_salary'])?></td>
        <td><?=$l['additional_allowance']>0?'<span class="pos">+'.fmt($l['additional_allowance']).'</span>':'—'?></td>
        <td><?=$l['bonus_amount']>0?'<span class="pos">+'.fmt($l['bonus_amount']).'</span>':'—'?></td>
        <td class="font-bold"><?=fmt($l['gross_salary'])?></td>
        <td><?=$l['absent_days']>0?'<span class="neg">'.fmt($l['absent_days']).'</span>':'—'?></td>
        <td><?=$l['late_hours']>0?'<span class="neg">'.fmt($l['late_hours']).'</span>':'—'?></td>
        <td><?=$l['advance_ded']>0?'<span class="neg">'.fmt($l['advance_ded']).'</span>':'—'?></td>
        <td><?=$l['fees_ded']>0?'<span class="neg">'.fmt($l['fees_ded']).'</span>':'—'?></td>
        <td><?=$l['other_ded']>0?'<span class="neg">'.fmt($l['other_ded']).'</span>':'—'?></td>
        <?php if($has_contrib): ?>
        <td><?=$l['epf_emp']>0?'<span class="neg">'.fmt($l['epf_emp']).'</span>':'—'?></td>
        <td><?=$l['socso_emp']>0?'<span class="neg">'.fmt($l['socso_emp']).'</span>':'—'?></td>
        <td><?=$l['eis_emp']>0?'<span class="neg">'.fmt($l['eis_emp']).'</span>':'—'?></td>
        <?php endif; ?>
        <td class="neg font-bold"><?=fmt($l['total_deductions'])?></td>
        <td class="net-val"><?=fmt($l['net_salary'])?></td>
        <?php if($has_contrib): ?>
        <td class="text-gray-500 text-xs"><?=$l['epf_empr']>0?fmt($l['epf_empr']):'—'?></td>
        <td class="text-gray-500 text-xs"><?=$l['socso_empr']>0?fmt($l['socso_empr']):'—'?></td>
        <td class="text-gray-500 text-xs"><?=$l['eis_empr']>0?fmt($l['eis_empr']):'—'?></td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
      <!-- إجمالي المجموعة -->
      <tr class="sub-tot">
        <td colspan="2" style="text-align:left;padding-left:12px">Subtotal — <?=$tag_label?></td>
        <td><?=fmt($tt['basic_salary'])?></td>
        <td><?=fmt($tt['additional_allowance'])?></td>
        <td><?=fmt($tt['bonus_amount'])?></td>
        <td><?=fmt($tt['gross_salary'])?></td>
        <td><?=fmt($tt['absent_days'])?></td>
        <td><?=fmt($tt['late_hours'])?></td>
        <td><?=fmt($tt['advance_ded'])?></td>
        <td><?=fmt($tt['fees_ded'])?></td>
        <td><?=fmt($tt['other_ded'])?></td>
        <?php if($has_contrib): ?>
        <td><?=fmt($tt['epf_emp'])?></td>
        <td><?=fmt($tt['socso_emp'])?></td>
        <td><?=fmt($tt['eis_emp'])?></td>
        <?php endif; ?>
        <td><?=fmt($tt['total_deductions'])?></td>
        <td class="net-val"><?=fmt($tt['net_salary'])?></td>
        <?php if($has_contrib): ?>
        <td><?=fmt($tt['epf_empr'])?></td>
        <td><?=fmt($tt['socso_empr'])?></td>
        <td><?=fmt($tt['eis_empr'])?></td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <!-- الإجمالي الكلي للشركة -->
    <tr class="grand-tot">
      <td colspan="2" style="text-align:left;padding-left:12px">Grand Total — <?=htmlspecialchars($company)?></td>
      <td><?=fmt($co_totals['basic_salary'])?></td>
      <td><?=fmt($co_totals['additional_allowance'])?></td>
      <td><?=fmt($co_totals['bonus_amount'])?></td>
      <td><?=fmt($co_totals['gross_salary'])?></td>
      <td><?=fmt($co_totals['absent_days'])?></td>
      <td><?=fmt($co_totals['late_hours'])?></td>
      <td><?=fmt($co_totals['advance_ded'])?></td>
      <td><?=fmt($co_totals['fees_ded'])?></td>
      <td><?=fmt($co_totals['other_ded'])?></td>
      <?php if($has_contrib): ?>
      <td><?=fmt($co_totals['epf_emp'])?></td>
      <td><?=fmt($co_totals['socso_emp'])?></td>
      <td><?=fmt($co_totals['eis_emp'])?></td>
      <?php endif; ?>
      <td><?=fmt($co_totals['total_deductions'])?></td>
      <td style="color:#86efac;font-size:14px;font-weight:900"><?=fmt($co_totals['net_salary'])?></td>
      <?php if($has_contrib): ?>
      <td><?=fmt($co_totals['epf_empr'])?></td>
      <td><?=fmt($co_totals['socso_empr'])?></td>
      <td><?=fmt($co_totals['eis_empr'])?></td>
      <?php endif; ?>
    </tr>
    </tbody>
  </table>
  </div>

  <?php if($has_contrib): ?>
  <!-- Contributions Summary -->
  <div class="p-4 bg-gray-50 border-t border-gray-100">
    <p class="text-xs font-bold text-gray-500 mb-3">Statutory Contributions Summary</p>
    <div class="flex flex-wrap gap-3">
      <div class="contrib-box"><p class="text-xs text-gray-400">EPF (Employee)</p><p class="font-bold text-red-600 mt-0.5"><?=fmt($co_totals['epf_emp'])?></p></div>
      <div class="contrib-box"><p class="text-xs text-gray-400">SOCSO (Employee)</p><p class="font-bold text-red-600 mt-0.5"><?=fmt($co_totals['socso_emp'])?></p></div>
      <div class="contrib-box"><p class="text-xs text-gray-400">EIS (Employee)</p><p class="font-bold text-red-600 mt-0.5"><?=fmt($co_totals['eis_emp'])?></p></div>
      <div class="w-px bg-gray-200 self-stretch mx-1"></div>
      <div class="contrib-box er"><p class="text-xs text-gray-400">EPF (Employer)</p><p class="font-bold text-green-700 mt-0.5"><?=fmt($co_totals['epf_empr'])?></p></div>
      <div class="contrib-box er"><p class="text-xs text-gray-400">SOCSO (Employer)</p><p class="font-bold text-green-700 mt-0.5"><?=fmt($co_totals['socso_empr'])?></p></div>
      <div class="contrib-box er"><p class="text-xs text-gray-400">EIS (Employer)</p><p class="font-bold text-green-700 mt-0.5"><?=fmt($co_totals['eis_empr'])?></p></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Signature Lines -->
  <div class="p-6 border-t border-gray-100 mt-2">
    <div class="grid grid-cols-3 gap-10">
      <?php foreach([
          ['title'=>'Accounting Manager', 'name'=>'AHMED ALMALJAMIE'],
          ['title'=>'Executive Chairman', 'name'=>'ABDULNOOR SALEH'],
          ['title'=>'General Manager', 'name'=>'ABDULQAWI AL-QADASI']
      ] as $sig): ?>
      <div class="text-center pt-2">
        <p class="text-xs text-gray-500 font-bold mb-14"><?=$sig['title']?></p>
        <div class="sign-line"></div>
        <p class="text-[11px] text-gray-800 font-extrabold uppercase"><?=$sig['name']?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php endforeach; ?>

<?php if(count($by_company)>1): ?>
<!-- Overall Summary -->
<div class="bg-white border border-gray-200 rounded-xl p-6 mt-2 no-print">
  <h3 class="font-extrabold text-gray-800 mb-4 text-base">
    <i class="fas fa-sigma mr-2 text-rose-700"></i>Overall Summary — All Companies
  </h3>
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-gray-50 rounded-xl p-4 text-center">
      <p class="text-xs text-gray-400 font-bold">Total Basic Salary</p>
      <p class="text-xl font-extrabold text-gray-800 mt-1"><?=fmt($grand_totals['basic_salary'])?></p>
    </div>
    <div class="bg-red-50 rounded-xl p-4 text-center">
      <p class="text-xs text-gray-400 font-bold">Total Deductions</p>
      <p class="text-xl font-extrabold text-red-700 mt-1"><?=fmt($grand_totals['total_deductions'])?></p>
    </div>
    <div class="bg-blue-50 rounded-xl p-4 text-center">
      <p class="text-xs text-gray-400 font-bold">Total Net Salary</p>
      <p class="text-xl font-extrabold text-blue-700 mt-1"><?=fmt($grand_totals['net_salary'])?></p>
    </div>
    <div class="bg-green-50 rounded-xl p-4 text-center">
      <p class="text-xs text-gray-400 font-bold">Total Employees</p>
      <p class="text-xl font-extrabold text-green-700 mt-1"><?=count($lines)?></p>
    </div>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- Footer -->
<div class="mt-8 pt-6 flex items-center justify-between no-print"
     style="border-top:1px solid rgba(139,26,42,.12)">
  <div class="flex items-center gap-3">
    <img src="https://cdn.prod.website-files.com/642e38eb59ef94ba3c2bf35b/68696ec0e6fe2cab7b377c39_724c268af6d0a34b14c1961f01f18585_Marifah_CMYK-p-500.png"
         alt="Marifah" class="h-8 object-contain opacity-40">
    <span class="text-xs" style="color:rgba(139,26,42,.5)">Marifah International School</span>
  </div>
  <p class="text-xs text-gray-300"><i class="fas fa-shield-halved mr-1"></i>Live data from Odoo</p>
</div>
</div>
</div>

<script>
const PASS='2526', SK='marifah_payroll_auth';
function checkSession(){if(sessionStorage.getItem(SK)===PASS)showApp()}
function checkPw(){
  const v=document.getElementById('pwInput').value;
  if(v===PASS){sessionStorage.setItem(SK,PASS);showApp()}
  else{
    const inp=document.getElementById('pwInput');
    inp.classList.add('error');
    document.getElementById('pwErr').textContent='Incorrect password';
    inp.value='';
    setTimeout(()=>inp.classList.remove('error'),500);
  }
}
function showApp(){
  document.getElementById('lock-screen').classList.add('hidden');
  document.getElementById('main-app').classList.add('visible');
}
function lockApp(){
  sessionStorage.removeItem(SK);
  document.getElementById('lock-screen').classList.remove('hidden');
  document.getElementById('main-app').classList.remove('visible');
  document.getElementById('pwInput').value='';
  document.getElementById('pwErr').textContent='';
}
const d=new Date();
document.getElementById('navDate').textContent=
  d.toLocaleDateString('en-MY',{weekday:'short',year:'numeric',month:'short',day:'numeric'});
checkSession();
</script>
</body>
</html>