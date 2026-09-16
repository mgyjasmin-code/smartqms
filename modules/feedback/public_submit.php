<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/../queue/public_intake.php';

requirePostRequest(true);
requireValidCsrf('index.php', 'public_feedback', [], 'Security check failed. Refresh the tracker and try again.', true);

$token = strtolower(trim((string) ($_POST['ticket_token'] ?? '')));
$rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
$comment = trim((string) ($_POST['comment'] ?? ''));
if (!isValidPublicTicketToken($token)) {
    jsonResponse(false, ['error' => 'Ticket not found.'], 404);
}
if ($rating === false || !isRatingInRange((int) $rating)) {
    jsonResponse(false, ['error' => 'Choose a rating from 1 to 5.'], 422);
}
if (strlen($comment) > 1000) {
    jsonResponse(false, ['error' => 'Comments must be 1000 characters or fewer.'], 422);
}

$conn->begin_transaction();
try {
    $ticket = publicQueueTicketByToken($conn, $token, true);
    if (!$ticket || ($ticket['lifecycle_status'] ?? '') !== 'completed') {
        $conn->rollback();
        jsonResponse(false, ['error' => 'Feedback is available only after the service is complete.'], 409);
    }
    if ((bool) ($ticket['has_feedback'] ?? false)) {
        $conn->rollback();
        jsonResponse(false, ['error' => 'Feedback was already submitted for this ticket.'], 409);
    }

    $insert = $conn->prepare("
        INSERT INTO feedback (ticket_id, user_id, window_id, service_id, rating, comment)
        VALUES (?, NULL, ?, ?, ?, ?)
    ");
    $ticketId = (int) $ticket['ticket_id'];
    $windowId = !empty($ticket['window_id']) ? (int) $ticket['window_id'] : null;
    $serviceId = (int) $ticket['service_id'];
    $ratingValue = (int) $rating;
    $insert->bind_param('iiiis', $ticketId, $windowId, $serviceId, $ratingValue, $comment);
    $insert->execute();
    $conn->commit();
    jsonResponse(true, ['message' => 'Thank you for your feedback.']);
} catch (mysqli_sql_exception $error) {
    $conn->rollback();
    if ((int) $error->getCode() === 1062) {
        jsonResponse(false, ['error' => 'Feedback was already submitted for this ticket.'], 409);
    }
    throw $error;
} catch (Throwable $error) {
    $conn->rollback();
    throw $error;
}
