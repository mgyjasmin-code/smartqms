<?php
/**
 * Authenticated, read-only arrival lookup for QR and reference workflows.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_STAFF);
requirePostRequest(true);
requireValidCsrf('', '', [], 'Security check failed. Refresh the page and try again.', true);

$lookupType = strtolower(trim((string) ($_POST['lookup_type'] ?? '')));
$lookupValue = trim((string) ($_POST['lookup_value'] ?? ''));

try {
    $ticket = findStaffArrivalTicket($conn, $lookupType, $lookupValue);
    if (!$ticket) {
        jsonResponse(false, ['error' => 'No appointment matched that ticket reference.'], 404);
    }
    jsonResponse(true, ['data' => $ticket]);
} catch (InvalidArgumentException $error) {
    jsonResponse(false, ['error' => $error->getMessage()], 422);
} catch (DomainException $error) {
    jsonResponse(false, ['error' => 'No appointment matched that ticket reference.'], 404);
} catch (Throwable $error) {
    error_log('Staff arrival lookup failed: ' . $error->getMessage());
    jsonResponse(false, ['error' => 'The appointment could not be looked up.'], 500);
}
