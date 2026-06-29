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
--   • Void timeout: admin-configurable (default 10 min)
--   • Priority queue: Senior Citizens and PWDs served first
-- ================================================================

CREATE DATABASE IF NOT EXISTS smartqms
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE smartqms;

-- ── 1. USERS ──────────────────────────────────────────────────
--    All roles (client, staff, admin) share this table.
--    Role is detected on login to redirect to the correct dashboard.
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  user_id        INT           AUTO_INCREMENT PRIMARY KEY,
  first_name     VARCHAR(50)   NOT NULL,
  last_name      VARCHAR(50)   NOT NULL,
  middle_name    VARCHAR(50)   DEFAULT NULL,
  phone_number   VARCHAR(15)   NOT NULL UNIQUE,   -- primary login identifier
  email          VARCHAR(100)  DEFAULT NULL,       -- optional
  password_hash  VARCHAR(255)  NOT NULL,
  role           ENUM('client','staff','admin')    DEFAULT 'client',
  is_verified    TINYINT(1)    DEFAULT 0,          -- 1 = phone OTP verified
  otp_code       VARCHAR(6)    DEFAULT NULL,
  otp_expires_at DATETIME      DEFAULT NULL,
  is_active      TINYINT(1)    DEFAULT 1,          -- admin can deactivate accounts
  last_login_at  DATETIME      DEFAULT NULL,
  created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default admin account — CHANGE PASSWORD AFTER FIRST LOGIN
INSERT INTO users
  (first_name, last_name, phone_number, email, password_hash, role, is_verified)
VALUES
  ('System', 'Administrator', '09000000000', 'admin@bhcqms.ph',
   '$2y$10$kNK5fhFTu8gy6wHNLwvIpe4iVUO0sZ5EZTDCzsIcw4WxlNsIbhtOC', 'admin', 1);


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
  description      TEXT         DEFAULT NULL, -- optional description for clients
  priority_only    TINYINT(1)   DEFAULT 0,    -- 1 = Senior/PWD clients only
  is_active        TINYINT(1)   DEFAULT 1,    -- admin can enable/disable per service
  display_order    TINYINT      DEFAULT 0,    -- controls order in dropdown
  created_by       INT          DEFAULT NULL,
  created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Default health services for a typical barangay health center
-- Admin can add, edit, deactivate, or reorder these
INSERT INTO health_services
  (service_code, service_name, service_encoded, priority_only, display_order)
VALUES
  ('SVC-001', 'General Checkup / Consultation',   1, 0, 1),
  ('SVC-002', 'Vaccination / Immunization',        2, 0, 2),
  ('SVC-003', 'Prenatal / Maternal Care',          3, 0, 3),
  ('SVC-004', 'Dental Services',                   4, 0, 4),
  ('SVC-005', 'Laboratory / Medical Certificate',  5, 0, 5),
  ('SVC-006', 'Family Planning',                   6, 0, 6),
  ('SVC-007', 'Senior Citizen Services',           7, 1, 7),
  ('SVC-008', 'PWD Assessment / Certification',    8, 1, 8);


-- ── 4. SERVICE WINDOWS ────────────────────────────────────────
--    Each window is assigned to one staff member and one service.
--    Admin configures and manages windows.
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS service_windows (
  window_id         INT         AUTO_INCREMENT PRIMARY KEY,
  window_name       VARCHAR(50) NOT NULL,         -- e.g. Window 1, Dental Window
  service_id        INT         DEFAULT NULL,      -- which health service this window handles
  staff_id          INT         DEFAULT NULL,      -- assigned staff member
  status            ENUM('open','busy','closed')   DEFAULT 'closed',
  priority_enabled  TINYINT(1)  DEFAULT 1,         -- 1 = accepts Senior/PWD clients
  is_active         TINYINT(1)  DEFAULT 1,
  created_at        TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (service_id) REFERENCES health_services(service_id) ON DELETE SET NULL,
  FOREIGN KEY (staff_id)   REFERENCES staff(staff_id)             ON DELETE SET NULL
) ENGINE=InnoDB;


