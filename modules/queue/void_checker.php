<?php
/**
 * SmartQMS -- Auto-Void Checker (AJAX endpoint)
 * Called every 30 seconds after staff clicks Call Next.
 * Checks if void_timeout_minutes has elapsed since called_at.
 * If yes -> sets ticket status to 'voided'.
 *
 * Flow:
 *   JS polls this every 30s -> PHP checks called_at vs NOW()
 *   -> If timeout exceeded -> void ticket -> notify admin
 *   -> Log in activity_logs and notifications table
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
header('Content-Type: application/json');

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== ROLE_STAFF) {
    jsonResponse(false, ['error' => 'Staff sign-in is required.'], 403);
}

requirePostRequest(true);
requireValidCsrf('', '', [], 'Security check failed. Please refresh the page and try again.', true);

$staffId = getCurrentStaffId($conn);
$window = $staffId ? getStaffWindow($conn, $staffId) : null;
if (!$window) {
    jsonResponse(false, ['error' => 'No active window assigned to this staff account.'], 404);
}

$windowId = (int) $window['window_id'];
$timeout = max(1, (int) getSetting($conn, 'void_timeout_minutes', '10'));
$stmt = $conn->prepare("
    SELECT *
    FROM queue_tickets
    WHERE status='serving'
      AND window_id=?
      AND called_at IS NOT NULL
      AND TIMESTAMPDIFF(MINUTE, called_at, NOW()) >= ?
");
$stmt->bind_param('ii', $windowId, $timeout);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$count = 0;
foreach ($tickets as $ticket) {
    $reason = 'Client did not appear within timeout';
    $ticketId = (int) $ticket['ticket_id'];
    $update = $conn->prepare("UPDATE queue_tickets SET status='voided', voided_at=NOW(), voided_reason=? WHERE ticket_id=?");
    $update->bind_param('si', $reason, $ticketId);
    $update->execute();
    if (!empty($ticket['window_id'])) {
        $open = 'open';
        $win = $conn->prepare("UPDATE service_windows SET status=? WHERE window_id=?");
        $win->bind_param('si', $open, $ticket['window_id']);
        $win->execute();
    }
    $message = 'Your ticket was voided because you did not appear when called.';
    $type = 'turn_void';
    $channel = 'browser';
    $pending = 'pending';
    $unread = 0;
    $notif = $conn->prepare("INSERT INTO notifications (ticket_id, user_id, message, type, channel, delivery_status, is_read) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $notif->bind_param('iissssi', $ticketId, $ticket['user_id'], $message, $type, $channel, $pending, $unread);
    $notif->execute();
    logActivity($conn, 'ticket_voided', $reason, $ticketId, (int) $ticket['user_id'], ROLE_CLIENT);
    $count++;
}

jsonResponse(true, ['voided' => $count, 'data' => ['voided' => $count]]);
?>
