<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/helpers/Auth.php';
Auth::start();
Auth::logout();
