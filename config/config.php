<?php
/**
 * SmartQMS application configuration loaded by every entry point.
 */

$smartqmsEnvironment = strtolower(trim((string) (getenv('SMARTQMS_APP_ENV') ?: 'development')));
if (!in_array($smartqmsEnvironment, ['development', 'testing', 'production'], true)) {
    throw new RuntimeException('SMARTQMS_APP_ENV must be development, testing, or production.');
}
define('APP_ENV', $smartqmsEnvironment);
define('APP_DEBUG', APP_ENV !== 'production' && (getenv('SMARTQMS_APP_DEBUG') ?: '0') === '1');

ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

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

$directHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443');
$trustedProxyEnabled = (getenv('SMARTQMS_TRUST_PROXY') ?: '0') === '1';
$trustedProxyIps = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) (getenv('SMARTQMS_TRUSTED_PROXY_IPS') ?: ''))
)));
$trustedProxy = $trustedProxyEnabled
    && in_array((string) ($_SERVER['REMOTE_ADDR'] ?? ''), $trustedProxyIps, true);
$forwardedHttps = $trustedProxy
    && strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0])) === 'https';
$isHttps = $directHttps || $forwardedHttps;

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_name('SMARTQMSSESSID');
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
    header_remove('X-Powered-By');
    $correlationId = preg_match('/^[a-f0-9]{32}$/', (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''))
        ? (string) $_SERVER['HTTP_X_REQUEST_ID']
        : bin2hex(random_bytes(16));
    define('SMARTQMS_CORRELATION_ID', $correlationId);
    define('SMARTQMS_CSP_NONCE', base64_encode(random_bytes(18)));
    header('X-Request-ID: ' . $correlationId);
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; script-src 'self' 'nonce-" . SMARTQMS_CSP_NONCE . "'; style-src 'self' 'unsafe-inline'");
    header('Referrer-Policy: no-referrer');
    // Arrival Check-In may use the same-origin camera after an explicit Staff
    // gesture. Microphone and location remain unavailable project-wide.
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
    $requestPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    foreach (['/views/admin/', '/views/staff/', '/api/admin/', '/staff/', '/login/', '/forgot-password/', '/verify-login/', '/change-password/', '/manage-reservation/', '/track/'] as $privatePath) {
        if (str_contains($requestPath, $privatePath)) {
            header('Cache-Control: no-store, private, max-age=0');
            header('Pragma: no-cache');
            break;
        }
    }
}

// Application identity and URL.
define('APP_NAME',    'SmartQMS -- Barangay Health Center');
define('APP_VERSION', '3.0');
$configuredAppUrl = rtrim(trim((string) (getenv('SMARTQMS_APP_URL') ?: '')), '/');
if ($configuredAppUrl === '') {
    $configuredAppUrl = 'http://localhost/smartqms';
}
if (APP_ENV === 'production') {
    if ($trustedProxyEnabled && !$trustedProxyIps) {
        throw new RuntimeException('SMARTQMS_TRUSTED_PROXY_IPS is required when proxy trust is enabled.');
    }
    if (!filter_var($configuredAppUrl, FILTER_VALIDATE_URL)
        || strtolower((string) parse_url($configuredAppUrl, PHP_URL_SCHEME)) !== 'https') {
        throw new RuntimeException('Production requires SMARTQMS_APP_URL with an HTTPS URL.');
    }
    if (!$isHttps && PHP_SAPI !== 'cli') {
        http_response_code(503);
        exit('SmartQMS requires HTTPS.');
    }
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
define('APP_URL', $configuredAppUrl);

// Optional Flask prediction service. Credentials remain server-only.
require_once __DIR__ . '/ml.php';
$smartqmsMlConfig = smartqmsMlConfig();
define('ML_API_URL', (string) $smartqmsMlConfig['predict_url']);
define('ML_HEALTH', (string) $smartqmsMlConfig['health_url']);

// QR code storage.
define('QR_DIR', __DIR__ . '/../assets/qr/');
define('QR_URL', APP_URL . '/assets/qr/');

// REF_PREFIX is retained for lookup of older BHC references.
define('REF_PREFIX', 'BHC');

date_default_timezone_set('Asia/Manila');

// Roles and authentication limits.
define('ROLE_CLIENT', 'client');
define('ROLE_STAFF',  'staff');
define('ROLE_ADMIN',  'admin');

require_once __DIR__ . '/supabase.php';

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
define('SESSION_IDLE_TIMEOUT_SECONDS', 1800);
define('SESSION_ABSOLUTE_TIMEOUT_SECONDS', 28800);
define('SESSION_ROTATE_SECONDS', 900);
define('PASSWORD_RESET_CAPABILITY_SECONDS', 300);
define('RESERVATION_VERIFY_ATTEMPT_LIMIT', 5);
define('RESERVATION_VERIFY_ATTEMPT_WINDOW_SECONDS', 900);
define('PUBLIC_REFERENCE_ATTEMPT_LIMIT', 5);
define('PUBLIC_REFERENCE_ATTEMPT_WINDOW_SECONDS', 900);
define('PUBLIC_REFERENCE_IP_ATTEMPT_LIMIT', 20);
define('PUBLIC_BOOKING_PHONE_LIMIT', 5);
define('PUBLIC_BOOKING_IP_LIMIT', 20);
define('PUBLIC_BOOKING_WINDOW_SECONDS', 3600);

require_once __DIR__ . '/helpers.php';
