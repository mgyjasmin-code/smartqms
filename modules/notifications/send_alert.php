<?php
/**
 * SmartQMS near-turn alert generator.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/ticket_sms.php';

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

function nearTurnAlertCandidates(mysqli $conn, ?int $serviceId = null): array {
    $sql = "
        SELECT qt.ticket_id, qt.user_id, qt.ticket_number, qt.service_id,
               COALESCE(NULLIF(qt.phone_number, ''), u.phone_number) AS phone_number,
               hs.service_name,
               (
                   SELECT COUNT(*)
                   FROM queue_tickets ahead
                   WHERE ahead.status = 'waiting'
                     AND ahead.service_id = qt.service_id
                     AND (
                         COALESCE(ahead.checked_in_at, ahead.issued_at) < COALESCE(qt.checked_in_at, qt.issued_at)
                         OR (COALESCE(ahead.checked_in_at, ahead.issued_at) = COALESCE(qt.checked_in_at, qt.issued_at)
                             AND ahead.ticket_id < qt.ticket_id)
                     )
                     AND ahead.lifecycle_status = 'waiting'
                     AND (ahead.user_id IS NOT NULL OR ahead.checked_in_at IS NOT NULL)
               ) AS people_ahead,
               EXISTS (
                   SELECT 1
                   FROM notifications existing
                   WHERE existing.ticket_id = qt.ticket_id
                     AND existing.type = 'turn_alert'
               ) AS browser_alert_sent,
               EXISTS (
                   SELECT 1 FROM ticket_sms_events sent
                   WHERE sent.ticket_id = qt.ticket_id AND sent.event_type = 'near_turn'
               ) AS sms_alert_sent
        FROM queue_tickets qt
        LEFT JOIN users u ON u.user_id = qt.user_id
        JOIN health_services hs ON hs.service_id = qt.service_id
        WHERE qt.status = 'waiting' AND qt.lifecycle_status = 'waiting'
          AND qt.ticket_number IS NOT NULL
          AND (qt.user_id IS NOT NULL OR qt.checked_in_at IS NOT NULL)
    ";
    $types = '';
    $params = [];
    if ($serviceId !== null && $serviceId > 0) {
        $sql .= " AND qt.service_id = ?";
        $types = 'i';
        $params[] = $serviceId;
    }
    $sql .= "
        HAVING people_ahead <= 2
           AND ((user_id IS NOT NULL AND browser_alert_sent = 0)
                OR (user_id IS NULL AND sms_alert_sent = 0))
        ORDER BY qt.service_id, qt.checked_in_at, qt.ticket_id
    ";

    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, $params[0]);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function processNearTurnAlerts(mysqli $conn, ?int $serviceId = null): int {
    $tickets = nearTurnAlertCandidates($conn, $serviceId);

    $created = 0;
    foreach ($tickets as $ticket) {
        $ticketId = (int) $ticket['ticket_id'];
        $peopleAhead = (int) $ticket['people_ahead'];

        $message = $peopleAhead === 0
            ? 'Your SmartQMS ticket ' . $ticket['ticket_number'] . ' is next. Please proceed to the waiting area.'
            : 'Your SmartQMS ticket ' . $ticket['ticket_number'] . ' is almost next. Only ' . $peopleAhead . ' ticket' . ($peopleAhead === 1 ? '' : 's') . ' ahead.';

        $phone = trim((string) ($ticket['phone_number'] ?? ''));
        $userId = (int) ($ticket['user_id'] ?? 0);
        if ($userId === 0) {
            if ($phone !== '' && sendTicketSmsOnce($conn, $ticketId, 'near_turn', $phone, $message)) {
                $created++;
            }
            continue;
        }
        $channel = $phone !== '' ? 'both' : 'browser';
        $smsOk = $phone !== '' ? sendTicketSmsOnce($conn, $ticketId, 'near_turn', $phone, $message, $userId) : true;
        $deliveryStatus = $smsOk ? 'sent' : 'failed';

        insertQueueNotification(
            $conn,
            $ticketId,
            $userId,
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