-- ── 5. QUEUE TICKETS ──────────────────────────────────────────
--    Core entity. One active ticket per client at a time.
--    Reference number format: BHC-YYYY-NNNN (e.g. BHC-2025-0001)
--    priority_level: 0 = regular, 1 = senior or PWD
--    Ordering: ORDER BY priority_level DESC, issued_at ASC
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS queue_tickets (
  ticket_id        INT          AUTO_INCREMENT PRIMARY KEY,
  user_id          INT          NOT NULL,
  window_id        INT          DEFAULT NULL,
  service_id       INT          NOT NULL,
  reference_number VARCHAR(20)  NOT NULL UNIQUE,  -- BHC-2025-0001
  qr_code_path     VARCHAR(255) DEFAULT NULL,     -- path to QR image in /assets/qr/
  ticket_number    VARCHAR(10)  NOT NULL,          -- display number (e.g. A-001)
  client_type      ENUM('regular','senior','pwd')  DEFAULT 'regular',
  priority_level   TINYINT      DEFAULT 0,         -- 0=regular, 1=senior/pwd
  status           ENUM('waiting','serving','completed','voided','skipped')
                   DEFAULT 'waiting',
  issued_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  called_at        DATETIME      DEFAULT NULL,      -- when staff clicked Call Next
  served_at        DATETIME      DEFAULT NULL,      -- when service started
  completed_at     DATETIME      DEFAULT NULL,      -- when staff marked Complete
  voided_at        DATETIME      DEFAULT NULL,
  voided_reason    VARCHAR(100) DEFAULT NULL,      -- e.g. Client did not appear
  FOREIGN KEY (user_id)    REFERENCES users(user_id),
  FOREIGN KEY (window_id)  REFERENCES service_windows(window_id) ON DELETE SET NULL,
  FOREIGN KEY (service_id) REFERENCES health_services(service_id)
) ENGINE=InnoDB;

-- Prevent duplicate active tickets per client
-- A client can only have one ticket with status 'waiting' or 'serving' at a time
-- NOTE: One active ticket per client is enforced in PHP (join_queue.php)
-- Check: SELECT COUNT(*) FROM queue_tickets WHERE user_id=? AND status IN ('waiting','serving')


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
  queue_length         INT          DEFAULT NULL,       -- number of tickets ahead at issue time
  hour_of_day          TINYINT      DEFAULT NULL,       -- 0–23
  day_of_week          TINYINT      DEFAULT NULL,       -- 0=Sun, 1=Mon ... 6=Sat
  service_type_encoded TINYINT      DEFAULT NULL,       -- from health_services.service_encoded
  client_type_encoded  TINYINT      DEFAULT NULL,       -- 0=regular, 1=senior, 2=pwd
  active_windows       TINYINT      DEFAULT NULL,       -- open windows at time of issue
  avg_service_time     DECIMAL(5,2) DEFAULT NULL,       -- rolling avg from last 20 tickets (min)
  -- Prediction and results
  predicted_wait_min   DECIMAL(6,2) DEFAULT NULL,       -- ML model output
  actual_wait_min      DECIMAL(6,2) DEFAULT NULL,       -- completed_at - issued_at in minutes
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
  dataset_used VARCHAR(100) DEFAULT NULL,   -- e.g. kaggle_hospital_queue.csv
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
  ('void_timeout_minutes', '10',                       'Void Timeout (minutes)',      'queue'),
  ('priority_queue_enabled','1',                       'Priority Queue (Senior/PWD)', 'queue'),
  -- SMS Configuration
  ('sms_enabled',          '0',                        'Enable SMS Notifications',    'sms'),
  ('sms_api_key',          '',                         'SMS API Key (Semaphore)',      'sms'),
  ('sms_sender_name',      'BHCQMS',                   'SMS Sender Name',             'sms'),
  -- Display Board
  ('display_board_token',  'changeme_random_token',    'Display Board Access Token',  'display'),
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


-- ── 11. FEEDBACK ──────────────────────────────────────────────
--    Post-service client rating. Feeds Report 10.
--    One feedback per completed ticket only.
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS feedback (
  feedback_id   INT       AUTO_INCREMENT PRIMARY KEY,
  ticket_id     INT       NOT NULL UNIQUE,   -- one feedback per ticket
  user_id       INT       NOT NULL,
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


-- ── USEFUL VIEWS ──────────────────────────────────────────────
--    Pre-built queries used by reports and dashboard.
-- ──────────────────────────────────────────────────────────────

-- View: Today's queue summary
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
  JOIN users u            ON qt.user_id    = u.user_id
  JOIN health_services hs ON qt.service_id = hs.service_id
  LEFT JOIN service_windows sw ON qt.window_id = sw.window_id
  WHERE DATE(qt.issued_at) = CURDATE()
  ORDER BY qt.priority_level DESC, qt.issued_at ASC;

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
    COUNT(wl.log_id)                         AS tickets_served,
    ROUND(AVG(wl.actual_wait_min), 2)        AS avg_wait_min,
    ROUND(AVG(wl.actual_service_dur)/60, 2)  AS avg_service_min
  FROM staff s
  JOIN users u              ON s.user_id   = u.user_id
  LEFT JOIN service_windows sw ON sw.staff_id = s.staff_id
  LEFT JOIN wait_time_logs wl  ON wl.staff_id = s.staff_id
  LEFT JOIN queue_tickets qt   ON wl.ticket_id = qt.ticket_id
                              AND DATE(qt.completed_at) = CURDATE()
  GROUP BY s.staff_id, staff_name, sw.window_name;
