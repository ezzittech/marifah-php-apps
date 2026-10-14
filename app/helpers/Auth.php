<?php
class Auth {
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
            session_start();
        }
    }
    public static function login($user) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['login_at']  = time();
    }
    public static function isLoggedIn() { return isset($_SESSION['user_id']); }
    public static function isAdmin()    { return self::isLoggedIn() && $_SESSION['role'] === 'admin'; }
    public static function requireLogin() {
        if (!self::isLoggedIn()) { header('Location: ' . APP_URL . '/public/login.php'); exit; }
    }
    public static function requireAdmin() {
        self::requireLogin();
        if (!self::isAdmin()) { header('Location: ' . APP_URL . '/public/dashboard.php'); exit; }
    }
    public static function user() {
        return ['id'=>$_SESSION['user_id']??null,'name'=>$_SESSION['user_name']??'','role'=>$_SESSION['role']??''];
    }
    public static function logout() { session_destroy(); header('Location: ' . APP_URL . '/public/login.php'); exit; }
}
