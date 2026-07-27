# SmartQMS — UI/UX Build Prompt for Codex

Web-Based Smart Queue Management System with Machine Learning-Based Waiting Time
Prediction Using Random Forest Algorithm. Built for a Barangay Health Center.

Paste the prompt below into Codex. It is written for a code-generation agent —
it specifies file targets, the existing PHP/Bootstrap stack, and the database
schema your views must read from, not just visual direction.

---

## Prompt

```
You are building the frontend views for SmartQMS, a PHP + MySQL + Bootstrap 5
web application. Do not invent new routes, tables, or fields — work strictly
against the schema and file structure below. Every screen must be a real PHP
file in the path listed, written to query the actual tables and columns named.

============================================================
PROJECT CONTEXT
============================================================
Stack: PHP 8.x, MySQL 8.x, Bootstrap 5, vanilla JavaScript (no framework).
Database: smartqms (see schema reference below).
Existing config helpers (already built, use them, do not redefine):
  require_once 'config/config.php';     // session, constants, requireLogin()
  require_once 'config/database.php';   // $conn (mysqli)
requireLogin(ROLE_CLIENT | ROLE_STAFF | ROLE_ADMIN) guards each page.
generateRefNumber($conn) returns the next BHC-YYYY-NNNN reference number.

Three roles share ONE login page (index.php). Role is read from users.role
and the user is redirected automatically — there is no role picker UI.
Staff accounts are created only by admin (no staff self-registration).
Clients register once, then log in with phone_number + password on every
future visit. A client may only have ONE ticket with status IN
('waiting','serving') at any time — block new queue joins otherwise.

============================================================
COLOR PALETTE — STRICT, DO NOT DEVIATE
============================================================
--bg-page:      #F4F4F4   page background only, never on cards
--text-primary: #2E2E2E   headings, body text, nav
--surface:      #FFFFFF   cards, panels, inputs
--accent:       #4A90E2   primary buttons, links, active nav state
--success:      #1D9E75   serving / completed / priority badges
--warning:      #BA7517   waiting / pending badges
--danger:       #D85A30   voided / error / destructive actions
--display-bg:   #2E2E2E   ONLY for the public display board page

Card shadow: 0 1px 4px rgba(0,0,0,0.08). No gradients anywhere. Flat fills only.
Table rows alternate #F4F4F4 / #FFFFFF. Priority rows (client_type IN
('senior','pwd')) get a left border: 3px solid #1D9E75 with a soft tint
background, never rounded on that single border.

============================================================
TYPOGRAPHY
============================================================
Load: <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600
&family=Inter:wght@400;500&display=swap" rel="stylesheet">

Poppins 600 — page titles, queue numbers, card headers
Poppins 500 — nav labels, form field labels, buttons
Inter 400    — body copy, table data, descriptions
Inter 500    — table headers, emphasized inline text

Sizes: queue number 40-48px, h1 24px, h2 18px, label 13px, body 15px,
table data 13px. 13px is the absolute floor anywhere in the system —
senior citizens are a primary user group, never go smaller.

============================================================
DATABASE SCHEMA REFERENCE (query against these exact tables/columns)
============================================================
users(user_id, first_name, last_name, middle_name, phone_number, email,
  password_hash, role['client'|'staff'|'admin'], is_verified, otp_code,
  otp_expires_at, is_active, last_login_at, created_at)

staff(staff_id, user_id, department, shift, added_by, added_at)

health_services(service_id, service_code, service_name, service_encoded,
  description, priority_only, is_active, display_order, created_by)
  -- admin-configurable per barangay, populates the client service dropdown

service_windows(window_id, window_name, service_id, staff_id,
  status['open'|'busy'|'closed'], priority_enabled, is_active)

queue_tickets(ticket_id, user_id, window_id, service_id, reference_number,
  qr_code_path, ticket_number, client_type['regular'|'senior'|'pwd'],
  priority_level, status['waiting'|'serving'|'completed'|'voided'|'skipped'],
  issued_at, called_at, served_at, completed_at, voided_at, voided_reason)

wait_time_logs(log_id, ticket_id, staff_id, queue_length, hour_of_day,
  day_of_week, service_type_encoded, client_type_encoded, active_windows,
  avg_service_time, predicted_wait_min, actual_wait_min,
  actual_service_dur, algorithm_used, logged_at)

ml_comparison_logs(id, run_date, algorithm, mae, rmse, r2, mape, is_best,
  dataset_used, sample_size)
  -- view v_ml_latest_comparison gives the latest run per algorithm

system_settings(setting_id, setting_key, setting_val, label, section,
  updated_by, updated_at)
  -- sections: info, queue, sms, display, ml

notifications(notif_id, ticket_id, user_id, message, type,
  channel['browser'|'sms'|'both'], delivery_status, is_read, sent_at)

sms_logs(log_id, user_id, phone, message, type, status['sent'|'failed'
  |'simulated'], sent_at)

feedback(feedback_id, ticket_id, user_id, window_id, service_id, rating(1-5),
  comment, submitted_at)

activity_logs(log_id, user_id, role, action, details, ticket_id, ip_address,
  logged_at)

Useful views already created: v_today_queue, v_ml_latest_comparison,
v_staff_productivity_today.

Priority ordering rule used everywhere a queue list is shown:
  ORDER BY priority_level DESC, issued_at ASC

============================================================
SCREENS TO BUILD
============================================================
Build each as its own PHP file. Use real query results, not static markup —
wire each page to the tables above. Use placeholder Filipino names and BHC
reference numbers (BHC-2025-0001 format) only where live data is empty.

--- 1. index.php (root) — Unified login ---
Single phone_number + password form. POST to modules/auth/login.php.
No role selector. Link: "New patient? Register here" -> views/client/register.php.
Small caption: "Staff and Admin accounts are managed by the administrator."

--- 2. views/client/register.php ---
Fields: First Name, Last Name, Middle Name (optional), Phone Number,
Email (optional), Password, Confirm Password, Client Classification as
three selectable cards (Regular / Senior Citizen / PWD) bound to radio
inputs. On submit -> modules/auth/register.php -> OTP modal (6-digit input,
60s resend countdown) -> modules/auth/verify_otp.php.

--- 3. views/client/index.php — Client dashboard ---
Two states from PHP, not JS toggling:
State A (no row in queue_tickets with status IN waiting/serving for this
user_id): centered card, "Get your queue number for today's visit",
button -> views/client/get_number.php.
State B (active ticket exists): show reference_number, ticket_number,
status badge, client_type priority badge if applicable, people ahead
(COUNT query using the priority ordering rule above), predicted_wait_min,
qr_code_path image, notification channel toggle.

--- 4. views/client/get_number.php — Join queue ---
Two-step form. Step 1: health service cards pulled from
SELECT * FROM health_services WHERE is_active=1 ORDER BY display_order.
Cards marked priority_only=1 only enabled if the logged-in user's
client_type is senior or pwd. Step 2: confirm client_type for this visit
(prefilled from users, editable), show live queue_length and
predicted_wait_min (call modules/queue/get_prediction.php). Submit posts
to modules/queue/join_queue.php.

--- 5. views/client/feedback.php ---
Shown after a ticket's status flips to completed. 1-5 star input bound to
feedback.rating, optional comment textarea. Posts to
modules/feedback/submit_feedback.php. One feedback per ticket_id only.

--- 6. views/display/board.php — Public display board ---
Dark theme (#2E2E2E background, white text). No sidebar, no login chrome.
Grid of cards, one per row in v_today_queue grouped by window_id where
status='serving': window_name, ticket_number large (Poppins 600, 48px+),
service_name, status pill. Bottom ticker shows next 3 waiting tickets by
the priority ordering rule. Auto-refresh every 10s via fetch to
modules/queue/status.php — no full page reload. This file is only ever
reached through root display.php, which checks a token against
system_settings.setting_key='display_board_token' before including it.

--- 7. views/staff/dashboard.php ---
Sidebar: Dashboard, My Window, Activity Log, Logout. Top stat row: tickets
served today (COUNT wait_time_logs WHERE staff_id=session AND
DATE(logged_at)=CURDATE()), currently serving ticket card, avg service
time today. Current ticket card shows client_type priority badge, called_at
with a live countdown to system_settings void_timeout_minutes. Buttons:
Mark Complete -> modules/service_window/complete_ticket.php, Skip ->
skip_ticket.php, Void -> manual void action.

--- 8. views/staff/window.php — My Window ---
Status toggle bound to service_windows.status (open/busy/closed), calls
modules/service_window/window_status.php on change. Queue list query:
SELECT * FROM queue_tickets WHERE status='waiting' AND service_id=
(this window's service_id) ORDER BY priority_level DESC, issued_at ASC.
Render priority rows with the left-border treatment from the palette
section. Call Next button posts to modules/service_window/call_next.php.

--- 9. views/staff/activity_log.php ---
Timeline list, not a table. Query: SELECT * FROM activity_logs WHERE
user_id=session staff user_id ORDER BY logged_at DESC, default filtered
to DATE(logged_at)=CURDATE() with a date range filter. Icon per action
type (call -> ti-phone, complete -> ti-check, skip -> ti-player-skip-forward,
void -> ti-x, window toggle -> ti-door).

--- 10. views/admin/dashboard.php ---
Sidebar nav (see nav structure below). KPI row, 4 cards: total tickets
today, avg actual_wait_min today, currently serving count, voided count
today. Two charts (Chart.js, already CDN-available): bar of tickets by
hour_of_day from wait_time_logs, line of predicted_wait_min vs
actual_wait_min over the last 7 days. Below each chart, a plain-sentence
interpretation built from the same query results in PHP, not hardcoded —
e.g. peak hour and the day's MAE expressed in words.

--- 11. views/admin/reports.php — Reports hub ---
Left panel: sidebar sub-menu, 10 items, numbered. Right panel: selected
report's chart + table + date range filter + CSV export button hitting
modules/reports/export_csv.php?report=X. Map each item to its existing
backend file:
  01 Queue Summary               -> modules/reports/queue_summary.php
  02 Predicted vs Actual Wait    -> modules/reports/predicted_vs_actual.php
  03 Peak Hour Analysis          -> modules/reports/peak_hour.php
  04 Service Counter Performance -> modules/reports/counter_performance.php
  05 Customer Turnaround Time    -> modules/reports/turnaround_time.php
  06 No-Show Report              -> modules/reports/no_show.php
  07 Staff Productivity          -> modules/reports/staff_productivity.php
  08 ML Accuracy Report          -> modules/reports/ml_accuracy.php
  09 Daily and Monthly Stats     -> modules/reports/daily_monthly_stats.php
  10 Customer Satisfaction       -> modules/reports/satisfaction.php
Report 08 renders the algorithm comparison table from
v_ml_latest_comparison with the is_best=1 row given a "Best" badge and
the #1D9E75 left border treatment.

--- 12. views/admin/settings.php — System settings ---
Read all rows from system_settings, group by the section column, render
five fieldsets in this exact order:
  info  -> bhc_name, bhc_address, bhc_barangay, bhc_contact
  queue -> queue_open_time, queue_close_time, max_queue_per_day,
           void_timeout_minutes, priority_queue_enabled (toggle)
  sms   -> sms_enabled (toggle), sms_api_key (password input with
           show/hide), sms_sender_name, "Send test SMS" button
  display -> display_board_token (with a "Copy display URL" button that
           builds the full link), and inline help showing the URL format
  ml    -> ml_last_trained (read-only), ml_dataset_used (read-only),
           "Retrain model" button, latest comparison table inline
Each fieldset posts independently to modules/settings/system_settings.php.

--- 13. views/admin/health_services.php — Manage services per barangay ---
This screen did not exist in the prior prompt version — it is new because
health services are now admin-configurable per barangay deployment. Table
of health_services (code, name, encoded value read-only once set,
priority_only toggle, is_active toggle, display_order drag or numeric
input). "Add new service" opens a modal with service_code, service_name,
description, priority_only checkbox. Posts to
modules/settings/health_services_mgmt.php. Inline note explaining that
service_encoded cannot change once tickets reference it.

--- 14. views/admin/users.php and add_staff.php ---
users.php: table of all users with role badge, is_active toggle. Filter
tabs: All / Clients / Staff / Admin. add_staff.php: form for first_name,
last_name, phone_number, email, password, department, shift, and a
service_windows assignment dropdown — submitting creates both a users row
(role='staff') and a staff row in one transaction.

--- 15. views/admin/ml_logs.php ---
Table from wait_time_logs joined to queue_tickets: reference_number,
predicted_wait_min, actual_wait_min, the difference, color-coded green
if within 2 minutes, amber if 2-5, red beyond 5.

--- 16. views/admin/activity_log.php ---
Same timeline pattern as the staff version but system-wide, with a role
filter (client/staff/admin) and a user filter dropdown.

============================================================
SIDEBAR NAV STRUCTURE — ADMIN
============================================================
Dashboard
User Management
  -> Add Staff (sub-link)
Service Windows
Health Services        <- new, admin-configurable per barangay
Reports                <- click expands the 10-item sub-menu in place,
                           does not navigate away, sub-menu persists open
                           state while any report is active
System Settings
Activity Log
Logout

============================================================
SIDEBAR NAV STRUCTURE — STAFF
============================================================
Dashboard
My Window
Activity Log
Logout

============================================================
COMPONENT RULES
============================================================
Status badges — exactly these four states, exactly these colors, reused
everywhere (display board, staff dashboard, admin tables):
  waiting   -> background #FAEEDA, text #854F0B
  serving   -> background #E1F5EE, text #0F6E56
  voided    -> background #FAECE7, text #993C1D
  priority  -> background #EAF3DE, text #3B6D11
Define these once in assets/css/style.css as .badge-waiting, .badge-serving,
.badge-voided, .badge-priority — every view reuses the class, never
reimplements inline.

Buttons: minimum 44px height everywhere (touch target floor). One filled
accent button per view maximum — everything else is outline or text style.

Forms: inline validation, red border + message below field on error, green
border on valid. Phone number fields validate against ^09\d{9}$.

Tables: table-layout fixed where columns are bounded, alternating row
backgrounds per the palette section, 13px data text.

Empty states: icon + one-sentence message + a single action where relevant.
Never leave a section blank with no explanation.

Mobile (<768px): sidebar collapses into a bottom navigation bar, icons
only with small labels beneath, fixed but in normal document flow (do not
use position:fixed in a way that breaks scroll — use a sticky footer wrapper).

============================================================
WHAT NOT TO DO
============================================================
Do not invent a "select your barangay" multi-tenant switcher — this is one
installation per barangay, configured through Health Services and System
Settings, not a multi-barangay picker in the UI.
Do not add a role selector to the login page.
Do not let staff self-register.
Do not allow a client to hold two simultaneous active tickets — enforce the
check server-side before insert, and disable the "Get Queue Number" button
client-side when an active ticket already exists.
Do not hardcode health services in markup — always query health_services.
Do not skip the void countdown timer on the staff dashboard — it is the
visual proof of the admin-configurable void_timeout_minutes feature.
```

