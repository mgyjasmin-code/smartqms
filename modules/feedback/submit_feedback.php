<?php
/**
 * SmartQMS -- Submit Post-Service Feedback
 * Client rates their experience after ticket is completed.
 * Rating: 1–5 stars. Comment is optional.
 * One feedback per completed ticket only.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_CLIENT);
header('Content-Type: application/json');

$ticketId = (int) ($_POST['ticket_id'] ?? 0);
$rating = (int) ($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if ($ticketId <= 0 || $rating < 1 || $rating > 5) {
    jsonResponse(false, ['error' => 'Ticket and a 1-5 rating are required.'], 422);
}

$stmt = $conn->prepare("SELECT * FROM queue_tickets WHERE ticket_id=? AND user_id=? AND status='completed' LIMIT 1");
$stmt->bind_param('ii', $ticketId, $_SESSION['user_id']);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
if (!$ticket) {
    jsonResponse(false, ['error' => 'Completed ticket not found for this account.'], 404);
}

$exists = $conn->prepare("SELECT feedback_id FROM feedback WHERE ticket_id=?");
$exists->bind_param('i', $ticketId);
$exists->execute();
if ($exists->get_result()->fetch_assoc()) {
    jsonResponse(false, ['error' => 'Feedback was already submitted for this ticket.'], 409);
}

$insert = $conn->prepare("INSERT INTO feedback (ticket_id, user_id, window_id, service_id, rating, comment) VALUES (?, ?, ?, ?, ?, NULLIF(?, ''))");
$insert->bind_param('iiiiis', $ticketId, $_SESSION['user_id'], $ticket['window_id'], $ticket['service_id'], $rating, $comment);
$insert->execute();
logActivity($conn, 'feedback_submitted', 'Rating: ' . $rating, $ticketId);
jsonResponse(true);
?>
