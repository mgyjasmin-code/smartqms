<?php
/**
 * SmartQMS -- Submit Post-Service Feedback
 * Client rates their experience after ticket is completed.
 * Rating: 1–5 stars. Comment is optional.
 * One feedback per completed ticket only.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/feedback_service.php';
requireLogin(ROLE_CLIENT);
header('Content-Type: application/json');

requirePostRequest(true);
requireValidCsrf('', '', [], 'Security check failed. Please refresh the page and try again.', true);

$ticketId = (int) ($_POST['ticket_id'] ?? 0);
$rating = (int) ($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if (!isRatingInRange($rating)) {
    jsonResponse(false, [
        'error' => 'Please correct the highlighted field.',
        'field_errors' => [
            'rating' => 'Choose a rating from 1 to 5.',
        ],
    ], 422);
}

if (!isPositiveIdentifier($ticketId)) {
    jsonResponse(false, ['error' => 'Ticket is required before submitting feedback.'], 422);
}

$result = submitFeedbackForClient(
    $conn,
    (int) $_SESSION['user_id'],
    $ticketId,
    $rating,
    $comment
);

if ($result['status'] === 'ticket_not_found') {
    jsonResponse(false, ['error' => 'Completed ticket not found for this account.'], 404);
}

if ($result['status'] === 'already_submitted') {
    jsonResponse(false, ['error' => 'Feedback was already submitted for this ticket.'], 409);
}

jsonResponse(true, ['feedback_id' => $result['feedback_id'] ?? null]);
?>
