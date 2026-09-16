# SmartQMS Supabase Migration Mapping

## Status and purpose

This document is the Phase 1 compatibility inventory for
`SMART-QMS-REVISION-IMPLEMENTATION.md`. It records the application contracts
that must remain stable while Supabase is introduced behind provider-neutral
adapters.

The current production implementation is a procedural PHP application backed
by MySQL/MariaDB. Supabase is not yet a runtime dependency. Existing PHP
routes, request fields, session keys, role checks, queue ordering, reports,
notifications, QR paths, and staff/admin layouts are compatibility contracts.

No current table or column is renamed by the Supabase rollout. Translation
between the two data models belongs at the adapter boundary.

## Current application inventory

### Runtime and presentation

| Area | Current owner | Compatibility requirement |
| --- | --- | --- |
| Request/session bootstrap | `config/config.php` | Preserve session cookies, security headers, constants, timezone, and helper loading |
| Local database connection | `config/database.php` | Keep the existing `$conn` `mysqli` contract available during rollout |
| Shared authenticated shell | `views/shared/includes/`, `assets/js/shell.js`, `assets/css/style.css` | Preserve responsive role navigation, theme, search, profile, logout, notifications, and toasts |
| Client UI | `views/client/`, `assets/js/client.js`, `assets/css/style.css` | Extend the current SmartQMS design system; do not replace the authenticated shell |
| Staff UI | `views/staff/`, `assets/js/staff.js`, `assets/css/style.css` | Preserve dashboard, My Window, Activity Log, and action workflows |
| Admin UI | `views/admin/`, `assets/js/admin.js`, `assets/css/admin.css` | Preserve current dashboard, management pages, reports, layout, and permissions |
| Display board | `display.php`, `views/display/board.php`, `assets/js/display.js`, `assets/css/display.css` | Preserve token-gated public display behavior |
| Optional ML boundary | `modules/queue/prediction.php`, `ML_API_URL` | Remain fail-soft; ticket creation must succeed with the PHP fallback |

### Authenticated page routes

| Role | Existing routes | Active shell destinations |
| --- | --- | --- |
| Client | `views/client/index.php`, `queue_status.php`, `ticket.php`, `feedback.php` | Dashboard, Queue Status, My Ticket |
| Staff | `views/staff/dashboard.php`, `window.php`, `activity_log.php` | Queue Management, My Window, Activity Log |
| Admin | `views/admin/dashboard.php`, `add_staff.php`, `services.php`, `windows.php`, `reports.php`, `users.php`, `ml_logs.php` | Dashboard, Staff Accounts, Health Services, Service Windows, Reports |

Public/authentication routes remain `index.php`, `views/client/register.php`,
`verify_otp.php`, `forgot_password.php`, `ticket_lookup.php`, and
`display.php`.

### Existing request endpoints

The canonical field-level inventory remains
`tests/contracts/http_contracts.php`. The endpoints affected by the provider
rollout are:

| Capability | Stable endpoint | Method and role |
| --- | --- | --- |
| Register | `modules/auth/register.php` | POST, guest |
| Login | `modules/auth/login.php` | POST, guest |
| Verify/resend OTP | `modules/auth/verify_otp.php`, `resend_otp.php` | POST, OTP session |
| Join queue | `modules/queue/join_queue.php` | POST, client |
| Current ticket | `modules/queue/ticket.php` | GET, client |
| Live queue | `modules/queue/status.php` | GET, authenticated or display token |
| Wait prediction | `modules/queue/get_prediction.php` | GET/POST, authenticated |
| Client notifications | `modules/notifications/get_notifications.php`, `mark_read.php` | POST, client |
| Client feedback | `modules/feedback/submit_feedback.php` | POST, client |
| Staff call/complete/skip/void | `modules/service_window/*.php` | POST, staff |
| Staff window status | `modules/service_window/window_status.php` | POST, staff |
| Service management | `modules/settings/health_services_mgmt.php` | GET/POST, admin |
| Reports and CSV | `modules/reports/*.php` | GET, admin |

New provider-neutral endpoints may be added under `api/`, but these existing
entry points and envelopes are not removed during the migration.

## Current MySQL-to-Supabase entity mapping

### Identity and authorization

| Current SmartQMS | Supabase target | Mapping rule |
| --- | --- | --- |
| `users.user_id` integer | `auth.users.id` UUID plus `profiles.id` | Store a stable mapping; never cast one identifier into the other |
| `users.first_name`, `last_name`, `middle_name` | `profiles.first_name`, `last_name`, `middle_name`, derived `full_name` | Retain separate names so current forms and reports remain lossless |
| `users.phone_number` | `profiles.phone` | Normalize to the existing Philippine mobile contract before writes |
| `users.email`, `password_hash`, OTP columns | Supabase Auth | Existing local auth remains available until explicit cutover; password hashes and OTP secrets are never exported to browser code |
| `users.role = client` | `user_roles.role = customer` | Adapter maps `client` to `customer`; PHP sessions continue to use `client` |
| `users.role = staff` | `user_roles.role = staff` | One-to-one semantic mapping |
| `users.role = admin` | `user_roles.role = admin` | Existing admins are not silently elevated to Super Admin |
| no current equivalent | `user_roles.role = super_admin` | Created only through a server-side, audited provisioning workflow |
| `users.is_active`, `is_verified`, `must_change_password` | profile/auth metadata | Keep server-enforced state during coexistence; no browser-supplied flag is trusted |
| `staff` | `staff_assignments` / profile role metadata | Preserve `staff_id`, `department`, `shift`, creator, and capability relationships |

