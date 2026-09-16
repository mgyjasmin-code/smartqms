<?php
/**
 * Authentication and OTP session lifecycle helpers.
 */

function setOtpSession(string $flow, int $userId): void {
    $_SESSION['otp_flow'] = $flow;
    $_SESSION['otp_user_id'] = $userId;
    $_SESSION['otp_started_at'] = time();
}

function startRegistrationOtpSession(int $userId): void {
    $_SESSION['pending_user_id'] = $userId;
    setOtpSession('register', $userId);
}

function startPasswordResetOtpSession(int $userId): void {
    $_SESSION['reset_user_id'] = $userId;
    setOtpSession('reset', $userId);
}

function markPasswordResetOtpVerified(mysqli $conn, int $userId): void {
    $_SESSION['reset_capability'] = createPasswordResetCapability($conn, $userId);
    unset($_SESSION['reset_user_id']);
}

function markOtpSent(): void {
    $_SESSION['otp_last_sent_at'] = time();
}

function clearOtpSession(): void {
    unset(
        $_SESSION['otp_flow'],
        $_SESSION['otp_user_id'],
        $_SESSION['otp_started_at'],
        $_SESSION['pending_user_id'],
        $_SESSION['pending_login_user_id'],
        $_SESSION['otp_last_sent_at'],
        $_SESSION['reset_user_id'],
        $_SESSION['reset_capability']
    );
}

function completeLogin(mysqli $conn, array $user): void {
    session_regenerate_id(true);
    $now = time();
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['name'] = trim($user['first_name'] . ' ' . $user['last_name']);
    $_SESSION['phone'] = $user['phone_number'] ?? '';
    $_SESSION['email'] = $user['email'] ?? '';
    $_SESSION['session_version'] = max(1, (int) ($user['session_version'] ?? 1));
    $_SESSION['auth_created_at'] = $now;
    $_SESSION['auth_last_activity_at'] = $now;
    $_SESSION['auth_rotated_at'] = $now;

    if ($user['role'] === ROLE_STAFF) {
        $staffId = authStaffIdByUserId($conn, $_SESSION['user_id']);
        if ($staffId !== null) {
            $_SESSION['staff_id'] = $staffId;
        }
    }

    recordAuthLoginCompletion($conn, $_SESSION['user_id']);
    logActivity($conn, 'login', 'User signed in');
}

function destroyAuthenticatedSession(): void {
    destroyLocalSessionState();
}
