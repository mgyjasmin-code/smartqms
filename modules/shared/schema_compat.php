<?php
/**
 * Runtime schema-compatibility guards retained for legacy installations.
 */

function ensureAuthAttemptsTable(mysqli $conn): void {
    static $done = false;
    if ($done) {
        return;
    }

    if (defined('APP_ENV') && APP_ENV === 'production') {
        if (!smartqmsTableExists($conn, 'auth_attempts')) {
            throw new RuntimeException('Required authentication migrations have not been applied.');
        }
        $done = true;
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

function ensureAuthSecuritySchema(mysqli $conn): void {
    static $done = false;
    if ($done) {
        return;
    }

    if (defined('APP_ENV') && APP_ENV === 'production') {
        if (!smartqmsTableExists($conn, 'auth_attempts')
            || !smartqmsTableExists($conn, 'password_reset_capabilities')
            || !smartqmsTableHasColumn($conn, 'users', 'otp_hash')
            || !smartqmsTableHasColumn($conn, 'users', 'session_version')) {
            throw new RuntimeException('Required authentication migrations have not been applied.');
        }
        $done = true;
        return;
    }

    ensureAuthAttemptsTable($conn);
    $columns = $conn->query("SHOW COLUMNS FROM users LIKE 'otp_hash'");
    if ($columns && $columns->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN otp_hash VARCHAR(255) DEFAULT NULL AFTER otp_code");
    }
    if (!smartqmsTableHasColumn($conn, 'users', 'session_version')) {
        $conn->query("ALTER TABLE users ADD COLUMN session_version INT NOT NULL DEFAULT 1 AFTER last_login_at");
    }
    $conn->query("
        CREATE TABLE IF NOT EXISTS password_reset_capabilities (
          capability_id BIGINT AUTO_INCREMENT PRIMARY KEY,
          user_id INT NOT NULL,
          nonce_hash CHAR(64) NOT NULL UNIQUE,
          flow VARCHAR(30) NOT NULL DEFAULT 'password_reset',
          issued_at DATETIME NOT NULL,
          expires_at DATETIME NOT NULL,
          consumed_at DATETIME DEFAULT NULL,
          INDEX idx_reset_capability_lookup (nonce_hash, expires_at, consumed_at),
          INDEX idx_reset_capability_user (user_id, issued_at),
          FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");
    $done = true;
}

function ensureEmailJobsTable(mysqli $conn): bool {
    static $checked = false;
    if ($checked) {
        return true;
    }

    if (defined('APP_ENV') && APP_ENV === 'production') {
        $checked = smartqmsTableExists($conn, 'email_jobs');
        if (!$checked) {
            error_log('Required email job migration has not been applied.');
        }
        return $checked;
    }

    $created = $conn->query("
        CREATE TABLE IF NOT EXISTS email_jobs (
          job_id INT AUTO_INCREMENT PRIMARY KEY,
          user_id INT DEFAULT NULL,
          recipient_email VARCHAR(190) NOT NULL,
          subject VARCHAR(255) NOT NULL,
          message TEXT NOT NULL,
          type VARCHAR(50) DEFAULT 'notification',
          status ENUM('pending','processing','sent','failed','cancelled') DEFAULT 'pending',
          attempts INT NOT NULL DEFAULT 0,
          error_msg VARCHAR(255) DEFAULT NULL,
          available_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          sent_at DATETIME DEFAULT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_email_jobs_status_user (status, user_id, available_at),
          INDEX idx_email_jobs_created_at (created_at)
        ) ENGINE=InnoDB
    ");

    if (!$created) {
        logEmailError('Could not ensure email_jobs table: ' . $conn->error);
        return false;
    }

    $checked = true;
    return true;
}

function smartqmsTableHasColumn(mysqli $conn, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)
        || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        return false;
    }
    $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $conn->real_escape_string($column) . "'");
    return $cache[$key] = (bool) ($result && $result->num_rows > 0);
}

function smartqmsTableExists(mysqli $conn, string $table): bool {
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return false;
    }

    $escaped = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$escaped}'");
    return $cache[$table] = (bool) ($result && $result->num_rows > 0);
}
