<?php
/**
 * SmartQMS -- ML Prediction Proxy
 * PHP calls this to get predicted wait time from Flask API.
 * Returns JSON: { predicted_wait_minutes: 8.3 }
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
header('Content-Type: application/json');

$features = [];

$ticketId = (int) requestValue('ticket_id', 0);
if ($ticketId > 0) {
    $stmt = $conn->prepare("SELECT queue_length, hour_of_day, day_of_week, service_type_encoded, client_type_encoded, active_windows, avg_service_time, predicted_wait_min FROM wait_time_logs WHERE ticket_id=?");
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row && $row['predicted_wait_min'] !== null) {
        jsonResponse(true, ['predicted_wait_minutes' => (float) $row['predicted_wait_min']]);
    }
    if ($row) {
        $features = $row;
    }
}

foreach (['queue_length', 'hour_of_day', 'day_of_week', 'service_type_encoded', 'client_type_encoded', 'active_windows', 'avg_service_time'] as $field) {
    if (!isset($features[$field])) {
        $features[$field] = requestValue($field, null);
    }
}

$queueLength = (int) ($features['queue_length'] ?? 0);
$activeWindows = (int) ($features['active_windows'] ?? 1);
$avgServiceTime = (float) ($features['avg_service_time'] ?? 5);
$fallback = fallbackWaitEstimate($queueLength, $activeWindows, $avgServiceTime);

$ch = curl_init(ML_API_URL);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 2,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($features),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response && $httpCode === 200) {
    $decoded = json_decode($response, true);
    if (isset($decoded['predicted_wait_minutes'])) {
        jsonResponse(true, ['predicted_wait_minutes' => (float) $decoded['predicted_wait_minutes'], 'source' => 'ml']);
    }
}

jsonResponse(true, ['predicted_wait_minutes' => $fallback, 'source' => 'fallback']);
?>
