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

function requestIpAddress(): string {
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfInput(): string {
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8')
        . '">';
}

function csrfMetaTag(): string {
    return '<meta name="csrf-token" content="'
        . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8')
        . '">';
}

function requestCsrfToken(): string {
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (is_string($header) && $header !== '') {
        return $header;
    }

    return is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
}

function isValidCsrfToken(): bool {
    $expected = $_SESSION['csrf_token'] ?? '';
    $provided = requestCsrfToken();

    return is_string($expected)
        && $expected !== ''
        && is_string($provided)
        && $provided !== ''
        && hash_equals($expected, $provided);
}

function requirePostRequest(
    bool $json = false,
    string $redirectPath = 'index.php',
    string $formKey = 'login',
    array $oldInput = [],
    string $message = 'Please submit the form again.'
): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        return;
    }

    if ($json) {
        jsonResponse(false, ['error' => $message], 405);
    }

    redirectWithFormFeedback($redirectPath, $formKey, [], $oldInput, $message);
}

function requireValidCsrf(
    string $redirectPath = 'index.php',
    string $formKey = 'login',
    array $oldInput = [],
    string $message = 'Security check failed. Please refresh the page and try again.',
    bool $json = false
): void {
    if (isValidCsrfToken()) {
        return;
    }

    if ($json) {
        jsonResponse(false, ['error' => $message], 419);
    }

    redirectWithFormFeedback($redirectPath, $formKey, [], $oldInput, $message);
}

function ensureAuthAttemptsTable(mysqli $conn): void {
    static $done = false;
    if ($done) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS auth_attempts (
          attempt_id INT AUTO_INCREMENT PRIMARY KEY,
          attempt_scope VARCHAR(40) NOT NULL,
          identifier_hash CHAR(64) NOT NULL,
          ip_address VARCHAR(45) NOT NULL,
          attempts INT NOT NULL DEFAULT 0,
          window_started_at DATETIME NOT NULL,
          last_attempt_at DATETIME NOT NULL,
          locked_until DATETIME DEFAULT NULL,
          UNIQUE KEY uniq_attempt_scope_identifier_ip (attempt_scope, identifier_hash, ip_address),
          INDEX idx_auth_attempts_locked_until (locked_until),
          INDEX idx_auth_attempts_last_attempt_at (last_attempt_at)
        ) ENGINE=InnoDB
    ");
    $done = true;
}

function authAttemptIdentifier(string $identifier): string {
    return hash('sha256', strtolower(trim($identifier)));
}

function authThrottleStatus(mysqli $conn, string $scope, string $identifier, int $limit, int $windowSeconds): array {
    ensureAuthAttemptsTable($conn);
    $hash = authAttemptIdentifier($identifier);
    $ip = requestIpAddress();
    $stmt = $conn->prepare("
        SELECT attempts, window_started_at, locked_until
        FROM auth_attempts
        WHERE attempt_scope = ? AND identifier_hash = ? AND ip_address = ?
        LIMIT 1
    ");
    $stmt->bind_param('sss', $scope, $hash, $ip);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return ['allowed' => true, 'retry_after' => 0];
    }

    $now = time();
    $lockedUntil = !empty($row['locked_until']) ? strtotime($row['locked_until']) : 0;
    if ($lockedUntil > $now) {
        return ['allowed' => false, 'retry_after' => $lockedUntil - $now];
    }

    $windowStarted = strtotime($row['window_started_at']);
    if (!$windowStarted || $now - $windowStarted >= $windowSeconds) {
        return ['allowed' => true, 'retry_after' => 0];
    }

    if ((int) $row['attempts'] >= $limit) {
        return ['allowed' => false, 'retry_after' => max(1, ($windowStarted + $windowSeconds) - $now)];
    }

    return ['allowed' => true, 'retry_after' => 0];
}

