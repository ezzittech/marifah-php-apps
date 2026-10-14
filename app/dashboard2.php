<?php
// ══════════════════════════════════════════════
//  Management Dashboard — Marifah International School
//  يجلب البيانات مباشرة من أودو عبر JSON-RPC
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

// ══════════════════════════════════
//  1. أرصدة البنوك والصناديق
// ══════════════════════════════════
$bank_accounts = [];
$cash_accounts = [];

if ($sid) {
    // جلب حسابات البنوك والصناديق
    $journals = odoo($odoo_url, $sid, 'account.journal', 'search_read',
        [[['type', 'in', ['bank', 'cash']]]],
        ['fields' => ['name', 'type', 'currency_id', 'default_account_id']]);

    if (is_array($journals)) {
        foreach ($journals as $j) {
            $acc_id = is_array($j['default_account_id']) ? $j['default_account_id'][0] : null;
            $balance = 0;
            if ($acc_id) {
                $lines = odoo($odoo_url, $sid, 'account.move.line', 'search_read',
                    [[['account_id','=',$acc_id],['parent_state','=','posted']]],
                    ['fields'=>['debit','credit']]);
                if (is_array($lines)) {
                    foreach ($lines as $l) {
                        $balance += ($l['debit'] - $l['credit']);
                    }
                }
            }
            $entry = [
                'name'     => $j['name'],
                'type'     => $j['type'],
                'balance'  => $balance,
                'currency' => is_array($j['currency_id']) ? $j['currency_id'][1] : 'MYR',
            ];
            if ($j['type'] === 'bank') $bank_accounts[] = $entry;
            else                       $cash_accounts[] = $entry;
        }
    }
}

// ══════════════════════════════════
//  2. الحركات الأخيرة (10 حركات)
// ══════════════════════════════════
$recent_moves = [];
if ($sid) {
    $moves = odoo($odoo_url, $sid, 'account.move', 'search_read',
        [[['state','=','posted'],['move_type','in',['out_invoice','in_invoice','entry']]]],
        ['fields'=>['name','date','partner_id','move_type','amount_total','currency_id','journal_id'],
         'order'=>'date desc, id desc', 'limit'=>10]);
    if (is_array($moves)) $recent_moves = $moves;
}

// ══════════════════════════════════
//  3. إحصائيات الطلاب والفواتير
// ══════════════════════════════════
$students_total   = 0;
$students_onsite  = 0;
$students_online  = 0;
$students_both    = 0;

$inv_total_count  = 0;
$inv_onsite_count = 0;
$inv_online_count = 0;

$inv_total_amount   = 0;
$inv_paid_amount    = 0;
$inv_residual_amount= 0;

$inv_onsite_amount  = 0;
$inv_online_amount  = 0;
$inv_onsite_residual= 0;
$inv_online_residual= 0;

// عملات غير MYR نحوّلها
$fx_rates = ['MYR'=>1.0];

if ($sid) {
    // أسعار الصرف
    $cur_data = odoo($odoo_url, $sid, 'res.currency', 'search_read',
        [[['active','=',true]]],
        ['fields'=>['name','rate']]);
    if (is_array($cur_data)) {
        foreach ($cur_data as $c) {
            if ((float)$c['rate'] > 0) $fx_rates[$c['name']] = (float)$c['rate'];
        }
    }

    // جلب الطلاب (شركاء لديهم x_student_type)
    $partners = odoo($odoo_url, $sid, 'res.partner', 'search_read',
        [[['x_student_type','!=',false]]],
        ['fields'=>['id','x_student_type']]);
    if (is_array($partners)) {
        $students_total = count($partners);
        $pid_type_map = [];
        foreach ($partners as $p) {
            $t = $p['x_student_type'];
            $pid_type_map[$p['id']] = $t;
            if ($t === 'physical') $students_onsite++;
            elseif ($t === 'online') $students_online++;
            elseif ($t === 'both')  $students_both++;
        }

        // جلب الفواتير
        $invoices = odoo($odoo_url, $sid, 'account.move', 'search_read',
            [[['move_type','=','out_invoice'],['state','=','posted']]],
            ['fields'=>['partner_id','currency_id','amount_total','amount_residual']]);

        if (is_array($invoices)) {
            $inv_total_count = count($invoices);
            foreach ($invoices as $inv) {
                if (empty($inv['partner_id'])) continue;
                $pid = is_array($inv['partner_id']) ? $inv['partner_id'][0] : $inv['partner_id'];
                $cur = is_array($inv['currency_id']) ? $inv['currency_id'][1] : 'MYR';
                $rate = $fx_rates[$cur] ?? 1.0;
                $total_myr    = ($rate > 0) ? ($inv['amount_total']    / $rate) : 0;
                $residual_myr = ($rate > 0) ? ($inv['amount_residual'] / $rate) : 0;
                $paid_myr     = $total_myr - $residual_myr;

                $inv_total_amount    += $total_myr;
                $inv_paid_amount     += $paid_myr;
                $inv_residual_amount += $residual_myr;

                $ptype = $pid_type_map[$pid] ?? '';
                if ($ptype === 'physical') {
                    $inv_onsite_count++;
                    $inv_onsite_amount   += $total_myr;
                    $inv_onsite_residual += $residual_myr;
                } elseif ($ptype === 'online' || $ptype === 'both') {
                    $inv_online_count++;
                    $inv_online_amount   += $total_myr;
                    $inv_online_residual += $residual_myr;
                }
            }
        }
    }
}

