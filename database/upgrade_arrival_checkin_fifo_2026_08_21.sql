-- SmartQMS Batch 8J: arrival check-in, strict FIFO, and number reservations.
-- Idempotent for the supported XAMPP MariaDB 10.4 runtime.

ALTER TABLE queue_tickets
  ADD COLUMN IF NOT EXISTS client_first_name VARCHAR(100) DEFAULT NULL AFTER ticket_token,
  ADD COLUMN IF NOT EXISTS client_last_name VARCHAR(100) DEFAULT NULL AFTER client_first_name,
  ADD COLUMN IF NOT EXISTS checked_in_at DATETIME DEFAULT NULL AFTER issued_at,
  ADD COLUMN IF NOT EXISTS scheduled_expires_at DATETIME DEFAULT NULL AFTER checked_in_at,
  ADD COLUMN IF NOT EXISTS check_in_method ENUM('qr','reference','walk-in') DEFAULT NULL AFTER scheduled_expires_at,
  ADD COLUMN IF NOT EXISTS checked_in_by INT DEFAULT NULL AFTER check_in_method;

ALTER TABLE queue_tickets
  MODIFY COLUMN ticket_number VARCHAR(10) DEFAULT NULL;

UPDATE queue_tickets qt
LEFT JOIN users u ON u.user_id = qt.user_id
SET qt.client_first_name = COALESCE(NULLIF(qt.client_first_name, ''), u.first_name),
    qt.client_last_name = COALESCE(NULLIF(qt.client_last_name, ''), u.last_name)
WHERE qt.user_id IS NOT NULL;

UPDATE queue_tickets
SET checked_in_at = COALESCE(checked_in_at, issued_at),
    check_in_method = COALESCE(check_in_method, IF(entry_type = 'walk-in', 'walk-in', 'reference'))
WHERE lifecycle_status IN ('waiting','calling','in-progress','completed')
   OR status IN ('waiting','serving','completed');

UPDATE queue_tickets
SET scheduled_expires_at = COALESCE(
      scheduled_expires_at,
      TIMESTAMP(DATE(issued_at) + INTERVAL 1 DAY) - INTERVAL 1 SECOND
    )
WHERE lifecycle_status = 'scheduled';

UPDATE queue_tickets
SET priority_level = 0
WHERE lifecycle_status IN ('scheduled','waiting','calling','in-progress');

UPDATE health_services SET priority_only = 0 WHERE priority_only <> 0;

CREATE TABLE IF NOT EXISTS counter_services (
  counter_id INT NOT NULL,
  service_id INT NOT NULL,
  PRIMARY KEY (counter_id, service_id),
  INDEX idx_counter_services_service (service_id, counter_id),
  CONSTRAINT fk_counter_services_counter
    FOREIGN KEY (counter_id) REFERENCES service_windows(window_id) ON DELETE CASCADE,
  CONSTRAINT fk_counter_services_service
    FOREIGN KEY (service_id) REFERENCES health_services(service_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Preserve every legacy one-service counter assignment before the mapping table
-- becomes the routing and active-counter authority.
INSERT IGNORE INTO counter_services (counter_id, service_id)
SELECT window_id, service_id
FROM service_windows
WHERE service_id IS NOT NULL;

INSERT INTO system_settings (setting_key, setting_val, label, section)
VALUES ('void_timeout_minutes', '5', 'Calling Timeout (minutes)', 'queue')
ON DUPLICATE KEY UPDATE
  setting_val = VALUES(setting_val),
  label = VALUES(label);

INSERT INTO system_settings (setting_key, setting_val, label, section)
VALUES ('priority_queue_enabled', '0', 'Legacy Priority Queue', 'queue')
ON DUPLICATE KEY UPDATE
  setting_val = VALUES(setting_val),
  label = VALUES(label);

CREATE TABLE IF NOT EXISTS ticket_print_batches (
  batch_id       INT AUTO_INCREMENT PRIMARY KEY,
  service_id     INT NOT NULL,
  service_date   DATE NOT NULL,
  start_number   INT NOT NULL,
  end_number     INT NOT NULL,
  created_by     INT NOT NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ticket_print_batch_service_date (service_id, service_date),
  CONSTRAINT fk_ticket_print_batch_service
    FOREIGN KEY (service_id) REFERENCES health_services(service_id),
  CONSTRAINT fk_ticket_print_batch_staff
    FOREIGN KEY (created_by) REFERENCES staff(staff_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ticket_number_reservations (
  reservation_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  batch_id        INT NOT NULL,
  service_id      INT NOT NULL,
  service_date    DATE NOT NULL,
  sequence_number INT NOT NULL,
  ticket_id       INT DEFAULT NULL,
  assigned_at     DATETIME DEFAULT NULL,
  UNIQUE KEY uq_ticket_reservation_number (service_id, service_date, sequence_number),
  UNIQUE KEY uq_ticket_reservation_ticket (ticket_id),
  INDEX idx_ticket_reservation_available (service_id, service_date, ticket_id, sequence_number),
  CONSTRAINT fk_ticket_reservation_batch
    FOREIGN KEY (batch_id) REFERENCES ticket_print_batches(batch_id) ON DELETE CASCADE,
  CONSTRAINT fk_ticket_reservation_service
    FOREIGN KEY (service_id) REFERENCES health_services(service_id),
  CONSTRAINT fk_ticket_reservation_ticket
    FOREIGN KEY (ticket_id) REFERENCES queue_tickets(ticket_id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET @sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'queue_tickets'
      AND index_name = 'idx_queue_scheduled_expiry'
  ),
  'SELECT 1',
  'CREATE INDEX idx_queue_scheduled_expiry ON queue_tickets (lifecycle_status, scheduled_expires_at, ticket_id)'
);
PREPARE smartqms_stmt FROM @sql; EXECUTE smartqms_stmt; DEALLOCATE PREPARE smartqms_stmt;

SET @sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'queue_tickets'
      AND index_name = 'idx_queue_fifo'
  ),
  'SELECT 1',
  'CREATE INDEX idx_queue_fifo ON queue_tickets (lifecycle_status, checked_in_at, ticket_id)'
);
PREPARE smartqms_stmt FROM @sql; EXECUTE smartqms_stmt; DEALLOCATE PREPARE smartqms_stmt;

SET @sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'queue_tickets'
      AND index_name = 'idx_queue_service_fifo'
  ),
  'SELECT 1',
  'CREATE INDEX idx_queue_service_fifo ON queue_tickets (service_id, lifecycle_status, checked_in_at, ticket_id)'
);
PREPARE smartqms_stmt FROM @sql; EXECUTE smartqms_stmt; DEALLOCATE PREPARE smartqms_stmt;

SET @sql = IF(
  EXISTS(
    SELECT 1 FROM information_schema.key_column_usage
    WHERE table_schema = DATABASE() AND table_name = 'queue_tickets'
      AND column_name = 'checked_in_by' AND referenced_table_name = 'staff'
  ),
  'SELECT 1',
  'ALTER TABLE queue_tickets ADD CONSTRAINT fk_queue_checked_in_by FOREIGN KEY (checked_in_by) REFERENCES staff(staff_id) ON DELETE SET NULL'
);
PREPARE smartqms_stmt FROM @sql; EXECUTE smartqms_stmt; DEALLOCATE PREPARE smartqms_stmt;
