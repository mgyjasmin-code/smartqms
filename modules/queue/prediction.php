<?php
/**
 * Queue wait-time feature construction and prediction helpers.
 */

function queuePredictionSnapshot(mysqli $conn, int $serviceId, string $clientType): ?array {
    $clientType = normalizeQueueClientType($clientType);

    $serviceStmt = $conn->prepare("SELECT * FROM health_services WHERE service_id = ? AND is_active = 1 LIMIT 1");
    $serviceStmt->bind_param('i', $serviceId);
    $serviceStmt->execute();
    $service = $serviceStmt->get_result()->fetch_assoc();
    if (!$service) {
        return null;
    }

    $priorityLevel = queuePriorityLevelForClientType($clientType);

    $queueLength = queueLengthForService($conn, $service);
    $activeWindows = countApplicableActiveWindows($conn, $service);

    $queueMode = normalizeQueueMode((string) ($service['queue_mode'] ?? 'central'));
    $avgSql = "
        SELECT AVG(recent.actual_service_dur) / 60 AS avg_min
        FROM (
            SELECT wl.actual_service_dur
            FROM wait_time_logs wl
            JOIN queue_tickets qt ON qt.ticket_id = wl.ticket_id
            WHERE qt.queue_mode = ?
              AND (? = 'central' OR qt.service_id = ?)
              AND wl.actual_service_dur IS NOT NULL
            ORDER BY wl.logged_at DESC
            LIMIT 20
        ) recent
    ";
    $avgStmt = $conn->prepare($avgSql);
    $avgStmt->bind_param('ssi', $queueMode, $queueMode, $serviceId);
    $avgStmt->execute();
    // Buffer the prepared result before issuing the schema-compatibility query
    // on the same mysqli connection. This is required by unbuffered drivers and
    // avoids "Commands out of sync" during concurrent ticket issuance.
    $avgRow = $avgStmt->get_result()->fetch_assoc();
    $defaultServiceMinutes = smartqmsTableHasColumn($conn, 'health_services', 'fallback_duration_mins')
        ? max(1.0, (float) ($service['fallback_duration_mins'] ?? 15))
        : 5.0;
    $avgServiceTime = (float) ($avgRow['avg_min'] ?? $defaultServiceMinutes);
    if ($avgServiceTime <= 0) {
        $avgServiceTime = $defaultServiceMinutes;
    }

    $features = [
        'queue_length' => $queueLength,
        'hour_of_day' => (int) date('G'),
        'day_of_week' => (int) date('w'),
        'service_type_encoded' => (int) $service['service_encoded'],
        'client_type_encoded' => clientTypeEncoded($clientType),
        'active_windows' => $activeWindows,
        'avg_service_time' => $avgServiceTime,
    ];

    return [
        'service' => $service,
        'availability' => serviceJoinAvailability($conn, $service),
        'features' => $features,
        'queue_length' => $queueLength,
        'active_windows' => $activeWindows,
        'avg_service_time' => $avgServiceTime,
        'priority_level' => $priorityLevel,
        'fallback_wait_min' => fallbackWaitEstimate($queueLength, $activeWindows, $avgServiceTime, $priorityLevel),
    ];
}

function clientTypeEncoded(string $type): int {
    return match ($type) {
        'senior' => 1,
        'pwd' => 2,
        default => 0,
    };
}

function fallbackWaitEstimate(int $queueLength, int $activeWindows, float $avgServiceTime, int $priorityLevel = 0): float {
    $windows = max(1, $activeWindows);
    $base = ($queueLength / $windows) * max(3.0, $avgServiceTime);
    if ($priorityLevel > 0) {
        $base *= 0.75;
    }
    return round(max(2, $base), 2);
}

/**
 * Ask the local ML service for an estimate without making queue operations
 * depend on that optional process. Any malformed or unreasonable response is
 * treated exactly like an unavailable service.
 */
function requestMlPrediction(array $features, int $timeoutSeconds = 2): ?array {
    if (!function_exists('curl_init')) {
        return null;
    }

    $payload = json_encode($features);
    if ($payload === false) {
        return null;
    }

    $config = function_exists('smartqmsMlConfig') ? smartqmsMlConfig() : [];
    $url = (string) ($config['predict_url'] ?? (defined('ML_API_URL') ? ML_API_URL : ''));
    if ($url === '') {
        return null;
    }
    $headers = ['Content-Type: application/json'];
    $token = trim((string) ($config['token'] ?? ''));
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 1,
        CURLOPT_TIMEOUT => max(1, min(5, $timeoutSeconds)),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $payload,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return parseMlPrediction($response, $httpCode);
}

function parseMlPrediction(mixed $response, int $httpCode): ?array {
    if (!is_string($response) || $httpCode !== 200) {
        return null;
    }

    $decoded = json_decode($response, true);
    $prediction = $decoded['estimated_wait_minutes']
        ?? $decoded['predicted_wait_minutes']
        ?? null;
    if (!is_numeric($prediction)) {
        return null;
    }

    $minutes = (float) $prediction;
    if (!is_finite($minutes) || $minutes < 0 || $minutes > 480) {
        return null;
    }

    $confidence = $decoded['confidence'] ?? null;
    if (!is_numeric($confidence) || !is_finite((float) $confidence)) {
        return null;
    }
    $confidence = (float) $confidence;
    if ($confidence < 0 || $confidence > 1) {
        return null;
    }

    $modelVersion = trim((string) ($decoded['model_version'] ?? ''));
    if ($modelVersion === '' || strlen($modelVersion) > 100) {
        return null;
    }

    return [
        'estimated_wait_minutes' => round($minutes, 2),
        'confidence' => round($confidence, 5),
        'model_version' => $modelVersion,
    ];
}

function requestMlWaitEstimate(array $features, int $timeoutSeconds = 2): ?float {
    $prediction = requestMlPrediction($features, $timeoutSeconds);
    return $prediction === null ? null : (float) $prediction['estimated_wait_minutes'];
}

function parseMlWaitEstimate(mixed $response, int $httpCode): ?float {
    if (!is_string($response) || $httpCode !== 200) {
        return null;
    }
    $decoded = json_decode($response, true);
    $value = $decoded['predicted_wait_minutes'] ?? null;
    if (!is_numeric($value)) {
        return null;
    }
    $minutes = (float) $value;
    return is_finite($minutes) && $minutes >= 0 && $minutes <= 480
        ? round($minutes, 2)
        : null;
}
