<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId > 0) {
    $stmt = $conn->prepare("
        SELECT ticket_token
        FROM queue_tickets
        WHERE user_id = ? AND ticket_token IS NOT NULL
        ORDER BY ticket_id DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!empty($row['ticket_token'])) {
        redirectTo('track/', ['token' => (string) $row['ticket_token']]);
    }
}
redirectTo('');
