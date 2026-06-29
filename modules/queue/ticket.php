<?php
/**
 * SmartQMS -- Ticket Data Fetcher
 * Returns ticket details for the client ticket view page.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_CLIENT);
header('Content-Type: application/json');

$ticket = getActiveTicket($conn, (int) $_SESSION['user_id']);
if (!$ticket) {
    jsonResponse(false, ['error' => 'No active ticket found.'], 404);
}
$ticket['people_ahead'] = peopleAhead($conn, $ticket);
jsonResponse(true, ['data' => $ticket]);
?>
