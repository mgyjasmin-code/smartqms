<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/public_intake.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(false, ['error' => 'Method not allowed.'], 405);
}

header('Cache-Control: no-store, private');
$token = strtolower(trim((string) ($_GET['token'] ?? '')));
$ticket = publicQueueTicketByToken($conn, $token);
if (!$ticket) {
    jsonResponse(false, ['error' => 'Ticket not found.'], 404);
}

jsonResponse(true, ['data' => publicQueueTicketProjection($conn, $ticket)]);
