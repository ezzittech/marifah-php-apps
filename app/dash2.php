<?php
$odoo_url = 'https://portal.marifah.edu.my';
$db       = 'Marifahdb';
$username = 'ezzittech@gmail.com';
$password = 'Abulez@2526';

function authenticate($url,$db,$login,$password){
    $ch=curl_init($url.'/web/session/authenticate');
    $payload=json_encode(['jsonrpc'=>'2.0','method'=>'call','params'=>['db'=>$db,'login'=>$login,'password'=>$password]]);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HEADER=>true,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_TIMEOUT=>30]);
    $r=curl_exec($ch);$hs=curl_getinfo($ch,CURLINFO_HEADER_SIZE);$h=substr($r,0,$hs);curl_close($ch);
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi',$h,$m);$c=[];
    foreach($m[1] as $i){parse_str($i,$x);$c=array_merge($c,$x);}
    return $c['session_id']??'';
}

function odoo($url,$sid,$model,$method,$args,$kwargs=[]){
    $ch=curl_init($url.'/web/dataset/call_kw');
    $payload=json_encode(['jsonrpc'=>'2.0','method'=>'call',
        'params'=>['model'=>$model,'method'=>$method,'args'=>$args,'kwargs'=>$kwargs]]);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Cookie: session_id='.$sid],
        CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_TIMEOUT=>90]);
    $r=curl_exec($ch);curl_close($ch);
    $d=json_decode($r,true);return $d['result']??null;
}

$sid=authenticate($odoo_url,$db,$username,$password);
$error=$sid?'':'فشل الاتصال بأودو';

// ══════════════════════════════════
//  1. الحركات الأخيرة (10 حركات)
// ══════════════════════════════════
$recent_moves=[];
if($sid){
    $moves=odoo($odoo_url,$sid,'account.move','search_read',
        [[['state','=','posted'],['move_type','in',['out_invoice','in_invoice','entry']]]],
        ['fields'=>['name','date','partner_id','move_type','amount_total','currency_id','journal_id'],
         'order'=>'date desc, id desc','limit'=>12]);
    if(is_array($moves))$recent_moves=$moves;
}

// ══════════════════════════════════
//  2. إحصائيات الطلاب والفواتير والتخفيضات
//     المنطق مطابق لصفحة student_invoice_report.php
// ══════════════════════════════════
$students_total    = 0;
$students_onsite   = 0;
$students_online   = 0;

$inv_total_amount    = 0.0;
$inv_paid_amount     = 0.0;
$inv_residual_amount = 0.0;
$inv_onsite_amount   = 0.0;
$inv_onsite_residual = 0.0;
$inv_online_amount   = 0.0;
$inv_online_residual = 0.0;

// متغيرات التخفيضات الجديدة
$discount_41_43_total = 0.0;
$discount_2172_total  = 0.0;

$fx_rates = ['MYR'=>1.0];

