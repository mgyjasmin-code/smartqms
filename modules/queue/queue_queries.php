<?php
/**
 * Shared queue lookup, locking, and ticket-number helpers.
 */

function normalizeQueueClientType(string $clientType): string {
    return in_array($clientType, ['regular', 'senior', 'pwd'], true)
        ? $clientType
        : 'regular';
}

function queuePriorityLevelForClientType(string $clientType): int {
    // Batch 8J keeps the historical classification value but removes it from
    // every live ordering decision.
    return 0;
}

function queueServiceAllowsClientType(array $service, string $clientType): bool {
    return true;
}

function ticketIssuanceLockName(int $year): string {
    return 'smartqms:ticket-issue:' . $year;
}

function acquireTicketIssuanceLock(mysqli $conn, int $year, int $timeoutSeconds = 5): bool {
    $lockName = ticketIssuanceLockName($year);
    $timeout = max(0, min(5, $timeoutSeconds));
    $stmt = $conn->prepare('SELECT GET_LOCK(?, ?) AS acquired');
    $stmt->bind_param('si', $lockName, $timeout);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['acquired'] ?? 0) === 1;
}

function releaseTicketIssuanceLock(mysqli $conn, int $year): void {
    $lockName = ticketIssuanceLockName($year);
    $stmt = $conn->prepare('SELECT RELEASE_LOCK(?)');
    $stmt->bind_param('s', $lockName);
    $stmt->execute();
}

function lockQueueClient(mysqli $conn, int $userId): bool {
    $stmt = $conn->prepare('SELECT user_id FROM users WHERE user_id = ? LIMIT 1 FOR UPDATE');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}

function getActiveTicketForLockedClient(mysqli $conn, int $userId): ?array {
    $hasLifecycle = smartqmsTableHasColumn($conn, 'queue_tickets', 'lifecycle_status');
    $activePredicate = $hasLifecycle
        ? "lifecycle_status IN ('scheduled','waiting','calling','in-progress')"
        : "status IN ('waiting','serving')";
    $stmt = $conn->prepare("
        SELECT ticket_id, user_id, service_id, queue_mode, reference_number, ticket_number,
               client_type, priority_level, status, lifecycle_status, issued_at, checked_in_at
        FROM queue_tickets
        WHERE user_id = ? AND {$activePredicate}
        ORDER BY issued_at DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function generateRefNumber(mysqli $conn, ?int $year = null): string {
    $year = $year ?? (int) date('Y');
    $referencePattern = '^' . preg_quote(REF_PREFIX, '/') . '-' . $year . '-[0-9]+$';
    $stmt = $conn->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(reference_number, '-', -1) AS UNSIGNED)) AS max_suffix
        FROM queue_tickets
        WHERE reference_number REGEXP ?
        FOR UPDATE
    ");
    $stmt->bind_param('s', $referencePattern);
    $stmt->execute();
    $nextSuffix = ((int) ($stmt->get_result()->fetch_assoc()['max_suffix'] ?? 0)) + 1;

    return REF_PREFIX . '-' . $year . '-' . str_pad($nextSuffix, 4, '0', STR_PAD_LEFT);
}

function generateDailyTicketNumber(mysqli $conn): string {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM queue_tickets
        WHERE DATE(issued_at) = CURDATE()
        FOR UPDATE
    ");
    $stmt->execute();
    $count = (int) ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);

    return 'A-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
}

function getActiveTicket(mysqli $conn, int $userId): ?array {
    $hasLifecycle = smartqmsTableHasColumn($conn, 'queue_tickets', 'lifecycle_status');
    $activePredicate = $hasLifecycle
        ? "qt.lifecycle_status IN ('scheduled','waiting','calling','in-progress')"
        : "qt.status IN ('waiting','serving')";
    $stmt = $conn->prepare("
        SELECT qt.*, hs.service_name, hs.service_encoded, sw.window_name, wl.predicted_wait_min
        FROM queue_tickets qt
        JOIN health_services hs ON hs.service_id = qt.service_id
        LEFT JOIN service_windows sw ON sw.window_id = qt.window_id
        LEFT JOIN wait_time_logs wl ON wl.ticket_id = qt.ticket_id
        WHERE qt.user_id = ? AND {$activePredicate}
        ORDER BY qt.issued_at DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function getCompletedTicketAwaitingFeedback(mysqli $conn, int $userId): ?array {
    $stmt = $conn->prepare("
        SELECT qt.*, hs.service_name, hs.service_encoded, sw.window_name, wl.predicted_wait_min
        FROM queue_tickets qt
        JOIN health_services hs ON hs.service_id = qt.service_id
        LEFT JOIN service_windows sw ON sw.window_id = qt.window_id
        LEFT JOIN wait_time_logs wl ON wl.ticket_id = qt.ticket_id
        WHERE qt.user_id = ?
          AND qt.status = 'completed'
          AND NOT EXISTS (
              SELECT 1
              FROM feedback f
              WHERE f.ticket_id = qt.ticket_id
          )
        ORDER BY COALESCE(qt.completed_at, qt.issued_at) DESC, qt.ticket_id DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function peopleAhead(mysqli $conn, array $ticket): int {
    $queueMode = normalizeQueueMode((string) ($ticket['queue_mode'] ?? 'central'));
    $checkedInAt = (string) ($ticket['checked_in_at'] ?? $ticket['issued_at'] ?? '');
    $ticketId = (int) ($ticket['ticket_id'] ?? PHP_INT_MAX);

    if ($checkedInAt === '' || ($ticket['lifecycle_status'] ?? 'waiting') !== 'waiting') {
        return 0;
    }

    if ($queueMode === QUEUE_MODE_SPECIALIZED) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS cnt
            FROM queue_tickets
            WHERE status = 'waiting'
              AND lifecycle_status = 'waiting'
              AND checked_in_at IS NOT NULL
              AND queue_mode = 'specialized'
              AND service_id = ?
              AND (
                checked_in_at < ?
                OR (checked_in_at = ? AND ticket_id < ?)
              )
        ");
        $serviceId = (int) $ticket['service_id'];
        $stmt->bind_param('issi', $serviceId, $checkedInAt, $checkedInAt, $ticketId);
    } else {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS cnt
            FROM queue_tickets
            WHERE status = 'waiting'
              AND lifecycle_status = 'waiting'
              AND checked_in_at IS NOT NULL
              AND queue_mode = 'central'
              AND (
                checked_in_at < ?
                OR (checked_in_at = ? AND ticket_id < ?)
              )
        ");
        $stmt->bind_param('ssi', $checkedInAt, $checkedInAt, $ticketId);
    }
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
}
