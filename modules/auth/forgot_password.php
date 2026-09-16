<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/auth_utils.php';

requirePostRequest(false, 'forgot-password/', 'forgot_password_request');

$action = $_POST['action'] ?? 'request_otp';

if ($action === 'request_otp') {
    $email = normalizeEmail((string) ($_POST['email'] ?? ''));
    requireValidCsrf('forgot-password/', 'forgot_password_request', ['email' => $email]);
    if (!isValidEmail($email)) {
        redirectWithFormFeedback('forgot-password/', 'forgot_password_request', [
            'email' => $email === '' ? 'Registered email address is required.' : 'Enter a valid registered email address.',
        ], ['email' => $email]);
    }

    $resetThrottle = authThrottleStatus($conn, 'forgot_password', $email, FORGOT_PASSWORD_ATTEMPT_LIMIT, FORGOT_PASSWORD_ATTEMPT_WINDOW_SECONDS);
    if (!$resetThrottle['allowed']) {
        redirectWithFormFeedback('forgot-password/', 'forgot_password_request', [], ['email' => $email], authThrottleMessage((int) $resetThrottle['retry_after']));
    }
    recordAuthAttempt($conn, 'forgot_password', $email, FORGOT_PASSWORD_ATTEMPT_LIMIT, FORGOT_PASSWORD_ATTEMPT_WINDOW_SECONDS);

    $user = authUserByEmail($conn, $email);
    $eligible = $user
        && in_array((string) $user['role'], [ROLE_ADMIN, ROLE_STAFF], true)
        && (int) $user['is_active'] === 1;
    recordSecurityEvent($conn, 'password_reset_requested', 'accepted', 'account', null, ['reason' => $eligible ? 'eligible' : 'non_eligible']);

    clearOtpSession();
    if ($eligible) {
        startPasswordResetOtpSession((int) $user['user_id']);
        if (issueOtp($conn, (int) $user['user_id'], 'Your SmartQMS password reset code is', 'otp')) {
            markOtpSent();
        }
    } else {
        // Preserve the same response and page stage without creating authority.
        $_SESSION['reset_user_id'] = -1;
        setOtpSession('reset', -1);
    }
    markOtpSent();

    redirectTo('forgot-password/', ['msg' => 'reset_otp_sent']);
}

if ($action === 'verify_otp') {
    $userId = (int) ($_SESSION['reset_user_id'] ?? $_SESSION['otp_user_id'] ?? 0);
    $otp = trim($_POST['otp_code'] ?? '');
    $throttleIdentifier = 'reset:' . $userId;

    requireValidCsrf('forgot-password/', 'forgot_password_otp');
    ensureAuthSecuritySchema($conn);

    $otpThrottle = authThrottleStatus($conn, 'otp_verify', $throttleIdentifier, OTP_VERIFY_ATTEMPT_LIMIT, OTP_VERIFY_ATTEMPT_WINDOW_SECONDS);
    if (!$otpThrottle['allowed']) {
        redirectWithFormFeedback('forgot-password/', 'forgot_password_otp', [
            'otp_code' => authThrottleMessage((int) $otpThrottle['retry_after']),
        ]);
    }

    if (!isSixDigitOtp($otp)) {
        if ($userId) {
            recordAuthAttempt($conn, 'otp_verify', $throttleIdentifier, OTP_VERIFY_ATTEMPT_LIMIT, OTP_VERIFY_ATTEMPT_WINDOW_SECONDS);
        }
        redirectWithFormFeedback('forgot-password/', 'forgot_password_otp', [
            'otp_code' => 'Enter the 6-digit OTP code.',
        ]);
    }

    if (!isPositiveIdentifier($userId)) {
        recordAuthAttempt($conn, 'otp_verify', $throttleIdentifier, OTP_VERIFY_ATTEMPT_LIMIT, OTP_VERIFY_ATTEMPT_WINDOW_SECONDS);
        redirectWithFormFeedback('forgot-password/', 'forgot_password_otp', [
            'otp_code' => 'The security code is invalid or expired.',
        ]);
    }

$otpStatus = verifyStoredOtp($conn, $userId, $otp);

    if ($otpStatus === 'wrong') {
        recordAuthAttempt($conn, 'otp_verify', $throttleIdentifier, OTP_VERIFY_ATTEMPT_LIMIT, OTP_VERIFY_ATTEMPT_WINDOW_SECONDS);
        redirectWithFormFeedback('forgot-password/', 'forgot_password_otp', [
            'otp_code' => 'The security code is invalid or expired.',
        ]);
    }

    if ($otpStatus === 'expired') {
        recordAuthAttempt($conn, 'otp_verify', $throttleIdentifier, OTP_VERIFY_ATTEMPT_LIMIT, OTP_VERIFY_ATTEMPT_WINDOW_SECONDS);
        redirectWithFormFeedback('forgot-password/', 'forgot_password_otp', [
            'otp_code' => 'The security code is invalid or expired.',
        ]);
    }

    clearAuthAttempts($conn, 'otp_verify', $throttleIdentifier);
    clearAuthOtp($conn, $userId);
    markPasswordResetOtpVerified($conn, $userId);
    redirectTo('forgot-password/', ['msg' => 'otp_verified']);
}

if ($action === 'reset_password') {
    $capability = (string) ($_SESSION['reset_capability'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    requireValidCsrf('forgot-password/', 'reset_password');

    if (!preg_match('/^[a-f0-9]{64}$/', $capability)) {
        redirectWithFormFeedback('forgot-password/', 'reset_password', [], [], 'Please verify your OTP before changing your password.');
    }

    $fieldErrors = [];
    if (!hasRequiredText($password, false)) {
        $fieldErrors['password'] = 'New password is required.';
    } elseif (strlen($password) < 12
        || !preg_match('/[A-Z]/', $password)
        || !preg_match('/[a-z]/', $password)
        || !preg_match('/\d/', $password)) {
        $fieldErrors['password'] = 'Use at least 12 characters with uppercase, lowercase, and a number.';
    }
    if (!hasRequiredText($confirmPassword, false)) {
        $fieldErrors['confirm_password'] = 'Please re-type your password.';
    } elseif (!passwordsMatch($password, $confirmPassword)) {
        $fieldErrors['confirm_password'] = 'Password entries do not match.';
    }

    if ($fieldErrors) {
        redirectWithFormFeedback('forgot-password/', 'reset_password', $fieldErrors);
    }

    $conn->begin_transaction();
    try {
        $userId = consumePasswordResetCapability($conn, $capability);
        if (!$userId) {
            throw new DomainException('The password reset authorization expired or was already used.');
        }
        $user = authUserById($conn, $userId);
        $role = (string) ($user['role'] ?? '');
        if (!in_array($role, [ROLE_ADMIN, ROLE_STAFF], true) || (int) ($user['is_active'] ?? 0) !== 1) {
            throw new DomainException('The password reset authorization is no longer valid.');
        }
        resetAuthPassword($conn, $userId, $password);
        logActivity($conn, 'password_reset', 'Staff or administrator reset password after OTP verification', null, $userId, $role);
        $conn->commit();
    } catch (DomainException $error) {
        $conn->rollback();
        clearOtpSession();
        redirectWithFormFeedback('forgot-password/', 'forgot_password_request', [], [], $error->getMessage());
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }

    clearOtpSession();
    redirectTo('login/', ['msg' => 'password_reset']);
}

requireValidCsrf('forgot-password/', 'forgot_password_request');
redirectWithFormFeedback('forgot-password/', 'forgot_password_request', [], [], 'Invalid password reset request.');
?>
