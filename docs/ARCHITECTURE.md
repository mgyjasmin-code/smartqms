# SmartQMS Architecture Guide

## Purpose and compatibility boundary

SmartQMS is a procedural PHP application with server-rendered views, focused
JSON endpoints, vanilla JavaScript, three CSS entry points, a MySQL/MariaDB
database, and an optional Flask prediction service.

The architecture is modular without introducing an application OOP layer.
Public routes, HTTP methods, request fields, redirects, JSON keys, session
keys, database structures, role permissions, and UI workflows are compatibility
contracts. Before changing one of those contracts, update the characterization
tests and obtain explicit approval for the behavior change.

The canonical endpoint inventory is
`tests/contracts/http_contracts.php`. The incremental refactoring history and
deferred work are in `docs/SMARTQMS_INCREMENTAL_REFACTORING_PLAN.md`.

## Request bootstrap and configuration

Production PHP entry points load `config/config.php`.

`config/config.php` owns:

- runtime, session, and log directory constants;
- the custom PHP session directory;
- strict, cookie-only session initialization;
- session cookie parameters;
- security headers;
- application, URL, role, OTP, login-throttle, ML, QR, and ticket constants;
- the application timezone; and
- the compatibility helper aggregator.

`config/helpers.php` is an include aggregator. It loads the shared procedural
modules and the queue/window helpers required by existing entry points. New
business logic must not be added to either configuration file.

Local database credentials belong in the ignored `config/database.php`. Email
delivery credentials belong in the ignored `config/email.local.php`. Do not
load production database credentials from tests.

The request path is:

1. An entry point loads `config/config.php` and `config/database.php`.
2. The entry point enforces the HTTP method, login role, and CSRF contract.
3. It normalizes and validates request fields.
4. It calls focused query or workflow procedures with an explicit `mysqli`
   connection.
5. It returns the existing redirect, HTML view, or JSON envelope.

## Shared procedural modules

| Module | Responsibility |
| --- | --- |
| `modules/shared/http.php` | JSON responses, POST enforcement, redirects, flash-form feedback, request values, and view helpers |
| `modules/shared/security.php` | Role enforcement, CSRF, IP lookup, authentication throttling, and throttle messages |
| `modules/shared/validation.php` | Shared scalar, email, phone, client-type, and status validation |
| `modules/shared/activity.php` | Activity-log writes |
| `modules/shared/settings.php` | Read-only compatibility access to runtime system settings |
| `modules/shared/assets.php` | Action and cache-busted asset URLs |
| `modules/shared/schema_compat.php` | Isolated request-time compatibility guards for older deployed schemas |

Keep these modules dependency-light and procedural. A shared procedure should
have one owner and accept dependencies such as `mysqli` explicitly.

## Authentication and session lifecycle

Authentication is split by responsibility:

| Module | Responsibility |
| --- | --- |
| `modules/auth/auth_queries.php` | User, client, staff, OTP, password, verification, and login-persistence queries |
| `modules/auth/otp_service.php` | OTP generation, hashing, persistence, verification, resend cooldown, and queued email coordination |
| `modules/auth/auth_session.php` | OTP flow state, authenticated session creation, session regeneration, and logout destruction |
| `modules/auth/auth_redirects.php` | Role destinations and OTP form destinations |
| `modules/auth/auth_utils.php` | Compatibility aggregation plus credential/password primitives |
| Auth handler files | Method/CSRF checks, request validation, orchestration, feedback, and redirects |

Authenticated session keys:

| Key | Meaning |
| --- | --- |
| `user_id` | Authenticated user identifier |
| `role` | `client`, `staff`, or `admin` |
| `name` | Display name |
| `email` | Authenticated email |
| `phone` | Authenticated phone number |
| `staff_id` | Staff profile identifier; set only for staff users |
| `csrf_token` | Per-session CSRF token |
| `form_feedback` | One-time, form-keyed field errors, old input, and form error |

Transient OTP/reset keys:

| Key | Meaning |
| --- | --- |
| `otp_flow` | `register`, `login`, or `reset` |
| `otp_user_id` | User currently being verified |
| `otp_started_at` | OTP flow start timestamp |
| `otp_last_sent_at` | Resend-cooldown timestamp |
| `pending_user_id` | Registration verification user |
| `pending_login_user_id` | Login verification user |
| `reset_user_id` | Password-reset verification user |
| `reset_verified_user_id` | User allowed to submit the new password |

