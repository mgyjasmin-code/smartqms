<?php
/**
 * SmartQMS near-turn alert generator.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/sms_sender.php';

function notificationAlreadySent(mysqli $conn, int $ticketId, string $type): bool {
    $stmt = $conn->prepare("SELECT notif_id FROM notifications WHERE ticket_id = ? AND type = ? LIMIT 1");
    $stmt->bind_param('is', $ticketId, $type);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}

function insertQueueNotification(
    mysqli $conn,
    int $ticketId,
    int $userId,
    string $message,
    string $type,
    string $channel,
    string $deliveryStatus
): void {
    $isRead = 0;
    $stmt = $conn->prepare("
        INSERT INTO notifications (ticket_id, user_id, message, type, channel, delivery_status, is_read)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iissssi', $ticketId, $userId, $message, $type, $channel, $deliveryStatus, $isRead);
    $stmt->execute();
}

function processNearTurnAlerts(mysqli $conn, ?int $serviceId = null): int {
    $sql = "
        SELECT qt.*, u.phone_number, hs.service_name
        FROM queue_tickets qt
        JOIN users u ON u.user_id = qt.user_id
        JOIN health_services hs ON hs.service_id = qt.service_id
        WHERE qt.status = 'waiting'
    ";
    $types = '';
    $params = [];
    if ($serviceId !== null && $serviceId > 0) {
        $sql .= " AND qt.service_id = ?";
        $types = 'i';
        $params[] = $serviceId;
    }
    $sql .= " ORDER BY qt.service_id, qt.priority_level DESC, qt.issued_at ASC";

    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, $params[0]);
    }
    $stmt->execute();
    $tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $created = 0;
    foreach ($tickets as $ticket) {
        $ticketId = (int) $ticket['ticket_id'];
        $peopleAhead = peopleAhead($conn, $ticket);
        if ($peopleAhead > 2 || notificationAlreadySent($conn, $ticketId, 'turn_alert')) {
            continue;
        }

        $message = $peopleAhead === 0
            ? 'Your SmartQMS ticket ' . $ticket['ticket_number'] . ' is next. Please proceed to the waiting area.'
            : 'Your SmartQMS ticket ' . $ticket['ticket_number'] . ' is almost next. Only ' . $peopleAhead . ' ticket' . ($peopleAhead === 1 ? '' : 's') . ' ahead.';

        $phone = trim((string) ($ticket['phone_number'] ?? ''));
        $channel = $phone !== '' ? 'both' : 'browser';
        $smsOk = $phone !== '' ? sendSMS($phone, $message, 'notification', (int) $ticket['user_id']) : true;
        $deliveryStatus = $smsOk ? 'sent' : 'failed';

        insertQueueNotification(
            $conn,
            $ticketId,
            (int) $ticket['user_id'],
            $message,
            'turn_alert',
            $channel,
            $deliveryStatus
        );
        $created++;
    }

    return $created;
}
?>
