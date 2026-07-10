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

$loginId = trim($_POST['login_id'] ?? '');
$password = $_POST['password'] ?? '';
$oldInput = ['login_id' => $loginId];
$fieldErrors = [];

requireValidCsrf('index.php', 'login', $oldInput);

if ($loginId === '') {
    $fieldErrors['login_id'] = 'Email address is required.';
} elseif (!filter_var($loginId, FILTER_VALIDATE_EMAIL)) {
    $fieldErrors['login_id'] = 'Enter a valid email address.';
}

if ($password === '') {
    $fieldErrors['password'] = 'Password is required.';
}

if ($fieldErrors) {
    redirectWithFormFeedback('index.php', 'login', $fieldErrors, $oldInput);
}

$email = strtolower($loginId);
$loginThrottle = authThrottleStatus($conn, 'login', $email, LOGIN_ATTEMPT_LIMIT, LOGIN_ATTEMPT_WINDOW_SECONDS);
if (!$loginThrottle['allowed']) {
    redirectWithFormFeedback('index.php', 'login', [], $oldInput, authThrottleMessage((int) $loginThrottle['retry_after']));
}

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$invalidLoginMessage = 'Email or password is incorrect.';

if (!$user) {
    recordAuthAttempt($conn, 'login', $email, LOGIN_ATTEMPT_LIMIT, LOGIN_ATTEMPT_WINDOW_SECONDS);
    redirectWithFormFeedback('index.php', 'login', [], $oldInput, $invalidLoginMessage);
}

if (!password_verify($password, $user['password_hash'])) {
    recordAuthAttempt($conn, 'login', $email, LOGIN_ATTEMPT_LIMIT, LOGIN_ATTEMPT_WINDOW_SECONDS);
    redirectWithFormFeedback('index.php', 'login', [], $oldInput, $invalidLoginMessage);
}

if ((int) $user['is_active'] !== 1) {
    recordAuthAttempt($conn, 'login', $email, LOGIN_ATTEMPT_LIMIT, LOGIN_ATTEMPT_WINDOW_SECONDS);
    redirectWithFormFeedback('index.php', 'login', [], $oldInput, 'This account cannot sign in right now.');
}

clearAuthAttempts($conn, 'login', $email);

if ($user['role'] === ROLE_CLIENT) {
    if ((int) $user['is_verified'] !== 1) {
        $_SESSION['pending_user_id'] = (int) $user['user_id'];
        setOtpSession('register', (int) $user['user_id']);
        $queued = issueOtp($conn, (int) $user['user_id'], 'Your SmartQMS verification code is', 'otp');
        if (!$queued) {
            redirectWithFormFeedback('views/client/verify_otp.php', 'verify_otp', [], [], 'Could not send OTP email right now. Please try resending the code.');
        }
        $_SESSION['otp_last_sent_at'] = time();
        redirectTo('views/client/verify_otp.php', [
            'msg' => 'otp_sent',
            'notice' => 'verify_before_login',
        ]);
    }

    $_SESSION['pending_login_user_id'] = (int) $user['user_id'];
    setOtpSession('login', (int) $user['user_id']);
    $queued = issueOtp($conn, (int) $user['user_id'], 'Your SmartQMS login code is', 'otp');
    if (!$queued) {
        redirectWithFormFeedback('index.php', 'login', [], $oldInput, 'Could not send OTP email right now. Please make sure your account has a valid email address.');
    }
    $_SESSION['otp_last_sent_at'] = time();
    redirectTo('views/client/verify_otp.php', ['msg' => 'login_otp_sent']);
}

completeLogin($conn, $user);
redirectAfterLogin($_SESSION['role']);
?>
