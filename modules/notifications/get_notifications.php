<?php
/**
 * SmartQMS -- Get Unread Notifications (AJAX)
 * Called every 10 seconds by client-side JS.
 * Returns unread notifications for the logged-in client.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/notification_queries.php';
header('Content-Type: application/json');

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== ROLE_CLIENT) {
    jsonResponse(false, ['error' => 'Client sign-in is required.'], 403);
}

requirePostRequest(true);
requireValidCsrf('', '', [], 'Security check failed. Please refresh the page and try again.', true);

$userId = (int) $_SESSION['user_id'];
$rows = consumeUnreadNotifications($conn, $userId);

jsonResponse(true, ['data' => $rows]);
?>
