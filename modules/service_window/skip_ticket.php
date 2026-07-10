<?php
/**
 * SmartQMS -- Skip a Ticket
 * Marks ticket as 'skipped'. Staff moves to next client.
 * Skipped tickets are counted in Report 6 (No-Show Report).
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/../notifications/send_alert.php';
requireLogin(ROLE_STAFF);
header('Content-Type: application/json');

requirePostRequest(true);
requireValidCsrf('', '', [], 'Security check failed. Please refresh the page and try again.', true);

$ticketId = (int) ($_POST['ticket_id'] ?? 0);
if ($ticketId <= 0) {
    jsonResponse(false, ['error' => 'Missing ticket id.'], 422);
}

$staffId = getCurrentStaffId($conn);
$window = $staffId ? getStaffWindow($conn, $staffId) : null;
if (!$window) {
    jsonResponse(false, ['error' => 'No active window assigned to this staff account.'], 404);
}
$windowId = (int) $window['window_id'];

$stmt = $conn->prepare("SELECT * FROM queue_tickets WHERE ticket_id=? AND status='serving' AND window_id=? LIMIT 1");
$stmt->bind_param('ii', $ticketId, $windowId);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
if (!$ticket) {
    jsonResponse(false, ['error' => 'Serving ticket not found for your assigned window.'], 404);
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
    try {
        processNearTurnAlerts($conn, (int) $ticket['service_id']);
    } catch (Throwable $alertError) {
        // Alert generation should not block ticket skipping.
    }
    jsonResponse(true);
} catch (Throwable $e) {
    $conn->rollback();
    jsonResponse(false, ['error' => 'Could not skip ticket.'], 500);
}
?>
