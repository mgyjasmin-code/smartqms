<?php
/**
 * SmartQMS -- OTP Verification
 * Verifies legacy registration codes. Interactive login no longer uses OTP.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/auth_utils.php';

$flow = $_SESSION['otp_flow'] ?? 'register';
if ($flow === 'login') {
    clearOtpSession();
    redirectTo('login/');
}
$formContext = otpFlowFormContext($flow);
requirePostRequest(false, $formContext['target'], $formContext['form_key']);

$userId = (int) ($_SESSION['otp_user_id'] ?? $_SESSION['pending_user_id'] ?? 0);
$otp = trim($_POST['otp_code'] ?? '');
$throttleIdentifier = $flow . ':' . $userId;

requireValidCsrf($formContext['target'], $formContext['form_key']);
ensureAuthSecuritySchema($conn);

$otpThrottle = authThrottleStatus($conn, 'otp_verify', $throttleIdentifier, OTP_VERIFY_ATTEMPT_LIMIT, OTP_VERIFY_ATTEMPT_WINDOW_SECONDS);
if (!$otpThrottle['allowed']) {
    redirectWithFormFeedback($formContext['target'], $formContext['form_key'], [
        'otp_code' => authThrottleMessage((int) $otpThrottle['retry_after']),
    ]);
}

if (!isPositiveIdentifier($userId) || !isSixDigitOtp($otp)) {
    if ($userId) {
        recordAuthAttempt($conn, 'otp_verify', $throttleIdentifier, OTP_VERIFY_ATTEMPT_LIMIT, OTP_VERIFY_ATTEMPT_WINDOW_SECONDS);
    }
    redirectWithFormFeedback($formContext['target'], $formContext['form_key'], [
        'otp_code' => 'Enter the 6-digit OTP code.',
    ]);
}

$otpStatus = verifyStoredOtp($conn, $userId, $otp);

if ($otpStatus === 'wrong') {
    recordAuthAttempt($conn, 'otp_verify', $throttleIdentifier, OTP_VERIFY_ATTEMPT_LIMIT, OTP_VERIFY_ATTEMPT_WINDOW_SECONDS);
    redirectWithFormFeedback($formContext['target'], $formContext['form_key'], [
        'otp_code' => 'Wrong OTP code.',
    ]);
}

if ($otpStatus === 'expired') {
    recordAuthAttempt($conn, 'otp_verify', $throttleIdentifier, OTP_VERIFY_ATTEMPT_LIMIT, OTP_VERIFY_ATTEMPT_WINDOW_SECONDS);
    redirectWithFormFeedback($formContext['target'], $formContext['form_key'], [
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
    verifyAuthUserEmail($conn, $userId);
    logActivity($conn, 'email_verified', 'Client verified email OTP', null, $userId, ROLE_CLIENT);
    $fullUser['is_verified'] = 1;
    clearOtpSession();
    completeLogin($conn, $fullUser);
    redirectAfterLogin($fullUser['role'], ['msg' => 'registered']);
}

clearOtpSession();
redirectWithFormFeedback('index.php', 'login', [], [], 'OTP session expired. Please try again.');
?>
