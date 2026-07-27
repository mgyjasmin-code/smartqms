<?php
/**
 * Mark displayed client notifications as read without affecting other users.
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

$notificationIds = $_POST['notification_ids'] ?? [];
if (!is_array($notificationIds)) {
    $notificationIds = [$notificationIds];
}

$userId = (int) $_SESSION['user_id'];
$marked = markNotificationIdsReadForUser($conn, $userId, $notificationIds);
$unreadCount = unreadNotificationCountForUser($conn, $userId);

jsonResponse(true, ['marked' => $marked, 'unread_count' => $unreadCount]);
?>
