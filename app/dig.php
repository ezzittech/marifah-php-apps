<?php
// ==========================================
//  أداة تشخيص حقول res.partner في أودو
//  Marifah International School
// ==========================================

$odoo_url = 'https://portal.marifah.edu.my';
$db       = 'Marifahdb';
$username = 'ezzittech@gmail.com';
$password = 'Abulez@2526';

// ==========================================
//  دوال الاتصال
// ==========================================

function authenticate($url, $db, $login, $password) {
    $ch = curl_init($url . '/web/session/authenticate');
    $payload = json_encode([
        'jsonrpc' => '2.0', 'method' => 'call',
        'params'  => ['db' => $db, 'login' => $login, 'password' => $password]
    ]);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HEADER         => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response    = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header      = substr($response, 0, $header_size);
    $body        = substr($response, $header_size);
    curl_close($ch);

    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $matches);
    $cookies = [];
    foreach ($matches[1] as $item) {
        parse_str($item, $cookie);
        $cookies = array_merge($cookies, $cookie);
    }

    $result = json_decode($body, true);
    $uid    = $result['result']['uid'] ?? null;

    return ['session_id' => $cookies['session_id'] ?? '', 'uid' => $uid];
}

function odoo_call($url, $session_id, $model, $method, $args, $kwargs = []) {
    $ch = curl_init($url . '/web/dataset/call_kw');
    $payload = json_encode([
        'jsonrpc' => '2.0', 'method' => 'call',
        'params'  => [
            'model'  => $model,
            'method' => $method,
            'args'   => $args,
            'kwargs' => $kwargs,
        ]
    ]);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Cookie: session_id=' . $session_id
        ],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['result'] ?? null;
}

// ==========================================
//  منطق التشخيص
// ==========================================

$auth       = authenticate($odoo_url, $db, $username, $password);
$session_id = $auth['session_id'];
$uid        = $auth['uid'];

$connected  = !empty($session_id) && $uid;
$x_fields   = [];
$sample     = [];
$all_fields = [];
$error_msg  = '';

if ($connected) {

    // 1) جلب تعريفات كل الحقول على res.partner
    $fields_def = odoo_call($odoo_url, $session_id, 'res.partner', 'fields_get', [], [
        'attributes' => ['string', 'type', 'relation', 'selection', 'store', 'required']
    ]);

    if (is_array($fields_def)) {
        foreach ($fields_def as $fname => $fdef) {
            if (strpos($fname, 'x_') === 0) {
                $x_fields[$fname] = $fdef;
            }
            $all_fields[$fname] = $fdef;
        }
    }

    // 2) جلب عينة: أول طالب لديه x_student_type
    $sample_records = odoo_call($odoo_url, $session_id, 'res.partner', 'search_read',
        [[['x_student_type', '!=', false]]],
        [
            'fields' => array_keys($x_fields),
            'limit'  => 3
        ]
    );

    if (is_array($sample_records)) {
        $sample = $sample_records;
    }

} else {
    $error_msg = 'فشل الاتصال — تحقق من بيانات الدخول أو عنوان الخادم.';
}

