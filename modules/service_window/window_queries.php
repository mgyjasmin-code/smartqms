<?php
/**
 * Shared staff and service-window lookup helpers.
 */

function getCurrentStaffId(mysqli $conn): ?int {
    if (isset($_SESSION['staff_id'])) {
        return (int) $_SESSION['staff_id'];
    }
    if (($_SESSION['role'] ?? '') !== ROLE_STAFF || empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $conn->prepare("SELECT staff_id FROM staff WHERE user_id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return null;
    }
    $_SESSION['staff_id'] = (int) $row['staff_id'];
    return (int) $row['staff_id'];
}

function getStaffWindow(mysqli $conn, int $staffId): ?array {
    $stmt = $conn->prepare("
        SELECT sw.*, hs.service_name
        FROM service_windows sw
        LEFT JOIN health_services hs ON hs.service_id = sw.service_id
        WHERE sw.staff_id = ? AND sw.is_active = 1
        LIMIT 1
    ");
    $stmt->bind_param('i', $staffId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function getStaffWindowForUpdate(mysqli $conn, int $staffId): ?array {
    $stmt = $conn->prepare("
        SELECT *
        FROM service_windows
        WHERE staff_id = ? AND is_active = 1
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->bind_param('i', $staffId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function getServingTicketForWindow(mysqli $conn, int $ticketId, int $windowId): ?array {
    $stmt = $conn->prepare("SELECT * FROM queue_tickets WHERE ticket_id=? AND status='serving' AND window_id=? LIMIT 1");
    $stmt->bind_param('ii', $ticketId, $windowId);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc() ?: null;
}

function getServingTicketForWindowForUpdate(mysqli $conn, int $ticketId, int $windowId): ?array {
    $stmt = $conn->prepare("
        SELECT *
        FROM queue_tickets
        WHERE ticket_id = ? AND status = 'serving' AND window_id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->bind_param('ii', $ticketId, $windowId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function getCurrentServingTicketForWindow(mysqli $conn, int $windowId, bool $forUpdate = false): ?array {
    $sql = "
        SELECT *
        FROM queue_tickets
        WHERE window_id = ? AND status = 'serving'
        ORDER BY called_at DESC
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $windowId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function getNextWaitingTicketForUpdate(mysqli $conn, int $serviceId): ?array {
    $stmt = $conn->prepare("
        SELECT *
        FROM queue_tickets
        WHERE status = 'waiting' AND service_id = ?
        ORDER BY priority_level DESC, issued_at ASC
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->bind_param('i', $serviceId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function getExpiredServingTicketsForUpdate(
    mysqli $conn,
    int $windowId,
    int $timeoutMinutes
): array {
    $stmt = $conn->prepare("
        SELECT *
        FROM queue_tickets
        WHERE status = 'serving'
          AND window_id = ?
          AND called_at IS NOT NULL
          AND TIMESTAMPDIFF(MINUTE, called_at, NOW()) >= ?
        FOR UPDATE
    ");
    $stmt->bind_param('ii', $windowId, $timeoutMinutes);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getStaffWindowCurrentTicket(mysqli $conn, int $windowId): ?array {
    $stmt = $conn->prepare("
        SELECT qt.*,
               CONCAT(u.first_name, ' ', u.last_name) AS client_name,
               u.first_name AS client_first_name,
               u.last_name AS client_last_name
        FROM queue_tickets qt
        JOIN users u ON u.user_id = qt.user_id
        WHERE qt.window_id = ? AND qt.status = 'serving'
        ORDER BY qt.called_at DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $windowId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function getStaffWindowWaitingTickets(mysqli $conn, int $serviceId, int $limit = 20): array {
    $limit = max(1, min(20, $limit));
    $stmt = $conn->prepare("
        SELECT qt.*,
               hs.service_name,
               CONCAT(u.first_name, ' ', u.last_name) AS client_name,
               u.first_name AS client_first_name,
               u.last_name AS client_last_name
        FROM queue_tickets qt
        JOIN health_services hs ON hs.service_id = qt.service_id
        JOIN users u ON u.user_id = qt.user_id
        WHERE qt.status = 'waiting' AND qt.service_id = ?
        ORDER BY qt.priority_level DESC, qt.issued_at ASC
        LIMIT {$limit}
    ");
    $stmt->bind_param('i', $serviceId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function staffVoidRemainingSeconds(?string $calledAt, int $timeoutMinutes, ?int $now = null): ?int {
    if (empty($calledAt)) {
        return null;
    }
    $calledTimestamp = strtotime($calledAt);
    if (!$calledTimestamp) {
        return max(0, $timeoutMinutes * 60);
    }
    $elapsed = max(0, ($now ?? time()) - $calledTimestamp);
    return max(0, ($timeoutMinutes * 60) - $elapsed);
}

function staffClientTypeLabel(?string $type): string {
    return match ($type) {
        'senior' => 'Senior Citizen',
        'pwd' => 'PWD',
        default => 'Regular',
    };
}

function staffClientInitials(?string $name): string {
    $name = trim((string) $name);
    if ($name === '') {
        return 'SQ';
    }
    $parts = preg_split('/\s+/', $name);
    $first = strtoupper(substr((string) ($parts[0] ?? 'S'), 0, 1));
    $last = strtoupper(substr((string) ($parts[count($parts) - 1] ?? 'Q'), 0, 1));
    return $first . $last;
}

function updateServiceWindowStatus(mysqli $conn, int $windowId, string $status): void {
    $stmt = $conn->prepare("UPDATE service_windows SET status=? WHERE window_id=?");
    $stmt->bind_param('si', $status, $windowId);
    $stmt->execute();
}