Successful login regenerates the session identifier. `clearOtpSession()` clears
all transient OTP/reset keys. Logout clears and destroys the authenticated
session.

Role destinations remain:

- client: `views/client/index.php`;
- staff: `views/staff/dashboard.php`;
- admin: `views/admin/dashboard.php`.

## Queue and ticket lifecycle

`modules/queue/queue_queries.php` owns queue reads, priority helpers, active
ticket lookup, named issuance locks, and reference/ticket-number allocation.
`modules/queue/prediction.php` owns prediction features and fallback behavior.
`modules/queue/ticket_service.php` owns transactional ticket creation.
`modules/queue/status_queries.php` owns live client/display read models.

Ticket statuses and transitions:

```text
join queue
    |
    v
 waiting --Call Next--> serving --Complete--> completed
                            |
                            +--Skip-------> skipped
                            |
                            +--Timeout----> voided
```

Only `waiting` and `serving` are active. Terminal tickets are `completed`,
`skipped`, and `voided`.

Ticket creation:

1. Normalize the client type and validate service eligibility.
2. Build a prediction snapshot.
3. Lock the client row and recheck for an active ticket.
4. Acquire the annual named issuance lock.
5. Allocate the reference and daily ticket number.
6. Insert the waiting ticket.
7. Generate and store the QR path.
8. Store the prediction feature/log row.
9. Write the activity log.
10. Commit, or roll back and remove only a newly created QR file.

The current reference format is `BHC-YYYY-NNNN`; the current daily display
number format is `A-NNN`.

## Priority behavior

Client types are `regular`, `senior`, and `pwd`.

- Senior and PWD clients receive priority level `1`.
- Regular clients receive priority level `0`.
- Priority-only services reject regular clients.
- Call Next orders waiting tickets by priority descending and issue time
  ascending.
- Tickets with the same priority remain FIFO.
- `peopleAhead()` counts higher-priority tickets plus earlier tickets at the
  same priority for the same service.

Priority behavior is a public workflow contract. Add or change a client type
only with coordinated database, PHP, ML, view, JavaScript, report, and test
changes.

## Prediction and fallback boundary

The canonical feature order is:

1. `queue_length`
2. `hour_of_day`
3. `day_of_week`
4. `service_type_encoded`
5. `client_type_encoded`
6. `active_windows`
7. `avg_service_time`

`queuePredictionSnapshot()` also returns the service row, priority level, and
fallback estimate. The PHP application sends only the feature object to
`ML_API_URL`.

The ML boundary is optional and fail-soft:

- cURL unavailable;
- connection or request timeout;
- non-200 response;
- malformed JSON;
- missing or nonnumeric prediction; or
- a prediction outside 0 to 480 minutes

all return `null` and select the PHP fallback. Core ticket issuance must not
depend on the Flask process.

The fallback is based on queue length divided by active windows, multiplied by
the recent average service duration. It has a two-minute minimum and applies
the existing priority adjustment.

## Notifications, email, and SMS

| Module | Responsibility |
| --- | --- |
| `modules/notifications/notification_queries.php` | Unread notification lookup and read consumption |
| `modules/notifications/send_alert.php` | Near-turn candidate selection, browser notification creation, and optional SMS orchestration |
| `modules/notifications/email_sender.php` | Email queueing, cancellation, local outbox, SMTP dispatch, and failure logging |
| `modules/notifications/sms_sender.php` | Semaphore/simulation boundary and `sms_logs` persistence |
| `modules/notifications/get_notifications.php` | Client JSON controller |
| `modules/notifications/dispatch_email_jobs.php` | Email-job dispatch entry point |

Near-turn alerts use the existing people-ahead semantics and are generated at
two or fewer tickets ahead. Email, SMS, and ML failures remain optional where
the current workflow treats them as optional; do not make the queue
transaction depend on an external provider.

## Staff state transitions

`modules/service_window/window_queries.php` owns staff/window lookup, locked
window/ticket reads, waiting-order reads, timeout math, presentation labels,
and window status writes.

`modules/service_window/ticket_actions.php` owns transactional mutations:

- Call Next locks the window and next waiting ticket, changes the ticket to
  `serving`, timestamps it, assigns the window, and marks the window `busy`.