Canonical PHP role names remain `client`, `staff`, and `admin`. The Supabase
role vocabulary is normalized only inside the provider adapter:

```text
client <-> customer
staff  <-> staff
admin  <-> admin
no local implicit mapping <-> super_admin
```

### Queue configuration

| Current SmartQMS | Supabase target | Mapping rule |
| --- | --- | --- |
| `health_services` | `services` | Preserve service code, encoded ML value, queue mode, description, priority-only flag, active state, and display order |
| single configured health center | `branches` | Seed one default branch from `bhc_name`/`bhc_address`; do not invent multiple operational branches |
| `service_windows` | `counters` | Window is the SmartQMS UI term; counter is the provider data term |
| `service_windows.location_description` | `counters.location_description` | Preserve optional physical-location text |
| `staff_service_capabilities` | `staff_service_capabilities` | Preserve specialized-service authorization independently of runtime window ownership |
| `system_settings` queue keys | branch/application settings | Continue reading local settings until a dedicated Supabase settings contract is approved |

Identifier rules:

- Existing integer IDs remain authoritative for existing PHP routes.
- Supabase tables use UUID primary keys.
- Imported rows carry a unique nullable `legacy_id` integer.
- Adapter responses expose a provider-neutral string `id` and an optional
  integer `legacy_id`; current PHP controllers continue to use `legacy_id`.
- No URL changes from integer to UUID occur in this revision.

### Ticket and queue lifecycle

| Current SmartQMS | Supabase target | Mapping rule |
| --- | --- | --- |
| `queue_tickets` | `tickets` | Preserve reference number, display ticket number, QR path, service, client type, priority, queue mode, timestamps, and window assignment |
| `queue_tickets.user_id` | `tickets.customer_id` | Resolve through the identity map; anonymous ticket creation is not enabled by default |
| user name/phone join | `tickets.customer_name`, `customer_phone` snapshot | Store an issuance-time snapshot while retaining the customer foreign key |
| `wait_time_logs.predicted_wait_min` | `tickets.predicted_wait_minutes` and prediction history | Ticket holds the current projection; history retains features and evaluation data |
| `wait_time_logs` | `ticket_predictions` | Preserve feature inputs, source/algorithm, actual wait, actual duration, confidence, and model version |
| `activity_logs` | `queue_events` for ticket events plus operational audit | Ticket mutations create queue events; non-ticket activity remains audit data |
| `notifications` | `notifications` | Preserve ownership, channel, delivery state, read state, type, and timestamp |
| `feedback` | `feedback` | Preserve the one-feedback-per-completed-ticket rule |

Status normalization is explicit and reversible:

| Current ticket status | Supabase ticket status | Notes |
| --- | --- | --- |
| `waiting` | `waiting` | Active |
| `serving` | `serving` | Active |
| `completed` | `done` | Terminal; adapter returns `completed` to existing PHP/UI |
| `skipped` | `skipped` | Terminal |
| `voided` | `voided` | Required extension to the draft Supabase list so timeout/manual void behavior is not lost |

| Current window status | Supabase counter status | Notes |
| --- | --- | --- |
| `open` | `open` | Ready to call |
| `busy` | `busy` | Required extension; a serving counter must not be represented as paused |
| `closed` | `closed` | Not accepting work |
| no current equivalent | `paused` | Optional future state; not emitted to existing views without approval |

Queue ordering remains priority descending and FIFO by issuance time within the
same priority. Central/specialized routing, priority-only eligibility, staff
capabilities, one active ticket per client, and transactional terminal-state
protection remain server-owned rules.

## Current field-name mapping

| Domain concept | Current field | Provider-neutral/Supabase field |
| --- | --- | --- |
| Service identifier | `service_id` | `service_id`, with UUID plus `legacy_id` mapping |
| Service name | `service_name` | `name` |
| Service code | `service_code` | `code` / `prefix` |
| ML service feature | `service_encoded` | `ml_value` |
| Service availability | `is_active` | `active` |
| Priority eligibility | `priority_only` | `priority_only` |
| Branch identifier | no current field | `branch_id` |
| Window/counter identifier | `window_id` | `counter_id` |
| Window/counter name | `window_name` | `name` |
| Customer identifier | `user_id` | `customer_id` |
| Customer classification | `client_type` | `client_type` |
| Queue priority | `priority_level` | `priority_level` / derived `priority` |
| External reference | `reference_number` | `reference_number` |
| Display number | `ticket_number` | `ticket_number` |
| Issue timestamp | `issued_at` | `created_at` |
| Call timestamp | `called_at` | `called_at` |
| Service-start timestamp | `served_at` | `served_at` |
| Completion timestamp | `completed_at` | `completed_at` |
| Predicted wait | `predicted_wait_min` | `predicted_wait_minutes` |
| Prediction algorithm | `algorithm_used` | `model_version` plus `source` |

