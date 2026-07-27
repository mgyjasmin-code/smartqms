<?php
/**
 * Shared activity logging helpers.
 */

function logActivity(mysqli $conn, string $action, string $details = '', ?int $ticketId = null, ?int $userId = null, ?string $role = null): void {
    $userId = $userId ?? ($_SESSION['user_id'] ?? null);
    if (!$userId) {
        return;
    }
    $role = $role ?? ($_SESSION['role'] ?? null);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, role, action, details, ticket_id, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isssis', $userId, $role, $action, $details, $ticketId, $ip);
    $stmt->execute();
}