- Complete locks the serving ticket, changes it to `completed`, writes actual
  wait/service metrics, creates the feedback notification, and reopens the
  window.
- Skip changes the serving ticket to `skipped`, records the reason/timestamp,
  and reopens the window.
- Automatic timeout changes eligible serving tickets to `voided`, creates
  browser notifications, and reopens the window when appropriate.
- Manual window status accepts only `open`, `busy`, or `closed`.

The handler files in `modules/service_window/` own authorization, CSRF/request
validation, HTTP status mapping, and the current JSON envelope.

## Administrator modules

| Module | Responsibility |
| --- | --- |
| `modules/admin/staff_accounts.php` | Staff-account input, validation, duplicate checks, transactional creation, and listing |
| `modules/admin/window_admin.php` | Window form input, validation, persistence, assignment lists, and view projection |
| `modules/admin/users.php` | User listing and activation/deactivation rules |
| `modules/settings/service_catalog.php` | Health-service validation, reads, writes, activation, and delete-or-deactivate behavior |
| `modules/settings/health_services_mgmt.php` | Compatibility JSON controller |

HTML views remain in `views/admin/`. The administrator Activity Logs and
Settings pages, together with the settings JSON write endpoint, were removed
after explicit approval on July 27, 2026. The underlying `activity_logs` and
`system_settings` tables remain compatibility dependencies for operational
auditing, notifications, queue behavior, and display-board authorization.

## Report subsystem

Report responsibilities are separated as follows:

| Module | Responsibility |
| --- | --- |
| `modules/reports/report_core.php` | Definitions, aliases, key normalization, inclusive date ranges, prepared fetches, metrics, columns, and empty reports |
| `modules/reports/builders/*.php` | One builder for each of the ten report keys |
| `modules/reports/report_utils.php` | Compatibility aggregator and report dispatcher |
| `modules/reports/report_chart.php` | Chart configuration returned to views |
| `modules/reports/report_http.php` | Role enforcement and JSON exception mapping |
| `modules/reports/export_csv.php` | CSV export and spreadsheet-formula injection guard |
| Existing report wrapper files | Stable public URLs that delegate to shared report handling |

Every report returns the established top-level structure. Chart output includes
`type`, `labels`, `datasets`, `unit`, and `summary`; table output includes
`columns` and `rows`. Unknown reports and invalid dates retain their current
exception behavior.

## JavaScript ownership

All browser code is event-driven and guarded against duplicate initialization.
Timers and in-flight work are cleaned up during page lifecycle transitions.

| File | Ownership |
| --- | --- |
| `assets/js/main.js` | Shared CSRF access, escaping, form validation/submission state, password toggles, OTP resend, logout modal, and shared initialization |
| `assets/js/client.js` | Service/prediction state, queue refresh, notifications, feedback, and ticket printing |
| `assets/js/staff.js` | Staff actions, status feedback, window status, void timers, and staff theme |
| `assets/js/admin.js` | Admin theme, charts, sidebar/submenus, user menu, pagination, confirmations, logout modal, and printing |
| `assets/js/display.js` | Display clock, queue polling, safe rendering, and timer cleanup |

Views configure scripts through `data-*` attributes. Do not add inline
handlers or restore removed global functions. When adding a feature:

1. Put its initializer in the role-owned script.
2. Root it at a specific `data-*` marker.
3. Use a per-root initialization guard.
4. Read URLs and state from data attributes rather than hardcoding page paths.
5. Preserve CSRF, `credentials: 'same-origin'`, request-in-flight guards, and
   cleanup behavior.

Frequently used contracts include `data-client-root`, `data-staff-shell`,
`data-admin-root`, `data-display-root`, `data-prediction-root`,
`data-queue-status-root`, `data-staff-action`, `data-status-url`,
`data-feedback-form`, `data-loading-text`, and the role theme attributes.

## CSS organization and token ownership

There are exactly three stylesheet entry points:

| File | Ownership |
| --- | --- |
| `assets/css/style.css` | Authentication, public/client, shared, and staff components, states, responsive behavior, reduced motion, and ticket printing |
| `assets/css/admin.css` | Admin tokens, light/dark themes, shell, components, reports, responsive rules, reduced motion, and report printing |
| `assets/css/display.css` | Display-board tokens, layout, window cards, status colors, ticker, and update text |

