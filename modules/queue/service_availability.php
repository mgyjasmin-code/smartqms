<?php
/**
 * Hybrid-queue routing and service-availability rules.
 *
 * These functions are the single source of truth for deciding which queue a
 * service uses and whether a Client may join it. UI availability is advisory;
 * state-changing handlers must call these functions again inside a transaction.
 */

const QUEUE_MODE_CENTRAL = 'central';
const QUEUE_MODE_SPECIALIZED = 'specialized';
const WINDOW_TYPE_SHARED = 'shared';
const WINDOW_TYPE_SPECIALIZED = 'specialized';

function normalizeQueueMode(?string $queueMode): string {
    return $queueMode === QUEUE_MODE_SPECIALIZED
        ? QUEUE_MODE_SPECIALIZED
        : QUEUE_MODE_CENTRAL;
}

function normalizeWindowType(?string $windowType): string {
    return $windowType === WINDOW_TYPE_SPECIALIZED
        ? WINDOW_TYPE_SPECIALIZED
        : WINDOW_TYPE_SHARED;
}

function queueOperatingHours(mysqli $conn): array {
    return [
        'open' => getSetting($conn, 'queue_open_time', '07:00'),
        'close' => getSetting($conn, 'queue_close_time', '17:00'),
    ];
}

function queueIsOpenAt(array $hours, ?DateTimeImmutable $now = null): bool {
    $now = $now ?? new DateTimeImmutable('now');
    $open = DateTimeImmutable::createFromFormat('!H:i', (string) ($hours['open'] ?? ''));
    $close = DateTimeImmutable::createFromFormat('!H:i', (string) ($hours['close'] ?? ''));
    if (!$open || !$close) {
        return false;
    }

    $currentMinutes = ((int) $now->format('G') * 60) + (int) $now->format('i');
    $openMinutes = ((int) $open->format('G') * 60) + (int) $open->format('i');
    $closeMinutes = ((int) $close->format('G') * 60) + (int) $close->format('i');

    if ($openMinutes === $closeMinutes) {
        return true;
    }
    if ($openMinutes < $closeMinutes) {
        return $currentMinutes >= $openMinutes && $currentMinutes < $closeMinutes;
    }
    return $currentMinutes >= $openMinutes || $currentMinutes < $closeMinutes;
}

function queueHasDailyCapacity(mysqli $conn): bool {
    $capacity = max(0, (int) getSetting($conn, 'max_queue_per_day', '100'));
    if ($capacity === 0) {
        return false;
    }

    $row = $conn->query("SELECT COUNT(*) AS total FROM queue_tickets WHERE DATE(issued_at) = CURDATE()")
        ->fetch_assoc();
    return (int) ($row['total'] ?? 0) < $capacity;
}

function countApplicableActiveWindows(mysqli $conn, array $service): int {
    $serviceId = (int) ($service['service_id'] ?? 0);

    if ($serviceId < 1) {
        return 0;
    }

    if (smartqmsTableExists($conn, 'counter_services')) {
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT sw.window_id) AS total
            FROM service_windows sw
            LEFT JOIN counter_services cs
              ON cs.counter_id = sw.window_id
             AND cs.service_id = ?
            JOIN staff operator_staff ON operator_staff.staff_id = sw.staff_id
            JOIN users operator_user
              ON operator_user.user_id = operator_staff.user_id
             AND operator_user.is_active = 1
            WHERE sw.is_active = 1
              AND sw.staff_id IS NOT NULL
              AND sw.status IN ('open','busy')
              AND (
                cs.service_id IS NOT NULL
                OR sw.service_id = ?
                OR (
                  sw.window_type = 'shared'
                  AND NOT EXISTS (
                    SELECT 1 FROM counter_services configured
                    WHERE configured.counter_id = sw.window_id
                  )
                )
              )
        ");
        $stmt->bind_param('ii', $serviceId, $serviceId);
        $stmt->execute();
        return (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    }

    // Compatibility fallback for databases that have not yet applied Batch 8J.
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT sw.window_id) AS total
        FROM service_windows sw
        JOIN staff s ON s.staff_id = sw.staff_id
        JOIN users operator_user ON operator_user.user_id = s.user_id
        WHERE sw.is_active = 1
          AND sw.service_id = ?
          AND sw.staff_id IS NOT NULL
          AND operator_user.is_active = 1
          AND sw.status IN ('open','busy')
    ");
    $stmt->bind_param('i', $serviceId);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
}

function queueLengthForService(mysqli $conn, array $service): int {
    $serviceId = (int) ($service['service_id'] ?? 0);
    if ($serviceId < 1) {
        return 0;
    }
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM queue_tickets
        WHERE status = 'waiting'
          AND lifecycle_status = 'waiting'
          AND checked_in_at IS NOT NULL
          AND service_id = ?
    ");
    $stmt->bind_param('i', $serviceId);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
}

function serviceJoinAvailability(
    mysqli $conn,
    array $service,
    ?DateTimeImmutable $now = null
): array {
    $queueMode = normalizeQueueMode((string) ($service['queue_mode'] ?? 'central'));
    $activeWindows = countApplicableActiveWindows($conn, $service);
    $reason = null;

    if ((int) ($service['is_active'] ?? 0) !== 1) {
        $reason = 'This service is not currently offered.';
    } elseif (!queueIsOpenAt(queueOperatingHours($conn), $now)) {
        $reason = 'Queueing is closed outside the configured service hours.';
    } elseif (!queueHasDailyCapacity($conn)) {
        $reason = 'The queue has reached today\'s capacity.';
    } elseif ($activeWindows < 1) {
        $reason = $queueMode === QUEUE_MODE_SPECIALIZED
            ? 'No qualified specialized window is operating right now.'
            : 'No shared service window is operating right now.';
    }

    return [
        'available' => $reason === null,
        'reason' => $reason,
        // Retained only as a response alias for legacy callers.
        'queue_mode' => $queueMode,
        'active_windows' => $activeWindows,
    ];
}

function findServiceForQueueing(mysqli $conn, int $serviceId, bool $forUpdate = false): ?array {
    $sql = 'SELECT * FROM health_services WHERE service_id = ? LIMIT 1';
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $serviceId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function listServiceJoinAvailability(mysqli $conn, ?DateTimeImmutable $now = null): array {
    $visibility = smartqmsTableHasColumn($conn, 'health_services', 'is_hidden')
        ? 'AND is_hidden = 0'
        : '';
    $services = $conn->query("
        SELECT * FROM health_services
        WHERE is_active = 1 {$visibility}
        ORDER BY display_order, service_name
    ")->fetch_all(MYSQLI_ASSOC);

    foreach ($services as &$service) {
        $service['availability'] = serviceJoinAvailability($conn, $service, $now);
    }
    unset($service);
    return $services;
}