// ==========================================
//  نوع الحقل بالعربية
// ==========================================
function type_label($type) {
    $map = [
        'char'       => 'نص قصير (Char)',
        'text'       => 'نص طويل (Text)',
        'integer'    => 'رقم صحيح (Integer)',
        'float'      => 'رقم عشري (Float)',
        'boolean'    => 'نعم/لا (Boolean)',
        'date'       => 'تاريخ (Date)',
        'datetime'   => 'تاريخ ووقت (Datetime)',
        'selection'  => 'قائمة اختيار (Selection)',
        'many2one'   => 'ارتباط واحد (Many2one)',
        'one2many'   => 'ارتباط متعدد (One2many)',
        'many2many'  => 'ارتباط متعدد-متعدد (Many2many)',
        'binary'     => 'ملف ثنائي (Binary)',
        'html'       => 'HTML',
        'monetary'   => 'مالي (Monetary)',
    ];
    return $map[$type] ?? $type;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🔍 أداة تشخيص حقول أودو — Marifah</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body { font-family: 'Tajawal', sans-serif; }
    pre  { direction: ltr; text-align: left; }
    .badge-type {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }
</style>
</head>
<body class="bg-gray-50 min-h-screen p-6">

<div class="max-w-6xl mx-auto space-y-6">

    <!-- ترويسة -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-microscope text-indigo-500"></i>
                    أداة تشخيص حقول res.partner
                </h1>
                <p class="text-gray-500 text-sm mt-1">
                    تعرض كل الحقول التي تبدأ بـ <code class="bg-gray-100 px-1 rounded">x_</code>
                    الموجودة فعلاً على نموذج الشركاء في أودو — مع عينة بياناتها الحقيقية
                </p>
            </div>

            <?php if ($connected): ?>
                <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-4 py-2 rounded-xl font-bold text-sm flex items-center gap-2">
                    <i class="fas fa-circle-check"></i> متصل بنجاح — uid: <?= (int)$uid ?>
                </span>
            <?php else: ?>
                <span class="bg-red-50 text-red-600 border border-red-200 px-4 py-2 rounded-xl font-bold text-sm flex items-center gap-2">
                    <i class="fas fa-circle-xmark"></i> <?= htmlspecialchars($error_msg) ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm text-gray-600">
            <div class="bg-gray-50 rounded-xl p-3 flex items-center gap-2">
                <i class="fas fa-server text-indigo-400"></i>
                <span><b>الخادم:</b> <?= htmlspecialchars($odoo_url) ?></span>
            </div>
            <div class="bg-gray-50 rounded-xl p-3 flex items-center gap-2">
                <i class="fas fa-database text-indigo-400"></i>
                <span><b>قاعدة البيانات:</b> <?= htmlspecialchars($db) ?></span>
            </div>
            <div class="bg-gray-50 rounded-xl p-3 flex items-center gap-2">
                <i class="fas fa-tag text-indigo-400"></i>
                <span><b>عدد الحقول المخصصة (x_):</b> <?= count($x_fields) ?></span>
            </div>
        </div>
    </div>

    <?php if ($connected && !empty($x_fields)): ?>

    <!-- جدول الحقول المخصصة -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center gap-2">
            <i class="fas fa-list-check text-indigo-500 text-lg"></i>
            <h2 class="text-lg font-extrabold text-gray-800">
                الحقول المخصصة (x_fields) على res.partner
            </h2>
            <span class="mr-auto bg-indigo-100 text-indigo-700 rounded-full px-3 py-0.5 text-sm font-bold">
                <?= count($x_fields) ?> حقل
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="py-3 px-5 font-bold text-gray-600 whitespace-nowrap">اسم الحقل (Technical)</th>
                        <th class="py-3 px-5 font-bold text-gray-600 whitespace-nowrap">التسمية (Label)</th>
                        <th class="py-3 px-5 font-bold text-gray-600 whitespace-nowrap">النوع</th>
                        <th class="py-3 px-5 font-bold text-gray-600 whitespace-nowrap">الجدول المرتبط</th>
                        <th class="py-3 px-5 font-bold text-gray-600 whitespace-nowrap">مخزن؟</th>
                        <th class="py-3 px-5 font-bold text-gray-600 whitespace-nowrap">قيم الاختيار</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($x_fields as $fname => $fdef): ?>
                    <?php
                        $type     = $fdef['type'] ?? '—';
                        $relation = $fdef['relation'] ?? '—';
                        $stored   = ($fdef['store'] ?? false) ? true : false;

                        // لون حسب النوع
                        $color = match($type) {
                            'many2one'  => 'bg-purple-100 text-purple-700',
                            'many2many' => 'bg-blue-100   text-blue-700',
                            'one2many'  => 'bg-sky-100    text-sky-700',
                            'selection' => 'bg-amber-100  text-amber-700',
                            'char'      => 'bg-gray-100   text-gray-600',
                            'boolean'   => 'bg-pink-100   text-pink-700',
                            default     => 'bg-gray-100   text-gray-600',
                        };

                        // قيم الاختيار
                        $sel_vals = '';
                        if ($type === 'selection' && !empty($fdef['selection'])) {
                            $pairs = [];
                            foreach ($fdef['selection'] as $pair) {
                                $pairs[] = '<code class="bg-gray-100 px-1 rounded text-xs">' . htmlspecialchars($pair[0]) . '</code> → ' . htmlspecialchars($pair[1]);
                            }
                            $sel_vals = implode('<br>', $pairs);
                        }
                    ?>
                    <tr class="hover:bg-indigo-50 transition-colors">
                        <td class="py-3 px-5 font-mono text-indigo-700 font-bold whitespace-nowrap">
                            <?= htmlspecialchars($fname) ?>
                        </td>
                        <td class="py-3 px-5 text-gray-700 whitespace-nowrap">
                            <?= htmlspecialchars($fdef['string'] ?? '—') ?>
                        </td>
                        <td class="py-3 px-5 whitespace-nowrap">
                            <span class="badge-type <?= $color ?>">
                                <?= htmlspecialchars(type_label($type)) ?>
                            </span>
                        </td>
                        <td class="py-3 px-5 font-mono text-xs text-gray-500 whitespace-nowrap">
                            <?= ($relation !== '—') ? htmlspecialchars($relation) : '<span class="text-gray-300">—</span>' ?>
                        </td>
                        <td class="py-3 px-5 text-center">
                            <?php if ($stored): ?>
                                <i class="fas fa-circle-check text-emerald-500" title="مخزن في قاعدة البيانات"></i>
                            <?php else: ?>
                                <i class="fas fa-circle-xmark text-gray-300" title="غير مخزن / computed"></i>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-5 text-sm text-gray-600 leading-relaxed">
                            <?= $sel_vals ?: '<span class="text-gray-300">—</span>' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- عينة بيانات حقيقية -->
    <?php if (!empty($sample)): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center gap-2">
            <i class="fas fa-vials text-emerald-500 text-lg"></i>
            <h2 class="text-lg font-extrabold text-gray-800">
                عينة بيانات حقيقية (أول 3 طلاب لديهم x_student_type)
            </h2>
        </div>
        <div class="p-5 space-y-4">
            <?php foreach ($sample as $i => $rec): ?>
            <div class="border border-gray-200 rounded-xl overflow-hidden">
                <div class="bg-gray-50 px-4 py-2 font-bold text-gray-700 border-b border-gray-200 flex items-center gap-2">
                    <i class="fas fa-user-graduate text-indigo-400"></i>
                    سجل #<?= $i+1 ?> — ID: <?= (int)$rec['id'] ?>
                </div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($x_fields as $fname => $fdef): ?>
                        <?php
                            $raw = $rec[$fname] ?? null;
                            if (is_array($raw)) {
                                // Many2one: [id, name]
                                $display = '<span class="font-mono text-purple-700">[' . $raw[0] . ']</span> ' . htmlspecialchars($raw[1] ?? '');
                            } elseif ($raw === false || $raw === null || $raw === '') {
                                $display = '<span class="text-gray-300 italic">فارغ / false</span>';
                            } else {
                                $display = '<span class="text-gray-800">' . htmlspecialchars((string)$raw) . '</span>';
                            }
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 px-4 font-mono text-xs text-indigo-600 w-48 whitespace-nowrap border-l border-gray-100">
                                <?= htmlspecialchars($fname) ?>
                            </td>
                            <td class="py-2 px-4 text-xs text-gray-400 w-32 whitespace-nowrap border-l border-gray-100">
                                <?= htmlspecialchars($fdef['string'] ?? '') ?>
                            </td>
                            <td class="py-2 px-4">
                                <?= $display ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Raw JSON للمطور -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <details>
            <summary class="p-5 cursor-pointer font-bold text-gray-700 flex items-center gap-2 list-none select-none hover:bg-gray-50">
                <i class="fas fa-code text-gray-400"></i>
                عرض البيانات الخام (JSON) — للمطور
                <i class="fas fa-chevron-down text-gray-400 mr-auto text-sm"></i>
            </summary>
            <div class="border-t border-gray-100 p-5">
                <p class="text-sm font-bold text-gray-600 mb-2">تعريفات الحقول x_fields:</p>
                <pre class="bg-gray-900 text-green-400 rounded-xl p-4 overflow-x-auto text-xs leading-relaxed"><?= htmlspecialchars(json_encode($x_fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>

                <?php if (!empty($sample)): ?>
                <p class="text-sm font-bold text-gray-600 mb-2 mt-4">عينة البيانات:</p>
                <pre class="bg-gray-900 text-green-400 rounded-xl p-4 overflow-x-auto text-xs leading-relaxed"><?= htmlspecialchars(json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                <?php endif; ?>
            </div>
        </details>
    </div>

    <?php elseif ($connected): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6 text-center">
        <i class="fas fa-triangle-exclamation text-amber-500 text-3xl mb-2"></i>
        <p class="font-bold text-amber-800">لم يتم العثور على أي حقول تبدأ بـ x_ على res.partner</p>
        <p class="text-amber-600 text-sm mt-1">تأكد أن الوحدة المخصصة مثبتة وأن المستخدم لديه صلاحية الوصول لها.</p>
    </div>
    <?php endif; ?>

    <p class="text-center text-xs text-gray-400 pb-4">
        <i class="fas fa-shield-halved"></i> هذه الصفحة للاستخدام التشخيصي فقط — احذفها بعد الانتهاء منها
    </p>

</div>
</body>
</html>