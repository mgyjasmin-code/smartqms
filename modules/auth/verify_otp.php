<?php
/**
 * SmartQMS -- OTP Verification
 * Verifies the 6-digit email OTP for registration or login.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/auth_utils.php';

requirePostRequest(false, 'views/client/verify_otp.php', 'verify_otp');

$flow = $_SESSION['otp_flow'] ?? 'register';
$userId = (int) ($_SESSION['otp_user_id'] ?? $_SESSION['pending_user_id'] ?? $_SESSION['pending_login_user_id'] ?? 0);
$otp = trim($_POST['otp_code'] ?? '');
$throttleIdentifier = $flow . ':' . $userId;

requireValidCsrf('views/client/verify_otp.php', 'verify_otp');
ensureAuthSecuritySchema($conn);

$otpThrottle = authThrottleStatus($conn, 'otp_verify', $throttleIdentifier, OTP_VERIFY_ATTEMPT_LIMIT, OTP_VERIFY_ATTEMPT_WINDOW_SECONDS);
if (!$otpThrottle['allowed']) {
    redirectWithFormFeedback('views/client/verify_otp.php', 'verify_otp', [
        'otp_code' => authThrottleMessage((int) $otpThrottle['retry_after']),
    ]);
}

if (!$userId || !preg_match('/^\d{6}$/', $otp)) {
    if ($userId) {
        recordAuthAttempt($conn, 'otp_verify', $throttleIdentifier, OTP_VERIFY_ATTEMPT_LIMIT, OTP_VERIFY_ATTEMPT_WINDOW_SECONDS);
    }
    redirectWithFormFeedback('views/client/verify_otp.php', 'verify_otp', [
        'otp_code' => 'Enter the 6-digit OTP code.',
    ]);
}

$stmt = $conn->prepare("SELECT user_id, otp_hash, otp_expires_at FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || empty($user['otp_hash']) || !password_verify($otp, $user['otp_hash'])) {
    recordAuthAttempt($conn, 'otp_verify', $throttleIdentifier, OTP_VERIFY_ATTEMPT_LIMIT, OTP_VERIFY_ATTEMPT_WINDOW_SECONDS);
    redirectWithFormFeedback('views/client/verify_otp.php', 'verify_otp', [
        'otp_code' => 'Wrong OTP code.',
    ]);
}

if (strtotime($user['otp_expires_at']) < time()) {
    recordAuthAttempt($conn, 'otp_verify', $throttleIdentifier, OTP_VERIFY_ATTEMPT_LIMIT, OTP_VERIFY_ATTEMPT_WINDOW_SECONDS);
    redirectWithFormFeedback('views/client/verify_otp.php', 'verify_otp', [
        'otp_code' => 'OTP expired. Please register again or ask the staff to help reset it.',
    ]);
}

$fullUser = authUserById($conn, $userId);
if (!$fullUser) {
    clearOtpSession();
    redirectWithFormFeedback('index.php', 'login', [], [], 'Account could not be found.');
}

if ($flow === 'register') {
    clearAuthAttempts($conn, 'otp_verify', $throttleIdentifier);
    $update = $conn->prepare("UPDATE users SET is_verified = 1, otp_code = NULL, otp_hash = NULL, otp_expires_at = NULL WHERE user_id = ?");
    $update->bind_param('i', $userId);
    $update->execute();
    logActivity($conn, 'email_verified', 'Client verified email OTP', null, $userId, ROLE_CLIENT);
    $fullUser['is_verified'] = 1;
    clearOtpSession();
    completeLogin($conn, $fullUser);
    redirectAfterLogin($fullUser['role'], ['msg' => 'registered']);
}

if ($flow === 'login') {
    clearAuthAttempts($conn, 'otp_verify', $throttleIdentifier);
    $update = $conn->prepare("UPDATE users SET otp_code = NULL, otp_hash = NULL, otp_expires_at = NULL WHERE user_id = ?");
    $update->bind_param('i', $userId);
    $update->execute();
    clearOtpSession();
    completeLogin($conn, $fullUser);
    redirectAfterLogin($fullUser['role'], ['msg' => 'login_verified']);
}

clearOtpSession();
redirectWithFormFeedback('index.php', 'login', [], [], 'OTP session expired. Please try again.');
?>
