<?php
/**
 * SmartQMS -- Update Window Status
 * Staff opens, sets to busy, or closes their service window.
 * Also updates the real-time display board immediately.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_STAFF);
header('Content-Type: application/json');

requirePostRequest(true);
requireValidCsrf('', '', [], 'Security check failed. Please refresh the page and try again.', true);

$status = $_POST['status'] ?? '';
if (!in_array($status, ['open', 'busy', 'closed'], true)) {
    jsonResponse(false, ['error' => 'Invalid status.'], 422);
}
$staffId = getCurrentStaffId($conn);
if (!$staffId) {
    jsonResponse(false, ['error' => 'Staff profile not found.'], 403);
}
$window = getStaffWindow($conn, $staffId);
if (!$window) {
    jsonResponse(false, ['error' => 'No active window assigned.'], 404);
}
$windowId = (int) $window['window_id'];
$stmt = $conn->prepare("UPDATE service_windows SET status=? WHERE window_id=?");
$stmt->bind_param('si', $status, $windowId);
$stmt->execute();
logActivity($conn, $status === 'closed' ? 'window_closed' : 'window_opened', 'Window set to ' . $status);
jsonResponse(true, ['data' => ['window_id' => $windowId, 'status' => $status]]);
?>
