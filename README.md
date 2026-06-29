# SmartQMS v3

**Web-Based Smart Queue Management System**
with Machine Learning-Based Waiting Time Prediction Using Random Forest Algorithm

Built for Barangay Health Centers in the Philippines.

---

## What This System Does

SmartQMS replaces physical paper-based queuing at barangay health centers with a
web-based system that:

- Lets clients join the queue online and receive a digital ticket with QR code
- Predicts how long each client will wait using a **Random Forest ML model**
- Gives priority to **Senior Citizens and PWDs** automatically
- Sends **SMS and browser notifications** when a client's turn is near
- Shows a **real-time display board** on a TV in the waiting area (no login needed)
- Gives the admin a **full analytics dashboard** with 10 report types
- Lets admin **configure health services** per barangay installation

---

## Tech Stack

| Layer      | Technology                                |
|------------|-------------------------------------------|
| Frontend   | HTML, Bootstrap 5, JavaScript             |
| Backend    | PHP 8.x                                   |
| Database   | MySQL 8.x (via XAMPP / phpMyAdmin)        |
| ML Model   | Python 3.10+, scikit-learn, Flask         |
| SMS API    | Semaphore (free tier — semaphore.co)      |
| Fonts      | Poppins + Inter (Google Fonts)            |

---

## User Roles

| Role          | How account is created          | Login           |
|---------------|---------------------------------|-----------------|
| Client        | Self-registration (web form)    | Phone + Password|
| Service Staff | Admin creates account manually  | Phone + Password|
| Administrator | Pre-seeded in database          | Phone + Password|

All three roles use the **same login page** (`index.php`).
The system detects the role from the database and redirects automatically.

---

## Quick Start

### Requirements

- XAMPP (PHP 8.x + MySQL 8.x + Apache)
- Python 3.10 or higher
- Git (optional, for version control)
- A modern web browser

---

### Step 1 — Run the Setup Script

Open a terminal and navigate to your XAMPP htdocs folder:

```bash
cd C:/xampp/htdocs
```

Run the setup script:

```bash
python setup_smartqms_v3.py
```

This creates all **20 folders** and **67 files** automatically.

---

### Step 2 — Set Up the Database

1. Start **XAMPP** — make sure Apache and MySQL are running
2. Open **phpMyAdmin**: `http://localhost/phpmyadmin`
3. Click **Import** tab
4. Select the file: `smartqms/database/smartqms_final.sql`
5. Click **Go**

This creates the `smartqms` database with all 12 tables, 3 views, and default data.

---

### Step 3 — Configure Database Connection

Open `smartqms/config/database.php` and update your MySQL password:

```php
define('DB_PASS', '');  // Set your MySQL root password here
```

> **Important:** `database.php` is excluded from GitHub via `.gitignore`
> because it contains your password. Each team member sets up their own copy.

---

### Step 4 — Set Up the ML Environment

Open a **new terminal** (keep it open while developing):

```bash
cd C:/xampp/htdocs/smartqms/ml
pip install -r requirements.txt
```

Add your training dataset to `ml/dataset/`:

```
ml/dataset/kaggle_queue_data.csv    <- Kaggle healthcare queue dataset
```

> See the **Dataset** section below for which Kaggle dataset to use.

Train the model and compare algorithms:

```bash
python compare_algorithms.py
```

This tests 4 algorithms (Linear Regression, Decision Tree, Gradient Boosting,
Random Forest), saves results to the database for Report 8, and saves the
best model as `model.pkl`.

Start the Flask prediction API:

```bash
python app.py
```

Keep this terminal open. The API runs at `http://localhost:5000`.

---

### Step 5 — Open the System

```
http://localhost/smartqms
```

**Default admin login:**

```
Phone    : 09000000000
Password : Admin123!
```

> Change the default admin password after first login. To set a new admin password, generate a bcrypt hash in PHP:
> ```php
> echo password_hash('YourPassword123', PASSWORD_BCRYPT);
> ```
> Then update the `password_hash` column for the admin user in phpMyAdmin.

---

### Step 6 — Configure for Your Barangay

Log in as admin and complete first-time setup:

