<?php
$odoo_url = 'https://portal.marifah.edu.my';
$db       = 'Marifahdb';$username = 'ezzittech@gmail.com';
$password = 'Abulez@2526';

function authenticate($url,$db,$login,$password){
    $ch=curl_init($url.'/web/session/authenticate');
    $payload=json_encode(['jsonrpc'=>'2.0','method'=>'call','params'=>['db'=>$db,'login'=>$login,'password'=>$password]]);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HEADER=>true,
        CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_TIMEOUT=>30]);
    $r=curl_exec($ch);$hs=curl_getinfo($ch,CURLINFO_HEADER_SIZE);$h=substr($r,0,$hs);curl_close($ch);
    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi',$h,$m);$c=[];
    foreach($m[1] as $i){parse_str($i,$x);$c=array_merge($c,$x);}
    return $c['session_id']??'';
}

function odoo_call($url,$sid,$model,$method,$args,$kwargs=[]){$ch=curl_init($url.'/web/dataset/call_kw');$payload=json_encode(['jsonrpc'=>'2.0','method'=>'call','params'=>['model'=>$model,'method'=>$method,'args'=>$args,'kwargs'=>$kwargs]]);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Cookie: session_id='.$sid],
        CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_TIMEOUT=>90]);
    $r=curl_exec($ch);curl_close($ch);$d=json_decode($r,true);return$d['result']??null;
}

$session_id=authenticate($odoo_url,$db,$username,$password);
$account_data = ['code'=>'120009', 'name'=>'Albara Cash', 'debit'=>0, 'credit'=>0, 'lines'=>[]];$error='';
$grand_debit=$grand_credit=0.0;

