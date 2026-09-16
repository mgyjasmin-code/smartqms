# SmartQMS

Web-Based Smart Queue Management System with ML-assisted waiting time prediction for Barangay Health Centers.

SmartQMS lets clients register, join a queue, receive a QR ticket, track queue status, and get near-turn notifications. Staff manage service windows and ticket flow. Admin users configure services, staff, windows, reports, display-board access, email, and SMS settings.

## Tech Stack

| Layer | Technology |
| --- | --- |
| Frontend | PHP views, Bootstrap 5, vanilla JavaScript |
| Backend | PHP 8.x |
| Database | MySQL/MariaDB through XAMPP |
| ML API | Python 3.10+, Flask, scikit-learn |
| Email | PHPMailer SMTP or local log mode |
| SMS | Semaphore API or simulated log mode |
| QR | `endroid/qr-code` via Composer |

## Quick Start

### 1. Requirements

- XAMPP with Apache, PHP 8.x, and MySQL/MariaDB
- Composer
- Python 3.10+ for the optional ML service
- A modern browser

### 2. Install PHP Dependencies

From the project root:

```bash
cd C:/xampp/htdocs/smartqms
composer install
```

The `vendor/` directory is generated locally and should not be committed.

### 3. Import the Database

Fresh install:

1. Start Apache and MySQL in XAMPP.
2. Open `http://localhost/phpmyadmin`.
3. Create or select the `smartqms` database.
4. Import `database/smartqms_final_v4.sql`.

Existing local install:

1. Back up your current `smartqms` database.
2. Import/run `database/upgrade_stabilization_2026_07_10.sql` against the existing database.

### 4. Configure Local Database Connection

Create or edit `config/database.php`:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'smartqms');
define('DB_CHARSET', 'utf8mb4');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset(DB_CHARSET);

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed.']));
}
?>
```

`config/database.php` is intentionally ignored because every team member may have different local credentials.

### 5. Local Admin Login

For local team/demo machines, the seed database includes this administrator account:

```text
Production installations do not include a default administrator. Create the
first administrator with `php scripts/bootstrap_admin.php`; credentials are
provided through deployment environment variables and are never committed.
```

These credentials are for local development and demos only. Change the admin password before using this system on any shared, public, or production-like machine.

### 6. Open the App

```text
http://localhost/smartqms
```

Use the local admin login above, then configure:

- Health center name, address, and contact details
- Health services
- Queue hours and void timeout
- Display-board token
- Staff accounts
- Service windows
- Optional email and SMS settings

## Email OTP Modes

SmartQMS sends OTP codes through the email job queue.

Default mode is local log mode. OTP emails are written to:

```text
storage/logs/email_outbox.log
```

For local development only, you may create `config/email.local.php` from
`config/email.example.php`. Production ignores local mail configuration and
requires the equivalent values from the deployment environment or secret
manager:

```php
define('EMAIL_DELIVERY_MODE', 'smtp');
define('EMAIL_SMTP_USERNAME', 'your-email@gmail.com');
define('EMAIL_SMTP_PASSWORD', 'your-gmail-app-password');
```

`config/email.local.php` is ignored by Git and must not be committed or copied
into a production artifact.

## SMS Modes

SMS is controlled in Admin Settings.

- `sms_enabled = 0`: SMS attempts are simulated and logged to `sms_logs`.
- `sms_enabled = 1` with a Semaphore API key: SMS messages are sent through Semaphore and still logged.

Near-turn alerts are generated when a waiting ticket has 2 or fewer tickets ahead. The system inserts a browser notification and sends/logs SMS when a phone number is available.

## Display Board

The display board is public only through a token-protected URL:

```text
http://localhost/smartqms/display.php?token=YOUR_TOKEN
```

Set `display_board_token` in Admin Settings before using the TV display. The live queue JSON endpoint rejects untokened anonymous requests.

## ML Waiting Time Prediction

The PHP app works even if the Flask ML service is not running. When the model API is unavailable, SmartQMS uses a PHP fallback estimate based on queue length, active windows, and average service duration.

Optional ML setup:

```bash
cd C:/xampp/htdocs/smartqms/ml
pip install -r requirements.txt
python generate_dataset.py
python compare_algorithms.py
python app.py
```

The ML scripts use one canonical dataset contract and write the reproducible
development dataset to `ml/dataset/synthetic_queue_data.csv`. The older
five-column `queue_data.csv` is not compatible with the application model and
is intentionally not used for training.

Run the validator and Flask contract tests with:

```bash
cd C:/xampp/htdocs/smartqms/ml
python -m unittest test_app.py
```

The Flask API runs at:

```text
http://localhost:5000
```

Report 8, ML Accuracy, is populated by `ml/compare_algorithms.py`.

## Reports

Admin Reports are database-backed and support date filters plus CSV export:

1. Queue Summary
2. Predicted vs Actual Wait
3. Peak Hour Analysis
4. Service Counter Performance
5. Customer Turnaround Time
6. No-Show Report
7. Staff Productivity
8. ML Accuracy Report
9. Daily and Monthly Stats
10. Satisfaction Report

If a report has no matching rows, SmartQMS shows an explicit empty state instead of sample or fake data.

## Demo Checklist

Use this flow to verify a local machine:

1. Import `database/smartqms_final_v4.sql`.
2. Run `composer install`.
3. Open `http://localhost/smartqms`.
4. Create the first administrator with `php scripts/bootstrap_admin.php`, then
   sign in with the deployment-provided credentials and rotate the password.
