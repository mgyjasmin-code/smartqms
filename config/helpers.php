<?php
/**
 * SmartQMS shared helpers.
 */

function jsonResponse(bool $success, array $payload = [], int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success], $payload));
    exit();
}

function redirectTo(string $path, array $params = []): void {
    $url = APP_URL . '/' . ltrim($path, '/');
    if ($params) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
    }
    header('Location: ' . $url);
    exit();
}

function requestValue(string $key, mixed $default = null): mixed {
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function normalizePhone(string $phone): string {
    return preg_replace('/\D+/', '', trim($phone));
}

function isValidPhMobile(string $phone): bool {
    return (bool) preg_match('/^09\d{9}$/', $phone);
}

function logActivity(mysqli $conn, string $action, string $details = '', ?int $ticketId = null, ?int $userId = null, ?string $role = null): void {
    $userId = $userId ?? ($_SESSION['user_id'] ?? null);
    if (!$userId) {
        return;
    }
    $role = $role ?? ($_SESSION['role'] ?? null);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, role, action, details, ticket_id, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isssis', $userId, $role, $action, $details, $ticketId, $ip);
    $stmt->execute();
}

function getSetting(mysqli $conn, string $key, string $default = ''): string {
    $stmt = $conn->prepare("SELECT setting_val FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row['setting_val'] ?? $default;
}

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

function postActionUrl(string $path): string {
    return APP_URL . '/' . ltrim($path, '/');
}
?>
