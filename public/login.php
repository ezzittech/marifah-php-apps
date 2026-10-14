<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/Auth.php';
Auth::start();
if (Auth::isLoggedIn()) { header('Location: ' . APP_URL . '/public/dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db   = Database::getInstance();
    $user = $db->fetchOne("SELECT * FROM users WHERE username=? AND is_active=1", [$_POST['username'] ?? '']);
    if ($user && password_verify($_POST['password'] ?? '', $user['password'])) {
        Auth::login($user);
        header('Location: ' . APP_URL . '/public/dashboard.php'); exit;
    }
    $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
}
require __DIR__ . '/../app/views/layouts/login_layout.php';
