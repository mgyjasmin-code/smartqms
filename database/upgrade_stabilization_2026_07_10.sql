-- SmartQMS stabilization upgrade for existing local databases.
-- Run this after selecting the smartqms database:
--   mysql -u root smartqms < database/upgrade_stabilization_2026_07_10.sql

SET @db_name := DATABASE();

-- users.otp_hash for hashed OTP storage
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'users' AND COLUMN_NAME = 'otp_hash') = 0,
  'ALTER TABLE users ADD COLUMN otp_hash VARCHAR(255) DEFAULT NULL AFTER otp_code',
  'SELECT ''users.otp_hash already exists'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS email_jobs (
  job_id          INT          AUTO_INCREMENT PRIMARY KEY,
  user_id         INT          DEFAULT NULL,
  recipient_email VARCHAR(190) NOT NULL,
  subject         VARCHAR(255) NOT NULL,
  message         TEXT         NOT NULL,
  type            VARCHAR(50)  DEFAULT 'notification',
  status          ENUM('pending','processing','sent','failed','cancelled') DEFAULT 'pending',
  attempts        INT          NOT NULL DEFAULT 0,
  error_msg       VARCHAR(255) DEFAULT NULL,
  available_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  sent_at         DATETIME     DEFAULT NULL,
  created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email_jobs_status_user (status, user_id, available_at),
  INDEX idx_email_jobs_created_at (created_at),
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO system_settings (setting_key, setting_val, label, section) VALUES
  ('display_board_token', '', 'Display Board Access Token', 'display'),
  ('ml_last_trained', '', 'Model Last Trained', 'ml'),
  ('ml_dataset_used', '', 'Training Dataset Used', 'ml')
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  section = VALUES(section);

UPDATE users
SET password_hash = '$2y$10$0dVVbYM0md/nlp53WdgET./DnO2eae3DZLJaMdU6OZeU29mXR7rHK',
    is_verified = 1,
    is_active = 1,
    otp_code = NULL,
    otp_hash = NULL,
    otp_expires_at = NULL
WHERE email = 'admin@smartqms.local' AND role = 'admin';

CREATE OR REPLACE VIEW v_today_queue AS
  SELECT
    qt.ticket_id,
    qt.reference_number,
    qt.ticket_number,
    CONCAT(u.first_name, ' ', u.last_name) AS client_name,
    u.phone_number,
    qt.client_type,
    qt.priority_level,
    hs.service_name,
    sw.window_name,
    qt.status,
    qt.issued_at,
    qt.called_at,
    qt.completed_at,
    TIMESTAMPDIFF(MINUTE, qt.issued_at, COALESCE(qt.completed_at, NOW()))
      AS total_minutes_in_system
  FROM queue_tickets qt
  JOIN users u ON qt.user_id = u.user_id
  JOIN health_services hs ON qt.service_id = hs.service_id
  LEFT JOIN service_windows sw ON qt.window_id = sw.window_id
  WHERE DATE(qt.issued_at) = CURDATE()
  ORDER BY qt.priority_level DESC, qt.issued_at ASC;

CREATE OR REPLACE VIEW v_ml_latest_comparison AS
  SELECT ml.*
  FROM ml_comparison_logs ml
  INNER JOIN (
    SELECT algorithm, MAX(run_date) AS latest_run
    FROM ml_comparison_logs
    GROUP BY algorithm
  ) latest ON ml.algorithm = latest.algorithm
           AND ml.run_date = latest.latest_run;

CREATE OR REPLACE VIEW v_staff_productivity_today AS
  SELECT
    s.staff_id,
    CONCAT(u.first_name, ' ', u.last_name) AS staff_name,
    sw.window_name,
    COUNT(qt.ticket_id) AS tickets_served,
    ROUND(AVG(CASE WHEN qt.ticket_id IS NOT NULL THEN wl.actual_wait_min END), 2) AS avg_wait_min,
    ROUND(AVG(CASE WHEN qt.ticket_id IS NOT NULL THEN wl.actual_service_dur END) / 60, 2) AS avg_service_min
  FROM staff s
  JOIN users u ON s.user_id = u.user_id
  LEFT JOIN service_windows sw ON sw.staff_id = s.staff_id
  LEFT JOIN wait_time_logs wl ON wl.staff_id = s.staff_id
  LEFT JOIN queue_tickets qt ON wl.ticket_id = qt.ticket_id
                           AND DATE(qt.completed_at) = CURDATE()
  GROUP BY s.staff_id, staff_name, sw.window_name;