if(!$session_id){$error='فشل الاتصال بأودو.';
} else {
    // جلب حركات الحساب 120009 فقط
    $raw=odoo_call($odoo_url,$session_id,'account.move.line','search_read',[[
        ['parent_state','=','posted'],
        ['display_type','not in',['line_section','line_note']],
        ['account_id.code', '=', '120009'] // فلتر حساب البنك المطلوب فقط
    ]],[
        'fields'=>['date','move_id','account_id','partner_id','name','ref',
                   'debit','credit','balance','currency_id','amount_currency'],
        'order' =>'date asc, id asc',
        'limit' =>10000
    ]);

    if(!is_array($raw)){$error='فشل جلب البيانات من أودو.';
    } else {
        foreach($raw as$l){
            // تحديث اسم الحساب في حال كان مختلفاً قليلاً في أودو
            if(is_array($l['account_id'])){
                $acc_name_full =$l['account_id'][1];
                if(preg_match('/^(\d+)\s+(.+)$/', $acc_name_full,$m)){
                    $account_data['name'] =$m[2];
                }
            }
            $account_data['debit']  += (float)$l['debit'];
            $account_data['credit'] += (float)$l['credit'];
            $account_data['lines'][] =$l;
            
            $grand_debit  += (float)$l['debit'];
            $grand_credit += (float)$l['credit'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>كشف حساب البنك — Albara Cash</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<style>
body{font-family:'Tajawal',sans-serif;background:#f0f4f8;}
::-webkit-scrollbar{width:8px;height:8px}::-webkit-scrollbar-thumb{background:#94a3b8;border-radius:4px}
.stat-card{transition:transform .15s,box-shadow .15s}.stat-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.09)}

@media print{
  .no-print{display:none!important}
  #print-area{display:block!important}
  body{background:white}
  @page{size:A4 landscape;margin:1cm 1.5cm}
}
#print-area{display:none}
</style>
</head>
<body class="min-h-screen p-4 md:p-6 antialiased">
<div class="max-w-screen-xl mx-auto space-y-5 no-print">

<!-- ══ Header ══ -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-3">
      <div class="w-11 h-11 rounded-xl bg-slate-800 flex items-center justify-center shadow-md flex-shrink-0">
        <i class="fas fa-building-columns text-white text-lg"></i>
      </div>
      <div>
        <h1 class="text-xl font-extrabold text-gray-900">كشف حساب بنكي - <?=$account_data['name']?></h1>
        <p class="text-gray-400 text-xs mt-0.5">رقم الحساب: <?=$account_data['code']?> — <?=date('d/m/Y H:i')?></p>
      </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <?php if($error):?>
        <span class="bg-red-50 text-red-600 border border-red-100 px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5">
          <i class="fas fa-circle-xmark"></i> <?=htmlspecialchars($error)?>
        </span>
      <?php else:?>
        <span class="bg-emerald-50 text-emerald-700 border border-emerald-100 px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5">
          <i class="fas fa-circle-check"></i> متصل | <?=count($account_data['lines'])?> حركة
        </span>
      <?php endif;?>
      <button onclick="exportExcel()" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 px-3 py-2 rounded-xl text-xs font-bold transition-colors flex items-center gap-1.5">
        <i class="fas fa-file-excel"></i> تصدير Excel
      </button>
      <button onclick="printAccount()" class="bg-slate-800 hover:bg-slate-700 text-white px-3 py-2 rounded-xl text-xs font-bold transition-colors flex items-center gap-1.5">
        <i class="fas fa-print"></i> طباعة الكشف
      </button>
    </div>
  </div>
</div>

<!-- ══ Stats ══ -->
<?php if(!$error):$grand_balance = $grand_debit -$grand_credit; ?>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
  <div class="stat-card bg-white rounded-2xl border border-gray-100 p-4 shadow-sm">
    <div class="flex items-center gap-2 mb-2">
      <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center">
        <i class="fas fa-list-ol text-slate-600 text-sm"></i></div>
      <span class="text-xs font-semibold text-gray-500">إجمالي الحركات</span>
    </div>
    <p class="text-2xl font-extrabold text-gray-900"><?=number_format(count($account_data['lines']))?></p>
  </div>
  <div class="stat-card bg-white rounded-2xl border border-gray-100 p-4 shadow-sm">
    <div class="flex items-center gap-2 mb-2">
      <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center">
        <i class="fas fa-arrow-down text-emerald-600 text-sm"></i></div>
      <span class="text-xs font-semibold text-gray-500">إجمالي المدين (إيداع)</span>
    </div>
    <p class="text-lg font-extrabold text-emerald-600" dir="ltr"><?=number_format($grand_debit,2)?></p>
  </div>
  <div class="stat-card bg-white rounded-2xl border border-gray-100 p-4 shadow-sm">
    <div class="flex items-center gap-2 mb-2">
      <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center">
        <i class="fas fa-arrow-up text-red-600 text-sm"></i></div>
      <span class="text-xs font-semibold text-gray-500">إجمالي الدائن (سحب)</span>
    </div>
    <p class="text-lg font-extrabold text-red-600" dir="ltr"><?=number_format($grand_credit,2)?></p>
  </div>
  <div class="stat-card bg-white rounded-2xl border border-gray-100 p-4 shadow-sm">
    <div class="flex items-center gap-2 mb-2">
      <div class="w-8 h-8 rounded-lg <?=$grand_balance>=0?'bg-emerald-100':'bg-red-100'?> flex items-center justify-center">
        <i class="fas fa-wallet <?=$grand_balance>=0?'text-emerald-600':'text-red-600'?> text-sm"></i></div>
      <span class="text-xs font-semibold text-gray-500">الرصيد الحالي</span>
    </div>
    <p class="text-lg font-extrabold <?=$grand_balance>=0?'text-emerald-600':'text-red-600'?>" dir="ltr"><?=number_format($grand_balance,2)?></p>
  </div>
</div>
<?php endif;?>

<!-- ══ Filters ══ -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div>
      <label class="block text-xs font-bold text-gray-500 mb-1.5">بحث (شريك / قيد / وصف)</label>
      <div class="relative">
        <input type="text" id="searchInput" placeholder="اكتب للبحث الفوري..."
          class="w-full border border-gray-200 rounded-xl py-2.5 pr-9 pl-3 text-sm focus:ring-2 focus:ring-slate-400 outline-none">
        <i class="fas fa-magnifying-glass absolute top-3 right-3 text-gray-400 text-sm"></i>
      </div>
    </div>
    <div>
      <label class="block text-xs font-bold text-gray-500 mb-1.5">من تاريخ</label>
      <input type="date" id="dateFrom" class="w-full border border-gray-200 rounded-xl py-2.5 px-3 text-sm focus:ring-2 focus:ring-slate-400 outline-none">
    </div>
    <div>
      <label class="block text-xs font-bold text-gray-500 mb-1.5">إلى تاريخ</label>
      <input type="date" id="dateTo" class="w-full border border-gray-200 rounded-xl py-2.5 px-3 text-sm focus:ring-2 focus:ring-slate-400 outline-none">
    </div>
  </div>
  <div class="mt-3 flex items-center justify-between">
    <p class="text-xs text-gray-400">
      يتم عرض <span id="visibleCount" class="font-bold text-gray-700">—</span> حركة
    </p>
    <button onclick="resetFilters()" class="text-xs text-slate-600 hover:text-slate-900 font-bold flex items-center gap-1">
      <i class="fas fa-rotate-right"></i> إعادة ضبط
    </button>
  </div>
</div>

<!-- ══ Ledger Table ══ -->
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mt-5">
  <div class="overflow-x-auto pb-4">
    <table class="w-full text-sm border-collapse" id="ledgerTable">
      <thead>
        <tr class="bg-slate-800 text-white text-right">
          <th class="py-3.5 px-5 whitespace-nowrap font-bold">التاريخ</th>
          <th class="py-3.5 px-4 whitespace-nowrap font-bold">القيد</th>
          <th class="py-3.5 px-4 whitespace-nowrap font-bold">الشريك</th>
          <th class="py-3.5 px-4 whitespace-nowrap font-bold">الوصف</th>
          <th class="py-3.5 px-5 text-left whitespace-nowrap font-bold">مدين (إيداع)</th>
          <th class="py-3.5 px-5 text-left whitespace-nowrap font-bold">دائن (سحب)</th>
          <th class="py-3.5 px-5 text-left whitespace-nowrap font-bold">الرصيد التراكمي</th>
        </tr>
      </thead>
      <tbody id="glBody"></tbody>
      <tfoot class="bg-slate-50 border-t-2 border-slate-200">
        <tr>
          <td colspan="4" class="py-4 px-5 font-extrabold text-right text-slate-700">
            <i class="fas fa-sigma ml-1 opacity-60"></i> الإجمالي للعمليات المعروضة:
          </td>
          <td class="py-4 px-5 font-extrabold text-left text-emerald-600" id="footDebit"  dir="ltr">—</td>
          <td class="py-4 px-5 font-extrabold text-left text-red-600" id="footCredit" dir="ltr">—</td>
          <td class="py-4 px-5 font-extrabold text-left" id="footBal" dir="ltr">—</td>
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
         alt="Logo" style="width:100px;object-fit:contain;">
    <div style="text-align:right;font-family:'Tajawal',sans-serif;">
      <p style="font-size:16px;font-weight:800;color:#0f172a;margin:0;">Marifah International School</p>
      <p style="font-size:11px;color:#475569;margin:4px 0 0;line-height:1.6;">
        Level 3 South City Plaza, Persiaran Serdang Perdana,<br>Seksyen 1, 43300 Seri Kembangan, Selangor
      </p>
      <p style="font-size:11px;font-weight:600;color:#0f172a;margin:5px 0 0;">info@marifah.edu.my | +60 18-988 9302</p>
    </div>
  </div>
  <div style="text-align:center;margin-bottom:20px;font-family:'Tajawal',sans-serif;">
    <p style="font-size:9px;letter-spacing:3px;color:#64748b;text-transform:uppercase;margin:0 0 4px;">Bank Statement</p>
    <h1 id="print-title" style="font-size:20px;font-weight:800;color:#0f172a;margin:0;">كشف حساب - <?=$account_data['name']?> (<?=$account_data['code']?>)</h1>
    <p id="print-subtitle" style="font-size:11px;color:#64748b;margin:4px 0 0;"></p>
  </div>
  <div id="print-content" style="font-family:'Tajawal',sans-serif;font-size:11px;direction:rtl;"></div>
  <div style="margin-top:30px;border-top:1px solid #cbd5e1;padding-top:12px;display:flex;justify-content:space-between;font-family:'Tajawal',sans-serif;font-size:10px;color:#64748b;">
    <p style="margin:0;">طُبع بتاريخ: <span id="print-date2"></span></p>
    <p style="margin:0;">marifah.edu.my</p>
  </div>
</div>

<script>
const ACCOUNT = <?=json_encode($account_data, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_UNESCAPED_UNICODE)?>;
const fmt = v => parseFloat(v||0).toLocaleString('en-MY', {minimumFractionDigits:2, maximumFractionDigits:2});

let filteredLines = [];

function render(){
  const searchRaw = document.getElementById('searchInput').value.trim();
  const search    = searchRaw.toLowerCase();
  const dateFrom  = document.getElementById('dateFrom').value;
  const dateTo    = document.getElementById('dateTo').value;

  const tbody = document.getElementById('glBody');
  tbody.innerHTML = '';

  let sumD = 0, sumC = 0, running = 0;
  let htmlStr = '';
  filteredLines = [];

  ACCOUNT.lines.forEach((l, i) => {
    // حساب الرصيد التراكمي الشامل لجميع الحركات منذ البداية وحتى هذه الحركة
    running += (l.debit || 0) - (l.credit || 0);

    // تطبيق فلاتر البحث والتاريخ
    const isAfterFrom = !dateFrom || l.date >= dateFrom;
    const isBeforeTo  = !dateTo || l.date <= dateTo;
    
    let matchSearch = true;
    if(search){
      const p = l.partner_id && l.partner_id[1] ? String(l.partner_id[1]).toLowerCase() : '';
      const n = l.name ? String(l.name).toLowerCase() : '';
      const m = l.move_id && l.move_id[1] ? String(l.move_id[1]).toLowerCase() : '';
      matchSearch = p.includes(search) || n.includes(search) || m.includes(search);
    }

    if(isAfterFrom && isBeforeTo && matchSearch){
      filteredLines.push({...l, current_running: running}); // تخزين الرصيد لحظة حدوث الحركة
      sumD += (l.debit || 0);
      sumC += (l.credit || 0);

      const partner = l.partner_id?.[1] ?? '';
      const moveName = l.move_id?.[1] ?? '—';
      
      // الألوان المطلوبة: المدين أخضر، الدائن أحمر
      const debitClass  = l.debit > 0 ? 'text-emerald-600 font-bold' : 'text-gray-400';
      const creditClass = l.credit > 0 ? 'text-red-600 font-bold' : 'text-gray-400';
      
      // الرصيد: أخضر إذا موجب، أحمر إذا سالب
      const balClass = running >= 0 ? 'text-emerald-600' : 'text-red-600';

      htmlStr += `
        <tr class="${filteredLines.length%2===0?'bg-slate-50':'bg-white'} hover:bg-indigo-50 transition-colors border-b border-slate-100">
          <td class="py-3 px-5 whitespace-nowrap text-slate-600">${l.date}</td>
          <td class="py-3 px-4 font-bold text-slate-800">${moveName}</td>
          <td class="py-3 px-4 text-indigo-700">${partner||'—'}</td>
          <td class="py-3 px-4 text-slate-600">${l.name||'—'}</td>
          <td class="py-3 px-5 text-left ${debitClass}" dir="ltr">${fmt(l.debit)}</td>
          <td class="py-3 px-5 text-left ${creditClass}" dir="ltr">${fmt(l.credit)}</td>
          <td class="py-3 px-5 text-left font-extrabold ${balClass}" dir="ltr">${fmt(running)}</td>
        </tr>`;
    }
  });

  if(!filteredLines.length){
    tbody.innerHTML=`<tr><td colspan="7" class="py-16 text-center text-gray-400">
      <i class="fas fa-magnifying-glass text-3xl mb-3 block opacity-30"></i>لا توجد حركات مطابقة للبحث</td></tr>`;
  } else {
    tbody.innerHTML = htmlStr;
  }

  document.getElementById('footDebit').textContent = fmt(sumD);
  document.getElementById('footCredit').textContent = fmt(sumC);
  
  const finalBalForPeriod = sumD - sumC;
  const footBalEl = document.getElementById('footBal');
  footBalEl.textContent = fmt(finalBalForPeriod);
  footBalEl.className = `py-4 px-5 font-extrabold text-left ${finalBalForPeriod >= 0 ? 'text-emerald-600' : 'text-red-600'}`;

  document.getElementById('visibleCount').textContent = filteredLines.length;
}

// ══ تصدير الحساب إلى Excel ══
function exportExcel() {
  if(!filteredLines.length) return alert('لا توجد بيانات لتصديرها');
  
  const dateFrom = document.getElementById('dateFrom').value;
  const dateTo   = document.getElementById('dateTo').value;

  let data = [];
  data.push(["تقرير كشف حساب بنكي"]);
  data.push(["اسم الحساب:", ACCOUNT.name, "رقم الحساب:", ACCOUNT.code]);
  data.push(["من تاريخ:", dateFrom || 'البداية', "إلى تاريخ:", dateTo || 'النهاية']);
  data.push([]);
  data.push(["التاريخ", "رقم القيد", "الشريك", "الوصف", "مدين (إيداع)", "دائن (سحب)", "الرصيد التراكمي"]);

  filteredLines.forEach(l => {
    data.push([
      l.date,
      l.move_id?.[1] ?? '',
      l.partner_id?.[1] ?? '',
      l.name ?? '',
      l.debit || 0,
      l.credit || 0,
      l.current_running
    ]);
  });

  const ws = XLSX.utils.aoa_to_sheet(data);
  ws['!dir'] = 'rtl';
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, "كشف حساب");
  XLSX.writeFile(wb, `Bank_Statement_${ACCOUNT.code}.xlsx`);
}

// ══ طباعة الحساب (PDF) ══
function printAccount(){
  if(!filteredLines.length) return alert('لا توجد حركات لطباعتها');
  
  const dateFrom = document.getElementById('dateFrom').value;
  const dateTo   = document.getElementById('dateTo').value;

  document.getElementById('print-subtitle').textContent =
    `الفترة: ${dateFrom||'البداية'} إلى ${dateTo||'النهاية'} | عدد الحركات: ${filteredLines.length}`;
  document.getElementById('print-date2').textContent = new Date().toLocaleDateString('ar-MY');

  let rows='';
  let sumD = 0, sumC = 0;

  filteredLines.forEach((l, i) => {
    sumD += (l.debit || 0);
    sumC += (l.credit || 0);
    const bg = i%2 ? '#f8fafc' : '#ffffff';
    
    const debitColor  = l.debit > 0 ? '#16a34a' : '#9ca3af'; // أخضر
    const creditColor = l.credit > 0 ? '#dc2626' : '#9ca3af'; // أحمر
    const balColor    = l.current_running >= 0 ? '#16a34a' : '#dc2626';

    rows += `<tr style="background:${bg};">
      <td style="padding:7px 10px;border-bottom:1px solid #e2e8f0;color:#475569;">${l.date}</td>
      <td style="padding:7px 10px;border-bottom:1px solid #e2e8f0;font-weight:700;color:#1e40af;">${l.move_id?.[1]??'—'}</td>
      <td style="padding:7px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;">${l.partner_id?.[1]??'—'}</td>
      <td style="padding:7px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;">${l.name||'—'}</td>
      <td style="padding:7px 10px;border-bottom:1px solid #e2e8f0;text-align:left;color:${debitColor};font-weight:600;" dir="ltr">${fmt(l.debit)}</td>
      <td style="padding:7px 10px;border-bottom:1px solid #e2e8f0;text-align:left;color:${creditColor};font-weight:600;" dir="ltr">${fmt(l.credit)}</td>
      <td style="padding:7px 10px;border-bottom:1px solid #e2e8f0;text-align:left;font-weight:700;color:${balColor};" dir="ltr">
        ${fmt(l.current_running)}
      </td>
    </tr>`;
  });

  document.getElementById('print-content').innerHTML=`
    <table style="width:100%;border-collapse:collapse;direction:rtl;">
      <thead>
        <tr style="background:#1e293b;color:white;">
          <th style="padding:9px 10px;text-align:right;font-weight:700;">التاريخ</th>
          <th style="padding:9px 10px;text-align:right;font-weight:700;">رقم القيد</th>
          <th style="padding:9px 10px;text-align:right;font-weight:700;">الشريك</th>
          <th style="padding:9px 10px;text-align:right;font-weight:700;">الوصف</th>
          <th style="padding:9px 10px;text-align:left;font-weight:700;">إيداع (مدين)</th>
          <th style="padding:9px 10px;text-align:left;font-weight:700;">سحب (دائن)</th>
          <th style="padding:9px 10px;text-align:left;font-weight:700;">الرصيد التراكمي</th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
      <tfoot>
        <tr style="background:#f8fafc;border-top:2px solid #1e293b;">
          <td colspan="4" style="padding:9px 10px;font-weight:800;text-align:right;color:#0f172a;">الإجمالي للحركات المعروضة</td>
          <td style="padding:9px 10px;font-weight:800;text-align:left;color:#16a34a;" dir="ltr">${fmt(sumD)}</td>
          <td style="padding:9px 10px;font-weight:800;text-align:left;color:#dc2626;" dir="ltr">${fmt(sumC)}</td>
          <td style="padding:9px 10px;"></td>
        </tr>
      </tfoot>
    </table>`;
  
  window.print();
}

function resetFilters(){
  document.getElementById('searchInput').value='';
  document.getElementById('dateFrom').value='';
  document.getElementById('dateTo').value='';
  render();
}

['searchInput', 'dateFrom', 'dateTo'].forEach(id => {
  const el = document.getElementById(id);
  el.addEventListener('input', render);
  el.addEventListener('change', render);
});

// التشغيل المبدئي
render();
</script>
</body>
</html>