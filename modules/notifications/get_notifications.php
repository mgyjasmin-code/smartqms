<?php
/**
 * SmartQMS -- Get Unread Notifications (AJAX)
 * Called every 10 seconds by client-side JS.
 * Returns unread notifications for the logged-in client.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_CLIENT);
header('Content-Type: application/json');

$userId = (int) $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT notif_id, ticket_id, message, type, sent_at FROM notifications WHERE user_id=? AND is_read=0 ORDER BY sent_at DESC");
$stmt->bind_param('i', $userId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($rows) {
    $ids = array_map(fn($row) => (int) $row['notif_id'], $rows);
    $idList = implode(',', $ids);
    $conn->query("UPDATE notifications SET is_read=1 WHERE notif_id IN ($idList)");
}

jsonResponse(true, ['data' => $rows]);
?>
