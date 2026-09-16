<?php
/** Privacy-minimized ticket status lookup by public reference number. */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/public_intake.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(false, ['error' => 'Method not allowed.'], 405);
}

header('Cache-Control: no-store, private');
$reference = strtoupper(trim((string) ($_GET['reference'] ?? '')));
$referenceLimit = authThrottleStatus($conn, 'public_reference', $reference, PUBLIC_REFERENCE_ATTEMPT_LIMIT, PUBLIC_REFERENCE_ATTEMPT_WINDOW_SECONDS);
$ipLimit = authThrottleStatus($conn, 'public_reference_ip', 'all', PUBLIC_REFERENCE_IP_ATTEMPT_LIMIT, PUBLIC_REFERENCE_ATTEMPT_WINDOW_SECONDS);
if (!$referenceLimit['allowed'] || !$ipLimit['allowed']) {
    recordSecurityEvent($conn, 'public_reference_lookup', 'rate_limited', 'ticket_reference', null, ['scope' => 'compatibility']);
    header('Retry-After: ' . max((int) $referenceLimit['retry_after'], (int) $ipLimit['retry_after'], 1));
    jsonResponse(false, ['error' => 'Too many lookup attempts. Try again later.'], 429);
}
recordAuthAttempt($conn, 'public_reference', $reference, PUBLIC_REFERENCE_ATTEMPT_LIMIT, PUBLIC_REFERENCE_ATTEMPT_WINDOW_SECONDS);
recordAuthAttempt($conn, 'public_reference_ip', 'all', PUBLIC_REFERENCE_IP_ATTEMPT_LIMIT, PUBLIC_REFERENCE_ATTEMPT_WINDOW_SECONDS);
header('Deprecation: true');
header('Sunset: Sat, 05 Dec 2026 00:00:00 GMT');
expireScheduledQueueTickets($conn);
$ticket = publicQueueTicketByReference($conn, $reference);
if (!$ticket) {
    recordSecurityEvent($conn, 'public_reference_lookup', 'not_found', 'ticket_reference', null, ['scope' => 'compatibility']);
    jsonResponse(false, ['error' => 'Ticket not found.'], 404);
}

recordSecurityEvent($conn, 'public_reference_lookup', 'success', 'ticket_reference', null, ['scope' => 'compatibility']);

jsonResponse(true, ['data' => publicReferenceStatusProjection($ticket)]);
