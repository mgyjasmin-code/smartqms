-- SmartQMS Hybrid Queue migration
-- Target: MySQL 8.x / MariaDB 10.4+
-- Precondition: smartqms_final_v4.sql or upgrade_stabilization_2026_07_10.sql
-- IMPORTANT: back up the database before applying this one-time migration.

ALTER TABLE users
  ADD COLUMN client_type ENUM('regular','senior','pwd')
    NOT NULL DEFAULT 'regular' AFTER phone_number,
  ADD COLUMN must_change_password TINYINT(1)
    NOT NULL DEFAULT 0 AFTER password_hash;

ALTER TABLE health_services
  ADD COLUMN queue_mode ENUM('central','specialized')
    NOT NULL DEFAULT 'central' AFTER service_encoded;

ALTER TABLE service_windows
  ADD COLUMN window_type ENUM('shared','specialized')
    NOT NULL DEFAULT 'shared' AFTER window_name,
  ADD COLUMN location_description VARCHAR(255)
    NULL AFTER window_type,
  MODIFY COLUMN service_id INT NULL,
  MODIFY COLUMN staff_id INT NULL;

CREATE TABLE staff_service_capabilities (
  capability_id INT AUTO_INCREMENT PRIMARY KEY,
  staff_id INT NOT NULL,
  service_id INT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  assigned_by INT NOT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_staff_service_capability (staff_id, service_id),
  KEY idx_capability_service_active (service_id, is_active, staff_id),
  CONSTRAINT fk_capability_staff
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id),
  CONSTRAINT fk_capability_service
    FOREIGN KEY (service_id) REFERENCES health_services(service_id),
  CONSTRAINT fk_capability_admin
    FOREIGN KEY (assigned_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

ALTER TABLE queue_tickets
  ADD COLUMN queue_mode ENUM('central','specialized')
    NOT NULL DEFAULT 'central' AFTER service_id,
  MODIFY COLUMN window_id INT NULL;

-- Preserve any existing specialized staff assignment as a capability before
-- runtime window ownership is cleared. The current SmartQMS seed has no such
-- assignment, but this keeps upgraded installations safe.
INSERT INTO staff_service_capabilities
  (staff_id, service_id, is_active, assigned_by)
SELECT DISTINCT sw.staff_id, hs.service_id, 1,
       COALESCE(s.added_by, (
         SELECT MIN(admin_user.user_id)
         FROM users admin_user
         WHERE admin_user.role = 'admin'
       ))
FROM service_windows sw
JOIN health_services hs ON hs.service_id = sw.service_id
JOIN staff s ON s.staff_id = sw.staff_id
WHERE sw.staff_id IS NOT NULL
  AND (UPPER(hs.service_code) = 'DENTAL' OR LOWER(hs.service_name) LIKE '%dental%')
  AND COALESCE(s.added_by, (
        SELECT MIN(admin_user.user_id)
        FROM users admin_user
        WHERE admin_user.role = 'admin'
      )) IS NOT NULL
ON DUPLICATE KEY UPDATE is_active = VALUES(is_active);

-- Dental is the approved initial specialized service. Other existing services
-- remain in the pooled Central Queue until an Administrator explicitly changes
-- their routing mode.
UPDATE health_services
SET queue_mode = CASE
  WHEN UPPER(service_code) = 'DENTAL' OR LOWER(service_name) LIKE '%dental%'
    THEN 'specialized'
  ELSE 'central'
END;

-- Existing physical windows are retained as shared counters. No existing
-- window is silently repurposed as a Dental room. Administrators can configure
-- a dedicated specialized window in Phase 2.
UPDATE service_windows
SET window_type = 'shared',
    location_description = NULL,
    service_id = NULL,
    staff_id = NULL,
    status = 'closed';

UPDATE queue_tickets qt
JOIN health_services hs ON hs.service_id = qt.service_id
SET qt.queue_mode = hs.queue_mode;

-- Preserve a useful default classification for existing Client accounts by
-- copying their most recently issued ticket classification when available.
UPDATE users u
JOIN (
  SELECT recent.user_id,
         SUBSTRING_INDEX(
           GROUP_CONCAT(recent.client_type ORDER BY recent.issued_at DESC, recent.ticket_id DESC),
           ',',
           1
         ) AS latest_client_type
  FROM queue_tickets recent
  GROUP BY recent.user_id
) latest ON latest.user_id = u.user_id
SET u.client_type = latest.latest_client_type
WHERE u.role = 'client';

CREATE INDEX idx_queue_call_central
  ON queue_tickets (status, queue_mode, priority_level, issued_at);

CREATE INDEX idx_queue_call_specialized
  ON queue_tickets (status, queue_mode, service_id, priority_level, issued_at);

CREATE INDEX idx_queue_user_active
  ON queue_tickets (user_id, status);

CREATE INDEX idx_window_runtime
  ON service_windows (is_active, window_type, service_id, status, staff_id);

CREATE UNIQUE INDEX uq_window_runtime_staff
  ON service_windows (staff_id);