1. Go to **System Settings > Health Center Info** — set barangay name and address
2. Go to **System Settings > Health Services** — enable/disable services for your barangay
3. Go to **System Settings > Queue Config** — set void timeout, queue hours, max queue
4. Go to **System Settings > SMS** — add Semaphore API key (optional)
5. Go to **Service Windows** — create windows and assign staff
6. Go to **User Management > Add Staff** — create staff accounts

---

## Display Board (TV Screen)

The queue display board is a **separate public URL** — no login required.
Staff opens it on a TV browser and leaves it running.

**URL format:**
```
http://localhost/smartqms/display.php?token=YOUR_TOKEN
```

The token is set in **Admin > System Settings > Display Board**.
Default token: `changeme_random_token` — **change this before going live.**

---

## Dataset

The ML model is trained on queue data with these columns:

| Column                | Description                              |
|-----------------------|------------------------------------------|
| `queue_length`        | Number of tickets ahead at issue time    |
| `hour_of_day`         | Hour when ticket was issued (0-23)       |
| `day_of_week`         | Day (0=Sun, 1=Mon ... 6=Sat)             |
| `service_type_encoded`| Numeric code of the health service (1-8) |
| `client_type_encoded` | 0=regular, 1=senior, 2=pwd              |
| `active_windows`      | Number of open service windows           |
| `avg_service_time`    | Rolling average service time in minutes  |
| `actual_wait_minutes` | Target variable (what the model predicts)|

**Recommended Kaggle datasets:**

1. Search: `"hospital queue waiting time"` on kaggle.com
2. Search: `"healthcare patient wait time prediction"`
3. Alternative: `"bank teller queue simulation dataset"`

If no dataset is available, generate synthetic data:

```bash
cd ml/
python generate_dataset.py
```

This creates `dataset/synthetic_queue_data.csv` with 5,000 realistic rows
based on barangay health center patterns (peak hours 8-11AM, 8 service types,
15% priority clients).

---

## Folder Structure