// ══════════════════════════════════
//  4. المصروفات الشهرية (حسابات المصروف)
// ══════════════════════════════════
$expense_data = []; // [month => [account => amount]]
$expense_accounts = []; // account_name => total

if ($sid) {
    // جلب حركات حسابات المصروفات لآخر 12 شهر
    $date_from = date('Y-m-d', strtotime('-12 months'));
    $exp_lines = odoo($odoo_url, $sid, 'account.move.line', 'search_read',
        [[['parent_state','=','posted'],
          ['account_id.account_type','in',['expense','expense_depreciation','expense_direct_cost']],
          ['date','>=',$date_from]]],
        ['fields'=>['date','account_id','debit','credit','name'],
         'order'=>'date asc', 'limit'=>5000]);

    if (is_array($exp_lines)) {
        foreach ($exp_lines as $l) {
            $month = substr($l['date'], 0, 7); // YYYY-MM
            $acc   = is_array($l['account_id']) ? $l['account_id'][1] : 'غير محدد';
            $amt   = ($l['debit'] - $l['credit']);
            if ($amt <= 0) continue;

            if (!isset($expense_data[$month])) $expense_data[$month] = [];
            $expense_data[$month][$acc] = ($expense_data[$month][$acc] ?? 0) + $amt;
            $expense_accounts[$acc] = ($expense_accounts[$acc] ?? 0) + $amt;
        }
        ksort($expense_data);
        arsort($expense_accounts);
    }
}

// أشهر للعرض
$months_labels = [];
$all_months = array_keys($expense_data);
foreach ($all_months as $m) {
    $ts = strtotime($m . '-01');
    $months_labels[$m] = date('M Y', $ts);
}

// أعلى 8 حسابات مصروفات
$top_accounts = array_slice($expense_accounts, 0, 8, true);
$top_acc_names = array_keys($top_accounts);

// بيانات الرسم البياني بالألوان المستوحاة من الشعار (تدرجات العنابي والأحمر)
$chart_datasets = [];
$palette = [
    '#581c22','#7f1d1d','#991b1b','#b91c1c','#dc2626',
    '#ef4444','#f87171','#fca5a5','#fb923c','#f59e0b'
];
foreach ($top_acc_names as $i => $acc) {
    $vals = [];
    foreach ($all_months as $m) {
        $vals[] = round($expense_data[$m][$acc] ?? 0, 2);
    }
    $chart_datasets[] = [
        'label'           => strlen($acc) > 35 ? substr($acc, 0, 35).'…' : $acc,
        'data'            => $vals,
        'backgroundColor' => $palette[$i % count($palette)] . '33',
        'borderColor'     => $palette[$i % count($palette)],
        'borderWidth'     => 2,
        'tension'         => 0.4,
        'fill'            => false,
        'pointRadius'     => 4,
    ];
}

// مصروفات هذا الشهر فقط (للبطاقة)
$this_month = date('Y-m');
$expenses_this_month = 0;
if (isset($expense_data[$this_month])) {
    $expenses_this_month = array_sum($expense_data[$this_month]);
}

// ══════════════════════════════════
//  مساعدات PHP
// ══════════════════════════════════
$collection_rate = $inv_total_amount > 0
    ? round($inv_paid_amount / $inv_total_amount * 100, 1) : 0;

