<?php
/**
 * SmartQMS -- Application Configuration
 * Loaded by every page via require_once.
 */

// ── Session ───────────────────────────────────────────────────
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

// ── App constants ─────────────────────────────────────────────
define('APP_NAME',    'SmartQMS -- Barangay Health Center');
define('APP_VERSION', '3.0');
define('APP_URL',     'http://localhost/smartqms');

// ── ML API ────────────────────────────────────────────────────
// Flask prediction server -- run: python ml/app.py
define('ML_API_URL',  'http://localhost:5000/predict');
define('ML_HEALTH',   'http://localhost:5000/health');

// ── QR Code storage ───────────────────────────────────────────
define('QR_DIR',      __DIR__ . '/../assets/qr/');
define('QR_URL',      APP_URL . '/assets/qr/');

// ── Reference number format ───────────────────────────────────
// BHC-YYYY-NNNN  e.g. BHC-2025-0001
define('REF_PREFIX',  'BHC');

// ── Timezone ──────────────────────────────────────────────────
date_default_timezone_set('Asia/Manila');

// ── Role constants ────────────────────────────────────────────
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

// ── Helper: check if user is logged in ────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// ── Helper: require login, redirect if not ───────────────────
function requireLogin(string $role = ''): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/index.php');
        exit();
    }
    if ($role && $_SESSION['role'] !== $role) {
        header('Location: ' . APP_URL . '/index.php');
        exit();
    }
}

// ── Helper: generate reference number ─────────────────────────
function generateRefNumber(mysqli $conn): string {
    $year = date('Y');
    $sql  = "SELECT COUNT(*) AS cnt FROM queue_tickets
             WHERE YEAR(issued_at) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['cnt'] + 1;
    return REF_PREFIX . '-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}
?>
