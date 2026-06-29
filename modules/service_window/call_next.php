<?php
/**
 * SmartQMS -- Call Next Client
 * Staff clicks Call Next -> system finds the next ticket.
 *
 * ORDERING RULE (priority queue):
 *   ORDER BY priority_level DESC, issued_at ASC
 *   This ensures Senior/PWD clients always come before regular clients.
 *   Among same priority level, earlier arrival is served first.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_STAFF);
header('Content-Type: application/json');

$staffId = getCurrentStaffId($conn);
if (!$staffId) {
    jsonResponse(false, ['error' => 'Staff profile not found.'], 403);
}
$window = getStaffWindow($conn, $staffId);
if (!$window) {
    jsonResponse(false, ['error' => 'No active window assigned to this staff account.'], 404);
}
if (($window['status'] ?? 'closed') === 'closed') {
    jsonResponse(false, ['error' => 'Open your window before calling the next client.'], 422);
}

$serviceId = (int) ($window['service_id'] ?? 0);
if ($serviceId <= 0) {
    jsonResponse(false, ['error' => 'This window is not assigned to a service.'], 422);
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("
        SELECT *
        FROM queue_tickets
        WHERE status='waiting' AND service_id=?
        ORDER BY priority_level DESC, issued_at ASC
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->bind_param('i', $serviceId);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    if (!$ticket) {
        $conn->rollback();
        jsonResponse(false, ['error' => 'No waiting tickets for this service.'], 404);
    }

    $ticketId = (int) $ticket['ticket_id'];
    $windowId = (int) $window['window_id'];
    $update = $conn->prepare("UPDATE queue_tickets SET status='serving', called_at=NOW(), served_at=NOW(), window_id=? WHERE ticket_id=?");
    $update->bind_param('ii', $windowId, $ticketId);
    $update->execute();
    $busy = 'busy';
    $winUpdate = $conn->prepare("UPDATE service_windows SET status=? WHERE window_id=?");
    $winUpdate->bind_param('si', $busy, $windowId);
    $winUpdate->execute();
    logActivity($conn, 'ticket_called', 'Called ticket ' . $ticket['ticket_number'], $ticketId);
    $conn->commit();

    $ticket['status'] = 'serving';
    $ticket['window_id'] = $windowId;
    jsonResponse(true, ['data' => $ticket]);
} catch (Throwable $e) {
    $conn->rollback();
    jsonResponse(false, ['error' => 'Could not call next ticket.'], 500);
}
?>
