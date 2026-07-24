<?php
/**
 * SmartQMS -- Live Queue Status (AJAX endpoint)
 * Called every 10 seconds by main.js to update the queue display.
 * Returns JSON -- used by both client pages and display board.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/status_queries.php';
header('Content-Type: application/json');

if (!isLoggedIn() && !hasValidDisplayStatusToken($conn)) {
    jsonResponse(false, ['error' => 'Queue status is not available for this request.'], 403);
}

$windows = queueStatusWindows($conn);
$next = queueStatusNextTickets($conn);
$viewerTicket = queueStatusViewerTicket($conn);

jsonResponse(true, ['data' => [
    'windows' => $windows,
    'next' => $next,
    'viewer_ticket' => $viewerTicket,
]]);
?>
