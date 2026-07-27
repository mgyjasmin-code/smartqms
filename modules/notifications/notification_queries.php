<?php
/**
 * Client-notification read and acknowledgement operations.
 */

function unreadNotificationsForUser(mysqli $conn, int $userId): array {
    $stmt = $conn->prepare("
        SELECT notif_id, ticket_id, message, type, sent_at
        FROM notifications
        WHERE user_id = ? AND is_read = 0
        ORDER BY sent_at DESC
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function recentNotificationsForUser(mysqli $conn, int $userId, int $limit = 10): array {
    $limit = max(1, min(50, $limit));
    $stmt = $conn->prepare("
        SELECT notif_id, ticket_id, message, type, sent_at, is_read
        FROM notifications
        WHERE user_id = ?
        ORDER BY sent_at DESC, notif_id DESC
        LIMIT ?
    ");
    $stmt->bind_param('ii', $userId, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function unreadNotificationCountForUser(mysqli $conn, int $userId): int {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS unread_count
        FROM notifications
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['unread_count'] ?? 0);
}

function markNotificationIdsReadForUser(mysqli $conn, int $userId, array $notificationIds): int {
    $ids = array_values(array_filter(
        array_unique(array_map('intval', $notificationIds)),
        static fn(int $id): bool => $id > 0
    ));
    if (!$ids) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = 'i' . str_repeat('i', count($ids));
    $params = array_merge([$userId], $ids);
    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = ?
          AND is_read = 0
          AND notif_id IN ({$placeholders})
    ");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->affected_rows;
}

/**
 * Backward-compatible helper retained for characterization callers.
 */
function markNotificationIdsRead(mysqli $conn, array $notificationIds): void {
    $ids = array_values(array_filter(
        array_unique(array_map('intval', $notificationIds)),
        static fn(int $id): bool => $id > 0
    ));
    if (!$ids) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notif_id IN ({$placeholders})");
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
}

function consumeUnreadNotifications(mysqli $conn, int $userId): array {
    $rows = unreadNotificationsForUser($conn, $userId);
    if ($rows) {
        markNotificationIdsReadForUser($conn, $userId, array_column($rows, 'notif_id'));
    }
    return $rows;
}
