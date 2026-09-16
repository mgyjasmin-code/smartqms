<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/ticket_actions.php';
requireLogin(ROLE_STAFF);
requirePostRequest(true);
requireValidCsrf('', '', [], 'Security check failed. Refresh the page and try again.', true);

$staffId = getCurrentStaffId($conn);
$ticketId = filter_var($_POST['ticket_id'] ?? null, FILTER_VALIDATE_INT);
if (!$staffId || $ticketId === false || (int) $ticketId < 1) {
    jsonResponse(false, ['error' => 'Ticket not found.'], 404);
}
try {
    $result = startTicketServiceForStaff($conn, (int) $staffId, (int) $ticketId);
    if (($result['status'] ?? '') === 'invalid_state') {
        jsonResponse(false, ['error' => 'Only a currently called ticket can start service.'], 409);
    }
    if (($result['status'] ?? '') !== 'success') {
        jsonResponse(false, ['error' => 'The ticket is no longer available.'], 404);
    }
    jsonResponse(true, ['data' => $result]);
} catch (Throwable $error) {
    jsonResponse(false, ['error' => 'Service could not be started.'], 500);
}
