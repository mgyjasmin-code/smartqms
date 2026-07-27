<?php
/**
 * Authentication-specific persistence operations.
 */

function authUserById(mysqli $conn, int $userId): ?array {
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function authUserByEmail(mysqli $conn, string $email): ?array {
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function authClientByEmail(mysqli $conn, string $email): ?array {
    $role = ROLE_CLIENT;
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND role = ? LIMIT 1");
    $stmt->bind_param('ss', $email, $role);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function authEmailExists(mysqli $conn, string $email): bool {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}

function createUnverifiedClient(
    mysqli $conn,
    string $firstName,
    string $lastName,
    string $email,
    string $passwordHash
): int {
    $role = ROLE_CLIENT;
    $verified = 0;
    $stmt = $conn->prepare("
        INSERT INTO users (first_name, last_name, email, password_hash, role, is_verified)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('sssssi', $firstName, $lastName, $email, $passwordHash, $role, $verified);
    $stmt->execute();
    return (int) $conn->insert_id;
}

function authOtpRecordByUserId(mysqli $conn, int $userId): ?array {
    $stmt = $conn->prepare("SELECT user_id, otp_hash, otp_expires_at FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function storeAuthOtp(mysqli $conn, int $userId, string $otpHash, string $expiresAt): void {
    $stmt = $conn->prepare("UPDATE users SET otp_code = NULL, otp_hash = ?, otp_expires_at = ? WHERE user_id = ?");
    $stmt->bind_param('ssi', $otpHash, $expiresAt, $userId);
    $stmt->execute();
}

function clearAuthOtp(mysqli $conn, int $userId): void {
    $stmt = $conn->prepare("UPDATE users SET otp_code = NULL, otp_hash = NULL, otp_expires_at = NULL WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
}

function verifyAuthUserEmail(mysqli $conn, int $userId): void {
    $stmt = $conn->prepare("
        UPDATE users
        SET is_verified = 1, otp_code = NULL, otp_hash = NULL, otp_expires_at = NULL
        WHERE user_id = ?
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
}

function resetAuthUserPassword(mysqli $conn, int $userId, string $passwordHash): void {
    $stmt = $conn->prepare("
        UPDATE users
        SET password_hash = ?, is_verified = 1, otp_code = NULL, otp_hash = NULL, otp_expires_at = NULL
        WHERE user_id = ?
    ");
    $stmt->bind_param('si', $passwordHash, $userId);
    $stmt->execute();
}

function authStaffIdByUserId(mysqli $conn, int $userId): ?int {
    $stmt = $conn->prepare("SELECT staff_id FROM staff WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $staff = $stmt->get_result()->fetch_assoc();
    return $staff ? (int) $staff['staff_id'] : null;
}

function recordAuthLoginCompletion(mysqli $conn, int $userId): void {
    ensureAuthSecuritySchema($conn);
    $stmt = $conn->prepare("
        UPDATE users
        SET last_login_at = NOW(), otp_code = NULL, otp_hash = NULL, otp_expires_at = NULL
        WHERE user_id = ?
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
}
