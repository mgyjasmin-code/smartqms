<?php
/**
 * SmartQMS -- Skip a Ticket
 * Marks ticket as 'skipped'. Staff moves to next client.
 * Skipped tickets are counted in Report 6 (No-Show Report).
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_STAFF);
header('Content-Type: application/json');

$ticketId = (int) ($_POST['ticket_id'] ?? 0);
if ($ticketId <= 0) {
    jsonResponse(false, ['error' => 'Missing ticket id.'], 422);
}

$stmt = $conn->prepare("SELECT * FROM queue_tickets WHERE ticket_id=? AND status='serving' LIMIT 1");
$stmt->bind_param('i', $ticketId);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
if (!$ticket) {
    jsonResponse(false, ['error' => 'Serving ticket not found.'], 404);
}

$conn->begin_transaction();
try {
    $reason = 'Client did not appear';
    $update = $conn->prepare("UPDATE queue_tickets SET status='skipped', voided_at=NOW(), voided_reason=? WHERE ticket_id=?");
    $update->bind_param('si', $reason, $ticketId);
    $update->execute();
    if (!empty($ticket['window_id'])) {
        $open = 'open';
        $win = $conn->prepare("UPDATE service_windows SET status=? WHERE window_id=?");
        $win->bind_param('si', $open, $ticket['window_id']);
        $win->execute();
    }
    logActivity($conn, 'ticket_skipped', 'Skipped ticket ' . $ticket['ticket_number'], $ticketId);
    $conn->commit();
    jsonResponse(true);
} catch (Throwable $e) {
    $conn->rollback();
    jsonResponse(false, ['error' => 'Could not skip ticket.'], 500);
}
?>
