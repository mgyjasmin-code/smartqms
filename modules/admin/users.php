<?php
/**
 * Administrator user listing and activation changes.
 */

function listAdminUsers(mysqli $conn): array {
    return $conn->query("
        SELECT user_id, first_name, last_name, phone_number, email, role,
               is_verified, is_active, created_at
        FROM users ORDER BY created_at DESC
    ")->fetch_all(MYSQLI_ASSOC);
}

function setAdminUserActive(mysqli $conn, int $userId, int $isActive, int $currentUserId): bool {
    if (!isPositiveIdentifier($userId) || $userId === $currentUserId) {
        return false;
    }
    $stmt = $conn->prepare("UPDATE users SET is_active=?, session_version=session_version+1 WHERE user_id=?");
    $stmt->bind_param('ii', $isActive, $userId);
    $stmt->execute();
    logActivity($conn, 'user_status_updated', 'user_id=' . $userId);
    recordSecurityEvent($conn, 'account_status_changed', 'success', 'account', (string) $userId, [
        'reason' => $isActive === 1 ? 'activated' : 'deactivated',
    ]);
    return true;
}
