<?php
/**
 * Confirm a Scheduled arrival exactly once and allocate its FIFO number.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_STAFF);
requirePostRequest(true);
requireValidCsrf('', '', [], 'Security check failed. Refresh the page and try again.', true);

$staffId = getCurrentStaffId($conn);
$ticketId = filter_var($_POST['ticket_id'] ?? null, FILTER_VALIDATE_INT);
$method = strtolower(trim((string) ($_POST['check_in_method'] ?? '')));

if (!$staffId) {
    jsonResponse(false, ['error' => 'An active Staff profile is required.'], 403);
}
if ($ticketId === false || (int) $ticketId < 1 || !in_array($method, ['qr', 'reference'], true)) {
    jsonResponse(false, ['error' => 'The arrival confirmation is not valid.'], 422);
}

try {
    $result = checkInScheduledQueueTicket($conn, (int) $staffId, (int) $ticketId, $method);
    if (($result['status'] ?? '') === 'expired') {
        jsonResponse(false, ['error' => 'This Scheduled appointment has expired.'], 410);
    }
    if (!in_array(($result['status'] ?? ''), ['checked_in', 'already_checked_in'], true)) {
        jsonResponse(false, ['error' => 'The appointment cannot be checked in.'], 409);
    }
    jsonResponse(true, ['data' => $result]);
} catch (DomainException $error) {
    jsonResponse(false, ['error' => $error->getMessage()], 409);
} catch (InvalidArgumentException $error) {
    jsonResponse(false, ['error' => $error->getMessage()], 422);
} catch (Throwable $error) {
    error_log('Staff arrival confirmation failed: ' . $error->getMessage());
    jsonResponse(false, ['error' => 'The appointment could not be checked in.'], 500);
}

