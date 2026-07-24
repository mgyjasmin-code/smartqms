<?php
/**
 * SmartQMS -- ML Prediction Proxy
 * PHP calls this to get predicted wait time from Flask API.
 * Returns JSON: { predicted_wait_minutes: 8.3 }
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(false, ['message' => 'Sign in before requesting queue predictions.'], 401);
}

$features = [];

$ticketId = (int) requestValue('ticket_id', 0);
if ($ticketId > 0) {
    $stmt = $conn->prepare("
        SELECT wl.queue_length, wl.hour_of_day, wl.day_of_week, wl.service_type_encoded,
               wl.client_type_encoded, wl.active_windows, wl.avg_service_time,
               wl.predicted_wait_min, qt.user_id, qt.window_id
        FROM wait_time_logs wl
        JOIN queue_tickets qt ON qt.ticket_id = wl.ticket_id
        WHERE wl.ticket_id=?
        LIMIT 1
    ");
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        jsonResponse(false, ['message' => 'Ticket prediction was not found.'], 404);
    }

    $allowed = false;
    if (($_SESSION['role'] ?? '') === ROLE_ADMIN) {
        $allowed = true;
    } elseif (($_SESSION['role'] ?? '') === ROLE_CLIENT) {
        $allowed = (int) $row['user_id'] === (int) $_SESSION['user_id'];
    } elseif (($_SESSION['role'] ?? '') === ROLE_STAFF) {
        $staffId = getCurrentStaffId($conn);
        $window = $staffId ? getStaffWindow($conn, $staffId) : null;
        $allowed = $window && (int) $row['window_id'] === (int) $window['window_id'];
    }

    if (!$allowed) {
        jsonResponse(false, ['message' => 'Ticket prediction is not available for this account.'], 403);
    }

    if ($row && $row['predicted_wait_min'] !== null) {
        jsonResponse(true, ['predicted_wait_minutes' => (float) $row['predicted_wait_min']]);
    }
    if ($row) {
        $features = $row;
    }
}

$serviceId = (int) requestValue('service_id', 0);
if ($serviceId > 0) {
    $clientType = (string) requestValue('client_type', 'regular');
    $snapshot = queuePredictionSnapshot($conn, $serviceId, $clientType);
    if (!$snapshot) {
        jsonResponse(false, ['message' => 'Choose an active health service.'], 422);
    }

    $features = $snapshot['features'];
    $fallback = (float) $snapshot['fallback_wait_min'];
    $prediction = $fallback;
    $source = 'fallback';

    $mlPrediction = requestMlWaitEstimate($features);
    if ($mlPrediction !== null) {
        $prediction = $mlPrediction;
        $source = 'ml';
    }

    jsonResponse(true, [
        'predicted_wait_minutes' => $prediction,
        'queue_length' => (int) $snapshot['queue_length'],
        'active_windows' => (int) $snapshot['active_windows'],
        'avg_service_time' => (float) $snapshot['avg_service_time'],
        'source' => $source,
    ]);
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

$mlPrediction = requestMlWaitEstimate($features);
if ($mlPrediction !== null) {
    jsonResponse(true, ['predicted_wait_minutes' => $mlPrediction, 'source' => 'ml']);
}

jsonResponse(true, ['predicted_wait_minutes' => $fallback, 'source' => 'fallback']);
?>
