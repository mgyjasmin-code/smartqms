<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/../../modules/feedback/feedback_service.php';
requireLogin(ROLE_CLIENT);

$ticketId = (int) ($_GET['ticket_id'] ?? 0);
$feedbackTicket = $ticketId > 0
    ? completedTicketAvailableForFeedback($conn, (int) $_SESSION['user_id'], $ticketId)
    : null;
if (!$feedbackTicket) {
    redirectTo('views/client/ticket.php', ['msg' => 'feedback_unavailable']);
}

redirectTo('views/client/ticket.php', ['feedback' => '1']);
