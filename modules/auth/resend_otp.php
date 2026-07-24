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

$formContext = otpFlowFormContext($flow);
$target = $formContext['target'];
$formKey = $formContext['form_key'];

requireValidCsrf($target, $formKey);

if (otpResendCooldownActive($lastSent)) {
    redirectWithFormFeedback($target, $formKey, [], [], 'Please wait 2 minutes before requesting another OTP email.');
}

$throttleIdentifier = 'resend:' . $flow . ':' . $userId;
$resendThrottle = authThrottleStatus($conn, 'otp_resend', $throttleIdentifier, OTP_RESEND_ATTEMPT_LIMIT, OTP_RESEND_ATTEMPT_WINDOW_SECONDS);
if (!$resendThrottle['allowed']) {
    redirectWithFormFeedback($target, $formKey, [], [], authThrottleMessage((int) $resendThrottle['retry_after']));
}
recordAuthAttempt($conn, 'otp_resend', $throttleIdentifier, OTP_RESEND_ATTEMPT_LIMIT, OTP_RESEND_ATTEMPT_WINDOW_SECONDS);

$prefix = otpMessagePrefixForFlow($flow);

$queued = issueOtp($conn, $userId, $prefix, 'otp');
if ($queued) {
    markOtpSent();
}

if (!$queued) {
    redirectWithFormFeedback($target, $formKey, [], [], 'Could not queue OTP email right now. Please try again later.');
}

redirectTo($target, ['msg' => 'otp_resent']);
?>
