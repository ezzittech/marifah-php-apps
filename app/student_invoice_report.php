<?php
$odoo_url      = 'https://portal.marifah.edu.my';
$db            = 'Marifahdb';$username      = 'ezzittech@gmail.com';
$password      = 'Abulez@2526';$BASE_CURRENCY = 'MYR';

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
function odoo_call($url,$sid,$model,$method,$args,$kwargs=[]){$ch=curl_init($url.'/web/dataset/call_kw');$payload=json_encode(['jsonrpc'=>'2.0','method'=>'call','params'=>['model'=>$model,'method'=>$method,'args'=>$args,'kwargs'=>$kwargs]]);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Cookie: session_id='.$sid],
        CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_TIMEOUT=>60]);
    $r=curl_exec($ch);curl_close($ch);$d=json_decode($r,true);return$d['result']??null;
}

$session_id=$error='';$final_data=$classes_map=[];$grand_by_cur=[];   // ['MYR'=>[total,paid,residual], 'IDR'=>..., 'USD'=>...]
$grand_invoices=$grand_students=0;
$fx_rates = ['MYR'=>1.0]; // مصفوفة لتخزين أسعار الصرف

$session_id=authenticate($odoo_url,$db,$username,$password);
if(!$session_id){$error='فشل الاتصال بأودو.';}
else{
    // جلب أسعار الصرف من أودو لمعادلة الإجمالي الكلي
    $cur_data = odoo_call($odoo_url,$session_id, 'res.currency', 'search_read',
        [[['active','=',true]]],
        ['fields'=>['name','rate']]);
    if (is_array($cur_data)) {
        foreach ($cur_data as$c) {
            if ((float)$c['rate'] > 0)$fx_rates[$c['name']] = (float)$c['rate'];
        }
    }

    $invoices=odoo_call($odoo_url,$session_id,'account.move','search_read',
        [[['move_type','=','out_invoice'],['state','=','posted']]],
        ['fields'=>['partner_id','currency_id','amount_total','amount_residual']]);
    if(!is_array($invoices)){$error='فشل جلب الفواتير.';}
    else{
        $partner_totals=[];$partner_names=[];
        foreach($invoices as$inv){
            if(empty($inv['partner_id']))continue;
            $pid  =is_array($inv['partner_id'])?$inv['partner_id'][0]:$inv['partner_id'];$pname=is_array($inv['partner_id'])?$inv['partner_id'][1]:'غير معروف';
            $cur  ='MYR';
            if(!empty($inv['currency_id']))$cur=is_array($inv['currency_id'])?$inv['currency_id'][1]:$inv['currency_id'];$partner_names[$pid]=$pname;
            $partner_totals[$pid][$cur]['invoice_count']  =($partner_totals[$pid][$cur]['invoice_count']??0)+1;
            $partner_totals[$pid][$cur]['amount_total']   =($partner_totals[$pid][$cur]['amount_total']??0)+(float)$inv['amount_total'];$partner_totals[$pid][$cur]['amount_residual']=($partner_totals[$pid][$cur]['amount_residual']??0)+(float)$inv['amount_residual'];
        }
        $partner_ids=array_keys($partner_totals);$partners_data=odoo_call($odoo_url,$session_id,'res.partner','search_read',
            [[['id','in',$partner_ids]]],['fields'=>['id','x_student_type','x_class_id']]);$pmap=[];if(is_array($partners_data))foreach($partners_data as $p)$pmap[$p['id']]=$p;

        foreach($partner_totals as$pid=>$currencies){$p=$pmap[$pid]??[];
            $type_raw=$p['x_student_type']??false;
            $type_val=($type_raw&&$type_raw!==false)?(string)$type_raw:'';
            $class_raw=$p['x_class_id']??false;
            if(is_array($class_raw)&&count($class_raw)===2){$class_id=$class_raw[0];$class_name=$class_raw[1];$classes_map[$class_id]=$class_name;
            }else{$class_id=0;$class_name='';}

            $cur_rows=[];$tot_inv=0;
            foreach($currencies as$cur=>$amounts){$paid=$amounts['amount_total']-$amounts['amount_residual'];
                $cur_rows[]=['currency'=>$cur,'is_base'=>($cur===$BASE_CURRENCY),
                    'invoice_count'=>$amounts['invoice_count'],
                    'amount_total'=>$amounts['amount_total'],
                    'amount_paid'=>$paid,
                    'amount_residual'=>$amounts['amount_residual']];
                $tot_inv+=$amounts['invoice_count'];
                // إجماليات كل عملة على حدة
                if(!isset($grand_by_cur[$cur]))$grand_by_cur[$cur]=['total'=>0,'paid'=>0,'residual'=>0,'invoices'=>0];
                $grand_by_cur[$cur]['total']    +=$amounts['amount_total'];$grand_by_cur[$cur]['paid']     +=$paid;
                $grand_by_cur[$cur]['residual'] +=$amounts['amount_residual'];$grand_by_cur[$cur]['invoices'] +=$amounts['invoice_count'];
            }
            $grand_invoices+=$tot_inv;
            $sort_total=array_sum(array_column(array_filter($cur_rows,fn($r)=>$r['currency']===$BASE_CURRENCY),'amount_total'));$sort_res  =array_sum(array_column(array_filter($cur_rows,fn($r)=>$r['currency']===$BASE_CURRENCY),'amount_residual'));
            $final_data[]=['partner_id'=>$pid,'name'=>$partner_names[$pid],
                'student_type'=>$type_val,'class_id'=>$class_id,'class_name'=>$class_name,
                'invoice_count'=>$tot_inv,'currencies'=>$cur_rows,
                'sort_total'=>$sort_total,'sort_residual'=>$sort_res];
        }
        $grand_students=count($final_data);
        usort($final_data,fn($a,$b)=>strcmp($a['name'],$b['name']));
        ksort($classes_map);
        // ترتيب العملات: MYR أولاً ثم الباقي
        uksort($grand_by_cur,fn($a,$b)=>($a===$BASE_CURRENCY?-1:($b===$BASE_CURRENCY?1:strcmp($a,$b))));
    }
}

