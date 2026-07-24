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
require_once __DIR__ . '/ticket_actions.php';
require_once __DIR__ . '/../notifications/send_alert.php';
requireLogin(ROLE_STAFF);
header('Content-Type: application/json');

requirePostRequest(true);
requireValidCsrf('', '', [], 'Security check failed. Please refresh the page and try again.', true);

$staffId = getCurrentStaffId($conn);
if (!$staffId) {
    jsonResponse(false, ['error' => 'Staff profile not found.'], 403);
}
try {
    $result = callNextTicketForStaff($conn, $staffId);
    if ($result['status'] === 'no_window') {
        jsonResponse(false, ['error' => 'No active window assigned to this staff account.'], 404);
    }
    if ($result['status'] === 'closed_window') {
        jsonResponse(false, ['error' => 'Open your window before calling the next client.'], 422);
    }
    if ($result['status'] === 'unassigned_service') {
        jsonResponse(false, ['error' => 'This window is not assigned to a service.'], 422);
    }
    if ($result['status'] === 'already_serving') {
        jsonResponse(false, ['error' => 'A ticket is already being served at this window.'], 409);
    }
    if ($result['status'] === 'empty_queue') {
        jsonResponse(false, ['error' => 'No waiting tickets for this service.'], 404);
    }
    try {
        processNearTurnAlerts($conn, (int) $result['service_id']);
    } catch (Throwable $alertError) {
        // Alert generation should not block queue operations.
    }

    jsonResponse(true, ['data' => $result['ticket']]);
} catch (Throwable $e) {
    jsonResponse(false, ['error' => 'Could not call next ticket.'], 500);
}
?>
