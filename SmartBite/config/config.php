<?php

// ---- Database ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'smartbite');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- App ----
define('APP_URL', 'http://localhost/SmartBite');
define('APP_NAME', 'SmartBite');

// ---- PayMongo ----
define('PAYMONGO_SECRET_KEY', 'sk_test_16L8WK6BpuBPuYRKFuAQLQS2');

define('PAYMONGO_WEBHOOK_SECRET', 'whsk_replace_me');

// ---- Environment ----
define('APP_DEBUG', true);

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
}

date_default_timezone_set('Asia/Manila');

// ---- Sessions ----
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $isHttps,
    ]);
    session_start();
}
