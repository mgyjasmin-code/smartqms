<?php
/**
 * SmartQMS -- Join Queue Handler
 * Client selects service type and joins the queue.
 *
 * BUSINESS RULES:
 *  - One active ticket per client at a time
 *    (status = waiting OR serving)
 *  - Priority clients (senior/pwd) get priority_level = 1
 *  - Reference number format: BHC-YYYY-NNNN
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

$serviceId = (int) ($_POST['service_id'] ?? 0);
$clientType = normalizeQueueClientType((string) ($_POST['client_type'] ?? 'regular'));
$oldInput = [
    'service_id' => (string) $serviceId,
    'client_type' => $clientType,
];

requireValidCsrf('views/client/index.php', 'join_queue', $oldInput);

$userId = (int) $_SESSION['user_id'];
if (getActiveTicket($conn, $userId)) {
    redirectTo('views/client/ticket.php', ['msg' => 'active']);
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

$mlPrediction = requestMlWaitEstimate($snapshot['features'], 2);

try {
    $result = createQueueTicket(
        $conn,
        $userId,
        $serviceId,
        $clientType,
        $priorityLevel,
        $snapshot,
        $mlPrediction
    );
    if (!$result['created']) {
        redirectTo('views/client/ticket.php', ['msg' => 'active']);
    }
    try {
        processNearTurnAlerts($conn, $serviceId);
    } catch (Throwable $alertError) {
        // Alert generation should not block ticket creation.
    }
    redirectTo('views/client/ticket.php');
} catch (Throwable $e) {
    redirectWithFormFeedback('views/client/index.php', 'join_queue', [], $oldInput, 'Could not create queue ticket.');
}
?>
