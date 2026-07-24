<?php
/**
 * SmartQMS -- Skip a Ticket
 * Marks ticket as 'skipped'. Staff moves to next client.
 * Skipped tickets are counted in Report 6 (No-Show Report).
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/ticket_actions.php';
require_once __DIR__ . '/../notifications/send_alert.php';
requireLogin(ROLE_STAFF);
header('Content-Type: application/json');

requirePostRequest(true);
requireValidCsrf('', '', [], 'Security check failed. Please refresh the page and try again.', true);

$ticketId = (int) ($_POST['ticket_id'] ?? 0);
if (!isPositiveIdentifier($ticketId)) {
    jsonResponse(false, ['error' => 'Missing ticket id.'], 422);
}

$staffId = getCurrentStaffId($conn);
if (!$staffId) {
    jsonResponse(false, ['error' => 'No active window assigned to this staff account.'], 404);
}

try {
    $result = skipTicketForStaff($conn, $staffId, $ticketId);
    if ($result['status'] === 'no_window') {
        jsonResponse(false, ['error' => 'No active window assigned to this staff account.'], 404);
    }
    if ($result['status'] === 'ticket_not_found') {
        jsonResponse(false, ['error' => 'Serving ticket not found for your assigned window.'], 404);
    }
    try {
        processNearTurnAlerts($conn, (int) $result['service_id']);
    } catch (Throwable $alertError) {
        // Alert generation should not block ticket skipping.
    }
    jsonResponse(true);
} catch (Throwable $e) {
    jsonResponse(false, ['error' => 'Could not skip ticket.'], 500);
}
?>
