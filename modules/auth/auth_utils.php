<?php
/**
 * Authentication compatibility aggregator.
 *
 * Existing handlers retain this include while focused procedural helpers own
 * queries, OTP delivery, session finalization, and role redirects.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../notifications/email_sender.php';
require_once __DIR__ . '/auth_queries.php';
require_once __DIR__ . '/otp_service.php';
require_once __DIR__ . '/auth_session.php';
require_once __DIR__ . '/auth_redirects.php';

function normalizedLoginCredentials(array $input): array {
    $loginId = trim((string) ($input['login_id'] ?? ''));
    return [
        'login_id' => $loginId,
        'email' => normalizeEmail($loginId),
        'password' => (string) ($input['password'] ?? ''),
    ];
}

function authLoginStatus(?array $user, string $password): string {
    if (!$user) {
        return 'invalid_credentials';
    }
    if (!password_verify($password, (string) $user['password_hash'])) {
        return 'invalid_credentials';
    }
    if ((int) $user['is_active'] !== 1) {
        return 'inactive';
    }
    return 'accepted';
}

function hashAuthPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT);
}

function resetAuthPassword(mysqli $conn, int $userId, string $password): void {
    ensureAuthSecuritySchema($conn);
    resetAuthUserPassword($conn, $userId, hashAuthPassword($password));
}