---

## What Changed From the Previous Prompt

| Area | Previous version | This version |
|---|---|---|
| Login | Implied per-role pages | One unified login, explicit no role-selector instruction |
| Health services | Fixed 8-item list in the prompt text | Admin-configurable screen added (`health_services.php`), prompt pulls from the `health_services` table instead of hardcoding |
| Reports nav | Described loosely | Mapped explicitly to all 10 existing backend files by exact path |
| Display board | Generic TV screen description | Tied to the `display_board_token` system setting and the public token-check entry point |
| Void timeout | Mentioned as a feature | Tied to a specific UI element — the countdown timer on the staff dashboard — so it's not just decorative |
| Schema | Not included | Full table and column reference included so Codex queries real data instead of inventing fields |
| New screen | — | `views/admin/health_services.php` added, since this is new since your last prompt |
| Scope guardrails | Implicit | Explicit "what not to do" section to prevent Codex from inventing a multi-barangay switcher or other scope creep |

---

## Notes Before You Paste This Into Codex

This prompt assumes Codex has access to your existing repository — the file
paths referenced (`modules/auth/login.php`, `config/config.php`, and so on)
are the same files generated by `setup_smartqms_v3.py`. If Codex is starting
from a blank workspace instead of your actual repo, paste your
`smartqms_final.sql` schema and the relevant `config/config.php` and
`config/database.php` content alongside this prompt so it has the real
column names rather than guessing.
