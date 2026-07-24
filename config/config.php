<?php
/**
 * SmartQMS application configuration loaded by every entry point.
 */

// Runtime and session storage.
defined('RUNTIME_DIR') || define('RUNTIME_DIR', __DIR__ . '/../storage');
defined('SESSION_DIR') || define('SESSION_DIR', RUNTIME_DIR . '/sessions');
defined('LOG_DIR') || define('LOG_DIR', RUNTIME_DIR . '/logs');

foreach ([RUNTIME_DIR, SESSION_DIR, LOG_DIR] as $runtimePath) {
    if (!is_dir($runtimePath)) {
        mkdir($runtimePath, 0775, true);
    }
}

if (is_dir(SESSION_DIR) && is_writable(SESSION_DIR)) {
    session_save_path(SESSION_DIR);
}

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: frame-ancestors 'self'");
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

// Application identity and URL.
define('APP_NAME',    'SmartQMS -- Barangay Health Center');
define('APP_VERSION', '3.0');
define('APP_URL',     'http://localhost/smartqms');

// Optional Flask prediction service.
define('ML_API_URL', 'http://localhost:5000/predict');
define('ML_HEALTH',  'http://localhost:5000/health');

// QR code storage.
define('QR_DIR', __DIR__ . '/../assets/qr/');
define('QR_URL', APP_URL . '/assets/qr/');

// Ticket reference format: BHC-YYYY-NNNN.
define('REF_PREFIX', 'BHC');

date_default_timezone_set('Asia/Manila');

// Roles and authentication limits.
define('ROLE_CLIENT', 'client');
define('ROLE_STAFF',  'staff');
define('ROLE_ADMIN',  'admin');

define('OTP_RESEND_COOLDOWN_SECONDS', 120);
define('LOGIN_ATTEMPT_LIMIT', 5);
define('LOGIN_ATTEMPT_WINDOW_SECONDS', 900);
define('OTP_VERIFY_ATTEMPT_LIMIT', 5);
define('OTP_VERIFY_ATTEMPT_WINDOW_SECONDS', 600);
define('OTP_RESEND_ATTEMPT_LIMIT', 5);
define('OTP_RESEND_ATTEMPT_WINDOW_SECONDS', 3600);
define('FORGOT_PASSWORD_ATTEMPT_LIMIT', 3);
define('FORGOT_PASSWORD_ATTEMPT_WINDOW_SECONDS', 900);
define('REGISTER_ATTEMPT_LIMIT', 5);
define('REGISTER_ATTEMPT_WINDOW_SECONDS', 3600);

require_once __DIR__ . '/helpers.php';
