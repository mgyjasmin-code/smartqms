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
requireLogin(ROLE_CLIENT);

$userId = (int) $_SESSION['user_id'];
if (getActiveTicket($conn, $userId)) {
    redirectTo('views/client/ticket.php', ['msg' => 'active']);
}

$serviceId = (int) ($_POST['service_id'] ?? 0);
$clientType = $_POST['client_type'] ?? 'regular';
if (!in_array($clientType, ['regular', 'senior', 'pwd'], true)) {
    $clientType = 'regular';
}
$priorityLevel = in_array($clientType, ['senior', 'pwd'], true) ? 1 : 0;

$serviceStmt = $conn->prepare("SELECT * FROM health_services WHERE service_id=? AND is_active=1 LIMIT 1");
$serviceStmt->bind_param('i', $serviceId);
$serviceStmt->execute();
$service = $serviceStmt->get_result()->fetch_assoc();
if (!$service) {
    redirectTo('views/client/index.php', ['error' => 'Choose an active health service.']);
}
if ((int) $service['priority_only'] === 1 && $priorityLevel === 0) {
    redirectTo('views/client/index.php', ['error' => 'That service is reserved for Senior/PWD clients.']);
}

$countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM queue_tickets WHERE status='waiting' AND service_id=?");
$countStmt->bind_param('i', $serviceId);
$countStmt->execute();
$queueLength = (int) ($countStmt->get_result()->fetch_assoc()['cnt'] ?? 0);

$winStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM service_windows WHERE is_active=1 AND status IN ('open','busy') AND (service_id=? OR service_id IS NULL)");
$winStmt->bind_param('i', $serviceId);
$winStmt->execute();
$activeWindows = max(1, (int) ($winStmt->get_result()->fetch_assoc()['cnt'] ?? 1));

$avgStmt = $conn->prepare("
    SELECT AVG(wl.actual_service_dur)/60 AS avg_min
    FROM wait_time_logs wl
    JOIN queue_tickets qt ON qt.ticket_id=wl.ticket_id
    WHERE qt.service_id=? AND wl.actual_service_dur IS NOT NULL
    ORDER BY wl.logged_at DESC
    LIMIT 20
");
$avgStmt->bind_param('i', $serviceId);
$avgStmt->execute();
$avgServiceTime = (float) ($avgStmt->get_result()->fetch_assoc()['avg_min'] ?? 5);
if ($avgServiceTime <= 0) {
    $avgServiceTime = 5.0;
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

    $hour = (int) date('G');
    $day = (int) date('w');
    $clientEncoded = clientTypeEncoded($clientType);
    $predicted = fallbackWaitEstimate($queueLength, $activeWindows, $avgServiceTime, $priorityLevel);

    $payload = [
        'queue_length' => $queueLength,
        'hour_of_day' => $hour,
        'day_of_week' => $day,
        'service_type_encoded' => (int) $service['service_encoded'],
        'client_type_encoded' => $clientEncoded,
        'active_windows' => $activeWindows,
        'avg_service_time' => $avgServiceTime,
    ];
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
    redirectTo('views/client/ticket.php');
} catch (Throwable $e) {
    $conn->rollback();
    redirectTo('views/client/index.php', ['error' => 'Could not create queue ticket.']);
}
?>