5. Add one staff account.
6. Create or assign a service window.
7. Set a display-board token.
8. Register a client and verify OTP from `storage/logs/email_outbox.log`.
9. Join the queue and confirm a QR ticket is generated.
10. Log in as staff, open the window, call next, complete, and submit feedback.
11. Check Admin Dashboard, Reports, CSV export, and Display Board.

## Verification Commands

The dependency-free PHP characterization suite currently registers 71 tests
and uses only an isolated database whose name ends in `_test` or `_testing`.
Copy `tests/config.example.php` to the ignored `tests/config.local.php`,
configure that database, and run:

```powershell
php tests/php/run.php
```

```bash
php -l index.php
composer validate --no-check-publish
```

For a full PHP syntax sweep in PowerShell:

```powershell
Get-ChildItem -Recurse -Filter *.php -File |
  Where-Object { $_.FullName -notmatch '\\vendor\\' } |
  ForEach-Object { php -l $_.FullName }
```

JavaScript syntax checks:

```powershell
node --check assets/js/main.js
node --check assets/js/client.js
node --check assets/js/staff.js
node --check assets/js/admin.js
node --check assets/js/display.js
node tests/js/run.js
```

## Refactored Architecture

- `config/config.php` initializes runtime/session settings, security headers,
  constants, and the shared compatibility bootstrap.
- `modules/shared/` owns cross-feature HTTP, security, validation, activity,
  settings, asset, and schema-compatibility procedures.
- Feature folders own their queries and transactional workflows: `auth`,
  `queue`, `service_window`, `settings`, `notifications`, and `reports`.
- PHP entry points remain thin procedural controllers; existing routes and
  public function names remain compatible.
- `assets/js` uses named event lifecycle initializers; `assets/css` retains the
  existing role-specific styles with consolidated theme tokens.

See the [architecture guide](docs/ARCHITECTURE.md) for bootstrap behavior,
module ownership, sessions, queue/staff state transitions, JavaScript and CSS
extension points, test safety, and ML restoration.

See the
[consolidated incremental refactoring plan](docs/SMARTQMS_INCREMENTAL_REFACTORING_PLAN.md)
for batch ownership, compatibility constraints, verification history, and
deferred follow-up work.

## Important Security Notes

- Do not commit `config/database.php`, `config/email.local.php`, logs, sessions, datasets, model files, generated QR images, or `vendor/`.
- Change the local demo admin password before any non-local use.
- Keep the display-board token random and private.
- Keep email and SMS API credentials out of the repository.
