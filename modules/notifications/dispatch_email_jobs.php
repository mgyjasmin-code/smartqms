<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/email_sender.php';

requirePostRequest(true);
requireValidCsrf('', '', [], 'Security check failed. Please refresh the page and try again.', true);

$userId = (int) (
    $_SESSION['otp_user_id']
    ?? $_SESSION['pending_user_id']
    ?? $_SESSION['reset_user_id']
    ?? 0
);

if (!$userId) {
    jsonResponse(false, ['message' => 'No OTP email is pending.'], 401);
}

$result = dispatchQueuedEmails($conn, $userId, 3);
jsonResponse(true, $result);
?>
