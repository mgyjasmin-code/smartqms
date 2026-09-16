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
$metadataSelect = smartqmsTableHasColumn($conn, 'wait_time_logs', 'prediction_confidence')
    ? ', wl.prediction_confidence, wl.model_version'
    : ", NULL AS prediction_confidence, NULL AS model_version";

$ticketId = (int) requestValue('ticket_id', 0);
if ($ticketId > 0) {
    $stmt = $conn->prepare("
        SELECT wl.queue_length, wl.hour_of_day, wl.day_of_week, wl.service_type_encoded,
               wl.client_type_encoded, wl.active_windows, wl.avg_service_time,
               wl.predicted_wait_min{$metadataSelect}, qt.user_id, qt.window_id
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
        jsonResponse(true, [
            'estimated_wait_minutes' => (float) $row['predicted_wait_min'],
            'predicted_wait_minutes' => (float) $row['predicted_wait_min'],
            'confidence' => $row['prediction_confidence'] !== null ? (float) $row['prediction_confidence'] : null,
            'model_version' => $row['model_version'] ?: 'legacy-unversioned',
        ]);
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

    $mlResult = requestMlPrediction($features);
    if ($mlResult !== null) {
        $prediction = (float) $mlResult['estimated_wait_minutes'];
        $source = 'ml';
    }

    jsonResponse(true, [
        'predicted_wait_minutes' => $prediction,
        'estimated_wait_minutes' => $prediction,
        'confidence' => $mlResult['confidence'] ?? null,
        'model_version' => $mlResult['model_version'] ?? 'fallback-v1',
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

$mlResult = requestMlPrediction($features);
if ($mlResult !== null) {
    jsonResponse(true, [
        'predicted_wait_minutes' => (float) $mlResult['estimated_wait_minutes'],
        'estimated_wait_minutes' => (float) $mlResult['estimated_wait_minutes'],
        'confidence' => (float) $mlResult['confidence'],
        'model_version' => (string) $mlResult['model_version'],
        'source' => 'ml',
    ]);
}

jsonResponse(true, [
    'predicted_wait_minutes' => $fallback,
    'estimated_wait_minutes' => $fallback,
    'confidence' => null,
    'model_version' => 'fallback-v1',
    'source' => 'fallback',
]);
?>
