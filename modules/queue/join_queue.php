<?php
/**
 * SmartQMS -- Join Queue Handler
 * Client selects service type and joins the queue.
 *
 * BUSINESS RULES:
 *  - One active ticket per client at a time
 *    (status = waiting OR serving)
 *  - Legacy priority fields remain compatibility-only; live ordering is FIFO
 *  - Reference number format: YYYYMMDD + 8-digit daily sequence
 *  - QR code generated and saved to /assets/qr/
 *  - ML API called for predicted wait time
 *  - Wait time log entry created immediately
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/qr_generate.php';
require_once __DIR__ . '/ticket_service.php';
require_once __DIR__ . '/../notifications/send_alert.php';
requireLogin(ROLE_CLIENT);

requirePostRequest(false, 'views/client/index.php', 'join_queue');

$userId = (int) $_SESSION['user_id'];
$profile = smartqmsCustomerBookingProfile($conn, $userId);
$booking = smartqmsCustomerBookingInput($_POST, $profile);
$serviceId = (int) $booking['service_id'];
$clientType = $booking['client_type'];
$oldInput = $booking;

requireValidCsrf('views/client/index.php', 'join_queue', $oldInput);

if (getActiveTicket($conn, $userId)) {
    redirectTo('views/client/ticket.php', ['msg' => 'active']);
}

$fieldErrors = smartqmsCustomerBookingErrors($booking);
$branches = smartqmsListBranches($conn);
if (!smartqmsCustomerBookingBranch($branches, $booking['branch_id'])) {
    $fieldErrors['branch_id'] = 'Choose an available health-center location.';
}
if ($booking['phone_number'] !== ''
    && smartqmsCustomerPhoneBelongsToAnotherUser($conn, $booking['phone_number'], $userId)) {
    $fieldErrors['phone_number'] = 'That phone number is already used by another account.';
}
if ($fieldErrors) {
    redirectWithFormFeedback('views/client/index.php', 'join_queue', $fieldErrors, $oldInput);
}

$priorityLevel = queuePriorityLevelForClientType($clientType);
$snapshot = isPositiveIdentifier($serviceId)
    ? queuePredictionSnapshot($conn, $serviceId, $clientType)
    : null;
if (!$snapshot) {
    redirectWithFormFeedback('views/client/index.php', 'join_queue', [
        'service_id' => 'Choose an active health service.',
    ], $oldInput);
}

$service = $snapshot['service'];
$priorityLevel = (int) $snapshot['priority_level'];

if (!queueServiceAllowsClientType($service, $clientType)) {
    redirectWithFormFeedback('views/client/index.php', 'join_queue', [
        'client_type' => 'That service is reserved for Senior/PWD clients.',
    ], $oldInput);
}

$mlResult = requestMlPrediction($snapshot['features'], 2);
$mlPrediction = $mlResult !== null ? (float) $mlResult['estimated_wait_minutes'] : null;

try {
    $result = createQueueTicket(
        $conn,
        $userId,
        $serviceId,
        $clientType,
        $priorityLevel,
        $snapshot,
        $mlPrediction,
        $booking,
        $mlResult
    );
    if (!$result['created']) {
        redirectTo('views/client/ticket.php', ['msg' => 'active']);
    }
    try {
        processNearTurnAlerts($conn, $serviceId);
    } catch (Throwable $alertError) {
        // Alert generation should not block ticket creation.
    }
    $_SESSION['name'] = trim($booking['first_name'] . ' ' . $booking['last_name']);
    $_SESSION['phone'] = $booking['phone_number'];
    redirectTo('views/client/ticket.php');
} catch (Throwable $e) {
    redirectWithFormFeedback('views/client/index.php', 'join_queue', [], $oldInput, 'Could not create queue ticket.');
}
?>
