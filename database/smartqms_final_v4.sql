-- ================================================================
-- SmartQMS v3 FINAL — Complete Database Schema
-- Web-Based Smart Queue Management System
-- Barangay Health Center — ML-Based Waiting Time Prediction
-- Using Random Forest Algorithm
--
-- Key decisions:
--   • One barangay per installation (admin configures health services)
--   • One login page for all roles — system auto-detects role
--   • Admin manually creates staff accounts
--   • Client: one active ticket at a time only
--   • Display board: separate public URL, no login required
--   • Calling timeout: admin-configurable (default 5 min)
--   • Live queue ordering: strict FIFO from physical check-in
-- ================================================================

CREATE DATABASE IF NOT EXISTS smartqms
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE smartqms;

CREATE TABLE IF NOT EXISTS schema_migrations (
  migration_id VARCHAR(100) PRIMARY KEY,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── 1. USERS ──────────────────────────────────────────────────
--    All roles (client, staff, admin) share this table.
--    Role is detected on login to redirect to the correct dashboard.
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  user_id        INT           AUTO_INCREMENT PRIMARY KEY,
  username       VARCHAR(100)  DEFAULT NULL UNIQUE,
  first_name     VARCHAR(50)   NOT NULL,
  last_name      VARCHAR(50)   NOT NULL,
  middle_name    VARCHAR(50)   DEFAULT NULL,
  phone_number   VARCHAR(15)   DEFAULT NULL UNIQUE, -- optional contact/staff phone
  client_type    ENUM('regular','senior','pwd') NOT NULL DEFAULT 'regular',
  email          VARCHAR(100)  NOT NULL UNIQUE,     -- primary email identifier
  password_hash  VARCHAR(255)  NOT NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  role           ENUM('client','staff','admin')    DEFAULT 'client',
  job_title      VARCHAR(100)  DEFAULT NULL,
  is_verified    TINYINT(1)    DEFAULT 0,          -- 1 = email OTP verified
  otp_code       VARCHAR(6)    DEFAULT NULL,
  otp_hash       VARCHAR(255)  DEFAULT NULL,
  otp_expires_at DATETIME      DEFAULT NULL,
  is_active      TINYINT(1)    DEFAULT 1,          -- admin can deactivate accounts
  last_login_at  DATETIME      DEFAULT NULL,
  session_version INT          NOT NULL DEFAULT 1,
  created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- No administrator is seeded. Create the first administrator with the
-- CLI-only scripts/bootstrap_admin.php command during deployment.

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


-- ── 2. STAFF ──────────────────────────────────────────────────
--    Extends users for staff-specific details.
--    Staff accounts are ONLY created by admin.
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS staff (
  staff_id    INT          AUTO_INCREMENT PRIMARY KEY,
  user_id     INT          NOT NULL UNIQUE,
  department  VARCHAR(100) DEFAULT NULL,
  shift       VARCHAR(50)  DEFAULT NULL,   -- e.g. Morning, Afternoon
  added_by    INT          DEFAULT NULL,   -- admin user_id who created this account
  added_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)  REFERENCES users(user_id)  ON DELETE CASCADE,
  FOREIGN KEY (added_by) REFERENCES users(user_id)  ON DELETE SET NULL
) ENGINE=InnoDB;