Do not introduce `@import`, a preprocessor, a CSS framework replacement, or a
new stylesheet URL without approval. Add shared/client/staff tokens to
`style.css`, admin theme tokens to `admin.css`, and display-only tokens to
`display.css`. Keep media, theme, state, focus, reduced-motion, and print
contexts separate when their cascade semantics differ.

## Database setup and compatibility

For a fresh database, import `database/smartqms_final_v4.sql`. For an existing
local installation, back it up and apply
`database/upgrade_stabilization_2026_07_10.sql`.

The application depends on the current table, column, unique-index, enum, and
timestamp contracts characterized by the integration suite. Database access
should use prepared statements and an explicitly passed `mysqli` connection.

`modules/shared/schema_compat.php` contains request-time compatibility behavior
for older installations. It is intentionally isolated but retained. Do not
remove it until every deployed database is inventoried and upgraded through an
approved versioned migration.

## Test database safety

Tests load credentials only from ignored `tests/config.local.php` or
`SMARTQMS_TEST_DB_*` environment variables. They never load
`config/database.php`.

The test harness rejects database names that do not end in `_test` or
`_testing`, verifies `SELECT DATABASE()` after connecting, prefixes fixtures
with `characterization_`, and rolls back data-changing scenarios. Tests do not
send email/SMS, call the ML service, or retain generated QR files.

Run the PHP suite:

```powershell
php tests/php/run.php
```

Run JavaScript runtime contracts:

```powershell
node tests/js/run.js
```

Concurrency checks are included by the PHP test runner and can also be run
directly:

```powershell
php tests/php/verify_queue_concurrency.php
php tests/php/verify_service_window_concurrency.php
```

## ML environment restoration

The expected Python packages are pinned in `ml/requirements.txt`. Restore the
environment without changing those pins:

```powershell
cd C:\xampp\htdocs\smartqms\ml
python -m venv venv
.\venv\Scripts\python.exe -m pip install -r requirements.txt
.\venv\Scripts\python.exe -m unittest test_app.py
```

Then generate/train and start the optional service:

```powershell
.\venv\Scripts\python.exe generate_dataset.py
.\venv\Scripts\python.exe compare_algorithms.py
.\venv\Scripts\python.exe train_model.py
.\venv\Scripts\python.exe app.py
```

The canonical dataset contract is in `ml/data_pipeline.py`. `ml/app.py`
validates the same feature order and bounds before calling the loaded model.
Generated datasets, virtual environments, and model artifacts are local and
ignored.

## Where to add future code

| Change | Correct owner |
| --- | --- |
| Shared scalar validation | `modules/shared/validation.php` |
| Feature-specific validation | The feature module next to its controller/workflow |
| Authentication query | `modules/auth/auth_queries.php` |
| Queue read/locking query | `modules/queue/queue_queries.php` |
| Live status projection | `modules/queue/status_queries.php` |
| Staff/window read or projection | `modules/service_window/window_queries.php` |
| Transactional staff mutation | `modules/service_window/ticket_actions.php` |
| Service/settings persistence | `modules/settings/service_catalog.php` or `settings_store.php` |
| Admin account/window persistence | `modules/admin/` |
| Report SQL/presentation | The appropriate report builder and shared report helper |
| Request/JSON/redirect mapping | Existing thin endpoint/controller |
| Shared browser behavior | `assets/js/main.js` |
| Client/staff/admin/display behavior | The corresponding role script |
| Shared/client/staff style | `assets/css/style.css` |
| Admin style or theme token | `assets/css/admin.css` |
| Display-board style | `assets/css/display.css` |
| New behavioral contract | `tests/php/`, `tests/js/`, or `tests/contracts/` |

Keep views focused on projection and markup. Keep endpoint files focused on
HTTP concerns. Keep SQL in focused query/workflow modules, transactions around
complete state changes, and compatibility entry points stable.

## Deferred compatibility work

The following require separate approval and verification:

- removal of any public endpoint or report wrapper;
- removal of the Composer Bootstrap dependency;
- removal of runtime schema compatibility guards;
- changes to routes, request fields, sessions, JSON envelopes, or role rules;
- display-token transport changes;
- database schema changes;
- model redesign or dependency upgrades; and
- physical stylesheet splitting before browser screenshot coverage exists.
