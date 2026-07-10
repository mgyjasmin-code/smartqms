<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/auth_utils.php';

requirePostRequest(false, 'views/client/forgot_password.php', 'forgot_password_request');

$action = $_POST['action'] ?? 'request_otp';

if ($action === 'request_otp') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    requireValidCsrf('views/client/forgot_password.php', 'forgot_password_request', ['email' => $email]);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirectWithFormFeedback('views/client/forgot_password.php', 'forgot_password_request', [
            'email' => $email === '' ? 'Registered email address is required.' : 'Enter a valid registered email address.',
        ], ['email' => $email]);
    }

    $resetThrottle = authThrottleStatus($conn, 'forgot_password', $email, FORGOT_PASSWORD_ATTEMPT_LIMIT, FORGOT_PASSWORD_ATTEMPT_WINDOW_SECONDS);
    if (!$resetThrottle['allowed']) {
        redirectWithFormFeedback('views/client/forgot_password.php', 'forgot_password_request', [], ['email' => $email], authThrottleMessage((int) $resetThrottle['retry_after']));
    }
    recordAuthAttempt($conn, 'forgot_password', $email, FORGOT_PASSWORD_ATTEMPT_LIMIT, FORGOT_PASSWORD_ATTEMPT_WINDOW_SECONDS);

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND role = ? LIMIT 1");
    $role = ROLE_CLIENT;
    $stmt->bind_param('ss', $email, $role);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || (int) $user['is_active'] !== 1) {
        redirectWithFormFeedback('views/client/forgot_password.php', 'forgot_password_request', [], ['email' => $email], 'Could not send a reset code for that email. Please check the address and try again.');
    }

    $_SESSION['reset_user_id'] = (int) $user['user_id'];
    setOtpSession('reset', (int) $user['user_id']);
    $queued = issueOtp($conn, (int) $user['user_id'], 'Your SmartQMS password reset code is', 'otp');
    if ($queued) {
        $_SESSION['otp_last_sent_at'] = time();
    }

    if (!$queued) {
        redirectWithFormFeedback('views/client/forgot_password.php', 'forgot_password_otp', [], [], 'Could not queue OTP email right now. Please try again later.');
    }

    redirectTo('views/client/forgot_password.php', ['msg' => 'reset_otp_sent']);
}

if ($action === 'verify_otp') {
    $userId = (int) ($_SESSION['reset_user_id'] ?? $_SESSION['otp_user_id'] ?? 0);
    $otp = trim($_POST['otp_code'] ?? '');
    $throttleIdentifier = 'reset:' . $userId;

    requireValidCsrf('views/client/forgot_password.php', 'forgot_password_otp');
    ensureAuthSecuritySchema($conn);

    $otpThrottle = authThrottleStatus($conn, 'otp_verify', $throttleIdentifier, OTP_VERIFY_ATTEMPT_LIMIT, OTP_VERIFY_ATTEMPT_WINDOW_SECONDS);
    if (!$otpThrottle['allowed']) {
        redirectWithFormFeedback('views/client/forgot_password.php', 'forgot_password_otp', [
            'otp_code' => authThrottleMessage((int) $otpThrottle['retry_after']),
        ]);
    }

    if (!$userId || !preg_match('/^\d{6}$/', $otp)) {
        if ($userId) {
            recordAuthAttempt($conn, 'otp_verify', $throttleIdentifier, OTP_VERIFY_ATTEMPT_LIMIT, OTP_VERIFY_ATTEMPT_WINDOW_SECONDS);
        }
        redirectWithFormFeedback('views/client/forgot_password.php', 'forgot_password_otp', [
            'otp_code' => 'Enter the 6-digit OTP code.',
        ]);
    }

    $stmt = $conn->prepare("SELECT user_id, otp_hash, otp_expires_at FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || empty($user['otp_hash']) || !password_verify($otp, $user['otp_hash'])) {
        recordAuthAttempt($conn, 'otp_verify', $throttleIdentifier, OTP_VERIFY_ATTEMPT_LIMIT, OTP_VERIFY_ATTEMPT_WINDOW_SECONDS);
        redirectWithFormFeedback('views/client/forgot_password.php', 'forgot_password_otp', [
            'otp_code' => 'Wrong OTP code.',
        ]);
    }

    if (strtotime($user['otp_expires_at']) < time()) {
        recordAuthAttempt($conn, 'otp_verify', $throttleIdentifier, OTP_VERIFY_ATTEMPT_LIMIT, OTP_VERIFY_ATTEMPT_WINDOW_SECONDS);
        redirectWithFormFeedback('views/client/forgot_password.php', 'forgot_password_otp', [
            'otp_code' => 'OTP expired. Please request a new code.',
        ]);
    }

    clearAuthAttempts($conn, 'otp_verify', $throttleIdentifier);
    $_SESSION['reset_verified_user_id'] = $userId;
    redirectTo('views/client/forgot_password.php', ['msg' => 'otp_verified']);
}

if ($action === 'reset_password') {
    $userId = (int) ($_SESSION['reset_verified_user_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    requireValidCsrf('views/client/forgot_password.php', 'reset_password');

    if (!$userId) {
        redirectWithFormFeedback('views/client/forgot_password.php', 'reset_password', [], [], 'Please verify your OTP before changing your password.');
    }

    $fieldErrors = [];
    if ($password === '') {
        $fieldErrors['password'] = 'New password is required.';
    } elseif (strlen($password) < 8) {
        $fieldErrors['password'] = 'Password must be at least 8 characters.';
    }
    if ($confirmPassword === '') {
        $fieldErrors['confirm_password'] = 'Please re-type your password.';
    } elseif ($password !== $confirmPassword) {
        $fieldErrors['confirm_password'] = 'Password entries do not match.';
    }

    if ($fieldErrors) {
        redirectWithFormFeedback('views/client/forgot_password.php', 'reset_password', $fieldErrors);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    ensureAuthSecuritySchema($conn);
    $stmt = $conn->prepare("UPDATE users SET password_hash = ?, is_verified = 1, otp_code = NULL, otp_hash = NULL, otp_expires_at = NULL WHERE user_id = ?");
    $stmt->bind_param('si', $hash, $userId);
    $stmt->execute();
    logActivity($conn, 'password_reset', 'Client reset password after OTP verification', null, $userId, ROLE_CLIENT);

    $user = authUserById($conn, $userId);
    if (!$user || $user['role'] !== ROLE_CLIENT) {
        clearOtpSession();
        redirectTo('index.php', ['msg' => 'password_reset']);
    }

    completeLogin($conn, $user);
    clearOtpSession();
    redirectAfterLogin(ROLE_CLIENT, ['msg' => 'password_reset']);
}

requireValidCsrf('views/client/forgot_password.php', 'forgot_password_request');
redirectWithFormFeedback('views/client/forgot_password.php', 'forgot_password_request', [], [], 'Invalid password reset request.');
?>
