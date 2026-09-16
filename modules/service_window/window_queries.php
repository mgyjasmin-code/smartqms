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
    $window = $stmt->get_result()->fetch_assoc() ?: null;
    if ($window) {
        $names = getWindowServiceNames($conn, (int) $window['window_id']);
        if ($names !== '') {
            $window['service_name'] = $names;
        } elseif (($window['window_type'] ?? 'shared') === 'shared' && empty($window['service_name'])) {
            $window['service_name'] = 'All services';
        }
    }
    return $window;
}

function getWindowServiceIds(mysqli $conn, array $window): array {
    $windowId = (int) ($window['window_id'] ?? 0);
    $hasCounterServices = smartqmsTableExists($conn, 'counter_services');
    if ($windowId > 0 && $hasCounterServices) {
        $stmt = $conn->prepare("
            SELECT cs.service_id
            FROM counter_services cs
            JOIN health_services hs ON hs.service_id = cs.service_id
            WHERE cs.counter_id = ? AND hs.is_active = 1
            ORDER BY hs.display_order, hs.service_name
        ");
        $stmt->bind_param('i', $windowId);
        $stmt->execute();
        $ids = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'service_id'));
        if ($ids) {
            return $ids;
        }
        $mapping = $conn->prepare('SELECT 1 FROM counter_services WHERE counter_id = ? LIMIT 1');
        $mapping->bind_param('i', $windowId);
        $mapping->execute();
        if ($mapping->get_result()->fetch_row()) {
            return [];
        }
    }

    $serviceId = (int) ($window['service_id'] ?? 0);
    if ($serviceId > 0) {
        return [$serviceId];
    }

    if (($window['window_type'] ?? 'shared') === 'shared') {
        $visibility = smartqmsTableHasColumn($conn, 'health_services', 'is_hidden')
            ? 'AND is_hidden = 0'
            : '';
        $result = $conn->query("SELECT service_id FROM health_services WHERE is_active = 1 {$visibility} ORDER BY display_order, service_name");
        return $result ? array_map('intval', array_column($result->fetch_all(MYSQLI_ASSOC), 'service_id')) : [];
    }

    return [];
}

