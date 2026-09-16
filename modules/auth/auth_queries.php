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

function authUserByLoginId(mysqli $conn, string $loginId): ?array {
    if (!smartqmsTableHasColumn($conn, 'users', 'username')) {
        return authUserByEmail($conn, normalizeEmail($loginId));
    }
    $canonical = strtolower(trim($loginId));
    $stmt = $conn->prepare('SELECT * FROM users WHERE LOWER(email) = ? OR LOWER(username) = ? LIMIT 1');
    $stmt->bind_param('ss', $canonical, $canonical);
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
    if (smartqmsTableHasColumn($conn, 'users', 'username')) {
        $stmt = $conn->prepare("
            INSERT INTO users (username, first_name, last_name, email, password_hash, role, is_verified)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('ssssssi', $email, $firstName, $lastName, $email, $passwordHash, $role, $verified);
        $stmt->execute();
        return (int) $conn->insert_id;
    }
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
        SET password_hash = ?, is_verified = 1, otp_code = NULL, otp_hash = NULL,
            otp_expires_at = NULL, session_version = session_version + 1
        WHERE user_id = ?
    ");
    $stmt->bind_param('si', $passwordHash, $userId);
    $stmt->execute();
}

function createPasswordResetCapability(mysqli $conn, int $userId): string {
    ensureAuthSecuritySchema($conn);
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $issuedAt = date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d H:i:s', time() + PASSWORD_RESET_CAPABILITY_SECONDS);
    $flow = 'password_reset';

    $invalidate = $conn->prepare('UPDATE password_reset_capabilities SET consumed_at = NOW() WHERE user_id = ? AND consumed_at IS NULL');
    $invalidate->bind_param('i', $userId);
    $invalidate->execute();

    $stmt = $conn->prepare(
        'INSERT INTO password_reset_capabilities (user_id, nonce_hash, flow, issued_at, expires_at) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('issss', $userId, $hash, $flow, $issuedAt, $expiresAt);
    $stmt->execute();
    return $token;
}

function consumePasswordResetCapability(mysqli $conn, string $token): ?int {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare("
        SELECT capability_id, user_id
        FROM password_reset_capabilities
        WHERE nonce_hash = ? AND flow = 'password_reset'
          AND consumed_at IS NULL AND expires_at > NOW()
        LIMIT 1 FOR UPDATE
    ");
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return null;
    }
    $capabilityId = (int) $row['capability_id'];
    $consume = $conn->prepare('UPDATE password_reset_capabilities SET consumed_at = NOW() WHERE capability_id = ? AND consumed_at IS NULL');
    $consume->bind_param('i', $capabilityId);
    $consume->execute();
    return $consume->affected_rows === 1 ? (int) $row['user_id'] : null;
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
