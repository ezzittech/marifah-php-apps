<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/Auth.php';
Auth::start();
header('Location: ' . APP_URL . (Auth::isLoggedIn() ? '/public/dashboard.php' : '/public/login.php'));
exit;