if($sid){
    // أسعار الصرف من أودو
    $cur_data=odoo($odoo_url,$sid,'res.currency','search_read',
        [[['active','=',true]]],['fields'=>['name','rate']]);
    if(is_array($cur_data)){
        foreach($cur_data as $c){
            if(!empty($c['name'])&&!empty($c['rate'])&&(float)$c['rate']>0)
                $fx_rates[(string)$c['name']]=(float)$c['rate'];
        }
    }

    // جلب الفواتير المنشورة
    $invoices=odoo($odoo_url,$sid,'account.move','search_read',
        [[['move_type','=','out_invoice'],['state','=','posted']]],
        ['fields'=>['partner_id','currency_id','amount_total','amount_residual']]);

    if(is_array($invoices)){
        // استخراج معرّفات الشركاء الفريدة
        $unique_pids=[];
        foreach($invoices as $inv){
            if(empty($inv['partner_id']))continue;
            $pid=is_array($inv['partner_id'])?$inv['partner_id'][0]:$inv['partner_id'];
            $unique_pids[$pid]=$pid;
        }
        $students_total=count($unique_pids);

        // جلب نوع الدراسة لكل شريك
        $pid_type_map=[];
        if(!empty($unique_pids)){
            $partners=odoo($odoo_url,$sid,'res.partner','search_read',
                [[['id','in',array_values($unique_pids)]]],
                ['fields'=>['id','x_student_type']]);
            if(is_array($partners)){
                foreach($partners as $p){
                    $t=is_string($p['x_student_type'])?$p['x_student_type']:'';
                    $pid_type_map[$p['id']]=$t;
                    // On-site = physical, Online = online أو both
                    if($t==='physical')     $students_onsite++;
                    elseif($t==='online'||$t==='both') $students_online++;
                }
            }
        }

        // حساب المبالغ بالـ MYR
        foreach($invoices as $inv){
            if(empty($inv['partner_id']))continue;
            $pid=is_array($inv['partner_id'])?$inv['partner_id'][0]:$inv['partner_id'];
            $cur='MYR';
            if(!empty($inv['currency_id'])&&is_array($inv['currency_id']))$cur=(string)$inv['currency_id'][1];
            elseif(is_string($inv['currency_id'])&&$inv['currency_id']!='')$cur=(string)$inv['currency_id'];
            $rate=$fx_rates[$cur]??1.0;
            $total_myr   =$rate>0?(float)$inv['amount_total']/$rate:0;
            $resid_myr   =$rate>0?(float)$inv['amount_residual']/$rate:0;
            $paid_myr    =$total_myr-$resid_myr;

            $inv_total_amount    +=$total_myr;
            $inv_paid_amount     +=$paid_myr;
            $inv_residual_amount +=$resid_myr;

            $ptype=$pid_type_map[$pid]??'';
            if($ptype==='physical'){
                $inv_onsite_amount  +=$total_myr;
                $inv_onsite_residual+=$resid_myr;
            }elseif($ptype==='online'||$ptype==='both'){
                $inv_online_amount  +=$total_myr;
                $inv_online_residual+=$resid_myr;
            }
        }
    }

    // جلب التخفيضات (لأرقام الأصناف المطلوبة فقط)
    $discount_lines = odoo($odoo_url, $sid, 'account.move.line', 'search_read',
        [[
            ['parent_state','=','posted'],
            ['move_id.move_type','=','out_invoice'],
            ['product_id','in',[41, 43, 2172]]
        ]],
        ['fields'=>['product_id', 'price_subtotal', 'currency_id']]
    );

    if(is_array($discount_lines)){
        foreach($discount_lines as $line){
            if(empty($line['product_id'])) continue;
            
            $pid = is_array($line['product_id']) ? $line['product_id'][0] : $line['product_id'];
            
            $cur = 'MYR';
            if(!empty($line['currency_id']) && is_array($line['currency_id'])) $cur = (string)$line['currency_id'][1];
            elseif(is_string($line['currency_id']) && $line['currency_id'] != '') $cur = (string)$line['currency_id'];
            
            $rate = $fx_rates[$cur] ?? 1.0;
            
            // نأخذ القيمة المطلقة للمبلغ لضمان ظهوره كرقم موجب، ونقسمه على سعر الصرف لتوحيده بالرنجت
            $amt_myr = $rate > 0 ? abs((float)($line['price_subtotal'] ?? 0)) / $rate : 0;

            if($pid == 41 || $pid == 43){
                $discount_41_43_total += $amt_myr;
            } elseif($pid == 2172){
                $discount_2172_total += $amt_myr;
            }
        }
    }
}

$collection_rate=$inv_total_amount>0?round($inv_paid_amount/$inv_total_amount*100,1):0;
$onsite_rate=$inv_onsite_amount>0?round(($inv_onsite_amount-$inv_onsite_residual)/$inv_onsite_amount*100,1):0;
$online_rate=$inv_online_amount>0?round(($inv_online_amount-$inv_online_residual)/$inv_online_amount*100,1):0;

// ══════════════════════════════════
//  3. المصروفات الشهرية
// ══════════════════════════════════
$expense_data=[];$expense_accounts=[];

if($sid){
    $date_from=date('Y-m-d',strtotime('-12 months'));
    $exp_lines=odoo($odoo_url,$sid,'account.move.line','search_read',
        [[['parent_state','=','posted'],
          ['account_id.account_type','in',['expense','expense_depreciation','expense_direct_cost']],
          ['date','>=',$date_from]]],
        ['fields'=>['date','account_id','debit','credit'],
         'order'=>'date asc','limit'=>5000]);
    if(is_array($exp_lines)){
        foreach($exp_lines as $l){
            $month=substr($l['date'],0,7);
            $acc=(!empty($l['account_id'])&&is_array($l['account_id']))?(string)$l['account_id'][1]:'غير محدد';
            $amt=(float)($l['debit']??0)-(float)($l['credit']??0);
            if($amt<=0)continue;
            $expense_data[$month][$acc]=($expense_data[$month][$acc]??0)+$amt;
            $expense_accounts[$acc]=($expense_accounts[$acc]??0)+$amt;
        }
        ksort($expense_data);arsort($expense_accounts);
    }
}