function fmtN($v, $dec=2) {
    return number_format((float)$v, $dec);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>لوحة تحكم الإدارة — Marifah</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
* { box-sizing: border-box; }
body { font-family: 'Tajawal', sans-serif; background: #f8fafc; }

/* ── Hero gradient ── */
.hero {
    background: linear-gradient(135deg, #ffffff 0%, #fff1f2 100%);
    border-bottom: 1px solid #ffe4e6;
    position: relative; overflow: hidden;
}
.hero::before {
    content:''; position:absolute; inset:0;
    background: radial-gradient(ellipse at 15% 50%, rgba(153,27,27,.04) 0%, transparent 55%),
                radial-gradient(ellipse at 85% 20%, rgba(88,28,34,.04) 0%, transparent 45%);
}
.orb {
    position:absolute; border-radius:50%;
    background: rgba(153,27,27,.05);
    animation: orb-float 10s ease-in-out infinite;
}
@keyframes orb-float {
    0%,100%{transform:translateY(0) scale(1)}
    50%{transform:translateY(-20px) scale(1.05)}
}

/* ── KPI Cards ── */
.kpi {
    background: #ffffff;
    border: 1px solid #f1f5f9;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
    border-radius: 16px;
    padding: 20px;
    transition: transform .2s, box-shadow .2s, border-color .2s;
    position: relative; overflow: hidden;
}
.kpi:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);
    border-color: #fee2e2;
}
.kpi::after {
    content:''; position:absolute; top:0; left:0;
    width:3px; height:100%; border-radius:0 2px 2px 0;
}
/* تم إعادة توظيف الألوان لتناسب درجات العنابي والأحمر والوردي */
.kpi-maroon::after { background: #7f1d1d; }
.kpi-red::after    { background: #b91c1c; }
.kpi-rose::after   { background: #e11d48; }
.kpi-amber::after  { background: #f59e0b; }
.kpi-green::after  { background: #10b981; }
.kpi-slate::after  { background: #94a3b8; }

/* ── Section cards ── */
.section-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
}
.section-title {
    font-size: 14px; font-weight: 800;
    color: #7f1d1d;
    letter-spacing: 1px; text-transform: uppercase;
    margin-bottom: 20px;
    display: flex; align-items: center; gap: 8px;
}

/* ── Progress bars ── */
.prog-track { background: #f1f5f9; border-radius: 999px; height: 8px; }
.prog-fill  { height: 8px; border-radius: 999px; transition: width 1s ease; }

/* ── Table ── */
.dash-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.dash-table th {
    color: #64748b; font-weight: 700; font-size: 11px;
    letter-spacing: 1px; text-transform: uppercase;
    padding: 10px 12px; border-bottom: 1px solid #e2e8f0;
    text-align: right;
}
.dash-table td {
    padding: 12px 12px; border-bottom: 1px solid #f1f5f9;
    color: #334155;
}
.dash-table tr:hover td { background: #f8fafc; }

/* ── Chart container ── */
.chart-wrap { position:relative; height: 340px; }

/* ── Scrollbar ── */
::-webkit-scrollbar { width:6px; height:6px; }
::-webkit-scrollbar-thumb { background:rgba(127,29,29,.2); border-radius:4px; }
::-webkit-scrollbar-track { background:#f1f5f9; }

/* ── Badges ── */
.badge {
    display:inline-block; padding:2px 10px;
    border-radius:999px; font-size:11px; font-weight:700;
}
.badge-inv { background:#fee2e2; color:#991b1b; }
.badge-out { background:#ffe4e6; color:#be123c; }
.badge-ent { background:#f1f5f9; color:#475569; }

/* ── Top expenses table ── */
.exp-row { transition: background .15s; }
.exp-row:hover { background: #f8fafc; }
</style>
</head>
<body class="min-h-screen text-slate-800 antialiased">

<!-- ══════════════════════════════════
     HERO HEADER
══════════════════════════════════ -->
<div class="hero px-6 pb-8 pt-6">
  <!-- Orbs -->
  <div class="orb" style="width:400px;height:400px;top:-150px;right:-100px;"></div>
  <div class="orb" style="width:250px;height:250px;bottom:-80px;left:5%;animation-delay:3s;"></div>

  <div class="relative z-10 max-w-screen-xl mx-auto">

    <!-- Top bar -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
      <div class="flex items-center gap-4">
        <!-- تم إزالة فلتر الأبيض عن الشعار ليظهر بألوانه الأصلية -->
        <img src="https://cdn.prod.website-files.com/642e38eb59ef94ba3c2bf35b/68696ec0e6fe2cab7b377c39_724c268af6d0a34b14c1961f01f18585_Marifah_CMYK-p-500.png"
             alt="Marifah" class="h-12 object-contain">
        <div>
          <h1 class="text-2xl font-extrabold text-[#581c22] leading-tight">لوحة تحكم الإدارة</h1>
          <p class="text-slate-500 text-xs mt-0.5">Management Dashboard — <?=date('l، d F Y')?></p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <?php if($error):?>
          <span class="bg-red-50 border border-red-200 text-red-600 px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5">
            <i class="fas fa-circle-xmark"></i> <?=htmlspecialchars($error)?>
          </span>
        <?php else:?>
          <span class="bg-emerald-50 border border-emerald-200 text-emerald-600 px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5">
            <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
            متصل بأودو
          </span>
        <?php endif;?>
        <a href="index.html"
           class="bg-[#7f1d1d] hover:bg-[#581c22] text-white px-4 py-2 rounded-xl text-sm font-bold transition-all flex items-center gap-2 shadow-sm">
          <i class="fas fa-home"></i> الرئيسية
        </a>
      </div>
    </div>

    <!-- ══ KPI Row 1: الطلاب ══ -->
    <p class="section-title mb-4"><i class="fas fa-graduation-cap text-[#991b1b]"></i> إحصائيات الطلاب</p>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">

      <div class="kpi kpi-maroon">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 rounded-xl bg-red-900/10 flex items-center justify-center">
            <i class="fas fa-users text-[#7f1d1d] text-lg"></i>
          </div>
          <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">إجمالي</span>
        </div>
        <p class="text-3xl font-extrabold text-[#581c22]"><?=number_format($students_total)?></p>
        <p class="text-xs text-slate-500 mt-1">إجمالي الطلاب المسجلين</p>
      </div>

      <div class="kpi kpi-red">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 rounded-xl bg-red-600/10 flex items-center justify-center">
            <i class="fas fa-school text-red-700 text-lg"></i>
          </div>
          <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">On-site</span>
        </div>
        <p class="text-3xl font-extrabold text-red-800"><?=number_format($students_onsite)?></p>
        <p class="text-xs text-slate-500 mt-1">
          <?=$students_total>0?round($students_onsite/$students_total*100,1):0?>% من الإجمالي
        </p>
        <div class="mt-2 prog-track">
          <div class="prog-fill bg-red-700" style="width:<?=$students_total>0?round($students_onsite/$students_total*100):0?>%"></div>
        </div>
      </div>

      <div class="kpi kpi-rose">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 rounded-xl bg-rose-600/10 flex items-center justify-center">
            <i class="fas fa-wifi text-rose-600 text-lg"></i>
          </div>
          <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Online</span>
        </div>
        <p class="text-3xl font-extrabold text-rose-700"><?=number_format($students_online)?></p>
        <p class="text-xs text-slate-500 mt-1">
          <?=$students_total>0?round($students_online/$students_total*100,1):0?>% من الإجمالي
        </p>
        <div class="mt-2 prog-track">
          <div class="prog-fill bg-rose-600" style="width:<?=$students_total>0?round($students_online/$students_total*100):0?>%"></div>
        </div>
      </div>

      <div class="kpi kpi-amber">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center">
            <i class="fas fa-layer-group text-amber-500 text-lg"></i>
          </div>
          <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Both</span>
        </div>
        <p class="text-3xl font-extrabold text-amber-600"><?=number_format($students_both)?></p>
        <p class="text-xs text-slate-500 mt-1">هجين (On-site + Online)</p>
      </div>

    </div>

    <!-- ══ KPI Row 2: الفواتير ══ -->
    <p class="section-title mb-4"><i class="fas fa-file-invoice-dollar text-[#991b1b]"></i> إحصائيات الفواتير (MYR)</p>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">

      <div class="kpi kpi-slate col-span-1">
        <div class="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center mb-3">
          <i class="fas fa-hashtag text-slate-500"></i>
        </div>
        <p class="text-2xl font-extrabold text-slate-700"><?=number_format($inv_total_count)?></p>
        <p class="text-xs text-slate-500 mt-1">إجمالي الفواتير</p>
      </div>

      <div class="kpi kpi-red col-span-1">
        <div class="w-9 h-9 rounded-xl bg-red-50 flex items-center justify-center mb-3">
          <i class="fas fa-school text-red-600 text-sm"></i>
        </div>
        <p class="text-2xl font-extrabold text-red-800"><?=number_format($inv_onsite_count)?></p>
        <p class="text-xs text-slate-500 mt-1">فواتير On-site</p>
        <p class="text-xs text-red-700 font-bold mt-1" dir="ltr"><?=fmtN($inv_onsite_amount)?> <span class="opacity-70 font-normal">MYR</span></p>
      </div>

      <div class="kpi kpi-rose col-span-1">
        <div class="w-9 h-9 rounded-xl bg-rose-50 flex items-center justify-center mb-3">
          <i class="fas fa-wifi text-rose-500 text-sm"></i>
        </div>
        <p class="text-2xl font-extrabold text-rose-700"><?=number_format($inv_online_count)?></p>
        <p class="text-xs text-slate-500 mt-1">فواتير Online</p>
        <p class="text-xs text-rose-600 font-bold mt-1" dir="ltr"><?=fmtN($inv_online_amount)?> <span class="opacity-70 font-normal">MYR</span></p>
      </div>

      <div class="kpi kpi-maroon col-span-1">
        <div class="w-9 h-9 rounded-xl bg-red-900/10 flex items-center justify-center mb-3">
          <i class="fas fa-receipt text-[#7f1d1d] text-sm"></i>
        </div>
        <p class="text-lg font-extrabold text-[#7f1d1d]" dir="ltr"><?=fmtN($inv_total_amount)?></p>
        <p class="text-xs text-slate-500 mt-1">إجمالي المفوتر (MYR)</p>
      </div>

      <div class="kpi kpi-green col-span-1">
        <div class="w-9 h-9 rounded-xl bg-emerald-50 flex items-center justify-center mb-3">
          <i class="fas fa-circle-check text-emerald-500 text-sm"></i>
        </div>
        <p class="text-lg font-extrabold text-emerald-600" dir="ltr"><?=fmtN($inv_paid_amount)?></p>
        <p class="text-xs text-slate-500 mt-1">المسدد (MYR)</p>
        <p class="text-xs text-emerald-600 mt-1 font-bold">تحصيل <?=$collection_rate?>%</p>
      </div>

      <div class="kpi kpi-rose col-span-1">
        <div class="w-9 h-9 rounded-xl bg-red-50 flex items-center justify-center mb-3">
          <i class="fas fa-hourglass-half text-red-500 text-sm"></i>
        </div>
        <p class="text-lg font-extrabold text-red-600" dir="ltr"><?=fmtN($inv_residual_amount)?></p>
        <p class="text-xs text-slate-500 mt-1">المتبقي (MYR)</p>
      </div>

    </div>

    <!-- شريط التحصيل الكلي -->
    <div class="mt-4 bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
      <div class="flex items-center justify-between mb-2 text-xs">
        <span class="font-bold text-slate-700">نسبة التحصيل الكلية</span>
        <span class="font-bold text-emerald-600 text-base"><?=$collection_rate?>%</span>
      </div>
      <div class="prog-track" style="height:12px;border-radius:6px;">
        <div class="prog-fill" style="width:<?=$collection_rate?>%;height:12px;border-radius:6px;
            background:linear-gradient(90deg,#10b981,#34d399);"></div>
      </div>
      <div class="flex justify-between mt-2 text-xs text-slate-500 font-medium">
        <span dir="ltr"><?=fmtN($inv_paid_amount)?> MYR مسدد</span>
        <span dir="ltr"><?=fmtN($inv_residual_amount)?> MYR متبقي</span>
      </div>
    </div>

  </div>
</div>

<!-- ══════════════════════════════════
     MAIN CONTENT (light body)
══════════════════════════════════ -->
<div class="max-w-screen-xl mx-auto px-6 py-8 space-y-8">

  <!-- ══ Row: البنوك + الصناديق + الحركات الأخيرة ══ -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- البنوك والصناديق -->
    <div class="section-card lg:col-span-1 space-y-4">
      <p class="section-title"><i class="fas fa-university text-[#7f1d1d]"></i> أرصدة البنوك والصناديق</p>

      <?php if(!empty($bank_accounts)):?>
      <div>
        <p class="text-xs text-slate-400 uppercase tracking-widest font-bold mb-3">
          <i class="fas fa-building-columns ml-1 text-slate-400"></i>البنوك
        </p>
        <?php foreach($bank_accounts as$b):
          $isPos=$b['balance']>=0;
        ?>
        <div class="flex items-center justify-between py-3 border-b border-slate-100">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-red-900/10 flex items-center justify-center flex-shrink-0">
              <i class="fas fa-landmark text-[#7f1d1d] text-xs"></i>
            </div>
            <p class="text-sm font-bold text-slate-700"><?=htmlspecialchars($b['name'])?></p>
          </div>
          <p class="font-extrabold text-sm <?=$isPos?'text-emerald-600':'text-red-600'?>" dir="ltr">
            <?=fmtN($b['balance'])?>
            <span class="text-xs opacity-70 font-normal text-slate-500"> MYR</span>
          </p>
        </div>
        <?php endforeach;?>
      </div>
      <?php endif;?>

      <?php if(!empty($cash_accounts)):?>
      <div class="mt-4">
        <p class="text-xs text-slate-400 uppercase tracking-widest font-bold mb-3">
          <i class="fas fa-cash-register ml-1 text-slate-400"></i>الصناديق
        </p>
        <?php foreach($cash_accounts as$c):
          $isPos=$c['balance']>=0;
        ?>
        <div class="flex items-center justify-between py-3 border-b border-slate-100">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center flex-shrink-0">
              <i class="fas fa-coins text-amber-600 text-xs"></i>
            </div>
            <p class="text-sm font-bold text-slate-700"><?=htmlspecialchars($c['name'])?></p>
          </div>
          <p class="font-extrabold text-sm <?=$isPos?'text-emerald-600':'text-red-600'?>" dir="ltr">
            <?=fmtN($c['balance'])?>
            <span class="text-xs opacity-70 font-normal text-slate-500"> MYR</span>
          </p>
        </div>
        <?php endforeach;?>
      </div>
      <?php endif;?>

      <?php if(empty($bank_accounts)&&empty($cash_accounts)):?>
        <p class="text-slate-400 text-sm text-center py-8">لا توجد بيانات</p>
      <?php endif;?>
    </div>

    <!-- الحركات الأخيرة -->
    <div class="section-card lg:col-span-2">
      <p class="section-title"><i class="fas fa-bolt text-[#7f1d1d]"></i> الحركات الأخيرة</p>
      <div class="overflow-x-auto">
        <table class="dash-table">
          <thead>
            <tr>
              <th>التاريخ</th>
              <th>المرجع</th>
              <th>الشريك</th>
              <th>النوع</th>
              <th class="text-left">المبلغ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($recent_moves as$mv):
              $type =$mv['move_type'];
              $badgeCls =$type==='out_invoice'?'badge-inv':($type==='in_invoice'?'badge-out':'badge-ent');$typeAr = $type==='out_invoice'?'فاتورة عميل':($type==='in_invoice'?'فاتورة مورد':'قيد');
              $partner = is_array($mv['partner_id']) ? $mv['partner_id'][1] : '—';$cur = is_array($mv['currency_id']) ?$mv['currency_id'][1] : 'MYR';
              $is_idr =$cur==='IDR';
            ?>
            <tr>
              <td class="text-slate-500 whitespace-nowrap text-xs font-medium"><?=htmlspecialchars($mv['date'])?></td>
              <td class="text-[#991b1b] font-bold text-xs whitespace-nowrap"><?=htmlspecialchars($mv['name']??'—')?></td>
              <td class="text-xs max-w-xs truncate font-medium text-slate-700">
                <?=htmlspecialchars(strlen($partner)>30?substr($partner,0,30).'…':$partner)?>
              </td>
              <td><span class="badge <?=$badgeCls?>"><?=$typeAr?></span></td>
              <td class="text-left font-bold text-sm whitespace-nowrap text-slate-800" dir="ltr">
                <?=$is_idr?number_format($mv['amount_total'],0):number_format($mv['amount_total'],2)?>
                <span class="text-xs text-slate-400 font-normal"> <?=$cur?></span>
              </td>
            </tr>
            <?php endforeach;?>
            <?php if(empty($recent_moves)):?>
              <tr><td colspan="5" class="text-center text-slate-400 py-8">لا توجد حركات</td></tr>
            <?php endif;?>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <!-- ══ المصروفات: رسم بياني + جدول أعلى الحسابات ══ -->
  <div class="section-card">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <p class="section-title mb-0"><i class="fas fa-chart-line text-[#7f1d1d]"></i> تحليل المصروفات الشهرية</p>

      <!-- فلتر الفترة -->
      <div class="flex items-center gap-3">
        <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2">
          <i class="fas fa-calendar-alt text-slate-400 text-sm"></i>
          <input type="month" id="filterFrom"
                 value="<?=date('Y-m', strtotime('-6 months'))?>"
                 max="<?=date('Y-m')?>"
                 class="bg-transparent text-slate-700 font-medium text-sm outline-none w-32">
          <span class="text-slate-400 text-xs">إلى</span>
          <input type="month" id="filterTo"
                 value="<?=date('Y-m')?>"
                 max="<?=date('Y-m')?>"
                 class="bg-transparent text-slate-700 font-medium text-sm outline-none w-32">
        </div>
        <button onclick="applyFilter()"
                class="bg-[#7f1d1d] hover:bg-[#581c22] text-white px-4 py-2 rounded-xl text-sm font-bold transition-colors flex items-center gap-2">
          <i class="fas fa-filter text-xs"></i> تطبيق
        </button>
        <button onclick="resetFilter()"
                class="bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-600 px-4 py-2 rounded-xl text-sm font-bold transition-colors">
          <i class="fas fa-rotate-right text-xs"></i>
        </button>
      </div>
    </div>

    <!-- ملاحظة هذا الشهر -->
    <div class="flex flex-wrap gap-4 mb-6">
      <div class="bg-red-50 border border-red-100 rounded-xl px-5 py-3 flex items-center gap-3">
        <i class="fas fa-fire text-red-500 text-xl"></i>
        <div>
          <p class="text-xs text-slate-500 font-medium">مصروفات هذا الشهر</p>
          <p class="text-xl font-extrabold text-red-700" dir="ltr">
            <?=fmtN($expenses_this_month)?> <span class="text-sm font-normal text-slate-400">MYR</span>
          </p>
        </div>
      </div>
      <div class="bg-slate-50 border border-slate-200 rounded-xl px-5 py-3 flex items-center gap-3">
        <i class="fas fa-calendar-days text-slate-400 text-xl"></i>
        <div>
          <p class="text-xs text-slate-500 font-medium">الفترة المعروضة</p>
          <p class="text-sm font-bold text-slate-700" id="periodLabel">—</p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

      <!-- الرسم البياني -->
      <div class="xl:col-span-2">
        <div class="chart-wrap">
          <canvas id="expChart"></canvas>
        </div>
      </div>

      <!-- جدول أعلى الحسابات -->
      <div class="xl:col-span-1">
        <p class="text-xs text-slate-400 uppercase tracking-widest font-bold mb-4">
          <i class="fas fa-ranking-star ml-1 text-amber-500"></i>أعلى الحسابات صرفاً
        </p>
        <div class="space-y-2" id="topAccList">
          <?php
          $i = 0;
          foreach ($top_accounts as $acc_n =>$acc_v):
            $pct_exp =$expenses_this_month > 0 ? round($acc_v / array_sum($expense_accounts) * 100, 1) : 0;
            $color =$palette[$i % count($palette)];
            $short = strlen($acc_n) > 30 ? substr($acc_n, 0, 30).'…' :$acc_n;
            $i++;
          ?>
          <div class="exp-row rounded-xl p-3 cursor-default border border-transparent hover:border-slate-100">
            <div class="flex items-center justify-between mb-1.5">
              <div class="flex items-center gap-2 min-w-0">
                <div class="w-3 h-3 rounded-sm flex-shrink-0 shadow-sm" style="background:<?=$color?>"></div>
                <p class="text-xs text-slate-600 font-bold truncate" title="<?=htmlspecialchars($acc_n)?>"><?=htmlspecialchars($short)?></p>
              </div>
              <p class="text-xs font-bold text-slate-800 flex-shrink-0 mr-2" dir="ltr"><?=fmtN($acc_v)?></p>
            </div>
            <div class="prog-track" style="height:4px;">
              <div class="prog-fill" style="width:<?=$pct_exp?>%;height:4px;background:<?=$color?>;"></div>
            </div>
          </div>
          <?php endforeach;?>
        </div>
      </div>

    </div>
  </div>

</div><!-- /content -->

<!-- ══════════════════════════════════
     JAVASCRIPT
══════════════════════════════════ -->
<script>
// ── بيانات المصروفات الخام من PHP ──────────────────────────
const EXP_RAW = <?=json_encode($expense_data, JSON_UNESCAPED_UNICODE)?>;
const TOP_ACCS = <?=json_encode($top_acc_names, JSON_UNESCAPED_UNICODE)?>;
const PALETTE  = <?=json_encode($palette)?>;
const ALL_MONTHS_LABELS = <?=json_encode($months_labels, JSON_UNESCAPED_UNICODE)?>;
const ALL_MONTHS_KEYS   = <?=json_encode(array_keys($months_labels), JSON_UNESCAPED_UNICODE)?>;

// ── Chart.js setup (Light Theme) ───────────────────────────
Chart.defaults.color = '#64748b'; // text-slate-500
Chart.defaults.borderColor = 'rgba(0,0,0,0.05)';

const ctx = document.getElementById('expChart').getContext('2d');
let myChart;

function buildChart(filteredKeys) {
    const labels = filteredKeys.map(k => ALL_MONTHS_LABELS[k] ?? k);
    const datasets = TOP_ACCS.map((acc, i) => ({
        label: acc.length > 35 ? acc.slice(0, 35) + '…' : acc,
        data: filteredKeys.map(k => EXP_RAW[k]?.[acc] ?? 0),
        backgroundColor: PALETTE[i % PALETTE.length] + '22',
        borderColor: PALETTE[i % PALETTE.length],
        borderWidth: 2,
        tension: 0.4,
        fill: false,
        pointRadius: 4,
        pointHoverRadius: 7,
        pointBackgroundColor: PALETTE[i % PALETTE.length],
    }));

    if (myChart) myChart.destroy();

    myChart = new Chart(ctx, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { family: 'Tajawal', size: 12, weight: '500' },
                        color: '#475569',
                        boxWidth: 12, boxHeight: 12,
                        padding: 16,
                        usePointStyle: true,
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(255,255,255,.98)',
                    titleColor: '#1e293b',
                    bodyColor: '#334155',
                    borderColor: 'rgba(0,0,0,.1)',
                    borderWidth: 1,
                    padding: 12,
                    titleFont: { family: 'Tajawal', size: 13, weight: '800' },
                    bodyFont:  { family: 'Tajawal', size: 12, weight: '500' },
                    boxPadding: 4,
                    callbacks: {
                        label: ctx => {
                            const v = parseFloat(ctx.raw).toLocaleString('en-MY',
                                {minimumFractionDigits:2, maximumFractionDigits:2});
                            return `  ${ctx.dataset.label}: ${v} MYR`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(0,0,0,.03)' },
                    ticks: { font: { family:'Tajawal', size:11 } }
                },
                y: {
                    grid: { color: 'rgba(0,0,0,.03)' },
                    ticks: {
                        font: { family:'Tajawal', size:11 },
                        callback: v => {
                            if (v >= 1000000) return (v/1000000).toFixed(1)+'M';
                            if (v >= 1000)    return (v/1000).toFixed(0)+'K';
                            return v;
                        }
                    }
                }
            }
        }
    });
}

// ── تطبيق الفلتر ───────────────────────────────────────────
function applyFilter() {
    const from = document.getElementById('filterFrom').value;
    const to   = document.getElementById('filterTo').value;
    const filtered = ALL_MONTHS_KEYS.filter(k => k >= from && k <= to);

    if (!filtered.length) {
        alert('لا توجد بيانات في هذه الفترة');
        return;
    }

    buildChart(filtered);
    updateTopAccList(filtered);

    // تحديث تسمية الفترة
    const fl = ALL_MONTHS_LABELS[filtered[0]] ?? filtered[0];
    const tl = ALL_MONTHS_LABELS[filtered[filtered.length-1]] ?? filtered[filtered.length-1];
    document.getElementById('periodLabel').textContent = `${fl} — ${tl}`;
}

function resetFilter() {
    document.getElementById('filterFrom').value = ALL_MONTHS_KEYS[0] ?? '';
    document.getElementById('filterTo').value   = ALL_MONTHS_KEYS[ALL_MONTHS_KEYS.length-1] ?? '';
    applyFilter();
}

// ── تحديث جدول أعلى الحسابات حسب الفلتر ──────────────────
function updateTopAccList(filteredKeys) {
    // إعادة حساب الإجماليات في الفترة المختارة
    const totals = {};
    TOP_ACCS.forEach(acc => {
        totals[acc] = filteredKeys.reduce((s, k) => s + (EXP_RAW[k]?.[acc] ?? 0), 0);
    });
    const grandTotal = Object.values(totals).reduce((a,b)=>a+b, 0);

    const container = document.getElementById('topAccList');
    container.innerHTML = '';
    TOP_ACCS
        .map((acc, i) => ({ acc, i, v: totals[acc] }))
        .sort((a,b) => b.v - a.v)
        .forEach(({ acc, i, v }) => {
            const pct = grandTotal > 0 ? (v / grandTotal * 100).toFixed(1) : 0;
            const short = acc.length > 30 ? acc.slice(0,30)+'…' : acc;
            const color = PALETTE[i % PALETTE.length];
            const fmtV = v.toLocaleString('en-MY',{minimumFractionDigits:2,maximumFractionDigits:2});
            container.innerHTML += `
              <div class="exp-row rounded-xl p-3 cursor-default border border-transparent hover:border-slate-100">
                <div class="flex items-center justify-between mb-1.5">
                  <div class="flex items-center gap-2 min-w-0">
                    <div class="w-3 h-3 rounded-sm flex-shrink-0 shadow-sm" style="background:${color}"></div>
                    <p class="text-xs text-slate-600 font-bold truncate" title="${acc}">${short}</p>
                  </div>
                  <p class="text-xs font-bold text-slate-800 flex-shrink-0 mr-2" dir="ltr">${fmtV}</p>
                </div>
                <div style="background:#f1f5f9;border-radius:999px;height:4px;">
                  <div style="width:${pct}%;height:4px;border-radius:999px;background:${color};"></div>
                </div>
              </div>`;
        });
}

// ── تهيئة أولية ────────────────────────────────────────────
(function init() {
    const from = document.getElementById('filterFrom').value;
    const to   = document.getElementById('filterTo').value;
    const filtered = ALL_MONTHS_KEYS.filter(k => k >= from && k <= to);
    const fk = filtered.length ? filtered : ALL_MONTHS_KEYS;

    buildChart(fk);
    updateTopAccList(fk);

    if (fk.length) {
        const fl = ALL_MONTHS_LABELS[fk[0]] ?? fk[0];
        const tl = ALL_MONTHS_LABELS[fk[fk.length-1]] ?? fk[fk.length-1];
        document.getElementById('periodLabel').textContent = `${fl} — ${tl}`;
    }
})();
</script>

</body>
</html>