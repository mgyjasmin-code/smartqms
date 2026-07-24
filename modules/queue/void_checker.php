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
require_once __DIR__ . '/../service_window/ticket_actions.php';
header('Content-Type: application/json');

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== ROLE_STAFF) {
    jsonResponse(false, ['error' => 'Staff sign-in is required.'], 403);
}

requirePostRequest(true);
requireValidCsrf('', '', [], 'Security check failed. Please refresh the page and try again.', true);

$staffId = getCurrentStaffId($conn);
if (!$staffId) {
    jsonResponse(false, ['error' => 'No active window assigned to this staff account.'], 404);
}

$timeout = max(1, (int) getSetting($conn, 'void_timeout_minutes', '10'));
try {
    $result = voidExpiredTicketsForStaff($conn, $staffId, $timeout);
    if ($result['status'] === 'no_window') {
        jsonResponse(false, ['error' => 'No active window assigned to this staff account.'], 404);
    }
    $count = (int) $result['voided'];
    jsonResponse(true, ['voided' => $count, 'data' => ['voided' => $count]]);
} catch (Throwable $error) {
    jsonResponse(false, ['error' => 'Could not check expired tickets.'], 500);
}
?>
