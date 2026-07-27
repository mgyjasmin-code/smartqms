<?php
/**
 * Shared session, CSRF, authentication, and throttling helpers.
 */

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function requireLogin(string $role = ''): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/index.php');
        exit();
    }
    if ($role && $_SESSION['role'] !== $role) {
        header('Location: ' . APP_URL . '/index.php');
        exit();
    }
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
