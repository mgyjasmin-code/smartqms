<?php
/**
 * SmartQMS -- Mark Ticket as Complete
 * Staff marks current ticket as done.
 * Records actual wait time and service duration for ML training.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_STAFF);
header('Content-Type: application/json');

$staffId = getCurrentStaffId($conn);
$ticketId = (int) ($_POST['ticket_id'] ?? 0);
if (!$staffId || $ticketId <= 0) {
    jsonResponse(false, ['error' => 'Missing staff or ticket.'], 422);
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
    $update = $conn->prepare("UPDATE queue_tickets SET status='completed', completed_at=NOW() WHERE ticket_id=?");
    $update->bind_param('i', $ticketId);
    $update->execute();

    $calc = $conn->prepare("SELECT TIMESTAMPDIFF(MINUTE, issued_at, completed_at) AS wait_min, TIMESTAMPDIFF(SECOND, served_at, completed_at) AS service_sec FROM queue_tickets WHERE ticket_id=?");
    $calc->bind_param('i', $ticketId);
    $calc->execute();
    $metrics = $calc->get_result()->fetch_assoc();
    $waitMin = (float) ($metrics['wait_min'] ?? 0);
    $serviceSec = (int) ($metrics['service_sec'] ?? 0);

    $log = $conn->prepare("UPDATE wait_time_logs SET actual_wait_min=?, actual_service_dur=?, staff_id=? WHERE ticket_id=?");
    $log->bind_param('diii', $waitMin, $serviceSec, $staffId, $ticketId);
    $log->execute();

    $open = 'open';
    $windowId = (int) ($ticket['window_id'] ?? 0);
    if ($windowId > 0) {
        $win = $conn->prepare("UPDATE service_windows SET status=? WHERE window_id=?");
        $win->bind_param('si', $open, $windowId);
        $win->execute();
    }

    $message = 'Your service is complete. Please submit feedback when convenient.';
    $type = 'feedback_prompt';
    $channel = 'browser';
    $pending = 'pending';
    $unread = 0;
    $notif = $conn->prepare("INSERT INTO notifications (ticket_id, user_id, message, type, channel, delivery_status, is_read) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $notif->bind_param('iissssi', $ticketId, $ticket['user_id'], $message, $type, $channel, $pending, $unread);
    $notif->execute();

    logActivity($conn, 'ticket_completed', 'Completed ticket ' . $ticket['ticket_number'], $ticketId);
    $conn->commit();
    jsonResponse(true, ['data' => ['ticket_id' => $ticketId, 'actual_wait_min' => $waitMin, 'actual_service_dur' => $serviceSec]]);
} catch (Throwable $e) {
    $conn->rollback();
    jsonResponse(false, ['error' => 'Could not complete ticket.'], 500);
}
?>
