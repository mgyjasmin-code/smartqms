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
    return in_array(normalizeQueueClientType($clientType), ['senior', 'pwd'], true)
        ? 1
        : 0;
}

function queueServiceAllowsClientType(array $service, string $clientType): bool {
    return (int) ($service['priority_only'] ?? 0) !== 1
        || queuePriorityLevelForClientType($clientType) === 1;
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
    $stmt = $conn->prepare("
        SELECT ticket_id, user_id, service_id, reference_number, ticket_number,
               client_type, priority_level, status, issued_at
        FROM queue_tickets
        WHERE user_id = ? AND status IN ('waiting','serving')
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
    $stmt = $conn->prepare("
        SELECT qt.*, hs.service_name, hs.service_encoded, sw.window_name, wl.predicted_wait_min
        FROM queue_tickets qt
        JOIN health_services hs ON hs.service_id = qt.service_id
        LEFT JOIN service_windows sw ON sw.window_id = qt.window_id
        LEFT JOIN wait_time_logs wl ON wl.ticket_id = qt.ticket_id
        WHERE qt.user_id = ? AND qt.status IN ('waiting','serving')
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
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM queue_tickets
        WHERE status = 'waiting'
          AND service_id = ?
          AND (
            priority_level > ?
            OR (priority_level = ? AND issued_at < ?)
          )
    ");
    $serviceId = (int) $ticket['service_id'];
    $priority = (int) $ticket['priority_level'];
    $issuedAt = $ticket['issued_at'];
    $stmt->bind_param('iiis', $serviceId, $priority, $priority, $issuedAt);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
}
