-- SmartQMS blueprint compatibility migration
-- Target: MySQL 8.x / MariaDB 10.4+
-- Precondition: smartqms_final_v4.sql plus the hybrid-queue migration.
-- IMPORTANT: back up the database before applying this one-time migration.

ALTER TABLE users
  ADD COLUMN username VARCHAR(100) DEFAULT NULL AFTER user_id,
  ADD COLUMN job_title VARCHAR(100) DEFAULT NULL AFTER role;

UPDATE users
SET username = LOWER(email)
WHERE username IS NULL OR username = '';

CREATE UNIQUE INDEX uq_users_username ON users (username);

ALTER TABLE health_services
  ADD COLUMN fallback_duration_mins INT NOT NULL DEFAULT 15 AFTER description,
  ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0 AFTER priority_only;

UPDATE health_services
SET is_hidden = IF(is_active = 1, 0, 1);

ALTER TABLE service_windows
  ADD COLUMN counter_number INT DEFAULT NULL AFTER window_id;

UPDATE service_windows
SET counter_number = window_id
WHERE counter_number IS NULL;

ALTER TABLE service_windows
  MODIFY COLUMN counter_number INT NOT NULL,
  ADD UNIQUE KEY uq_service_windows_counter_number (counter_number);

CREATE TABLE counter_services (
  counter_id INT NOT NULL,
  service_id INT NOT NULL,
  PRIMARY KEY (counter_id, service_id),
  CONSTRAINT fk_counter_services_window
    FOREIGN KEY (counter_id) REFERENCES service_windows(window_id) ON DELETE CASCADE,
  CONSTRAINT fk_counter_services_service
    FOREIGN KEY (service_id) REFERENCES health_services(service_id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO counter_services (counter_id, service_id)
SELECT window_id, service_id
FROM service_windows
WHERE service_id IS NOT NULL
ON DUPLICATE KEY UPDATE service_id = VALUES(service_id);

ALTER TABLE queue_tickets
  MODIFY COLUMN user_id INT NULL,
  ADD COLUMN ticket_token VARCHAR(64) DEFAULT NULL AFTER user_id,
  ADD COLUMN client_name VARCHAR(100) DEFAULT NULL AFTER ticket_token,
  ADD COLUMN phone_number VARCHAR(20) DEFAULT NULL AFTER client_name,
  ADD COLUMN entry_type ENUM('walk-in','online') NOT NULL DEFAULT 'online' AFTER ticket_number,
  ADD COLUMN lifecycle_status
    ENUM('scheduled','waiting','calling','in-progress','completed','void')
    NOT NULL DEFAULT 'waiting' AFTER status,
  ADD COLUMN started_at DATETIME DEFAULT NULL AFTER served_at,
  ADD UNIQUE KEY uq_queue_tickets_token (ticket_token),
  ADD KEY idx_queue_public_token_status (ticket_token, lifecycle_status),
  ADD KEY idx_queue_counter_lifecycle (window_id, lifecycle_status, called_at);

UPDATE queue_tickets qt
LEFT JOIN users u ON u.user_id = qt.user_id
SET qt.client_name = COALESCE(NULLIF(qt.client_name, ''), CONCAT_WS(' ', u.first_name, u.last_name)),
    qt.phone_number = COALESCE(NULLIF(qt.phone_number, ''), u.phone_number),
    qt.lifecycle_status = CASE qt.status
      WHEN 'waiting' THEN 'waiting'
      WHEN 'serving' THEN IF(qt.served_at IS NULL, 'calling', 'in-progress')
      WHEN 'completed' THEN 'completed'
      ELSE 'void'
    END,
    qt.started_at = COALESCE(qt.started_at, qt.served_at);

ALTER TABLE feedback
  MODIFY COLUMN user_id INT NULL;

CREATE OR REPLACE VIEW v_today_queue AS
  SELECT
    qt.ticket_id,
    qt.reference_number,
    qt.ticket_number,
    COALESCE(NULLIF(qt.client_name, ''), CONCAT_WS(' ', u.first_name, u.last_name), 'Queue client') AS client_name,
    COALESCE(NULLIF(qt.phone_number, ''), u.phone_number) AS phone_number,
    qt.client_type,
    qt.priority_level,
    hs.service_name,
    sw.window_name,
    qt.status,
    qt.issued_at,
    qt.called_at,
    qt.completed_at,
    TIMESTAMPDIFF(MINUTE, qt.issued_at, COALESCE(qt.completed_at, NOW())) AS total_minutes_in_system
  FROM queue_tickets qt
  LEFT JOIN users u ON qt.user_id = u.user_id
  JOIN health_services hs ON qt.service_id = hs.service_id
  LEFT JOIN service_windows sw ON qt.window_id = sw.window_id
  WHERE DATE(qt.issued_at) = CURDATE();