function recordAuthAttempt(mysqli $conn, string $scope, string $identifier, int $limit, int $windowSeconds): void {
    ensureAuthAttemptsTable($conn);
    $hash = authAttemptIdentifier($identifier);
    $ip = requestIpAddress();
    $now = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("
        SELECT attempts, window_started_at, locked_until
        FROM auth_attempts
        WHERE attempt_scope = ? AND identifier_hash = ? AND ip_address = ?
        LIMIT 1
    ");
    $stmt->bind_param('sss', $scope, $hash, $ip);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        $attempts = 1;
        $lockedUntil = $attempts >= $limit ? date('Y-m-d H:i:s', time() + $windowSeconds) : null;
        $insert = $conn->prepare("
            INSERT INTO auth_attempts
              (attempt_scope, identifier_hash, ip_address, attempts, window_started_at, last_attempt_at, locked_until)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->bind_param('sssisss', $scope, $hash, $ip, $attempts, $now, $now, $lockedUntil);
        $insert->execute();
        return;
    }

    $windowStarted = strtotime($row['window_started_at']);
    $lockedUntilTs = !empty($row['locked_until']) ? strtotime($row['locked_until']) : 0;
    if (!$windowStarted || time() - $windowStarted >= $windowSeconds || ($lockedUntilTs > 0 && $lockedUntilTs <= time())) {
        $attempts = 1;
        $lockedUntil = null;
        $update = $conn->prepare("
            UPDATE auth_attempts
            SET attempts = ?, window_started_at = ?, last_attempt_at = ?, locked_until = ?
            WHERE attempt_scope = ? AND identifier_hash = ? AND ip_address = ?
        ");
        $update->bind_param('issssss', $attempts, $now, $now, $lockedUntil, $scope, $hash, $ip);
        $update->execute();
        return;
    }

    $attempts = ((int) $row['attempts']) + 1;
    $lockedUntil = $attempts >= $limit ? date('Y-m-d H:i:s', time() + $windowSeconds) : null;
    $update = $conn->prepare("
        UPDATE auth_attempts
        SET attempts = ?, last_attempt_at = ?, locked_until = ?
        WHERE attempt_scope = ? AND identifier_hash = ? AND ip_address = ?
    ");
    $update->bind_param('isssss', $attempts, $now, $lockedUntil, $scope, $hash, $ip);
    $update->execute();
}

function clearAuthAttempts(mysqli $conn, string $scope, string $identifier): void {
    ensureAuthAttemptsTable($conn);
    $hash = authAttemptIdentifier($identifier);
    $ip = requestIpAddress();
    $stmt = $conn->prepare("DELETE FROM auth_attempts WHERE attempt_scope = ? AND identifier_hash = ? AND ip_address = ?");
    $stmt->bind_param('sss', $scope, $hash, $ip);
    $stmt->execute();
}

function authThrottleMessage(int $retryAfterSeconds): string {
    $minutes = max(1, (int) ceil($retryAfterSeconds / 60));
    return 'Too many attempts. Please try again in ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.';
}

function redirectTo(string $path, array $params = []): void {
    $url = APP_URL . '/' . ltrim($path, '/');
    if ($params) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
    }
    header('Location: ' . $url);
    exit();
}

function flashFormFeedback(string $formKey, array $fieldErrors = [], array $oldInput = [], string $formError = ''): void {
    $_SESSION['form_feedback'][$formKey] = [
        'field_errors' => array_filter($fieldErrors, static fn($message) => trim((string) $message) !== ''),
        'old' => array_map(static function ($value): string {
            if (is_scalar($value) || $value === null) {
                return (string) $value;
            }

            return '';
        }, $oldInput),
        'form_error' => trim($formError),
    ];
}

function consumeFormFeedback(string $formKey): array {
    $empty = [
        'field_errors' => [],
        'old' => [],
        'form_error' => '',
    ];

    $feedback = $_SESSION['form_feedback'][$formKey] ?? [];
    unset($_SESSION['form_feedback'][$formKey]);

    if (empty($_SESSION['form_feedback'])) {
        unset($_SESSION['form_feedback']);
    }

    return array_merge($empty, is_array($feedback) ? $feedback : []);
}

function redirectWithFormFeedback(
    string $path,
    string $formKey,
    array $fieldErrors = [],
    array $oldInput = [],
    string $formError = '',
    array $params = []
): void {
    flashFormFeedback($formKey, $fieldErrors, $oldInput, $formError);
    redirectTo($path, $params);
}

function fieldError(array $feedback, string $field): string {
    return (string) ($feedback['field_errors'][$field] ?? '');
}

function oldFormValue(array $feedback, string $field, string $default = ''): string {
    return (string) ($feedback['old'][$field] ?? $default);
}

function fieldInvalidClass(array $feedback, string $field): string {
    return fieldError($feedback, $field) !== '' ? ' is-invalid' : '';
}

function fieldAriaInvalid(array $feedback, string $field): string {
    return fieldError($feedback, $field) !== '' ? ' aria-invalid="true"' : '';
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

function queuePredictionSnapshot(mysqli $conn, int $serviceId, string $clientType): ?array {
    if (!in_array($clientType, ['regular', 'senior', 'pwd'], true)) {
        $clientType = 'regular';
    }

    $serviceStmt = $conn->prepare("SELECT * FROM health_services WHERE service_id = ? AND is_active = 1 LIMIT 1");
    $serviceStmt->bind_param('i', $serviceId);
    $serviceStmt->execute();
    $service = $serviceStmt->get_result()->fetch_assoc();
    if (!$service) {
        return null;
    }

    $priorityLevel = in_array($clientType, ['senior', 'pwd'], true) ? 1 : 0;

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

function postActionUrl(string $path): string {
    return APP_URL . '/' . ltrim($path, '/');
}

function assetUrl(string $path): string {
    $path = ltrim($path, '/');
    $file = __DIR__ . '/../' . $path;
    $version = is_file($file) ? (string) filemtime($file) : APP_VERSION;

    return APP_URL . '/' . $path . '?v=' . rawurlencode($version);
}
?>
