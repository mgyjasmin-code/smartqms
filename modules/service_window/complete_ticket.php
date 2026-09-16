<?php
/**
 * SmartQMS -- Mark Ticket as Complete
 * Staff marks current ticket as done.
 * Records actual wait time and service duration for ML training.
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
$ticketId = (int) ($_POST['ticket_id'] ?? 0);
if ((!$staffId && smartqmsDataProviderMode() !== 'supabase') || !isPositiveIdentifier($ticketId)) {
    jsonResponse(false, ['error' => 'Missing staff or ticket.'], 422);
}

try {
    $result = smartqmsCompleteForStaff($conn, (int) $staffId, (int) $_SESSION['user_id'], $ticketId);
    if ($result['status'] === 'no_window') {
        jsonResponse(false, ['error' => 'No active window assigned to this staff account.'], 404);
    }
    if ($result['status'] === 'ticket_not_found') {
        jsonResponse(false, ['error' => 'Serving ticket not found for your assigned window.'], 404);
    }
    if (smartqmsDataProviderMode() !== 'supabase') {
        try {
            processNearTurnAlerts($conn, (int) $result['service_id']);
        } catch (Throwable $alertError) {
            // Alert generation should not block ticket completion.
        }
    }
    jsonResponse(true, ['data' => [
        'ticket_id' => $result['ticket_id'],
        'actual_wait_min' => $result['actual_wait_min'] ?? $result['actual_wait_minutes'] ?? 0,
        'actual_service_dur' => $result['actual_service_dur'] ?? $result['actual_service_seconds'] ?? 0,
    ]]);
} catch (Throwable $e) {
    jsonResponse(false, ['error' => 'Could not complete ticket.'], 500);
}
?>
