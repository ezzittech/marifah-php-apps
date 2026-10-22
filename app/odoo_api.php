<?php
// ===================================================================
// ملف: odoo_api.php
// الإصدار: 2.0 — مع إصلاح سياق الشركة ودعم معلومات الكاش
// ===================================================================

$odoo_url = 'https://portal.marifah.edu.my';
$db       = 'Marifahdb';
$username = 'ezzittech@gmail.com';
$password = 'Abulez@2526';

// ------------------------------------------------------------------
// authenticate()
// يُرجع session_id أو سلسلة فارغة عند الفشل
// ------------------------------------------------------------------
function authenticate($url, $db, $login, $password) {
    $ch = curl_init($url . '/web/session/authenticate');
    $payload = json_encode([
        'jsonrpc' => '2.0',
        'method'  => 'call',
        'params'  => ['db' => $db, 'login' => $login, 'password' => $password]
    ]);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HEADER         => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $r  = curl_exec($ch);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $h  = substr($r, 0, $hs);
    curl_close($ch);

    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $h, $m);
    $c = [];
    foreach ($m[1] as $i) {
        parse_str($i, $x);
        $c = array_merge($c, $x);
    }
    return $c['session_id'] ?? '';
}

// ------------------------------------------------------------------
// odoo()
// استدعاء مباشر لـ Odoo JSON-RPC مع ضمان تمرير context صحيح
//
// ✅ الإصلاح الجوهري:
//   - context يُدمج دائماً داخل kwargs قبل الإرسال
//   - لو مرّرت allowed_company_ids من الخارج تُستخدم، وإلا [] كقيمة افتراضية
// ------------------------------------------------------------------
function odoo($url, $sid, $model, $method, $args, $kwargs = []) {

    // دمج الـ context: ما يأتي من المستدعي له الأولوية
    $incoming_ctx = $kwargs['context'] ?? [];
    $kwargs['context'] = array_merge(
        ['lang' => 'ar_001', 'tz' => 'Asia/Kuala_Lumpur'],
        $incoming_ctx   // allowed_company_ids و company_id يأتيان من هنا
    );

    $ch = curl_init($url . '/web/dataset/call_kw');
    $payload = json_encode([
        'jsonrpc' => '2.0',
        'method'  => 'call',
        'params'  => [
            'model'  => $model,
            'method' => $method,
            'args'   => $args,
            'kwargs' => $kwargs,
        ],
    ]);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Cookie: session_id=' . $sid,
        ],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 90,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);

    $d = json_decode($r, true);
    return $d['result'] ?? null;
}

// ------------------------------------------------------------------
// odoo_cached()
// نفس odoo() لكن مع كاش ملفات JSON + إرجاع معلومات التحديث
//
// يُرجع مصفوفة:
//   ['data' => ..., 'from_cache' => bool, 'cached_at' => timestamp|null]
// ------------------------------------------------------------------
function odoo_cached($url, $sid, $model, $method, $args, $kwargs = [], $cache_minutes = 60) {
    $cache_dir  = __DIR__ . '/cache/';
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }

    $query_hash    = md5(json_encode([$model, $method, $args, $kwargs]));
    $cache_file    = $cache_dir . $query_hash . '.json';
    $cache_seconds = $cache_minutes * 60;

    // ── قراءة الكاش إذا لم تنتهِ صلاحيته ──
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_seconds) {
        $cached = json_decode(file_get_contents($cache_file), true);
        if ($cached !== null) {
            return [
                'data'       => $cached,
                'from_cache' => true,
                'cached_at'  => filemtime($cache_file),
                'cache_file' => $cache_file,
            ];
        }
    }

    // ── جلب من أودو ──
    $result = odoo($url, $sid, $model, $method, $args, $kwargs);

    if ($result !== null) {
        file_put_contents($cache_file, json_encode($result));
    }

    return [
        'data'       => $result,
        'from_cache' => false,
        'cached_at'  => $result !== null ? time() : null,
        'cache_file' => $cache_file,
    ];
}

// ------------------------------------------------------------------
// cache_clear_all()
// حذف جميع ملفات الكاش (تُستدعى عند الضغط على "تحديث البيانات")
// ------------------------------------------------------------------
function cache_clear_all() {
    $cache_dir = __DIR__ . '/cache/';
    if (!is_dir($cache_dir)) return;
    foreach (glob($cache_dir . '*.json') as $f) {
        @unlink($f);
    }
}