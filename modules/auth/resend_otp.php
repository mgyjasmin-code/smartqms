<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/auth_utils.php';

requirePostRequest(false, 'index.php', 'login');

$flow = $_SESSION['otp_flow'] ?? '';
$userId = (int) ($_SESSION['otp_user_id'] ?? 0);
$lastSent = (int) ($_SESSION['otp_last_sent_at'] ?? 0);

if (!$flow || !$userId) {
    redirectWithFormFeedback('index.php', 'login', [], [], 'OTP session expired. Please try again.');
}

$target = $flow === 'reset' ? 'views/client/forgot_password.php' : 'views/client/verify_otp.php';
$formKey = $flow === 'reset' ? 'forgot_password_otp' : 'verify_otp';

requireValidCsrf($target, $formKey);

if ($lastSent && time() - $lastSent < OTP_RESEND_COOLDOWN_SECONDS) {
    redirectWithFormFeedback($target, $formKey, [], [], 'Please wait 2 minutes before requesting another OTP email.');
}

$throttleIdentifier = 'resend:' . $flow . ':' . $userId;
$resendThrottle = authThrottleStatus($conn, 'otp_resend', $throttleIdentifier, OTP_RESEND_ATTEMPT_LIMIT, OTP_RESEND_ATTEMPT_WINDOW_SECONDS);
if (!$resendThrottle['allowed']) {
    redirectWithFormFeedback($target, $formKey, [], [], authThrottleMessage((int) $resendThrottle['retry_after']));
}
recordAuthAttempt($conn, 'otp_resend', $throttleIdentifier, OTP_RESEND_ATTEMPT_LIMIT, OTP_RESEND_ATTEMPT_WINDOW_SECONDS);

$prefix = $flow === 'login'
    ? 'Your SmartQMS login code is'
    : ($flow === 'reset' ? 'Your SmartQMS password reset code is' : 'Your SmartQMS verification code is');

$queued = issueOtp($conn, $userId, $prefix, 'otp');
if ($queued) {
    $_SESSION['otp_last_sent_at'] = time();
}

if (!$queued) {
    redirectWithFormFeedback($target, $formKey, [], [], 'Could not queue OTP email right now. Please try again later.');
}

redirectTo($target, ['msg' => 'otp_resent']);
?>
