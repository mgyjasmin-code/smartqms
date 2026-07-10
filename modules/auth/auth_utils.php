<?php
/**
 * Shared helpers for auth flows that use email OTP.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../notifications/email_sender.php';

function ensureAuthSecuritySchema(mysqli $conn): void {
    static $done = false;
    if ($done) {
        return;
    }

    ensureAuthAttemptsTable($conn);
    $columns = $conn->query("SHOW COLUMNS FROM users LIKE 'otp_hash'");
    if ($columns && $columns->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN otp_hash VARCHAR(255) DEFAULT NULL AFTER otp_code");
    }
    $done = true;
}

function authUserById(mysqli $conn, int $userId): ?array {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function setOtpSession(string $flow, int $userId): void {
    $_SESSION['otp_flow'] = $flow;
    $_SESSION['otp_user_id'] = $userId;
    $_SESSION['otp_started_at'] = time();
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
        $_SESSION['reset_verified_user_id']
    );
}

function issueOtp(mysqli $conn, int $userId, string $messagePrefix, string $type = 'otp', bool $queue = true): bool {
    ensureAuthSecuritySchema($conn);
    $user = authUserById($conn, $userId);
    if (!$user || empty($user['email'])) {
        return false;
    }

    $otp = (string) random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_BCRYPT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $stmt = $conn->prepare("UPDATE users SET otp_code = NULL, otp_hash = ?, otp_expires_at = ? WHERE user_id = ?");
    $stmt->bind_param('ssi', $otpHash, $expiresAt, $userId);
    $stmt->execute();

    $subject = 'Your SmartQMS verification code';
    $message = $messagePrefix . " {$otp}.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.";

    if ($queue) {
        cancelPendingEmailJobs($conn, $userId, $type);
        return queueEmail($conn, $user['email'], $subject, $message, $type, $userId);
    }

    return sendEmail($user['email'], $subject, $message, $type, $userId);
}

function completeLogin(mysqli $conn, array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['name'] = trim($user['first_name'] . ' ' . $user['last_name']);
    $_SESSION['phone'] = $user['phone_number'] ?? '';
    $_SESSION['email'] = $user['email'] ?? '';

    if ($user['role'] === ROLE_STAFF) {
        $staffStmt = $conn->prepare("SELECT staff_id FROM staff WHERE user_id = ? LIMIT 1");
        $staffStmt->bind_param('i', $_SESSION['user_id']);
        $staffStmt->execute();
        $staff = $staffStmt->get_result()->fetch_assoc();
        if ($staff) {
            $_SESSION['staff_id'] = (int) $staff['staff_id'];
        }
    }

    ensureAuthSecuritySchema($conn);
    $update = $conn->prepare("UPDATE users SET last_login_at = NOW(), otp_code = NULL, otp_hash = NULL, otp_expires_at = NULL WHERE user_id = ?");
    $update->bind_param('i', $_SESSION['user_id']);
    $update->execute();
    logActivity($conn, 'login', 'User signed in');
}

function redirectAfterLogin(string $role, array $params = []): void {
    if ($role === ROLE_ADMIN) {
        redirectTo('views/admin/dashboard.php', $params);
    }
    if ($role === ROLE_STAFF) {
        redirectTo('views/staff/dashboard.php', $params);
    }
    redirectTo('views/client/index.php', $params);
}
?>