-- ── 3. HEALTH SERVICES ────────────────────────────────────────
--    Admin-configurable per barangay installation.
--    Each barangay may offer different services.
--    service_encoded is the numeric value fed into the ML model.
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS health_services (
  service_id       INT          AUTO_INCREMENT PRIMARY KEY,
  service_code     VARCHAR(10)  NOT NULL UNIQUE,
  service_name     VARCHAR(100) NOT NULL,
  service_encoded  TINYINT      NOT NULL,     -- numeric value for ML feature input
  queue_mode       ENUM('central','specialized') NOT NULL DEFAULT 'central',
  description      TEXT         DEFAULT NULL, -- optional description for clients
  fallback_duration_mins INT     NOT NULL DEFAULT 15,
  priority_only    TINYINT(1)   DEFAULT 0,    -- legacy compatibility; not enforced
  is_hidden        TINYINT(1)   NOT NULL DEFAULT 0,
  is_active        TINYINT(1)   DEFAULT 1,    -- admin can enable/disable per service
  display_order    TINYINT      DEFAULT 0,    -- controls order in dropdown
  created_by       INT          DEFAULT NULL,
  created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Default health services for a typical barangay health center
-- Admin can add, edit, deactivate, or reorder these
INSERT INTO health_services
  (service_code, service_name, service_encoded, queue_mode, priority_only, display_order)
VALUES
  ('SVC-001', 'General Checkup / Consultation',   1, 'central',     0, 1),
  ('SVC-002', 'Vaccination / Immunization',       2, 'central',     0, 2),
  ('SVC-003', 'Prenatal / Maternal Care',         3, 'central',     0, 3),
  ('SVC-004', 'Dental Services',                  4, 'specialized', 0, 4),
  ('SVC-005', 'Laboratory / Medical Certificate', 5, 'central',     0, 5),
  ('SVC-006', 'Family Planning',                  6, 'central',     0, 6),
  ('SVC-007', 'Senior Citizen Services',          7, 'central',     0, 7),
  ('SVC-008', 'PWD Assessment / Certification',   8, 'central',     0, 8);


-- ── 4. SERVICE WINDOWS ────────────────────────────────────────
--    Admin configures physical windows. Staff ownership is runtime-only.
--    Shared windows have service_id NULL; specialized windows require a service.
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS service_windows (
  window_id         INT         AUTO_INCREMENT PRIMARY KEY,
  counter_number    INT         NOT NULL UNIQUE,
  window_name       VARCHAR(50) NOT NULL,         -- e.g. Window 1, Dental Window
  window_type       ENUM('shared','specialized') NOT NULL DEFAULT 'shared',
  location_description VARCHAR(255) DEFAULT NULL,
  service_id        INT         DEFAULT NULL,      -- specialized service only
  staff_id          INT         DEFAULT NULL,      -- current runtime operator only
  status            ENUM('open','busy','closed')   DEFAULT 'closed',
  priority_enabled  TINYINT(1)  DEFAULT 1,         -- legacy compatibility
  is_active         TINYINT(1)  DEFAULT 1,
  created_at        TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (service_id) REFERENCES health_services(service_id) ON DELETE SET NULL,
  UNIQUE KEY uq_window_runtime_staff (staff_id),
  INDEX idx_window_runtime (is_active, window_type, service_id, status, staff_id),
  FOREIGN KEY (staff_id)   REFERENCES staff(staff_id)             ON DELETE SET NULL
) ENGINE=InnoDB;

-- Blueprint-compatible many-to-many counter/service routing. The legacy
-- service_windows.service_id column remains the specialized-service fallback.
CREATE TABLE IF NOT EXISTS counter_services (
  counter_id INT NOT NULL,
  service_id INT NOT NULL,
  PRIMARY KEY (counter_id, service_id),
  FOREIGN KEY (counter_id) REFERENCES service_windows(window_id) ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES health_services(service_id) ON DELETE CASCADE
) ENGINE=InnoDB;


-- ── 4A. STAFF SPECIALIZED CAPABILITIES ───────────────────────
--    Central services need no mapping. Specialized windows require an active
--    capability matching their configured service.
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS staff_service_capabilities (
  capability_id INT AUTO_INCREMENT PRIMARY KEY,
  staff_id INT NOT NULL,
  service_id INT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  assigned_by INT NOT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_staff_service_capability (staff_id, service_id),
  INDEX idx_capability_service_active (service_id, is_active, staff_id),
  FOREIGN KEY (staff_id) REFERENCES staff(staff_id),
  FOREIGN KEY (service_id) REFERENCES health_services(service_id),
  FOREIGN KEY (assigned_by) REFERENCES users(user_id)
) ENGINE=InnoDB;


-- ── 5. QUEUE TICKETS ──────────────────────────────────────────
--    Core entity. One active ticket per client at a time.
--    Reference number format: BHC-YYYY-NNNN (e.g. BHC-2025-0001)
--    Legacy classification/priority columns remain for historical compatibility.
--    Live ordering is strict FIFO by checked_in_at, then ticket_id.
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS queue_tickets (
  ticket_id        INT          AUTO_INCREMENT PRIMARY KEY,
  user_id          INT          DEFAULT NULL,
  ticket_token     VARCHAR(64)  DEFAULT NULL UNIQUE,
  manage_token_hash CHAR(64)    DEFAULT NULL UNIQUE,
  manage_token_issued_at DATETIME DEFAULT NULL,
  manage_token_expires_at DATETIME DEFAULT NULL,
  manage_token_revoked_at DATETIME DEFAULT NULL,
  client_first_name VARCHAR(100) DEFAULT NULL,
  client_last_name  VARCHAR(100) DEFAULT NULL,
  client_name      VARCHAR(100) DEFAULT NULL,
  phone_number     VARCHAR(20)  DEFAULT NULL,
  window_id        INT          DEFAULT NULL,
  service_id       INT          NOT NULL,
  queue_mode       ENUM('central','specialized') NOT NULL DEFAULT 'central',
  reference_number VARCHAR(20)  NOT NULL UNIQUE,  -- BHC-2025-0001
  qr_code_path     VARCHAR(255) DEFAULT NULL,     -- path to QR image in /assets/qr/
  ticket_number    VARCHAR(10)  DEFAULT NULL,       -- assigned at physical arrival
  entry_type       ENUM('walk-in','online') NOT NULL DEFAULT 'online',
  client_type      ENUM('regular','senior','pwd')  DEFAULT 'regular',
  priority_level   TINYINT      DEFAULT 0,         -- legacy compatibility; active rows use 0
  status           ENUM('waiting','serving','completed','voided','skipped')
                   DEFAULT 'waiting',
  lifecycle_status ENUM('scheduled','waiting','calling','in-progress','completed','void')
                   NOT NULL DEFAULT 'waiting',
  issued_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  checked_in_at    DATETIME      DEFAULT NULL,
  scheduled_expires_at DATETIME  DEFAULT NULL,
  check_in_method  ENUM('qr','reference','walk-in') DEFAULT NULL,
  checked_in_by    INT           DEFAULT NULL,
  called_at        DATETIME      DEFAULT NULL,      -- when staff clicked Call Next
  served_at        DATETIME      DEFAULT NULL,      -- when service started
  started_at       DATETIME      DEFAULT NULL,      -- blueprint alias for served_at
  completed_at     DATETIME      DEFAULT NULL,      -- when staff marked Complete
  voided_at        DATETIME      DEFAULT NULL,
  voided_reason    VARCHAR(100) DEFAULT NULL,      -- e.g. Client did not appear
  FOREIGN KEY (user_id)    REFERENCES users(user_id),
  FOREIGN KEY (window_id)  REFERENCES service_windows(window_id) ON DELETE SET NULL,
  FOREIGN KEY (service_id) REFERENCES health_services(service_id),
  FOREIGN KEY (checked_in_by) REFERENCES staff(staff_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_queue_call_central
  ON queue_tickets (status, queue_mode, priority_level, issued_at);
CREATE INDEX idx_queue_call_specialized
  ON queue_tickets (status, queue_mode, service_id, priority_level, issued_at);
CREATE INDEX idx_queue_user_active
  ON queue_tickets (user_id, status);
CREATE INDEX idx_queue_public_token_status
  ON queue_tickets (ticket_token, lifecycle_status);
CREATE INDEX idx_queue_counter_lifecycle
  ON queue_tickets (window_id, lifecycle_status, called_at);
CREATE INDEX idx_queue_scheduled_expiry
  ON queue_tickets (lifecycle_status, scheduled_expires_at, ticket_id);
CREATE INDEX idx_queue_fifo
  ON queue_tickets (lifecycle_status, checked_in_at, ticket_id);
CREATE INDEX idx_queue_service_fifo
  ON queue_tickets (service_id, lifecycle_status, checked_in_at, ticket_id);

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
) ENGINE=InnoDB;


-- ── 5A. PREPRINTED NUMBER RESERVATIONS ─────────────────────
--    Optional Staff-created number batches. Allocation always remains
--    per-service and per-local-date; exhausted batches never block check-in.
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ticket_print_batches (
  batch_id       INT AUTO_INCREMENT PRIMARY KEY,
  service_id     INT NOT NULL,
  service_date   DATE NOT NULL,
  start_number   INT NOT NULL,
  end_number     INT NOT NULL,
  created_by     INT NOT NULL,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ticket_print_batch_service_date (service_id, service_date),
  FOREIGN KEY (service_id) REFERENCES health_services(service_id),
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
  FOREIGN KEY (batch_id) REFERENCES ticket_print_batches(batch_id) ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES health_services(service_id),
  FOREIGN KEY (ticket_id) REFERENCES queue_tickets(ticket_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Prevent duplicate active tickets per client
-- A client can only have one ticket with status waiting, serving, or skipped.
-- NOTE: One active ticket per client is enforced in PHP (join_queue.php)
-- Check: SELECT COUNT(*) FROM queue_tickets WHERE user_id=? AND status IN ('waiting','serving','skipped')


-- ── 6. WAIT TIME LOGS ─────────────────────────────────────────
--    Stores ML input features + predicted vs actual wait time.
--    This is the training dataset that grows over time.
--    Also feeds: Report 2, Report 5, Report 7, Report 8.
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS wait_time_logs (
  log_id               INT          AUTO_INCREMENT PRIMARY KEY,
  ticket_id            INT          NOT NULL UNIQUE,
  staff_id             INT          DEFAULT NULL,       -- who served this client (Report 7)
  -- ML feature inputs
  queue_length         INT          DEFAULT NULL,       -- checked-in waiting tickets ahead at arrival
  hour_of_day          TINYINT      DEFAULT NULL,       -- 0–23
  day_of_week          TINYINT      DEFAULT NULL,       -- 0=Sun, 1=Mon ... 6=Sat
  service_type_encoded TINYINT      DEFAULT NULL,       -- from health_services.service_encoded
  client_type_encoded  TINYINT      DEFAULT NULL,       -- 0=regular, 1=senior, 2=pwd
  active_windows       TINYINT      DEFAULT NULL,       -- mapped open counters at prediction time
  avg_service_time     DECIMAL(5,2) DEFAULT NULL,       -- rolling avg from last 20 tickets (min)
  -- Prediction and results
  predicted_wait_min   DECIMAL(6,2) DEFAULT NULL,       -- ML model output
  prediction_confidence DECIMAL(6,5) DEFAULT NULL,      -- validated model confidence (0..1)
  model_version        VARCHAR(100) DEFAULT NULL,       -- immutable deployed model identifier
  actual_wait_min      DECIMAL(6,2) DEFAULT NULL,       -- service start - physical check-in in minutes
  actual_service_dur   INT          DEFAULT NULL,       -- service duration in seconds (Report 7)
  algorithm_used       VARCHAR(50)  DEFAULT 'Random Forest',
  logged_at            TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES queue_tickets(ticket_id),
  FOREIGN KEY (staff_id)  REFERENCES staff(staff_id) ON DELETE SET NULL
) ENGINE=InnoDB;


-- ── 7. ML COMPARISON LOGS ─────────────────────────────────────
--    Stores results from compare_algorithms.py.
--    Feeds Report 8 (Machine Learning Accuracy Report).
--    is_best = 1 marks the algorithm selected as the deployed model.
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ml_comparison_logs (
  id           INT          AUTO_INCREMENT PRIMARY KEY,
  run_date     DATE         NOT NULL,
  algorithm    VARCHAR(50)  NOT NULL,
  mae          DECIMAL(8,4) DEFAULT NULL,
  rmse         DECIMAL(8,4) DEFAULT NULL,
  r2           DECIMAL(6,4) DEFAULT NULL,
  mape         DECIMAL(6,2) DEFAULT NULL,
  is_best      TINYINT(1)   DEFAULT 0,
  dataset_used VARCHAR(100) DEFAULT NULL,   -- ml/dataset/queue_data.csv database export
  sample_size  INT          DEFAULT NULL,   -- number of training samples used
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- ── 8. SYSTEM SETTINGS ────────────────────────────────────────
--    Admin-configurable key-value store.
--    Controls void timeout, SMS, queue hours, BHC info, etc.
--    All values are strings — PHP casts to correct type on read.
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS system_settings (
  setting_id   INT          AUTO_INCREMENT PRIMARY KEY,
  setting_key  VARCHAR(100) NOT NULL UNIQUE,
  setting_val  VARCHAR(500) NOT NULL,
  label        VARCHAR(150) DEFAULT NULL,  -- human-readable label shown in admin UI
  section      VARCHAR(50)  DEFAULT NULL,  -- groups settings into UI sections
  updated_by   INT          DEFAULT NULL,
  updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Default settings — admin updates these on first login
INSERT INTO system_settings (setting_key, setting_val, label, section) VALUES
  -- Health Center Info
  ('bhc_name',             'Barangay Health Center',  'Health Center Name',          'info'),
  ('bhc_address',          '',                         'Health Center Address',       'info'),
  ('bhc_contact',          '',                         'Contact Number',              'info'),
  ('bhc_barangay',         '',                         'Barangay Name',               'info'),
  -- Queue Configuration
  ('queue_open_time',      '07:00',                    'Queue Opens At',              'queue'),
  ('queue_close_time',     '17:00',                    'Queue Closes At',             'queue'),
  ('max_queue_per_day',    '100',                      'Max Queue per Day',           'queue'),
  ('void_timeout_minutes', '5',                        'Calling Timeout (minutes)',   'queue'),
  ('priority_queue_enabled','0',                       'Legacy Priority Queue',       'queue'),
  -- SMS Configuration
  ('sms_enabled',          '0',                        'Enable SMS Notifications',    'sms'),
  ('sms_sender_name',      'BHCQMS',                   'SMS Sender Name',             'sms'),
  -- Display Board
  ('display_board_token',  '',                         'Display Board Access Token',  'display'),
  -- ML Model
  ('ml_last_trained',      '',                         'Model Last Trained',          'ml'),
  ('ml_dataset_used',      '',                         'Training Dataset Used',       'ml');


-- ── 9. NOTIFICATIONS ──────────────────────────────────────────
--    In-app browser notifications + SMS notifications.
--    channel 'both' = send browser + SMS simultaneously.
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
  notif_id         INT          AUTO_INCREMENT PRIMARY KEY,
  ticket_id        INT          NOT NULL,
  user_id          INT          NOT NULL,
  message          VARCHAR(255) NOT NULL,
  type             VARCHAR(50)  DEFAULT NULL,  -- e.g. turn_alert, void_warning
  channel          ENUM('browser','sms','both')     DEFAULT 'both',
  delivery_status  ENUM('pending','sent','failed')  DEFAULT 'pending',
  is_read          TINYINT(1)   DEFAULT 0,
  sent_at          TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES queue_tickets(ticket_id),
  FOREIGN KEY (user_id)   REFERENCES users(user_id)
) ENGINE=InnoDB;


-- ── 10. SMS LOGS ──────────────────────────────────────────────
--    Logs every SMS attempt (OTP, notification, test).
--    status 'simulated' = no real SMS sent (no API key configured).
--    Show this table to panel as proof SMS feature is built.
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sms_logs (
  log_id    INT         AUTO_INCREMENT PRIMARY KEY,
  user_id   INT         DEFAULT NULL,
  phone     VARCHAR(15) NOT NULL,
  message   TEXT        NOT NULL,
  type      ENUM('otp','notification','test')       DEFAULT 'notification',
  status    ENUM('sent','failed','simulated')       DEFAULT 'simulated',
  error_msg VARCHAR(255) DEFAULT NULL,              -- API error message if failed
  sent_at   TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
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


-- ── 11. FEEDBACK ──────────────────────────────────────────────
--    Post-service client rating. Feeds Report 10.
--    One feedback per completed ticket only.
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS feedback (
  feedback_id   INT       AUTO_INCREMENT PRIMARY KEY,
  ticket_id     INT       NOT NULL UNIQUE,   -- one feedback per ticket
  user_id       INT       DEFAULT NULL,
  window_id     INT       DEFAULT NULL,
  service_id    INT       DEFAULT NULL,
  rating        TINYINT   NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment       TEXT      DEFAULT NULL,
  submitted_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id)  REFERENCES queue_tickets(ticket_id),
  FOREIGN KEY (user_id)    REFERENCES users(user_id),
  FOREIGN KEY (window_id)  REFERENCES service_windows(window_id) ON DELETE SET NULL,
  FOREIGN KEY (service_id) REFERENCES health_services(service_id) ON DELETE SET NULL
) ENGINE=InnoDB;


-- ── 12. ACTIVITY LOGS ─────────────────────────────────────────
--    Tracks every significant user action system-wide.
--    Admin views all logs; staff views their own only.
--    Feeds: Report 7 (Staff Productivity), Admin Activity Log page.
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS activity_logs (
  log_id      INT          AUTO_INCREMENT PRIMARY KEY,
  user_id     INT          NOT NULL,
  role        VARCHAR(10)  DEFAULT NULL,        -- snapshot of role at time of action
  action      VARCHAR(100) NOT NULL,            -- e.g. ticket_called, window_opened
  details     TEXT         DEFAULT NULL,        -- JSON or readable description
  ticket_id   INT          DEFAULT NULL,        -- linked ticket if applicable
  ip_address  VARCHAR(45)  DEFAULT NULL,
  logged_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)   REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (ticket_id) REFERENCES queue_tickets(ticket_id) ON DELETE SET NULL
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
  FOREIGN KEY (actor_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO schema_migrations (migration_id)
VALUES ('20260905_001_production_security');


-- ── USEFUL VIEWS ──────────────────────────────────────────────
--    Pre-built queries used by reports and dashboard.
-- ──────────────────────────────────────────────────────────────

-- View: Today's queue summary
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
    TIMESTAMPDIFF(MINUTE, qt.issued_at, COALESCE(qt.completed_at, NOW()))
      AS total_minutes_in_system
  FROM queue_tickets qt
  LEFT JOIN users u       ON qt.user_id    = u.user_id
  JOIN health_services hs ON qt.service_id = hs.service_id
  LEFT JOIN service_windows sw ON qt.window_id = sw.window_id
  WHERE DATE(qt.issued_at) = CURDATE()
  ORDER BY COALESCE(qt.checked_in_at, qt.issued_at) ASC, qt.ticket_id ASC;

-- View: ML accuracy summary (last run per algorithm)
CREATE OR REPLACE VIEW v_ml_latest_comparison AS
  SELECT ml.*
  FROM ml_comparison_logs ml
  INNER JOIN (
    SELECT algorithm, MAX(run_date) AS latest_run
    FROM ml_comparison_logs
    GROUP BY algorithm
  ) latest ON ml.algorithm = latest.algorithm
           AND ml.run_date = latest.latest_run;

-- View: Staff productivity today
CREATE OR REPLACE VIEW v_staff_productivity_today AS
  SELECT
    s.staff_id,
    CONCAT(u.first_name, ' ', u.last_name) AS staff_name,
    sw.window_name,
    COUNT(qt.ticket_id)                      AS tickets_served,
    ROUND(AVG(CASE WHEN qt.ticket_id IS NOT NULL THEN wl.actual_wait_min END), 2)
                                               AS avg_wait_min,
    ROUND(AVG(CASE WHEN qt.ticket_id IS NOT NULL THEN wl.actual_service_dur END)/60, 2)
                                               AS avg_service_min
  FROM staff s
  JOIN users u              ON s.user_id   = u.user_id
  LEFT JOIN service_windows sw ON sw.staff_id = s.staff_id
  LEFT JOIN wait_time_logs wl  ON wl.staff_id = s.staff_id
  LEFT JOIN queue_tickets qt   ON wl.ticket_id = qt.ticket_id
                              AND DATE(qt.completed_at) = CURDATE()
  GROUP BY s.staff_id, staff_name, sw.window_name;
