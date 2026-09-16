-- SmartQMS production-security capability and audit foundations.
-- Apply with a migration account; the runtime account should not have DDL rights.

CREATE TABLE IF NOT EXISTS schema_migrations (
  migration_id VARCHAR(100) PRIMARY KEY,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS session_version INT NOT NULL DEFAULT 1 AFTER last_login_at;

ALTER TABLE queue_tickets
  ADD COLUMN IF NOT EXISTS manage_token_hash CHAR(64) DEFAULT NULL AFTER ticket_token,
  ADD COLUMN IF NOT EXISTS manage_token_issued_at DATETIME DEFAULT NULL AFTER manage_token_hash,
  ADD COLUMN IF NOT EXISTS manage_token_expires_at DATETIME DEFAULT NULL AFTER manage_token_issued_at,
  ADD COLUMN IF NOT EXISTS manage_token_revoked_at DATETIME DEFAULT NULL AFTER manage_token_expires_at;

CREATE UNIQUE INDEX IF NOT EXISTS uq_queue_manage_token_hash
  ON queue_tickets (manage_token_hash);

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
  CONSTRAINT fk_reset_capability_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS security_events (
  event_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  actor_user_id INT DEFAULT NULL,
  event_type VARCHAR(80) NOT NULL,
  outcome VARCHAR(30) NOT NULL,
  target_type VARCHAR(50) DEFAULT NULL,
  target_id VARCHAR(100) DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  correlation_id CHAR(32) NOT NULL,
  metadata_json JSON DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_security_event_type_time (event_type, created_at),
  INDEX idx_security_event_actor_time (actor_user_id, created_at),
  CONSTRAINT fk_security_event_actor FOREIGN KEY (actor_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

DELETE FROM system_settings WHERE setting_key = 'sms_api_key';

INSERT IGNORE INTO schema_migrations (migration_id)
VALUES ('20260905_001_production_security');