```
smartqms/
|-- config/
|   |-- database.php           <- DB connection (NOT in GitHub)
|   `-- config.php             <- App constants, helpers, session
|-- modules/
|   |-- auth/
|   |   |-- login.php          <- Unified login, auto-detects role
|   |   |-- logout.php
|   |   |-- register.php       <- Client self-registration
|   |   `-- verify_otp.php     <- SMS OTP verification
|   |-- queue/
|   |   |-- join_queue.php     <- One-active-ticket check + ML call
|   |   |-- ticket.php         <- Ticket data fetcher
|   |   |-- status.php         <- Live queue JSON (AJAX endpoint)
|   |   |-- get_prediction.php <- PHP-to-Flask ML proxy
|   |   |-- qr_generate.php    <- QR code image generator
|   |   `-- void_checker.php   <- Auto-void expired tickets
|   |-- service_window/
|   |   |-- call_next.php      <- Priority-ordered queue call
|   |   |-- complete_ticket.php<- Logs actual wait + service time
|   |   |-- window_status.php  <- Open/close window
|   |   `-- skip_ticket.php
|   |-- notifications/
|   |   |-- send_alert.php     <- Triggers at 2 tickets ahead
|   |   |-- get_notifications.php <- AJAX unread notifications
|   |   `-- sms_sender.php     <- Semaphore API wrapper
|   |-- feedback/
|   |   `-- submit_feedback.php<- Post-service 1-5 star rating
|   |-- reports/
|   |   |-- queue_summary.php          <- Report 01
|   |   |-- predicted_vs_actual.php    <- Report 02
|   |   |-- peak_hour.php              <- Report 03
|   |   |-- counter_performance.php    <- Report 04
|   |   |-- turnaround_time.php        <- Report 05
|   |   |-- no_show.php                <- Report 06
|   |   |-- staff_productivity.php     <- Report 07
|   |   |-- ml_accuracy.php            <- Report 08
|   |   |-- daily_monthly_stats.php    <- Report 09
|   |   |-- satisfaction.php           <- Report 10
|   |   `-- export_csv.php             <- Generic CSV export
|   `-- settings/
|       |-- system_settings.php        <- Read/write system_settings table
|       `-- health_services_mgmt.php   <- Manage service dropdown
|-- ml/
|   |-- train_model.py         <- Random Forest training
|   |-- compare_algorithms.py  <- LR vs DT vs GBM vs RF comparison
|   |-- generate_dataset.py    <- Synthetic data generator (backup)
|   |-- app.py                 <- Flask API server (/predict)
|   |-- predict.py             <- Standalone test script
|   |-- model.pkl              <- Saved model (NOT in GitHub)
|   |-- requirements.txt
|   `-- dataset/
|       |-- kaggle_queue_data.csv
|       `-- queue_logs.csv     <- System-generated data (grows over time)
|-- views/
|   |-- client/
|   |   |-- index.php          <- Dashboard (no ticket / active ticket)
|   |   |-- ticket.php         <- Ticket with QR code + wait time
|   |   |-- queue_status.php   <- Live status page
|   |   `-- feedback.php       <- Post-service star rating
|   |-- staff/
|   |   |-- dashboard.php      <- Current ticket + action buttons
|   |   |-- window.php         <- Queue list with priority rows
|   |   `-- activity_log.php   <- Timeline of today's actions
|   |-- admin/
|   |   |-- dashboard.php      <- KPI cards + charts + interpretation
|   |   |-- users.php          <- Manage all accounts
|   |   |-- add_staff.php      <- Create staff accounts
|   |   |-- windows.php        <- Configure service windows
|   |   |-- reports.php        <- All 10 reports with sub-menu
|   |   |-- ml_logs.php        <- Predicted vs actual logs
|   |   |-- settings.php       <- System settings (5 sections)
|   |   `-- activity_log.php   <- System-wide activity log
|   `-- display/
|       `-- board.php          <- TV display board view
|-- assets/
|   |-- css/
|   |   |-- style.css          <- Main stylesheet
|   |   `-- display.css        <- Display board dark theme
|   |-- js/
|   |   |-- main.js            <- Polling, notifications, validation
|   |   `-- display.js         <- Display board auto-refresh
|   |-- img/                   <- Logo and image assets
|   `-- qr/                    <- Generated QR code images
|-- database/
|   |-- smartqms_final.sql     <- Full schema (12 tables + 3 views)
|   `-- README.txt
|-- index.php                  <- Entry point + unified login page
|-- display.php                <- Public display board (token-protected)
|-- .htaccess
|-- .gitignore
`-- README.md
```

---

## Database Tables

| Table                | Purpose                                              |
|----------------------|------------------------------------------------------|
| `users`              | All accounts — clients, staff, admin (one table)     |
| `staff`              | Staff-specific info — department, shift, added_by    |
| `health_services`    | Admin-configurable service dropdown per barangay     |
| `service_windows`    | Service counters — linked to staff and service type  |
| `queue_tickets`      | Core entity — every queue transaction                |
| `wait_time_logs`     | ML training data — predicted vs actual per ticket    |
| `ml_comparison_logs` | Algorithm comparison results (feeds Report 8)        |
| `system_settings`    | All admin-configurable key-value settings            |
| `notifications`      | Browser and SMS notifications per ticket             |
| `sms_logs`           | All SMS attempts (sent, failed, or simulated)        |
| `feedback`           | Post-service ratings 1-5 stars (feeds Report 10)     |
| `activity_logs`      | Every significant user action with timestamp         |

---

## ML Model

**Algorithm:** Random Forest Regressor (scikit-learn)

**Why Random Forest over Linear Regression:**

- Queue wait time is non-linear — multiple factors interact simultaneously
- Random Forest handles non-linear relationships naturally
- Resistant to overfitting through bagging (multiple trees averaged)
- Proven lowest RMSE in healthcare queue studies (6.69 min vs baseline)
- Provides feature importance scores for reporting

**Features used for prediction:**

```
queue_length, hour_of_day, day_of_week, service_type_encoded,
client_type_encoded, active_windows, avg_service_time
```

**Evaluation metrics (Chapter 4):**

- MAE — Mean Absolute Error (minutes)
- RMSE — Root Mean Square Error (minutes)
- R2 — Coefficient of Determination (0 to 1)
- MAPE — Mean Absolute Percentage Error (%)
- K-Fold CV — 10-fold Cross-Validation score

---

## Reports

All 10 reports are in Admin > Reports (expandable sidebar sub-menu):

| # | Report Name                        | Key Data Source           |
|---|------------------------------------|---------------------------|
| 1 | Queue Summary                      | `queue_tickets`           |
| 2 | Predicted vs Actual Wait Time      | `wait_time_logs`          |
| 3 | Peak Hour Analysis                 | `queue_tickets.issued_at` |
| 4 | Service Counter Performance        | `wait_time_logs + windows`|
| 5 | Customer Turnaround Time           | `queue_tickets`           |
| 6 | No-Show Report                     | `queue_tickets (voided)`  |
| 7 | Staff Productivity                 | `wait_time_logs + staff`  |
| 8 | Machine Learning Accuracy          | `ml_comparison_logs`      |
| 9 | Daily and Monthly Statistics       | `queue_tickets (grouped)` |
|10 | Customer Satisfaction              | `feedback`                |

All reports support date range filters and CSV export.

---

## Git Workflow for Team

Each member works on their own branch. Only the leader merges to `main`.

**Branch assignments:**

```
main                  <- protected, leader merges here every Friday
dev/leader-frontend   <- you (leader + frontend)
dev/member2-frontend  <- member 2 (frontend support)
dev/member3-backend   <- member 3 (backend + DB + ML bridge)
dev/member4-backend   <- member 4 (backend + reports, async tasks)
```

**Daily commands:**

```bash
# Start of day
git pull origin main