function getWindowServiceNames(mysqli $conn, int $windowId): string {
    if (!smartqmsTableExists($conn, 'counter_services')) {
        return '';
    }
    $stmt = $conn->prepare("
        SELECT GROUP_CONCAT(hs.service_name ORDER BY hs.display_order SEPARATOR ', ') AS service_names
        FROM counter_services cs
        JOIN health_services hs ON hs.service_id = cs.service_id AND hs.is_active = 1
        WHERE cs.counter_id = ?
    ");
    $stmt->bind_param('i', $windowId);
    $stmt->execute();
    return trim((string) ($stmt->get_result()->fetch_assoc()['service_names'] ?? ''));
}

function getWindowServices(mysqli $conn, array $window): array {
    $ids = getWindowServiceIds($conn, $window);
    if (!$ids) {
        return [];
    }
    $idList = implode(',', array_map('intval', $ids));
    $visibility = smartqmsTableHasColumn($conn, 'health_services', 'is_hidden')
        ? 'AND is_hidden = 0'
        : '';
    $result = $conn->query("
        SELECT service_id, service_name, priority_only
        FROM health_services
        WHERE is_active = 1 AND service_id IN ({$idList}) {$visibility}
        ORDER BY display_order, service_name
    ");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
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
        WHERE status = 'waiting'
          AND lifecycle_status = 'waiting'
          AND checked_in_at IS NOT NULL
          AND service_id = ?
        ORDER BY checked_in_at ASC, ticket_id ASC
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->bind_param('i', $serviceId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function getNextWaitingTicketForWindowForUpdate(mysqli $conn, array $window): ?array {
    $serviceIds = getWindowServiceIds($conn, $window);
    if (!$serviceIds) {
        return null;
    }
    $idList = implode(',', array_map('intval', $serviceIds));
    $stmt = $conn->prepare("
        SELECT *
        FROM queue_tickets
        WHERE status = 'waiting'
          AND lifecycle_status = 'waiting'
          AND checked_in_at IS NOT NULL
          AND service_id IN ({$idList})
        ORDER BY checked_in_at ASC, ticket_id ASC
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function getExpiredServingTicketsForUpdate(
    mysqli $conn,
    int $windowId,
    int $timeoutMinutes
): array {
    $lifecycle = smartqmsTableHasColumn($conn, 'queue_tickets', 'lifecycle_status')
        ? "AND lifecycle_status = 'calling'"
        : '';
    $stmt = $conn->prepare("
        SELECT *
        FROM queue_tickets
        WHERE status = 'serving'
          AND window_id = ?
          AND called_at IS NOT NULL
          {$lifecycle}
          AND TIMESTAMPDIFF(SECOND, called_at, NOW()) >= (? * 60)
        FOR UPDATE
    ");
    $stmt->bind_param('ii', $windowId, $timeoutMinutes);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getStaffWindowCurrentTicket(mysqli $conn, int $windowId): ?array {
    $clientName = smartqmsTableHasColumn($conn, 'queue_tickets', 'client_name')
        ? "COALESCE(NULLIF(qt.client_name, ''), CONCAT_WS(' ', u.first_name, u.last_name), 'Queue client')"
        : "COALESCE(CONCAT_WS(' ', u.first_name, u.last_name), 'Queue client')";
    $stmt = $conn->prepare("
        SELECT qt.*,
               {$clientName} AS client_name,
               u.first_name AS client_first_name,
               u.last_name AS client_last_name
        FROM queue_tickets qt
        LEFT JOIN users u ON u.user_id = qt.user_id
        WHERE qt.window_id = ? AND qt.status = 'serving'
        ORDER BY qt.called_at DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $windowId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function getStaffWindowWaitingTicketsForWindow(mysqli $conn, array $window, int $limit = 20): array {
    $limit = max(1, min(100, $limit));
    $serviceIds = getWindowServiceIds($conn, $window);
    if (!$serviceIds) {
        return [];
    }
    $idList = implode(',', array_map('intval', $serviceIds));
    $clientName = smartqmsTableHasColumn($conn, 'queue_tickets', 'client_name')
        ? "COALESCE(NULLIF(qt.client_name, ''), CONCAT_WS(' ', u.first_name, u.last_name), 'Queue client')"
        : "COALESCE(CONCAT_WS(' ', u.first_name, u.last_name), 'Queue client')";
    $stmt = $conn->prepare("
        SELECT qt.*, hs.service_name,
               {$clientName} AS client_name,
               u.first_name AS client_first_name,
               u.last_name AS client_last_name
        FROM queue_tickets qt
        JOIN health_services hs ON hs.service_id = qt.service_id
        LEFT JOIN users u ON u.user_id = qt.user_id
        WHERE qt.status = 'waiting'
          AND qt.lifecycle_status = 'waiting'
          AND qt.checked_in_at IS NOT NULL
          AND qt.service_id IN ({$idList})
        ORDER BY qt.checked_in_at ASC, qt.ticket_id ASC
        LIMIT {$limit}
    ");
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getStaffWindowKpis(mysqli $conn, array $window): array {
    $kpis = [
        'tickets_today' => 0,
        'active' => 0,
        'waiting' => 0,
        'completed' => 0,
        'voided' => 0,
    ];
    $serviceIds = getWindowServiceIds($conn, $window);
    if (!$serviceIds) {
        return $kpis;
    }
    $idList = implode(',', array_map('intval', $serviceIds));
    $result = $conn->query("
        SELECT
          SUM(CASE WHEN DATE(COALESCE(checked_in_at, issued_at)) = CURDATE()
                        AND checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS tickets_today,
          SUM(CASE WHEN status = 'serving'
                        AND lifecycle_status IN ('calling', 'in-progress') THEN 1 ELSE 0 END) AS active,
          SUM(CASE WHEN status = 'waiting' AND lifecycle_status = 'waiting'
                        AND checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS waiting,
          SUM(CASE WHEN status = 'completed' AND lifecycle_status = 'completed'
                        AND DATE(completed_at) = CURDATE() THEN 1 ELSE 0 END) AS completed,
          SUM(CASE WHEN status IN ('voided', 'skipped') AND lifecycle_status = 'void'
                        AND DATE(COALESCE(voided_at, issued_at)) = CURDATE() THEN 1 ELSE 0 END) AS voided
        FROM queue_tickets
        WHERE service_id IN ({$idList})
    ");
    $row = $result ? ($result->fetch_assoc() ?: []) : [];
    foreach (array_keys($kpis) as $key) {
        $kpis[$key] = (int) ($row[$key] ?? 0);
    }
    return $kpis;
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
        WHERE qt.status = 'waiting'
          AND qt.lifecycle_status = 'waiting'
          AND qt.checked_in_at IS NOT NULL
          AND qt.service_id = ?
        ORDER BY qt.checked_in_at ASC, qt.ticket_id ASC
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