## Adapter and cutover boundaries

### Provider modes

The rollout uses an explicit server setting:

```text
local       Existing MySQL/MariaDB behavior only (default)
shadow      Local remains authoritative; eligible writes may be mirrored and compared
supabase    Supabase is authoritative for migrated capabilities
```

Missing or invalid Supabase configuration always resolves to `local`. Browser
code never receives the service-role key. A failed remote request must not
silently create a second local ticket after an ambiguous remote write.

### Browser boundary

Browser-safe configuration may contain only:

- Supabase project URL;
- Supabase publishable/anonymous key; and
- provider mode/capability flags that disclose no secret.

The initial JavaScript adapters call existing/protected SmartQMS PHP endpoints
by default. Supabase REST/Realtime access is enabled only for deliberately
public or RLS-protected reads. Privileged staff/admin mutations always pass
through PHP.

### Server boundary

Server-only configuration owns:

- `SUPABASE_SERVICE_ROLE_KEY`;
- optional Supabase JWT verification/audience values;
- the Python ML URL and token; and
- shadow-write diagnostics.

All protected endpoints continue to validate the local session and CSRF token.
When Supabase access tokens are accepted, the server validates the token,
resolves roles from trusted data, checks branch/capability scope, validates the
payload, executes one operation, and writes an audit event.

## Supabase schema compatibility decisions

The Supabase development migration must include:

- `profiles`, `user_roles`, `services`, `branches`, `counters`, `tickets`,
  `ticket_predictions`, `queue_events`, `notifications`, `feedback`, and
  `staff_service_capabilities`;
- UUID primary keys plus unique nullable `legacy_id` fields for migrated
  entities;
- a default branch for the current single-location installation;
- ticket statuses `waiting`, `serving`, `done`, `skipped`, and `voided`;
- counter statuses `open`, `busy`, `paused`, and `closed`;
- uniqueness for ticket/reference numbers and one feedback row per ticket;
- indexes supporting branch/service/status/priority/FIFO reads;
- RLS enabled on every application table; and
- restricted functions for transactional ticket issuance and staff actions.

The current `system_settings`, `sms_logs`, `email_jobs`, `auth_attempts`, and
`ml_comparison_logs` remain server-side compatibility dependencies in the
first rollout. They are not made browser-readable.

## RLS ownership matrix

| Table | Anonymous | Customer | Staff | Admin | Super Admin |
| --- | --- | --- | --- | --- | --- |
| Services/branches | Read active rows only | Read active rows | Read assigned branch configuration | Manage | Manage |
| Counters/live queue projection | Read sanitized public projection only | Read sanitized projection | Read assigned branch | Manage | Manage |
| Profiles | None | Own row | Own row plus permitted operational projection | Restricted administration | Manage |
| User roles | None | Own effective role only | Own effective roles only | Read required assignments | Grant/revoke |
| Tickets | Sanitized live projection only | Create/read own | Read/update assigned branch via controlled operation | Read/manage operationally | Manage |
| Queue events | None | Events safe for own ticket | Assigned branch | Read | Manage |
| Predictions | None | Own ticket projection | Assigned branch | Read/report | Manage |
| Notifications | None | Own rows | No customer inbox access | Operational support only | Manage |
| Feedback | None | Insert/read own ticket feedback | Read only if specifically required | Report/read | Manage |

Public live-queue responses never expose customer identifiers, names, phone
numbers, profile rows, or private ticket references.

## Phase 1 verification evidence

- Baseline command: `php tests/php/run.php`
- Result at inventory time: 111 passed, 0 failed.
- Existing endpoint contract source: `tests/contracts/http_contracts.php`.
- Existing schema sources: `database/smartqms_final_v4.sql` and
  `database/upgrade_hybrid_queue_2026_08_18.sql`.
- Existing architecture source: `docs/ARCHITECTURE.md`.
- Repository search found no production Supabase integration or Supabase
  secret before Phase 2.

## Phase 2 entry criteria

Phase 2 may begin only with these invariants:

1. `local` remains the default provider and preserves all current behavior.
2. Server and browser configuration are separated so secrets cannot be
   serialized into HTML or JavaScript.
3. JavaScript pages depend on a small adapter API rather than scattered
   Supabase calls.
4. Adapter status/role/identifier translation follows this document.
5. Supabase migrations are development artifacts and do not alter the current
   MySQL schema.
6. Remote configuration absence is a tested, nonfatal state.

