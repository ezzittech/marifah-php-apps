<?php
// ==========================================
//  أداة تشخيص مشكلة فاطمة — duplicated_ref_ids
//  school_cashier_security — Marifah
// ==========================================

$odoo_url = 'https://portal.marifah.edu.my';
$db       = 'Marifahdb';
$username = 'ezzittech@gmail.com';
$password = 'Abulez@2526';

// ==========================================
//  دوال الاتصال (نفس الأسلوب المعتمد)
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
    return ['session_id' => $cookies['session_id'] ?? '', 'uid' => $uid, 'raw' => $result];
}

function odoo_call($url, $session_id, $model, $method, $args, $kwargs = []) {
    $ch = curl_init($url . '/web/dataset/call_kw');
    $payload = json_encode([
        'jsonrpc' => '2.0', 'method' => 'call',
        'params'  => compact('model', 'method', 'args', 'kwargs')
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
//  جلب البيانات التشخيصية
// ==========================================

$auth       = authenticate($odoo_url, $db, $username, $password);
$session_id = $auth['session_id'];
$uid        = $auth['uid'];
$connected  = !empty($session_id) && $uid;

$results = [];

if ($connected) {

    // ── 1. معلومات المستخدمة فاطمة (id=8) ──────────────────────────────
    $fatheia = odoo_call($odoo_url, $session_id, 'res.users', 'search_read',
        [[['id', '=', 8]]],
        ['fields' => ['name', 'login', 'groups_id', 'share'], 'limit' => 1]
    );

    // أسماء المجموعات التي تنتمي إليها
    $group_ids   = $fatheia[0]['groups_id'] ?? [];
    $group_names = [];
    if (!empty($group_ids)) {
        $groups = odoo_call($odoo_url, $session_id, 'res.groups', 'search_read',
            [[['id', 'in', $group_ids]]],
            ['fields' => ['name', 'full_name', 'category_id']]
        );
        foreach ($groups as $g) {
            $group_names[$g['id']] = $g['full_name'] ?? $g['name'];
        }
    }
    $results['fatheia_user']   = $fatheia[0] ?? null;
    $results['fatheia_groups'] = $group_names;

    // ── 2. قواعد السجلات المطبقة على account.move ───────────────────────
    $record_rules = odoo_call($odoo_url, $session_id, 'ir.rule', 'search_read',
        [[['model_id.model', '=', 'account.move'], ['active', '=', true]]],
        ['fields' => ['name', 'domain_force', 'groups', 'perm_read', 'perm_write', 'perm_create', 'perm_unlink', 'global']]
    );
    $results['account_move_rules'] = $record_rules;

    // ── 3. ACL على account.move لمجموعات الكاشير ────────────────────────
    $acl = odoo_call($odoo_url, $session_id, 'ir.model.access', 'search_read',
        [[['model_id.model', '=', 'account.move']]],
        ['fields' => ['name', 'group_id', 'perm_read', 'perm_write', 'perm_create', 'perm_unlink', 'active']]
    );
    $results['account_move_acl'] = $acl;

    // ── 4. تعريف حقل duplicated_ref_ids على account.move ────────────────
    $move_fields = odoo_call($odoo_url, $session_id, 'account.move', 'fields_get',
        [['duplicated_ref_ids']],
        ['attributes' => ['string', 'type', 'relation', 'store', 'compute', 'related', 'readonly']]
    );
    $results['duplicated_ref_ids_def'] = $move_fields;

    // ── 5. كل الحقول x_ على account.move (للمراجعة) ────────────────────
    $all_move_fields = odoo_call($odoo_url, $session_id, 'account.move', 'fields_get',
        [],
        ['attributes' => ['string', 'type', 'store', 'compute', 'related']]
    );
    $x_move_fields = [];
    foreach (($all_move_fields ?? []) as $fname => $fdef) {
        if (strpos($fname, 'x_') === 0) {
            $x_move_fields[$fname] = $fdef;
        }
    }
    $results['x_fields_on_account_move'] = $x_move_fields;

    // ── 6. عينة فواتير مرئية للكاشير admin (مقارنة) ─────────────────────
    // نجلب آخر 5 فواتير بمختلف أنواع الطلاب لنرى توزيع البيانات
    $moves_sample = odoo_call($odoo_url, $session_id, 'account.move', 'search_read',
        [[['move_type', 'in', ['out_invoice', 'out_receipt']]]],
        [
            'fields' => ['id', 'name', 'ref', 'partner_id', 'x_student_type', 'state', 'move_type'],
            'limit'  => 10,
            'order'  => 'id desc',
        ]
    );
    $results['recent_moves_sample'] = $moves_sample;

    // ── 7. فواتير تحمل ref مكررة (هذا سبب المشكلة) ──────────────────────
    // نبحث عن ref مستخدمة أكثر من مرة
    $moves_with_ref = odoo_call($odoo_url, $session_id, 'account.move', 'search_read',
        [[['ref', '!=', false], ['move_type', 'in', ['out_invoice', 'out_receipt']]]],
        [
            'fields' => ['id', 'name', 'ref', 'partner_id', 'x_student_type'],
            'limit'  => 50,
            'order'  => 'ref asc',
        ]
    );

    // نجمع الـ refs المكررة
    $ref_map = [];
    foreach (($moves_with_ref ?? []) as $m) {
        $ref = $m['ref'];
        if (!isset($ref_map[$ref])) $ref_map[$ref] = [];
        $ref_map[$ref][] = $m;
    }
    $duplicated_refs = array_filter($ref_map, fn($v) => count($v) > 1);
    $results['duplicated_refs_found'] = $duplicated_refs;

    // ── 8. قواعد السجلات المطبقة على account.move.line ──────────────────
    $line_rules = odoo_call($odoo_url, $session_id, 'ir.rule', 'search_read',
        [[['model_id.model', '=', 'account.move.line'], ['active', '=', true]]],
        ['fields' => ['name', 'domain_force', 'groups', 'perm_read', 'global']]
    );
    $results['move_line_rules'] = $line_rules;

    // ── 9. مجموعات الكاشير الموجودة في النظام ───────────────────────────
    $cashier_groups = odoo_call($odoo_url, $session_id, 'res.groups', 'search_read',
        [[['full_name', 'ilike', 'cashier']]],
        ['fields' => ['name', 'full_name', 'users', 'implied_ids']]
    );
    $results['cashier_groups'] = $cashier_groups;

}

// ==========================================
//  تحليل المشكلة تلقائياً
// ==========================================

function analyze_problem($results) {
    $issues = [];

    // هل فاطمة لديها مجموعة online فقط؟
    $groups = $results['fatheia_groups'] ?? [];
    $has_physical = false;
    $has_online   = false;
    foreach ($groups as $g) {
        if (stripos($g, 'physical') !== false || stripos($g, 'cashier_physical') !== false) $has_physical = true;
        if (stripos($g, 'online')   !== false || stripos($g, 'cashier_online')   !== false) $has_online   = true;
    }

    if ($has_online && !$has_physical) {
        $issues[] = [
            'level' => 'warning',
            'title' => 'فاطمة في مجموعة Online فقط',
            'desc'  => 'تأكيد: فاطمة cashier online، لا ترى فواتير physical — هذا صحيح لكن المشكلة تأتي من duplicated_ref_ids.'
        ];
    }
    if ($has_physical && $has_online) {
        $issues[] = [
            'level' => 'info',
            'title' => 'فاطمة في كلا المجموعتين (both)',
            'desc'  => 'لو كانت في كلا المجموعتين يجب أن ترى كل شيء — تحقق من كود _is_cashier_user.'
        ];
    }

    // هل duplicated_ref_ids computed بدون sudo؟
    $def = $results['duplicated_ref_ids_def']['duplicated_ref_ids'] ?? null;
    if ($def) {
        if (!empty($def['compute']) && empty($def['store'])) {
            $issues[] = [
                'level' => 'danger',
                'title' => '🔴 duplicated_ref_ids: computed غير مخزن بدون sudo',
                'desc'  => 'هذا الحقل يجلب سجلات account.move أخرى بنفس الـ ref. 
                            لأنه computed وليس stored، أودو يقرأ السجلات في سياق المستخدم الحالي (فاطمة).
                            record rules تمنعها من رؤية فواتير physical → AccessError.
                            الحل: إضافة sudo() في compute method أو استثناء هذا الحقل من record rules.'
            ];
        }
        if (!empty($def['store'])) {
            $issues[] = [
                'level' => 'info',
                'title' => 'duplicated_ref_ids مخزن (stored)',
                'desc'  => 'الحقل stored — المشكلة قد تكون في write trigger وليس القراءة.'
            ];
        }
    } else {
        $issues[] = [
            'level' => 'danger',
            'title' => '🔴 لم يتم إيجاد تعريف duplicated_ref_ids',
            'desc'  => 'الحقل غير ظاهر لهذا المستخدم (admin) — قد يكون محدوداً بمجموعة. تحقق يدوياً.'
        ];
    }

    // هل توجد refs مكررة بأنواع طلاب مختلفة؟
    $dups = $results['duplicated_refs_found'] ?? [];
    $mixed_type_dups = [];
    foreach ($dups as $ref => $moves) {
        $types = array_unique(array_column($moves, 'x_student_type'));
        if (count($types) > 1 || in_array(false, $types)) {
            $mixed_type_dups[$ref] = $moves;
        }
    }
    if (!empty($mixed_type_dups)) {
        $issues[] = [
            'level' => 'danger',
            'title' => '🔴 وجود refs مكررة بأنواع طلاب مختلطة',
            'desc'  => 'هذه هي القنبلة الموقوتة. فواتير تحمل نفس الـ ref لكن x_student_type مختلف (online + physical أو null). 
                        عندما تفتح فاطمة فاتورة online، أودو يحاول جلب duplicated_ref_ids ويصطدم بفاتورة physical فتُرفض.'
        ];
        $results['mixed_type_dups'] = $mixed_type_dups;
    }

    // هل القواعد صحيحة؟
    $rules = $results['account_move_rules'] ?? [];
    foreach ($rules as $rule) {
        if (stripos($rule['name'], 'cashier') !== false || stripos($rule['name'], 'student') !== false) {
            $domain = $rule['domain_force'] ?? '';
            // نتحقق إذا كانت القاعدة تحمي write بدون استثناء للـ computed fields
            if ($rule['perm_write'] && !$rule['perm_read']) {
                $issues[] = [
                    'level' => 'warning',
                    'title' => 'قاعدة write فقط بدون read: ' . $rule['name'],
                    'desc'  => 'قاعدة مقيدة للكتابة بدون تقييد للقراءة — ربما تسبب تعارضاً.'
                ];
            }
        }
    }

    return ['issues' => $issues, 'mixed_dups' => $mixed_type_dups ?? []];
}

$analysis = $connected ? analyze_problem($results) : ['issues' => [], 'mixed_dups' => []];

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🩺 تشخيص مشكلة فاطمة — duplicated_ref_ids</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body { font-family: 'Tajawal', sans-serif; }
    pre  { direction: ltr; text-align: left; }
    .badge { display:inline-block; padding:2px 10px; border-radius:999px; font-size:12px; font-weight:700; }
</style>
</head>
<body class="bg-gray-50 min-h-screen p-6">
<div class="max-w-6xl mx-auto space-y-6">

    <!-- ترويسة -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h1 class="text-2xl font-extrabold text-gray-800 flex items-center gap-2">
            <i class="fas fa-stethoscope text-red-500"></i>
            تشخيص مشكلة: Failed to write field account.move.duplicated_ref_ids
        </h1>
        <p class="text-gray-500 text-sm mt-1">
            المستخدمة: <b>فاطمة الشرابي (id=8)</b> — خطأ عند الكتابة على حقل <code class="bg-gray-100 px-1 rounded">account.move.duplicated_ref_ids</code>
        </p>
        <div class="mt-3 flex flex-wrap gap-3">
            <span class="<?= $connected ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200' ?> border px-4 py-2 rounded-xl font-bold text-sm flex items-center gap-2">
                <i class="fas <?= $connected ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                <?= $connected ? "متصل — uid: $uid" : 'فشل الاتصال' ?>
            </span>
            <span class="bg-blue-50 text-blue-700 border border-blue-200 px-4 py-2 rounded-xl font-bold text-sm">
                <i class="fas fa-bug"></i>
                مشاكل محتملة: <?= count($analysis['issues']) ?>
            </span>
        </div>
    </div>

    <?php if (!$connected): ?>
    <div class="bg-red-50 border border-red-200 rounded-2xl p-6 text-center">
        <i class="fas fa-triangle-exclamation text-red-500 text-3xl mb-2"></i>
        <p class="font-bold text-red-800">فشل الاتصال — تحقق من بيانات الدخول</p>
    </div>
    <?php else: ?>

    <!-- ── تحليل تلقائي للمشكلة ── -->
    <?php if (!empty($analysis['issues'])): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-lg font-extrabold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-magnifying-glass-chart text-orange-500"></i>
            نتائج التحليل التلقائي
        </h2>
        <div class="space-y-3">
            <?php foreach ($analysis['issues'] as $issue): ?>
            <?php
                $colors = [
                    'danger'  => 'border-red-300 bg-red-50',
                    'warning' => 'border-amber-300 bg-amber-50',
                    'info'    => 'border-blue-300 bg-blue-50',
                ];
                $icon_colors = [
                    'danger'  => 'text-red-500',
                    'warning' => 'text-amber-500',
                    'info'    => 'text-blue-500',
                ];
                $icons = [
                    'danger'  => 'fa-circle-xmark',
                    'warning' => 'fa-triangle-exclamation',
                    'info'    => 'fa-circle-info',
                ];
                $c = $colors[$issue['level']] ?? 'border-gray-200 bg-gray-50';
                $ic = $icon_colors[$issue['level']] ?? 'text-gray-400';
                $ii = $icons[$issue['level']] ?? 'fa-info';
            ?>
            <div class="border rounded-xl p-4 <?= $c ?>">
                <div class="font-bold text-gray-800 flex items-center gap-2 mb-1">
                    <i class="fas <?= $ii ?> <?= $ic ?>"></i>
                    <?= htmlspecialchars($issue['title']) ?>
                </div>
                <p class="text-sm text-gray-600"><?= nl2br(htmlspecialchars($issue['desc'])) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Refs مكررة بأنواع مختلطة ── -->
    <?php if (!empty($analysis['mixed_dups'])): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-red-200 overflow-hidden">
        <div class="p-5 border-b border-red-100 bg-red-50 flex items-center gap-2">
            <i class="fas fa-fire text-red-500 text-lg"></i>
            <h2 class="text-lg font-extrabold text-red-800">
                ⚠️ Refs مكررة بأنواع طلاب مختلطة — هذا سبب المشكلة!
            </h2>
        </div>
        <div class="p-5 space-y-4">
            <?php foreach ($analysis['mixed_dups'] as $ref => $moves): ?>
            <div class="border border-red-200 rounded-xl overflow-hidden">
                <div class="bg-red-50 px-4 py-2 font-bold text-red-800 border-b border-red-100">
                    ref: <code><?= htmlspecialchars($ref) ?></code>
                    <span class="mr-2 text-xs font-normal text-red-600">(<?= count($moves) ?> فاتورة بنفس الـ ref)</span>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="py-2 px-4 text-right">ID</th>
                            <th class="py-2 px-4 text-right">الاسم</th>
                            <th class="py-2 px-4 text-right">الشريك</th>
                            <th class="py-2 px-4 text-right">x_student_type</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach ($moves as $m): ?>
                        <?php
                            $type = $m['x_student_type'] ?: 'null/false';
                            $type_color = match($type) {
                                'online'   => 'bg-blue-100 text-blue-700',
                                'physical' => 'bg-green-100 text-green-700',
                                'both'     => 'bg-purple-100 text-purple-700',
                                default    => 'bg-gray-100 text-gray-500',
                            };
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 px-4 font-mono"><?= (int)$m['id'] ?></td>
                            <td class="py-2 px-4"><?= htmlspecialchars($m['name']) ?></td>
                            <td class="py-2 px-4"><?= htmlspecialchars(is_array($m['partner_id']) ? $m['partner_id'][1] : '—') ?></td>
                            <td class="py-2 px-4">
                                <span class="badge <?= $type_color ?>"><?= htmlspecialchars($type) ?></span>
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

    <!-- ── مجموعات فاطمة ── -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center gap-2">
            <i class="fas fa-user-shield text-indigo-500 text-lg"></i>
            <h2 class="text-lg font-extrabold text-gray-800">مجموعات فاطمة (id=8)</h2>
        </div>
        <div class="p-5">
            <?php if (!empty($results['fatheia_user'])): ?>
            <div class="mb-3 grid grid-cols-2 gap-3 text-sm">
                <div class="bg-gray-50 p-3 rounded-xl">
                    <b>الاسم:</b> <?= htmlspecialchars($results['fatheia_user']['name'] ?? '—') ?>
                </div>
                <div class="bg-gray-50 p-3 rounded-xl">
                    <b>البريد:</b> <?= htmlspecialchars($results['fatheia_user']['login'] ?? '—') ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($results['fatheia_groups'])): ?>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($results['fatheia_groups'] as $id => $name): ?>
                <?php
                    $is_cashier = stripos($name, 'cashier') !== false;
                    $color = $is_cashier ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-gray-100 text-gray-600 border border-gray-200';
                ?>
                <span class="badge <?= $color ?> text-xs">
                    <?php if ($is_cashier): ?><i class="fas fa-star text-amber-500 mr-1"></i><?php endif; ?>
                    <?= htmlspecialchars($name) ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-gray-400 italic">لم يتم إيجاد مجموعات</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── تعريف duplicated_ref_ids ── -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center gap-2">
            <i class="fas fa-code-branch text-purple-500 text-lg"></i>
            <h2 class="text-lg font-extrabold text-gray-800">تعريف حقل <code>duplicated_ref_ids</code></h2>
        </div>
        <div class="p-5">
            <?php $def = $results['duplicated_ref_ids_def']['duplicated_ref_ids'] ?? null; ?>
            <?php if ($def): ?>
            <table class="w-full text-sm border border-gray-100 rounded-xl overflow-hidden">
                <?php foreach ($def as $k => $v): ?>
                <tr class="border-b border-gray-50 hover:bg-gray-50">
                    <td class="py-2 px-4 font-mono text-indigo-600 w-40"><?= htmlspecialchars($k) ?></td>
                    <td class="py-2 px-4 text-gray-700">
                        <?php if (is_array($v)): ?>
                            <code class="text-xs bg-gray-100 px-1 rounded"><?= htmlspecialchars(json_encode($v)) ?></code>
                        <?php elseif ($v === true): ?>
                            <i class="fas fa-check text-emerald-500"></i> true
                        <?php elseif ($v === false || $v === null || $v === ''): ?>
                            <span class="text-gray-300">false / null</span>
                        <?php else: ?>
                            <?= htmlspecialchars((string)$v) ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php else: ?>
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 font-bold">
                <i class="fas fa-triangle-exclamation"></i>
                الحقل <code>duplicated_ref_ids</code> غير موجود في fields_get — قد يكون محدوداً بصلاحيات معينة
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── قواعد السجلات على account.move ── -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center gap-2">
            <i class="fas fa-shield-halved text-blue-500 text-lg"></i>
            <h2 class="text-lg font-extrabold text-gray-800">
                قواعد السجلات على account.move
                <span class="mr-2 bg-blue-100 text-blue-700 rounded-full px-3 py-0.5 text-sm font-bold">
                    <?= count($results['account_move_rules'] ?? []) ?> قاعدة
                </span>
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="py-3 px-4">الاسم</th>
                        <th class="py-3 px-4">Global؟</th>
                        <th class="py-3 px-4">المجموعات</th>
                        <th class="py-3 px-4">R W C D</th>
                        <th class="py-3 px-4">Domain</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach (($results['account_move_rules'] ?? []) as $rule): ?>
                    <?php
                        $is_cashier_rule = stripos($rule['name'], 'cashier') !== false
                                        || stripos($rule['name'], 'student') !== false
                                        || stripos($rule['name'], 'online')  !== false
                                        || stripos($rule['name'], 'physical') !== false;
                        $row_class = $is_cashier_rule ? 'bg-amber-50 hover:bg-amber-100' : 'hover:bg-gray-50';
                    ?>
                    <tr class="<?= $row_class ?>">
                        <td class="py-2 px-4 font-bold text-gray-700 whitespace-nowrap">
                            <?php if ($is_cashier_rule): ?><i class="fas fa-star text-amber-500 text-xs mr-1"></i><?php endif; ?>
                            <?= htmlspecialchars($rule['name']) ?>
                        </td>
                        <td class="py-2 px-4 text-center">
                            <?= $rule['global'] ? '<i class="fas fa-globe text-blue-500"></i>' : '<i class="fas fa-users text-gray-400"></i>' ?>
                        </td>
                        <td class="py-2 px-4 text-xs text-gray-500">
                            <?php
                            $grp_ids = $rule['groups'] ?? [];
                            if (empty($grp_ids)) {
                                echo '<span class="text-gray-300">كل المجموعات</span>';
                            } else {
                                // نجلب أسماء المجموعات
                                $rule_groups = odoo_call($odoo_url, $session_id, 'res.groups', 'search_read',
                                    [[['id', 'in', $grp_ids]]],
                                    ['fields' => ['full_name']]
                                );
                                foreach (($rule_groups ?? []) as $rg) {
                                    echo '<span class="badge bg-gray-100 text-gray-600 text-xs mr-1">' . htmlspecialchars($rg['full_name']) . '</span>';
                                }
                            }
                            ?>
                        </td>
                        <td class="py-2 px-4 font-mono text-xs text-center whitespace-nowrap">
                            <span class="<?= $rule['perm_read']   ? 'text-emerald-600' : 'text-gray-300' ?>">R</span>
                            <span class="<?= $rule['perm_write']  ? 'text-emerald-600' : 'text-gray-300' ?>">W</span>
                            <span class="<?= $rule['perm_create'] ? 'text-emerald-600' : 'text-gray-300' ?>">C</span>
                            <span class="<?= $rule['perm_unlink'] ? 'text-emerald-600' : 'text-gray-300' ?>">D</span>
                        </td>
                        <td class="py-2 px-4 font-mono text-xs text-blue-600 max-w-xs truncate" title="<?= htmlspecialchars($rule['domain_force'] ?? '') ?>">
                            <?= htmlspecialchars($rule['domain_force'] ?? '[]') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── مجموعات الكاشير ── -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-lg font-extrabold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-users text-amber-500"></i>
            مجموعات الكاشير في النظام
        </h2>
        <?php foreach (($results['cashier_groups'] ?? []) as $cg): ?>
        <div class="border border-amber-200 bg-amber-50 rounded-xl p-4 mb-3">
            <div class="font-bold text-amber-900"><?= htmlspecialchars($cg['full_name']) ?></div>
            <div class="text-sm text-amber-700 mt-1">
                عدد المستخدمين: <b><?= count($cg['users']) ?></b>
                | المستخدمون: <?= implode(', ', array_map('intval', $cg['users'])) ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── خلاصة الحل المقترح ── -->
    <div class="bg-white rounded-2xl shadow-sm border border-emerald-200 p-6">
        <h2 class="text-lg font-extrabold text-emerald-800 mb-4 flex items-center gap-2">
            <i class="fas fa-screwdriver-wrench text-emerald-500"></i>
            الحلول المقترحة بناءً على التشخيص
        </h2>
        <div class="space-y-3 text-sm text-gray-700">
            <div class="border border-emerald-200 bg-emerald-50 rounded-xl p-4">
                <b class="text-emerald-800">✅ الحل الأول (الأنظف): استخدام sudo في compute method</b>
                <p class="mt-1">إضافة override لـ <code>duplicated_ref_ids</code> في <code>account_move.py</code> يستخدم <code>sudo()</code>:</p>
                <pre class="bg-gray-900 text-green-400 rounded-lg p-3 mt-2 text-xs overflow-x-auto">def _compute_duplicated_ref_ids(self):
    # sudo لتجاوز record rules عند جلب الفواتير المكررة
    for move in self:
        if move.ref:
            duplicates = self.sudo().search([
                ('ref', '=', move.ref),
                ('id', '!=', move.id),
                ('company_id', '=', move.company_id.id),
            ])
            move.duplicated_ref_ids = duplicates
        else:
            move.duplicated_ref_ids = self.env['account.move']</pre>
            </div>
            <div class="border border-blue-200 bg-blue-50 rounded-xl p-4">
                <b class="text-blue-800">✅ الحل الثاني: استثناء هذا الحقل من record rules</b>
                <p class="mt-1">في ملف <code>record_rules.xml</code>، تأكد أن قواعد read لـ cashier لا تمنع جلب السجلات عند الحساب من داخل computed field. هذا أصعب تطبيقاً.</p>
            </div>
            <div class="border border-amber-200 bg-amber-50 rounded-xl p-4">
                <b class="text-amber-800">⚠️ الحل الثالث المؤقت: إخفاء الحقل من views الكاشير</b>
                <p class="mt-1">في views الكاشير، إخفاء حقل <code>duplicated_ref_ids</code> حتى لا يُحسب أصلاً. حل سريع لكن لا يعالج الجذر.</p>
            </div>
        </div>
    </div>

    <!-- Raw JSON -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <details>
            <summary class="p-5 cursor-pointer font-bold text-gray-700 flex items-center gap-2 list-none select-none hover:bg-gray-50">
                <i class="fas fa-code text-gray-400"></i>
                كل البيانات الخام (JSON) — للمطور
                <i class="fas fa-chevron-down text-gray-400 mr-auto text-sm"></i>
            </summary>
            <div class="border-t border-gray-100 p-5">
                <?php foreach ($results as $key => $val): ?>
                <p class="text-sm font-bold text-gray-600 mb-2 mt-4"><?= htmlspecialchars($key) ?>:</p>
                <pre class="bg-gray-900 text-green-400 rounded-xl p-4 overflow-x-auto text-xs leading-relaxed mb-2"><?= htmlspecialchars(json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                <?php endforeach; ?>
            </div>
        </details>
    </div>

    <?php endif; ?>

    <p class="text-center text-xs text-gray-400 pb-4">
        <i class="fas fa-shield-halved"></i> هذه الصفحة للاستخدام التشخيصي فقط — احذفها بعد الانتهاء منها
    </p>

</div>
</body>
</html>