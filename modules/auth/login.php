<?php
/**
 * SmartQMS -- Unified Login Handler
 * Handles POST from index.php login form.
 * Detects role from database -> redirects to correct dashboard.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/auth_utils.php';

requirePostRequest(false, 'index.php', 'login', [], 'Please sign in using the form.');

$credentials = normalizedLoginCredentials($_POST);
$loginId = $credentials['login_id'];
$email = $credentials['email'];
$password = $credentials['password'];
$oldInput = ['login_id' => $loginId];
$fieldErrors = [];

requireValidCsrf('index.php', 'login', $oldInput);

if (!hasRequiredText($loginId)) {
    $fieldErrors['login_id'] = 'Email address is required.';
} elseif (!isValidEmail($loginId)) {
    $fieldErrors['login_id'] = 'Enter a valid email address.';
}

if (!hasRequiredText($password, false)) {
    $fieldErrors['password'] = 'Password is required.';
}

if ($fieldErrors) {
    redirectWithFormFeedback('index.php', 'login', $fieldErrors, $oldInput);
}

$loginThrottle = authThrottleStatus($conn, 'login', $email, LOGIN_ATTEMPT_LIMIT, LOGIN_ATTEMPT_WINDOW_SECONDS);
if (!$loginThrottle['allowed']) {
    redirectWithFormFeedback('index.php', 'login', [], $oldInput, authThrottleMessage((int) $loginThrottle['retry_after']));
}

$user = authUserByEmail($conn, $email);
$loginStatus = authLoginStatus($user, $password);
$invalidLoginMessage = 'Email or password is incorrect.';

if ($loginStatus === 'invalid_credentials') {
    recordAuthAttempt($conn, 'login', $email, LOGIN_ATTEMPT_LIMIT, LOGIN_ATTEMPT_WINDOW_SECONDS);
    redirectWithFormFeedback('index.php', 'login', [], $oldInput, $invalidLoginMessage);
}

if ($loginStatus === 'inactive') {
    recordAuthAttempt($conn, 'login', $email, LOGIN_ATTEMPT_LIMIT, LOGIN_ATTEMPT_WINDOW_SECONDS);
    redirectWithFormFeedback('index.php', 'login', [], $oldInput, 'This account cannot sign in right now.');
}

clearAuthAttempts($conn, 'login', $email);

if ($user['role'] === ROLE_CLIENT) {
    if ((int) $user['is_verified'] !== 1) {
        startRegistrationOtpSession((int) $user['user_id']);
        $queued = issueOtp($conn, (int) $user['user_id'], 'Your SmartQMS verification code is', 'otp');
        if (!$queued) {
            redirectWithFormFeedback('views/client/verify_otp.php', 'verify_otp', [], [], 'Could not send OTP email right now. Please try resending the code.');
        }
        markOtpSent();
        redirectTo('views/client/verify_otp.php', [
            'msg' => 'otp_sent',
            'notice' => 'verify_before_login',
        ]);
    }

    startLoginOtpSession((int) $user['user_id']);
    $queued = issueOtp($conn, (int) $user['user_id'], 'Your SmartQMS login code is', 'otp');
    if (!$queued) {
        redirectWithFormFeedback('index.php', 'login', [], $oldInput, 'Could not send OTP email right now. Please make sure your account has a valid email address.');
    }
    markOtpSent();
    redirectTo('views/client/verify_otp.php', ['msg' => 'login_otp_sent']);
}

completeLogin($conn, $user);
redirectAfterLogin($_SESSION['role']);
?>