# After finishing a task
git add .
git commit -m "feat: build login page UI with Bootstrap"
git push origin dev/your-branch

# Leader only -- every Friday after code review
git checkout main
git merge dev/member3-backend
git push origin main
git checkout dev/leader-frontend
```

---

## SMS Setup (Semaphore)

1. Sign up at **semaphore.co** (free credits on signup)
2. Get your API key from the dashboard
3. Log in to SmartQMS as admin
4. Go to **System Settings > SMS Configuration**
5. Enter your API key and sender name (e.g. `BHCQMS`)
6. Click **Send Test SMS** to verify

If no API key is configured, the system logs all SMS as `simulated`
in the `sms_logs` table. Show this table to your panel as proof
the SMS feature is architecturally complete.

---

## Priority Queue Rules

Senior Citizens and PWDs always get served before regular clients:

```sql
-- Staff calls next ticket using this order
SELECT * FROM queue_tickets
WHERE status = 'waiting' AND service_id = ?
ORDER BY priority_level DESC, issued_at ASC
LIMIT 1
```

`priority_level = 1` for Senior and PWD, `priority_level = 0` for Regular.
Among same priority level, earlier arrival is served first (FIFO).

---

## Void Ticket System

When staff clicks **Call Next**, the ticket's `called_at` timestamp is saved.
JavaScript polls `void_checker.php` every 30 seconds.

If `NOW() - called_at >= void_timeout_minutes`:

- Ticket status is set to `voided`
- `voided_reason` is set to `"Client did not appear within timeout"`
- Entry added to `notifications` and `activity_logs`
- Staff dashboard updates automatically

Admin sets the timeout in **System Settings > Queue Configuration** (default: 10 minutes).

---

## Environment Notes

- `config/database.php` — excluded from GitHub, each member creates their own
- `ml/model.pkl` — excluded from GitHub, regenerated by `compare_algorithms.py`
- `assets/qr/*.png` — excluded from GitHub, generated at runtime
- Run `python app.py` in a separate terminal while developing
- Display board URL requires the token from System Settings

---

## Panelist Revisions Addressed

| Revision                          | Status   | Implementation                              |
|-----------------------------------|----------|---------------------------------------------|
| Reference number and QR code      | Done     | `queue_tickets.reference_number` + QR file  |
| SMS authentication (OTP)          | Done     | `users.otp_code` + `verify_otp.php`         |
| Voiding late appointee            | Done     | Admin-configurable `void_timeout_minutes`   |
| Dashboard with interpretation     | Done     | Auto-generated text below each chart        |
| Training data requirement         | Done     | Kaggle dataset + Chapter 3 documentation    |
| Algorithm comparison              | Done     | `compare_algorithms.py` + Report 8         |

---

*SmartQMS v3 — Capstone Project*
*Web-Based Smart Queue Management System with ML-Based Waiting Time Prediction*
*Using Random Forest Algorithm | Barangay Health Center*