$months_labels=[];
foreach(array_keys($expense_data) as $m)
    $months_labels[$m]=date('M Y',strtotime($m.'-01'));

$top_accounts=array_slice($expense_accounts,0,8,true);
$top_acc_names=array_keys($top_accounts);

// لوحة ألوان Marifah
$palette=['#7f1d1d','#991b1b','#b91c1c','#dc2626','#ef4444','#f87171','#f59e0b','#fb923c'];

$this_month=date('Y-m');
$expenses_this_month=isset($expense_data[$this_month])?array_sum($expense_data[$this_month]):0;

function fmtN($v,$dec=2){return number_format((float)$v,$dec);}
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
*{box-sizing:border-box}
body{font-family:'Tajawal',sans-serif;background:#fdf2f4;min-height:100vh}

/* ══ Hero ══ */
.hero{
    background:linear-gradient(150deg,#1a0608 0%,#4a0e18 40%,#7f1d1d 75%,#4a0e18 100%);
    position:relative;overflow:hidden;
}
.hero::before{
    content:'';position:absolute;inset:0;
    background:
        radial-gradient(ellipse at 10% 60%,rgba(180,30,55,.2) 0%,transparent 50%),
        radial-gradient(ellipse at 90% 15%,rgba(100,10,20,.25) 0%,transparent 45%);
}
.orb{position:absolute;border-radius:50%;background:rgba(180,30,55,.07);animation:orbf 11s ease-in-out infinite}
@keyframes orbf{0%,100%{transform:translateY(0) scale(1)}50%{transform:translateY(-16px) scale(1.05)}}

/* ══ KPI ══ */
.kpi{
    background:#fff;border:1px solid #fce7eb;
    border-radius:16px;padding:20px;
    transition:transform .2s,box-shadow .2s,border-color .2s;
    position:relative;overflow:hidden;
    box-shadow:0 2px 8px rgba(127,29,29,.06);
}
.kpi:hover{transform:translateY(-3px);box-shadow:0 12px 28px rgba(127,29,29,.12);border-color:#fda4af}
.kpi::after{content:'';position:absolute;top:0;left:0;width:3px;height:100%;border-radius:0 2px 2px 0}
.kpi-r1::after{background:#7f1d1d}
.kpi-r2::after{background:#b91c1c}
.kpi-r3::after{background:#ef4444}
.kpi-g::after {background:#10b981}

/* ══ Section ══ */
.scard{background:#fff;border:1px solid #fce7eb;border-radius:20px;padding:24px;box-shadow:0 2px 8px rgba(127,29,29,.05)}
.stitle{font-size:13px;font-weight:800;color:#7f1d1d;letter-spacing:1.5px;text-transform:uppercase;
        margin-bottom:20px;display:flex;align-items:center;gap:8px}

/* ══ Progress ══ */
.ptrack{background:#fee2e2;border-radius:999px;height:8px}
.pfill {height:8px;border-radius:999px;transition:width 1s ease}

/* ══ Table ══ */
.dtable{width:100%;border-collapse:collapse;font-size:13px}
.dtable th{color:#9f1239;font-weight:700;font-size:11px;letter-spacing:1px;text-transform:uppercase;
           padding:10px 14px;border-bottom:2px solid #fce7eb;text-align:right}
.dtable td{padding:12px 14px;border-bottom:1px solid #fdf2f4;color:#334155;vertical-align:middle}
.dtable tr:hover td{background:#fdf2f4}

/* ══ Badges ══ */
.bdg{display:inline-block;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700}
.bdg-inv{background:#fee2e2;color:#991b1b}
.bdg-out{background:#fef3c7;color:#92400e}
.bdg-ent{background:#f1f5f9;color:#475569}

/* ══ Chart ══ */
.cwrap{position:relative;height:340px}

/* ══ Scrollbar ══ */
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-thumb{background:rgba(127,29,29,.25);border-radius:4px}

/* ══ Exp row ══ */
.erow{transition:background .15s;border-radius:10px}
.erow:hover{background:#fdf2f4}

/* ══ Divider line ══ */
.sec-div{height:1px;background:linear-gradient(90deg,transparent,#fda4af,transparent);margin:8px 0}
</style>
</head>
<body class="antialiased">

<!-- ══════════════════════════
     HERO
══════════════════════════ -->
<div class="hero px-6 pb-10 pt-6">
  <div class="orb" style="width:450px;height:450px;top:-160px;right:-110px"></div>
  <div class="orb" style="width:280px;height:280px;bottom:-90px;left:4%;animation-delay:4s"></div>

  <div class="relative z-10 max-w-screen-xl mx-auto">

    <!-- Top bar -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-10">
      <div class="flex items-center gap-4">
        <img src="https://cdn.prod.website-files.com/642e38eb59ef94ba3c2bf35b/68696ec0e6fe2cab7b377c39_724c268af6d0a34b14c1961f01f18585_Marifah_CMYK-p-500.png"
             alt="Marifah" class="h-12 object-contain brightness-0 invert opacity-90">
        <div class="border-r border-white/15 pr-4">
          <h1 class="text-2xl font-extrabold text-white leading-tight">لوحة تحكم الإدارة</h1>
          <p class="text-white/40 text-xs mt-0.5">Management Dashboard — <?=date('d/m/Y H:i')?></p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <?php if($error):?>
          <span class="bg-red-500/20 border border-red-400/30 text-red-200 px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5">
            <i class="fas fa-circle-xmark"></i> <?=htmlspecialchars($error)?>
          </span>
        <?php else:?>
          <span class="bg-emerald-500/15 border border-emerald-400/25 text-emerald-300 px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5">
            <span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></span>
            متصل بأودو
          </span>
        <?php endif;?>
        <a href="index.html"
           class="bg-white/10 hover:bg-white/20 border border-white/15 text-white px-4 py-2 rounded-xl text-sm font-bold transition-all flex items-center gap-2">
          <i class="fas fa-home"></i> الرئيسية
        </a>
      </div>
    </div>

    <!-- ══ قسم الطلاب ══ -->
    <p class="text-xs font-bold tracking-widest text-white/30 uppercase mb-4">
      <i class="fas fa-graduation-cap text-rose-300 ml-2"></i>إحصائيات الطلاب
    </p>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">

      <!-- إجمالي -->
      <div class="kpi kpi-r1">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:rgba(127,29,29,.1)">
            <i class="fas fa-users text-[#7f1d1d] text-lg"></i>
          </div>
          <span class="text-xs font-bold text-slate-400 tracking-widest uppercase">إجمالي</span>
        </div>
        <p class="text-4xl font-extrabold text-[#581c22]"><?=number_format($students_total)?></p>
        <p class="text-xs text-slate-500 mt-1.5">طالب مرتبط بفواتير فعلية</p>
      </div>

      <!-- On-site -->
      <div class="kpi kpi-r2">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:rgba(185,28,28,.1)">
            <i class="fas fa-school text-[#b91c1c] text-lg"></i>
          </div>
          <span class="text-xs font-bold tracking-widest uppercase" style="color:#b91c1c">On-site</span>
        </div>
        <p class="text-4xl font-extrabold text-[#7f1d1d]"><?=number_format($students_onsite)?></p>
        <p class="text-xs text-slate-500 mt-1.5">
          <?=$students_total>0?round($students_onsite/$students_total*100,1):0?>% من الإجمالي
        </p>
        <div class="mt-2 ptrack">
          <div class="pfill" style="width:<?=$students_total>0?round($students_onsite/$students_total*100):0?>%;background:#b91c1c"></div>
        </div>
      </div>

      <!-- Online -->
      <div class="kpi kpi-r3">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:rgba(239,68,68,.1)">
            <i class="fas fa-wifi text-[#ef4444] text-lg"></i>
          </div>
          <span class="text-xs font-bold tracking-widest uppercase" style="color:#ef4444">Online</span>
        </div>
        <p class="text-4xl font-extrabold" style="color:#991b1b"><?=number_format($students_online)?></p>
        <p class="text-xs text-slate-500 mt-1.5">
          <?=$students_total>0?round($students_online/$students_total*100,1):0?>% من الإجمالي
        </p>
        <div class="mt-2 ptrack">
          <div class="pfill" style="width:<?=$students_total>0?round($students_online/$students_total*100):0?>%;background:#ef4444"></div>
        </div>
      </div>

    </div>

    <!-- ══ قسم المبالغ ══ -->
    <p class="text-xs font-bold tracking-widest text-white/30 uppercase mb-4">
      <i class="fas fa-coins text-amber-300 ml-2"></i>المبالغ الموحّدة بالرنجت الماليزي (MYR)
    </p>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7 gap-4">

      <div class="kpi kpi-r1 col-span-1">
        <div class="w-9 h-9 rounded-xl flex items-center justify-center mb-3" style="background:rgba(127,29,29,.08)">
          <i class="fas fa-receipt text-[#7f1d1d]"></i>
        </div>
        <p class="text-xs text-slate-500 font-medium mb-0.5">إجمالي المفوتر</p>
        <p class="text-lg font-extrabold text-[#7f1d1d]" dir="ltr"><?=fmtN($inv_total_amount)?></p>
        <p class="text-xs text-slate-400 mt-0.5">MYR</p>
      </div>

      <div class="kpi kpi-g col-span-1">
        <div class="w-9 h-9 rounded-xl bg-emerald-50 flex items-center justify-center mb-3">
          <i class="fas fa-circle-check text-emerald-500"></i>
        </div>
        <p class="text-xs text-slate-500 font-medium mb-0.5">إجمالي المسدد</p>
        <p class="text-lg font-extrabold text-emerald-600" dir="ltr"><?=fmtN($inv_paid_amount)?></p>
        <p class="text-xs text-emerald-500 mt-0.5 font-bold">تحصيل <?=$collection_rate?>%</p>
      </div>

      <div class="kpi kpi-r3 col-span-1">
        <div class="w-9 h-9 rounded-xl bg-red-50 flex items-center justify-center mb-3">
          <i class="fas fa-hourglass-half text-red-500"></i>
        </div>
        <p class="text-xs text-slate-500 font-medium mb-0.5">إجمالي المتبقي</p>
        <p class="text-lg font-extrabold text-red-600" dir="ltr"><?=fmtN($inv_residual_amount)?></p>
        <p class="text-xs text-slate-400 mt-0.5">MYR</p>
      </div>

      <div class="kpi kpi-r2 col-span-1">
        <div class="w-9 h-9 rounded-xl flex items-center justify-center mb-3" style="background:rgba(185,28,28,.08)">
          <i class="fas fa-school text-[#b91c1c]"></i>
        </div>
        <p class="text-xs text-slate-500 font-medium mb-0.5">On-site المفوتر</p>
        <p class="text-lg font-extrabold text-[#991b1b]" dir="ltr"><?=fmtN($inv_onsite_amount)?></p>
        <p class="text-xs font-bold mt-0.5" style="color:#b91c1c">تحصيل <?=$onsite_rate?>%</p>
      </div>

      <div class="kpi kpi-r3 col-span-1">
        <div class="w-9 h-9 rounded-xl bg-red-50 flex items-center justify-center mb-3">
          <i class="fas fa-wifi text-red-400"></i>
        </div>
        <p class="text-xs text-slate-500 font-medium mb-0.5">Online المفوتر</p>
        <p class="text-lg font-extrabold text-red-700" dir="ltr"><?=fmtN($inv_online_amount)?></p>
        <p class="text-xs font-bold text-red-400 mt-0.5">تحصيل <?=$online_rate?>%</p>
      </div>

      <!-- التخفيضات 41 و 43 -->
      <div class="kpi kpi-r1 col-span-1">
        <div class="w-9 h-9 rounded-xl flex items-center justify-center mb-3" style="background:rgba(245,158,11,.15)">
          <i class="fas fa-tags text-amber-500"></i>
        </div>
        <p class="text-xs text-slate-500 font-medium mb-0.5">تخفيضات عامة (النسبة)</p>
        <p class="text-lg font-extrabold text-amber-600" dir="ltr"><?=fmtN($discount_41_43_total)?></p>
        <p class="text-xs text-slate-400 mt-0.5">MYR (لصنف 41 و 43)</p>
      </div>

      <!-- التخفيض 2172 -->
      <div class="kpi kpi-r3 col-span-1">
        <div class="w-9 h-9 rounded-xl flex items-center justify-center mb-3" style="background:rgba(239,68,68,.15)">
          <i class="fas fa-percent text-red-500"></i>
        </div>
        <p class="text-xs text-slate-500 font-medium mb-0.5">تخفيضات On-school</p>
        <p class="text-lg font-extrabold text-red-600" dir="ltr"><?=fmtN($discount_2172_total)?></p>
        <p class="text-xs text-slate-400 mt-0.5">MYR (لصنف 2172)</p>
      </div>

    </div>

    <!-- شريط التحصيل -->
    <div class="mt-5 bg-white/08 backdrop-blur-sm border border-white/10 rounded-2xl p-5">
      <div class="flex items-center justify-between mb-2.5 text-xs">
        <span class="font-bold text-white/70">نسبة التحصيل الكلية (بالمعادل للرنجت)</span>
        <span class="font-extrabold text-emerald-300 text-lg"><?=$collection_rate?>%</span>
      </div>
      <div style="background:rgba(255,255,255,.1);border-radius:999px;height:12px">
        <div style="width:<?=$collection_rate?>%;height:12px;border-radius:999px;
             background:linear-gradient(90deg,#10b981,#34d399);transition:width 1s ease"></div>
      </div>
      <div class="flex justify-between mt-2 text-xs text-white/30">
        <span dir="ltr"><?=fmtN($inv_paid_amount)?> MYR محصّل</span>
        <span dir="ltr"><?=fmtN($inv_residual_amount)?> MYR متبقي</span>
      </div>
    </div>

  </div>
</div>

<!-- ══════════════════════════
     MAIN CONTENT
══════════════════════════ -->
<div class="max-w-screen-xl mx-auto px-6 py-8 space-y-8">

  <!-- الحركات الأخيرة -->
  <div class="scard">
    <p class="stitle"><i class="fas fa-bolt"></i> آخر الحركات المالية</p>
    <div class="overflow-x-auto">
      <table class="dtable">
        <thead>
          <tr>
            <th>التاريخ</th>
            <th>رقم القيد</th>
            <th>الشريك / العميل</th>
            <th class="text-center">النوع</th>
            <th class="text-center">العملة</th>
            <th class="text-left">المبلغ</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($recent_moves as $mv):
            $type=$mv['move_type'];
            $bdg =$type==='out_invoice'?'bdg-inv':($type==='in_invoice'?'bdg-out':'bdg-ent');
            $typeAr=$type==='out_invoice'?'فاتورة عميل':($type==='in_invoice'?'فاتورة مورد':'قيد يومية');
            $partner=(!empty($mv['partner_id'])&&is_array($mv['partner_id']))?(string)$mv['partner_id'][1]:'—';
            $cur=(!empty($mv['currency_id'])&&is_array($mv['currency_id']))?(string)$mv['currency_id'][1]:'MYR';
            $is_idr=$cur==='IDR';
            $amt=(float)($mv['amount_total']??0);
            $curBadge=$cur==='MYR'
              ?'<span style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe" class="bdg">'.$cur.'</span>'
              :($cur==='IDR'
                ?'<span style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d" class="bdg">'.$cur.'</span>'
                :'<span style="background:#f0fdf4;color:#166534;border:1px solid #86efac" class="bdg">'.$cur.'</span>');
          ?>
          <tr>
            <td class="whitespace-nowrap">
              <span class="text-xs font-semibold text-slate-500"><?=htmlspecialchars($mv['date']??'')?></span>
            </td>
            <td class="whitespace-nowrap">
              <span class="font-bold text-[#7f1d1d] text-sm"><?=htmlspecialchars($mv['name']??'—')?></span>
            </td>
            <td class="max-w-xs">
              <span class="text-sm font-medium text-slate-700 truncate block">
                <?=htmlspecialchars(mb_strlen($partner)>40?mb_substr($partner,0,40).'…':$partner)?>
              </span>
            </td>
            <td class="text-center whitespace-nowrap">
              <span class="bdg <?=$bdg?>"><?=$typeAr?></span>
            </td>
            <td class="text-center"><?=$curBadge?></td>
            <td class="text-left font-extrabold text-slate-800 whitespace-nowrap" dir="ltr">
              <?=$is_idr?number_format($amt,0):number_format($amt,2)?>
              <span class="text-xs text-slate-400 font-normal ml-0.5"><?=htmlspecialchars($cur)?></span>
            </td>
          </tr>
          <?php endforeach;?>
          <?php if(empty($recent_moves)):?>
            <tr><td colspan="6" class="text-center text-slate-400 py-10 font-semibold">لا توجد حركات</td></tr>
          <?php endif;?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- المصروفات -->
  <div class="scard">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <p class="stitle mb-0"><i class="fas fa-chart-line"></i> تحليل المصروفات الشهرية</p>
      <div class="flex items-center gap-2">
        <div class="flex items-center gap-2 bg-rose-50 border border-rose-200 rounded-xl px-3 py-2">
          <i class="fas fa-calendar-alt text-rose-400 text-sm"></i>
          <input type="month" id="filterFrom"
                 value="<?=date('Y-m',strtotime('-6 months'))?>"
                 max="<?=date('Y-m')?>"
                 class="bg-transparent text-slate-700 font-bold text-sm outline-none w-30">
          <span class="text-slate-400 text-xs mx-1">→</span>
          <input type="month" id="filterTo"
                 value="<?=date('Y-m')?>"
                 max="<?=date('Y-m')?>"
                 class="bg-transparent text-slate-700 font-bold text-sm outline-none w-30">
        </div>
        <button onclick="applyFilter()"
                class="bg-[#7f1d1d] hover:bg-[#581c22] text-white px-4 py-2 rounded-xl text-sm font-bold transition-colors flex items-center gap-1.5 shadow-sm">
          <i class="fas fa-filter text-xs"></i> تطبيق
        </button>
        <button onclick="resetFilter()"
                class="bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-600 px-3 py-2 rounded-xl text-sm font-bold transition-colors"
                title="إعادة ضبط">
          <i class="fas fa-rotate-right"></i>
        </button>
      </div>
    </div>

    <!-- بطاقتا الشهر الحالي والفترة -->
    <div class="flex flex-wrap gap-3 mb-6">
      <div class="bg-red-50 border border-red-100 rounded-xl px-5 py-3 flex items-center gap-3">
        <i class="fas fa-fire text-red-500 text-xl"></i>
        <div>
          <p class="text-xs text-slate-500 font-medium">مصروفات هذا الشهر</p>
          <p class="text-xl font-extrabold text-[#7f1d1d]" dir="ltr">
            <?=fmtN($expenses_this_month)?> <span class="text-sm font-normal text-slate-400">MYR</span>
          </p>
        </div>
      </div>
      <div class="bg-rose-50 border border-rose-100 rounded-xl px-5 py-3 flex items-center gap-3">
        <i class="fas fa-calendar-days text-rose-400 text-xl"></i>
        <div>
          <p class="text-xs text-slate-500 font-medium">الفترة المعروضة</p>
          <p class="text-sm font-bold text-slate-700" id="periodLabel">—</p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

      <!-- الرسم البياني -->
      <div class="xl:col-span-2">
        <div class="cwrap">
          <canvas id="expChart"></canvas>
        </div>
      </div>

      <!-- أعلى الحسابات -->
      <div class="xl:col-span-1">
        <p class="text-xs text-[#7f1d1d] uppercase tracking-widest font-bold mb-4">
          <i class="fas fa-ranking-star ml-1 text-amber-500"></i>أعلى الحسابات صرفاً
        </p>
        <div class="space-y-2" id="topAccList">
          <?php $i=0; foreach($top_accounts as $an=>$av):
            $pct=array_sum($expense_accounts)>0?round($av/array_sum($expense_accounts)*100,1):0;
            $col=$palette[$i%count($palette)];
            $short=mb_strlen($an)>32?mb_substr($an,0,32).'…':$an;$i++;
          ?>
          <div class="erow p-3">
            <div class="flex items-center justify-between mb-1.5">
              <div class="flex items-center gap-2 min-w-0">
                <div class="w-3 h-3 rounded-sm flex-shrink-0" style="background:<?=$col?>"></div>
                <p class="text-xs text-slate-600 font-bold truncate" title="<?=htmlspecialchars($an)?>"><?=htmlspecialchars($short)?></p>
              </div>
              <p class="text-xs font-extrabold text-slate-800 flex-shrink-0 mr-2" dir="ltr"><?=fmtN($av)?></p>
            </div>
            <div style="background:#fee2e2;border-radius:999px;height:4px">
              <div style="width:<?=$pct?>%;height:4px;border-radius:999px;background:<?=$col?>"></div>
            </div>
          </div>
          <?php endforeach;?>
        </div>
      </div>
    </div>
  </div>

</div>

<script>
const EXP_RAW           = <?=json_encode((object)$expense_data,JSON_UNESCAPED_UNICODE)?>;
const TOP_ACCS          = <?=json_encode($top_acc_names,JSON_UNESCAPED_UNICODE)?>;
const PALETTE           = <?=json_encode($palette)?>;
const ALL_MONTHS_LABELS = <?=json_encode((object)$months_labels,JSON_UNESCAPED_UNICODE)?>;
const ALL_MONTHS_KEYS   = <?=json_encode(array_keys($months_labels),JSON_UNESCAPED_UNICODE)?>;

Chart.defaults.color='#64748b';
Chart.defaults.borderColor='rgba(127,29,29,.07)';

const ctx=document.getElementById('expChart').getContext('2d');
let myChart;

function buildChart(keys){
    const labels=keys.map(k=>ALL_MONTHS_LABELS[k]??k);
    const datasets=TOP_ACCS.map((acc,i)=>({
        label:acc.length>35?acc.slice(0,35)+'…':acc,
        data:keys.map(k=>EXP_RAW[k]?.[acc]??0),
        backgroundColor:PALETTE[i%PALETTE.length]+'22',
        borderColor:PALETTE[i%PALETTE.length],
        borderWidth:2,tension:0.4,fill:false,
        pointRadius:4,pointHoverRadius:7,
        pointBackgroundColor:PALETTE[i%PALETTE.length],
    }));
    if(myChart)myChart.destroy();
    myChart=new Chart(ctx,{
        type:'line',data:{labels,datasets},
        options:{
            responsive:true,maintainAspectRatio:false,
            interaction:{mode:'index',intersect:false},
            plugins:{
                legend:{position:'bottom',labels:{
                    font:{family:'Tajawal',size:11},color:'#475569',
                    boxWidth:12,boxHeight:12,padding:14,usePointStyle:true
                }},
                tooltip:{
                    backgroundColor:'rgba(255,255,255,.98)',
                    titleColor:'#1e293b',bodyColor:'#334155',
                    borderColor:'rgba(127,29,29,.15)',borderWidth:1,
                    padding:12,
                    titleFont:{family:'Tajawal',size:13,weight:'800'},
                    bodyFont:{family:'Tajawal',size:12},
                    callbacks:{label:c=>{
                        const v=parseFloat(c.raw).toLocaleString('en-MY',{minimumFractionDigits:2,maximumFractionDigits:2});
                        return `  ${c.dataset.label}: ${v} MYR`;
                    }}
                }
            },
            scales:{
                x:{grid:{color:'rgba(127,29,29,.05)'},ticks:{font:{family:'Tajawal',size:11}}},
                y:{grid:{color:'rgba(127,29,29,.05)'},ticks:{font:{family:'Tajawal',size:11},
                    callback:v=>{
                        if(v>=1000000)return(v/1000000).toFixed(1)+'M';
                        if(v>=1000)return(v/1000).toFixed(0)+'K';
                        return v;
                    }
                }}
            }
        }
    });
}

function applyFilter(){
    const from=document.getElementById('filterFrom').value;
    const to  =document.getElementById('filterTo').value;
    const fk  =ALL_MONTHS_KEYS.filter(k=>k>=from&&k<=to);
    if(!fk.length){alert('لا توجد بيانات في هذه الفترة');return;}
    buildChart(fk);updateTopAccList(fk);
    const fl=ALL_MONTHS_LABELS[fk[0]]??fk[0];
    const tl=ALL_MONTHS_LABELS[fk[fk.length-1]]??fk[fk.length-1];
    document.getElementById('periodLabel').textContent=`${fl} — ${tl}`;
}

function resetFilter(){
    document.getElementById('filterFrom').value=ALL_MONTHS_KEYS[0]??'';
    document.getElementById('filterTo').value  =ALL_MONTHS_KEYS[ALL_MONTHS_KEYS.length-1]??'';
    applyFilter();
}

function updateTopAccList(fk){
    const totals={};
    TOP_ACCS.forEach(a=>{totals[a]=fk.reduce((s,k)=>s+(EXP_RAW[k]?.[a]??0),0);});
    const grand=Object.values(totals).reduce((a,b)=>a+b,0);
    const c=document.getElementById('topAccList');c.innerHTML='';
    TOP_ACCS.map((a,i)=>({a,i,v:totals[a]})).sort((x,y)=>y.v-x.v).forEach(({a,i,v})=>{
        const pct=grand>0?(v/grand*100).toFixed(1):0;
        const sh=a.length>32?a.slice(0,32)+'…':a;
        const col=PALETTE[i%PALETTE.length];
        const fv=v.toLocaleString('en-MY',{minimumFractionDigits:2,maximumFractionDigits:2});
        c.innerHTML+=`<div class="erow p-3">
          <div class="flex items-center justify-between mb-1.5">
            <div class="flex items-center gap-2 min-w-0">
              <div class="w-3 h-3 rounded-sm flex-shrink-0" style="background:${col}"></div>
              <p class="text-xs text-slate-600 font-bold truncate" title="${a}">${sh}</p>
            </div>
            <p class="text-xs font-extrabold text-slate-800 flex-shrink-0 mr-2" dir="ltr">${fv}</p>
          </div>
          <div style="background:#fee2e2;border-radius:999px;height:4px">
            <div style="width:${pct}%;height:4px;border-radius:999px;background:${col}"></div>
          </div>
        </div>`;
    });
}

(function init(){
    const from=document.getElementById('filterFrom').value;
    const to  =document.getElementById('filterTo').value;
    const fk  =ALL_MONTHS_KEYS.filter(k=>k>=from&&k<=to);
    const keys=fk.length?fk:ALL_MONTHS_KEYS;
    buildChart(keys);updateTopAccList(keys);
    if(keys.length){
        const fl=ALL_MONTHS_LABELS[keys[0]]??keys[0];
        const tl=ALL_MONTHS_LABELS[keys[keys.length-1]]??keys[keys.length-1];
        document.getElementById('periodLabel').textContent=`${fl} — ${tl}`;
    }
})();
</script>
</body>
</html>