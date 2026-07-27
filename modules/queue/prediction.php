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

    $countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM queue_tickets WHERE status = 'waiting' AND service_id = ?");
    $countStmt->bind_param('i', $serviceId);
    $countStmt->execute();
    $queueLength = (int) ($countStmt->get_result()->fetch_assoc()['cnt'] ?? 0);

    $winStmt = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM service_windows
        WHERE is_active = 1
          AND status IN ('open','busy')
          AND (service_id = ? OR service_id IS NULL)
    ");
    $winStmt->bind_param('i', $serviceId);
    $winStmt->execute();
    $activeWindows = max(1, (int) ($winStmt->get_result()->fetch_assoc()['cnt'] ?? 1));

    $avgStmt = $conn->prepare("
        SELECT AVG(recent.actual_service_dur) / 60 AS avg_min
        FROM (
            SELECT wl.actual_service_dur
            FROM wait_time_logs wl
            JOIN queue_tickets qt ON qt.ticket_id = wl.ticket_id
            WHERE qt.service_id = ? AND wl.actual_service_dur IS NOT NULL
            ORDER BY wl.logged_at DESC
            LIMIT 20
        ) recent
    ");
    $avgStmt->bind_param('i', $serviceId);
    $avgStmt->execute();
    $avgServiceTime = (float) ($avgStmt->get_result()->fetch_assoc()['avg_min'] ?? 5);
    if ($avgServiceTime <= 0) {
        $avgServiceTime = 5.0;
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
function requestMlWaitEstimate(array $features, int $timeoutSeconds = 2): ?float {
    if (!function_exists('curl_init')) {
        return null;
    }

    $payload = json_encode($features);
    if ($payload === false) {
        return null;
    }

    $ch = curl_init(ML_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 1,
        CURLOPT_TIMEOUT => max(1, min(2, $timeoutSeconds)),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return parseMlWaitEstimate($response, $httpCode);
}

function parseMlWaitEstimate(mixed $response, int $httpCode): ?float {
    if (!is_string($response) || $httpCode !== 200) {
        return null;
    }

    $decoded = json_decode($response, true);
    $prediction = $decoded['predicted_wait_minutes'] ?? null;
    if (!is_numeric($prediction)) {
        return null;
    }

    $minutes = (float) $prediction;
    if (!is_finite($minutes) || $minutes < 0 || $minutes > 480) {
        return null;
    }

    return round($minutes, 2);
}