// مساعدة: حساب الإجمالي الكلي الموحد (بما يعادل الرنجت MYR) بناء على أسعار الصرف من أودو
$grand_total_myr_all = 0;
$grand_paid_myr_all = 0;
$grand_residual_myr_all = 0;

foreach($grand_by_cur as$cur => $amounts){$rate = $fx_rates[$cur] ?? 1.0;
    // القسمة على الـ rate بناءً على تركيبة أودو للعملات 
    $grand_total_myr_all    += ($rate > 0) ? ($amounts['total'] /$rate) : 0;
    $grand_paid_myr_all     += ($rate > 0) ? ($amounts['paid'] /$rate) : 0;
    $grand_residual_myr_all += ($rate > 0) ? ($amounts['residual'] /$rate) : 0;
}
$grand_rate_all =$grand_total_myr_all > 0 ? round($grand_paid_myr_all / $grand_total_myr_all * 100, 1) : 0;

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>تقرير فواتير الطلاب — Marifah</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body{font-family:'Tajawal',sans-serif;background:#f0f4f8;}
::-webkit-scrollbar{width:5px;height:5px}::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:4px}
.stat-card{transition:transform .15s,box-shadow .15s}.stat-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.09)}
thead th{position:sticky;top:0;z-index:10}
.badge-idr{background:#fef3c7;color:#92400e;border:1px solid #fcd34d}
.badge-myr{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
.badge-usd{background:#f0fdf4;color:#166534;border:1px solid #86efac}
.badge-cur{background:#f3f4f6;color:#374151;border:1px solid #e5e7eb}
.row-secondary-idr{background:#fffbeb;border-right:3px solid #f59e0b}
.row-secondary-idr:hover{background:#fef3c7!important}
.row-secondary-usd{background:#f0fdf4;border-right:3px solid #22c55e}
.row-secondary-usd:hover{background:#dcfce7!important}
@media print{.no-print{display:none!important}body{background:white}}
</style>
</head>
<body class="min-h-screen p-4 md:p-6 antialiased">
<div class="max-w-screen-xl mx-auto space-y-5">

<!-- ══ Header ══ -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 no-print">
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-3">
      <div class="w-11 h-11 rounded-xl bg-indigo-600 flex items-center justify-center shadow-md flex-shrink-0">
        <i class="fas fa-file-invoice-dollar text-white text-lg"></i>
      </div>
      <div>
        <h1 class="text-xl font-extrabold text-gray-900">تقرير فواتير الطلاب الشامل</h1>
        <p class="text-gray-400 text-xs mt-0.5">Marifah International School — <?=date('d/m/Y H:i')?></p>
      </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <?php if($error):?>
        <span class="bg-red-50 text-red-600 border border-red-100 px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5">
          <i class="fas fa-circle-xmark"></i> <?=htmlspecialchars($error)?>
        </span>
      <?php else:?>
        <span class="bg-emerald-50 text-emerald-700 border border-emerald-100 px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5">
          <i class="fas fa-circle-check"></i> متصل بنجاح
        </span>
      <?php endif;?>
      <button onclick="window.print()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl text-sm font-bold transition-colors flex items-center gap-2">
        <i class="fas fa-print"></i> طباعة
      </button>
      <a href="index.html" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-sm font-bold transition-colors flex items-center gap-2 shadow-sm">
        <i class="fas fa-home"></i> الرئيسية
      </a>
    </div>
  </div>
</div>

<!-- ══ Stats — بطاقة لكل عملة ══ -->
<?php if(!$error):?>
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 no-print">

  <!-- طلاب -->
  <div class="stat-card bg-white rounded-2xl border border-gray-100 p-4 shadow-sm col-span-1">
    <div class="flex items-center gap-2 mb-2">
      <div class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center">
        <i class="fas fa-users text-indigo-600 text-sm"></i></div>
      <span class="text-xs font-semibold text-gray-500">الطلاب</span>
    </div>
    <p class="text-2xl font-extrabold text-gray-900"><?=number_format($grand_students)?></p>
    <p class="text-xs text-gray-400 mt-1"><?=number_format($grand_invoices)?> فاتورة</p>
  </div>

  <?php
  $cur_colors=['MYR'=>['bg'=>'bg-blue-600','light'=>'bg-blue-50','text'=>'text-blue-700','bar'=>'bg-blue-400'],
               'IDR'=>['bg'=>'bg-amber-500','light'=>'bg-amber-50','text'=>'text-amber-700','bar'=>'bg-amber-400'],
               'USD'=>['bg'=>'bg-emerald-600','light'=>'bg-emerald-50','text'=>'text-emerald-700','bar'=>'bg-emerald-400']];
  foreach($grand_by_cur as$cur=>$amounts):$cc=$cur_colors[$cur]??['bg'=>'bg-gray-600','light'=>'bg-gray-50','text'=>'text-gray-700','bar'=>'bg-gray-400'];
    $rate=$amounts['total']>0?round($amounts['paid']/$amounts['total']*100,1):0;
    $is_idr=($cur==='IDR');$fmt_n=fn($v)=>$is_idr?number_format($v,0):number_format($v,2);
  ?>
  <div class="stat-card bg-white rounded-2xl border border-gray-100 p-4 shadow-sm">
    <div class="flex items-center justify-between mb-2">
      <div class="w-8 h-8 rounded-lg <?=$cc['light']?> flex items-center justify-center">
        <i class="fas fa-coins <?=$cc['text']?> text-sm"></i></div>
      <span class="text-xs font-extrabold px-2 py-0.5 rounded <?=$cc['light']?> <?=$cc['text']?>"><?=$cur?></span>
    </div>
    <p class="text-xs font-semibold text-gray-400 mb-0.5">المفوتر</p>
    <p class="text-base font-extrabold <?=$cc['text']?>" dir="ltr"><?=$fmt_n($amounts['total'])?></p>
    <div class="mt-2 h-1.5 rounded-full bg-gray-100">
      <div class="h-1.5 rounded-full <?=$cc['bar']?>" style="width:<?=$rate?>%"></div>
    </div>
    <p class="text-xs text-gray-400 mt-1">تحصيل <b><?=$rate?>%</b> | متبقي: <span class="text-red-500 font-bold"><?=$fmt_n($amounts['residual'])?></span></p>
  </div>
  <?php endforeach;?>

  <!-- بطاقة الإجمالي الكلي المعادل للعملة الأساسية -->
  <div class="stat-card bg-indigo-600 rounded-2xl border border-indigo-700 p-4 shadow-md col-span-1 text-white">
    <div class="flex items-center justify-between mb-2">
      <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center">
        <i class="fas fa-globe text-white text-sm"></i>
      </div>
      <span class="text-xs font-extrabold px-2 py-0.5 rounded bg-white/20 text-white">إجمالي المعادل</span>
    </div>
    <p class="text-xs font-semibold text-indigo-200 mb-0.5">المفوتر (MYR)</p>
    <p class="text-base font-extrabold text-white" dir="ltr"><?=number_format($grand_total_myr_all, 2)?></p>
    <div class="mt-2 h-1.5 rounded-full bg-indigo-900/40">
      <div class="h-1.5 rounded-full bg-emerald-400" style="width:<?=$grand_rate_all?>%"></div>
    </div>
    <p class="text-xs text-indigo-200 mt-1">تحصيل <b><?=$grand_rate_all?>%</b> | متبقي: <span class="text-red-300 font-bold"><?=number_format($grand_residual_myr_all, 2)?></span></p>
  </div>

</div>

<!-- تنبيه العملات -->
<div class="bg-gradient-to-l from-amber-50 to-blue-50 border border-amber-200 rounded-xl px-4 py-3 flex items-start gap-3 text-sm no-print">
  <i class="fas fa-circle-info text-amber-500 mt-0.5 flex-shrink-0"></i>
  <p class="text-amber-900">
    <b>ملاحظة العملات:</b>
    الإجماليات في التذييل مقسّمة حسب كل عملة بشكل مستقل.
    الصفوف ذات الخلفية <span class="bg-amber-100 px-1 rounded font-bold text-amber-700">الصفراء = IDR</span>
    والخلفية <span class="bg-emerald-100 px-1 rounded font-bold text-emerald-700">الخضراء = USD</span>.
  </p>
</div>
<?php endif;?>

<!-- ══ Filters ══ -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 no-print">
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div>
      <label class="block text-xs font-bold text-gray-500 mb-1.5">بحث باسم الطالب</label>
      <div class="relative">
        <input type="text" id="searchInput" placeholder="اكتب اسم الطالب..."
          class="w-full border border-gray-200 rounded-xl py-2.5 pr-9 pl-3 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
        <i class="fas fa-magnifying-glass absolute top-3 right-3 text-gray-400 text-sm"></i>
      </div>
    </div>
    <div>
      <label class="block text-xs font-bold text-gray-500 mb-1.5">نوع الدراسة</label>
      <div class="relative">
        <select id="typeFilter" class="w-full border border-gray-200 rounded-xl py-2.5 px-3 pl-8 text-sm appearance-none focus:ring-2 focus:ring-indigo-400 outline-none bg-white">
          <option value="">الكل</option>
          <option value="physical">On-site</option>
          <option value="online">Online</option>
          <option value="both">Both</option>
          <option value="__none__">غير محدد</option>
        </select>
        <i class="fas fa-chevron-down absolute top-3.5 left-3 text-gray-400 text-xs pointer-events-none"></i>
      </div>
    </div>
    <div>
      <label class="block text-xs font-bold text-gray-500 mb-1.5">الصف</label>
      <div class="relative">
        <select id="classFilter" class="w-full border border-gray-200 rounded-xl py-2.5 px-3 pl-8 text-sm appearance-none focus:ring-2 focus:ring-indigo-400 outline-none bg-white">
          <option value="">الكل</option>
          <?php foreach($classes_map as $cid=>$cn):?>
          <option value="<?=$cid?>"><?=htmlspecialchars($cn)?></option>
          <?php endforeach;?>
          <option value="0">غير محدد</option>
        </select>
        <i class="fas fa-chevron-down absolute top-3.5 left-3 text-gray-400 text-xs pointer-events-none"></i>
      </div>
    </div>
  </div>
  <div class="mt-3 flex items-center justify-between">
    <p class="text-xs text-gray-400">
      عرض <span id="visibleCount" class="font-bold text-gray-700">0</span>
      من <span class="font-bold text-gray-700"><?=count($final_data)?></span> طالب
      (<span id="visibleRows" class="font-bold text-gray-700">0</span> صف)
    </p>
    <button onclick="resetFilters()" class="text-xs text-indigo-600 hover:text-indigo-800 font-bold flex items-center gap-1">
      <i class="fas fa-rotate-right"></i> إعادة ضبط
    </button>
  </div>
</div>

<!-- ══ Table ══ -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
  <div class="overflow-x-auto" style="max-height:68vh;overflow-y:auto;">
    <table class="w-full text-right text-sm border-collapse">
      <thead>
        <tr class="bg-gray-800 text-white">
          <th class="py-3.5 px-4 bg-gray-800 sticky top-0">#</th>
          <th class="py-3.5 px-5 bg-gray-800 sticky top-0 cursor-pointer hover:bg-gray-700" onclick="sortTable('name')">اسم الطالب / العميل <i class="fas fa-sort text-gray-400 text-xs"></i></th>
          <th class="py-3.5 px-4 bg-gray-800 sticky top-0 text-center cursor-pointer hover:bg-gray-700" onclick="sortTable('student_type')">نوع الدراسة <i class="fas fa-sort text-gray-400 text-xs"></i></th>
          <th class="py-3.5 px-4 bg-gray-800 sticky top-0 text-center cursor-pointer hover:bg-gray-700" onclick="sortTable('class_name')">الصف <i class="fas fa-sort text-gray-400 text-xs"></i></th>
          <th class="py-3.5 px-4 bg-gray-800 sticky top-0 text-center">العملة</th>
          <th class="py-3.5 px-5 bg-gray-800 sticky top-0 text-left cursor-pointer hover:bg-gray-700" onclick="sortTable('sort_total')">إجمالي المفوتر <i class="fas fa-sort text-gray-400 text-xs"></i></th>
          <th class="py-3.5 px-5 bg-gray-800 sticky top-0 text-left">المسدد</th>
          <th class="py-3.5 px-5 bg-gray-800 sticky top-0 text-left cursor-pointer hover:bg-gray-700" onclick="sortTable('sort_residual')">المتبقي <i class="fas fa-sort text-gray-400 text-xs"></i></th>
          <th class="py-3.5 px-4 bg-gray-800 sticky top-0 text-center cursor-pointer hover:bg-gray-700" onclick="sortTable('invoice_count')">فواتير <i class="fas fa-sort text-gray-400 text-xs"></i></th>
        </tr>
      </thead>
      <tbody id="reportBody" class="divide-y divide-gray-100"></tbody>
      <tfoot id="reportFoot" class="border-t-2 border-gray-300 bg-gray-50">
        <!-- يتم ملؤه ديناميكياً حسب العملات الظاهرة -->
      </tfoot>
    </table>
  </div>
</div>

</div>
<script>
const RAW  = <?=json_encode($final_data,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE)?>;
const BASE = 'MYR';

const TYPE_LABELS={physical:'On-site',online:'Online',both:'Both','':'غير محدد'};
const TYPE_COLORS={physical:'bg-sky-100 text-sky-700',online:'bg-violet-100 text-violet-700',both:'bg-amber-100 text-amber-700','':'bg-gray-100 text-gray-500'};

// تنسيق حسب العملة
function fmt(v,cur){
  const n=parseFloat(v);
  if(cur==='IDR')return n.toLocaleString('id-ID',{minimumFractionDigits:0,maximumFractionDigits:0});
  if(cur==='USD')return n.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
  return n.toLocaleString('en-MY',{minimumFractionDigits:2,maximumFractionDigits:2});
}
function fmtMYR(v){return parseFloat(v).toLocaleString('en-MY',{minimumFractionDigits:2,maximumFractionDigits:2});}

function curBadge(cur){
  const cls=cur==='MYR'?'badge-myr':cur==='IDR'?'badge-idr':cur==='USD'?'badge-usd':'badge-cur';
  return `<span class="inline-block px-2 py-0.5 rounded text-xs font-extrabold ${cls}">${cur}</span>`;
}
function rowClass(cur){
  if(cur==='IDR')return 'row-secondary-idr transition-colors';
  if(cur==='USD')return 'row-secondary-usd transition-colors';
  return 'hover:bg-indigo-50/40 transition-colors';
}
function barColor(cur){
  if(cur==='IDR')return 'bg-amber-400';
  if(cur==='USD')return 'bg-emerald-400';
  return 'bg-indigo-400';
}

let sortKey='name',sortDir=1;

function render(){
  const search  =document.getElementById('searchInput').value.trim().toLowerCase();
  const typeVal =document.getElementById('typeFilter').value;
  const classVal=document.getElementById('classFilter').value;

  let students=RAW.filter(r=>{
    if(search&&!r.name.toLowerCase().includes(search))return false;
    if(typeVal==='__none__'&&r.student_type!=='')return false;
    if(typeVal!==''&&typeVal!=='__none__'&&r.student_type!==typeVal)return false;
    if(classVal!==''&&String(r.class_id)!==classVal)return false;
    return true;
  });

  students.sort((a,b)=>{
    const av=a[sortKey]??'',bv=b[sortKey]??'';
    if(typeof av==='number')return(av-bv)*sortDir;
    return String(av).localeCompare(String(bv),'ar')*sortDir;
  });

  const tbody=document.getElementById('reportBody');
  tbody.innerHTML='';

  // تجميع الإجماليات حسب كل عملة
  const sumByCur={};
  let sumInvTotal=0,rowCount=0,idx=0;

  if(!students.length){
    tbody.innerHTML=`<tr><td colspan="9" class="py-16 text-center text-gray-400">
      <i class="fas fa-magnifying-glass text-3xl mb-3 block opacity-30"></i>لا توجد سجلات مطابقة</td></tr>`;
  }else{
    students.forEach(r=>{
      idx++;
      const typeLabel=TYPE_LABELS[r.student_type]??r.student_type??'غير محدد';
      const typeColor=TYPE_COLORS[r.student_type]??TYPE_COLORS[''];
      const className=r.class_name||'غير محدد';
      const curs=r.currencies;
      const rowspan=curs.length;
      sumInvTotal+=r.invoice_count;

      curs.forEach((c,ci)=>{
        const dueClass=c.amount_residual>0?'text-red-600':'text-emerald-600';
        const pct=c.amount_total>0?Math.round(c.amount_paid/c.amount_total*100):0;

        // تراكم الإجماليات
        if(!sumByCur[c.currency])sumByCur[c.currency]={total:0,paid:0,residual:0,invoices:0};
        sumByCur[c.currency].total    +=c.amount_total;
        sumByCur[c.currency].paid     +=c.amount_paid;
        sumByCur[c.currency].residual +=c.amount_residual;
        sumByCur[c.currency].invoices +=c.invoice_count;

        const tr=document.createElement('tr');
        tr.className=rowClass(c.currency);

        let firstCols='';
        if(ci===0){
          firstCols=`
            <td class="py-3 px-4 text-gray-400 text-xs font-semibold align-top" rowspan="${rowspan}">${idx}</td>
            <td class="py-3 px-5 align-top" rowspan="${rowspan}">
              <p class="font-bold text-gray-800 whitespace-nowrap">${r.name}</p>
              <p class="text-xs text-gray-400 mt-0.5">${r.invoice_count} فاتورة</p>
            </td>
            <td class="py-3 px-4 text-center align-top" rowspan="${rowspan}">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold ${typeColor}">${typeLabel}</span>
            </td>
            <td class="py-3 px-4 text-center align-top" rowspan="${rowspan}">
              <span class="inline-flex px-2.5 py-0.5 rounded-md border border-gray-200 text-xs font-bold text-gray-600 bg-gray-50">${className}</span>
            </td>`;
        }

        tr.innerHTML=`${firstCols}
          <td class="py-3 px-4 text-center">${curBadge(c.currency)}</td>
          <td class="py-3 px-5 font-bold text-gray-800 whitespace-nowrap" dir="ltr">
            ${fmt(c.amount_total,c.currency)} <span class="text-gray-400 text-xs font-normal">${c.currency}</span>
          </td>
          <td class="py-3 px-5 whitespace-nowrap" dir="ltr">
            <span class="font-bold text-emerald-600">${fmt(c.amount_paid,c.currency)}</span>
            <span class="text-gray-400 text-xs font-normal"> ${c.currency}</span>
            <div class="mt-1 h-1 rounded-full bg-gray-100 w-14 inline-block align-middle">
              <div class="h-1 rounded-full ${barColor(c.currency)}" style="width:${pct}%"></div>
            </div>
          </td>
          <td class="py-3 px-5 whitespace-nowrap" dir="ltr">
            <span class="font-bold ${dueClass}">${fmt(c.amount_residual,c.currency)}</span>
            <span class="text-gray-400 text-xs font-normal"> ${c.currency}</span>
          </td>
          <td class="py-3 px-4 text-center font-bold text-indigo-600">${c.invoice_count}</td>`;
        tbody.appendChild(tr);
        rowCount++;
      });
    });
  }

  // ══ بناء التذييل بعملات متعددة ══
  const tfoot=document.getElementById('reportFoot');
  tfoot.innerHTML='';
  const currencies=Object.keys(sumByCur).sort(k=>k===BASE?-1:1);
  currencies.forEach((cur,i)=>{
    const s=sumByCur[cur];
    const isBase=(cur===BASE);
    const bg=isBase?'bg-indigo-50 border-t-2 border-indigo-300':cur==='IDR'?'bg-amber-50 border-t border-amber-200':cur==='USD'?'bg-emerald-50 border-t border-emerald-200':'bg-gray-50 border-t border-gray-200';
    const tc=isBase?'text-indigo-900':cur==='IDR'?'text-amber-900':cur==='USD'?'text-emerald-900':'text-gray-800';
    const tv=isBase?'text-blue-700':cur==='IDR'?'text-amber-700':cur==='USD'?'text-emerald-700':'text-gray-700';
    const tr=document.createElement('tr');
    tr.className=bg;
    tr.innerHTML=`
      <td colspan="4" class="py-3 px-5 font-extrabold ${tc} text-right">
        <i class="fas fa-sigma ml-1 opacity-50"></i>
        الإجماليات (${cur}):
      </td>
      <td class="py-3 px-4 text-center">${curBadge(cur)}</td>
      <td class="py-3 px-5 font-extrabold ${tv} text-left" dir="ltr">${fmt(s.total,cur)} <span class="text-xs font-normal opacity-60">${cur}</span></td>
      <td class="py-3 px-5 font-extrabold text-emerald-600 text-left" dir="ltr">${fmt(s.paid,cur)} <span class="text-xs font-normal opacity-60">${cur}</span></td>
      <td class="py-3 px-5 font-extrabold text-red-600 text-left" dir="ltr">${fmt(s.residual,cur)} <span class="text-xs font-normal opacity-60">${cur}</span></td>
      <td class="py-3 px-4 font-extrabold ${tc} text-center">${s.invoices}</td>`;
    tfoot.appendChild(tr);
  });

  document.getElementById('visibleCount').textContent=students.length;
  document.getElementById('visibleRows').textContent=rowCount;
}

function sortTable(key){sortDir=(sortKey===key)?sortDir*-1:1;sortKey=key;render();}
function resetFilters(){
  document.getElementById('searchInput').value='';
  document.getElementById('typeFilter').value='';
  document.getElementById('classFilter').value='';
  render();
}
document.getElementById('searchInput').addEventListener('input',render);
document.getElementById('typeFilter').addEventListener('change',render);
document.getElementById('classFilter').addEventListener('change',render);
render();
</script>
</body>
</html>