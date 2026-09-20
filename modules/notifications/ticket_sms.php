<?php
/** One SMS per ticket and event, including account-free reservations. */
require_once __DIR__ . '/sms_sender.php';

function bookingConfirmationSms(string $reference, string $visitDate): string {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $visitDate);
    if (!$date || $date->format('Y-m-d') !== $visitDate) {
        throw new InvalidArgumentException('Invalid visit date for booking SMS.');
    }
    return 'SmartQMS: Appointment confirmed ' . $date->format('M j, Y')
        . '. Ref ' . $reference
        . '. Show this at check-in. Queue number issued on arrival.';
}

function sendBookingConfirmationForTicket(mysqli $conn, int $ticketId): bool {
    if ($ticketId < 1) {
        throw new InvalidArgumentException('Invalid booking ticket ID.');
    }
    $stmt = $conn->prepare("
        SELECT ticket_id, phone_number, reference_number, scheduled_expires_at,
               entry_type, lifecycle_status
        FROM queue_tickets
        WHERE ticket_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    if (!$ticket || $ticket['entry_type'] !== 'online' || $ticket['lifecycle_status'] !== 'scheduled') {
        return false;
    }

    $visitDate = substr((string) $ticket['scheduled_expires_at'], 0, 10);
    return sendTicketSmsOnce(
        $conn,
        $ticketId,
        'booking_confirmation',
        (string) $ticket['phone_number'],
        bookingConfirmationSms((string) $ticket['reference_number'], $visitDate)
    );
}

function sendTicketSmsOnce(
    mysqli $conn,
    int $ticketId,
    string $eventType,
    string $phone,
    string $message,
    int $userId = 0
): bool {
    if ($ticketId < 1 || !in_array($eventType, ['booking_confirmation', 'near_turn'], true)) {
        throw new InvalidArgumentException('Invalid ticket SMS event.');
    }
    // The unique key is the claim: concurrent staff actions cannot text twice.
    $claim = $conn->prepare("INSERT IGNORE INTO ticket_sms_events (ticket_id, event_type, phone, status) VALUES (?, ?, ?, 'pending')");
    $claim->bind_param('iss', $ticketId, $eventType, $phone);
    $claim->execute();
    if ($claim->affected_rows !== 1) {
        return false;
    }

    $status = 'failed';
    try {
        sendSMS($conn, $phone, $message, 'notification', $userId, $status);
    } catch (Throwable $error) {
        error_log('SmartQMS ticket SMS dispatch failed for event ' . $eventType . '.');
    }
    $update = $conn->prepare('UPDATE ticket_sms_events SET status = ? WHERE ticket_id = ? AND event_type = ?');
    $update->bind_param('sis', $status, $ticketId, $eventType);
    $update->execute();
    return $status === 'sent' || $status === 'simulated';
}
