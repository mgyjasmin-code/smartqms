<?php
/**
 * Runtime schema-compatibility guards retained for legacy installations.
 */

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

function ensureAuthSecuritySchema(mysqli $conn): void {
    static $done = false;
    if ($done) {
        return;
    }

    ensureAuthAttemptsTable($conn);
    $columns = $conn->query("SHOW COLUMNS FROM users LIKE 'otp_hash'");
    if ($columns && $columns->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN otp_hash VARCHAR(255) DEFAULT NULL AFTER otp_code");
    }
    $done = true;
}

function ensureEmailJobsTable(mysqli $conn): bool {
    static $checked = false;
    if ($checked) {
        return true;
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
