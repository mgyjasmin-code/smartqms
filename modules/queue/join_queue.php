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
require_once __DIR__ . '/../notifications/send_alert.php';
requireLogin(ROLE_CLIENT);

requirePostRequest(false, 'views/client/index.php', 'join_queue');

$serviceId = (int) ($_POST['service_id'] ?? 0);
$clientType = $_POST['client_type'] ?? 'regular';
if (!in_array($clientType, ['regular', 'senior', 'pwd'], true)) {
    $clientType = 'regular';
}
$oldInput = [
    'service_id' => (string) $serviceId,
    'client_type' => $clientType,
];

requireValidCsrf('views/client/index.php', 'join_queue', $oldInput);

$userId = (int) $_SESSION['user_id'];
if (getActiveTicket($conn, $userId)) {
    redirectTo('views/client/ticket.php', ['msg' => 'active']);
}

$priorityLevel = in_array($clientType, ['senior', 'pwd'], true) ? 1 : 0;
$snapshot = queuePredictionSnapshot($conn, $serviceId, $clientType);
if (!$snapshot) {
    redirectWithFormFeedback('views/client/index.php', 'join_queue', [
        'service_id' => 'Choose an active health service.',
    ], $oldInput);
}

$service = $snapshot['service'];
$priorityLevel = (int) $snapshot['priority_level'];

if ((int) $service['priority_only'] === 1 && $priorityLevel === 0) {
    redirectWithFormFeedback('views/client/index.php', 'join_queue', [
        'client_type' => 'That service is reserved for Senior/PWD clients.',
    ], $oldInput);
}

$referenceNumber = generateRefNumber($conn);
$dailyStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM queue_tickets WHERE DATE(issued_at)=CURDATE()");
$dailyStmt->execute();
$ticketNumber = 'A-' . str_pad(((int) ($dailyStmt->get_result()->fetch_assoc()['cnt'] ?? 0)) + 1, 3, '0', STR_PAD_LEFT);

$conn->begin_transaction();
try {
    $insert = $conn->prepare("INSERT INTO queue_tickets (user_id, service_id, reference_number, ticket_number, client_type, priority_level) VALUES (?, ?, ?, ?, ?, ?)");
    $insert->bind_param('iisssi', $userId, $serviceId, $referenceNumber, $ticketNumber, $clientType, $priorityLevel);
    $insert->execute();
    $ticketId = $conn->insert_id;

    $qrPath = generateQR($referenceNumber, (string) $ticketId);
    $qrUpdate = $conn->prepare("UPDATE queue_tickets SET qr_code_path = ? WHERE ticket_id = ?");
    $qrUpdate->bind_param('si', $qrPath, $ticketId);
    $qrUpdate->execute();

    $payload = $snapshot['features'];
    $hour = (int) $payload['hour_of_day'];
    $day = (int) $payload['day_of_week'];
    $clientEncoded = (int) $payload['client_type_encoded'];
    $queueLength = (int) $payload['queue_length'];
    $activeWindows = (int) $payload['active_windows'];
    $avgServiceTime = (float) $payload['avg_service_time'];
    $predicted = (float) $snapshot['fallback_wait_min'];

    $ch = curl_init(ML_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 2,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response && $httpCode === 200) {
        $decoded = json_decode($response, true);
        if (isset($decoded['predicted_wait_minutes'])) {
            $predicted = (float) $decoded['predicted_wait_minutes'];
        }
    }

    $log = $conn->prepare("
        INSERT INTO wait_time_logs
          (ticket_id, queue_length, hour_of_day, day_of_week, service_type_encoded, client_type_encoded, active_windows, avg_service_time, predicted_wait_min)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $serviceEncoded = (int) $service['service_encoded'];
    $log->bind_param('iiiiiiidd', $ticketId, $queueLength, $hour, $day, $serviceEncoded, $clientEncoded, $activeWindows, $avgServiceTime, $predicted);
    $log->execute();

    logActivity($conn, 'ticket_created', 'Client joined queue', $ticketId);
    $conn->commit();
    try {
        processNearTurnAlerts($conn, $serviceId);
    } catch (Throwable $alertError) {
        // Alert generation should not block ticket creation.
    }
    redirectTo('views/client/ticket.php');
} catch (Throwable $e) {
    $conn->rollback();
    redirectWithFormFeedback('views/client/index.php', 'join_queue', [], $oldInput, 'Could not create queue ticket.');
}
?>
