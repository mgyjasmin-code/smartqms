<?php
/**
 * SmartQMS -- Update Window Status
 * Staff opens, sets to busy, or closes their service window.
 * Also updates the real-time display board immediately.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/ticket_actions.php';
requireLogin(ROLE_STAFF);
header('Content-Type: application/json');

requirePostRequest(true);
requireValidCsrf('', '', [], 'Security check failed. Please refresh the page and try again.', true);

$status = $_POST['status'] ?? '';
if (!isAllowedWindowStatus($status)) {
    jsonResponse(false, ['error' => 'Invalid status.'], 422);
}
$staffId = getCurrentStaffId($conn);
if (!$staffId && smartqmsDataProviderMode() !== 'supabase') {
    jsonResponse(false, ['error' => 'Staff profile not found.'], 403);
}
try {
    $result = smartqmsSetCounterStatusForStaff($conn, (int) $staffId, (int) $_SESSION['user_id'], $status);
    if ($result['status'] === 'no_window') {
        jsonResponse(false, ['error' => 'No active window assigned.'], 404);
    }
    jsonResponse(true, ['data' => [
        'window_id' => $result['window_id'] ?? $result['counter_id'] ?? null,
        'status' => $result['window_status'] ?? $result['counter_status'] ?? $status,
    ]]);
} catch (Throwable $error) {
    jsonResponse(false, ['error' => 'Could not update window status.'], 500);
}
?>
