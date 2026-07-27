# SmartQMS Incremental Refactoring Plan

## Purpose

This is the consolidated implementation plan and progress record for the
approved SmartQMS refactor. It combines backend modularization, behavioral
characterization, event-driven JavaScript organization, and the styling/CSS
refactor in one ordered plan.

The work is intentionally incremental. Each batch is independently verified
before the next batch begins.

## Non-Negotiable Compatibility Rules

- Preserve routes, URL paths, HTTP methods, request field names, response keys,
  redirects, session keys, UI workflows, and client/staff/admin permissions.
- Preserve the existing MySQL schema and current data compatibility.
- Keep PHP procedural; do not introduce classes, inheritance, or interfaces.
- Do not add dependencies or replace the existing PHP, JavaScript, CSS, or ML
  toolchain without approval.
- Keep optional email, SMS, QR, and ML failures from blocking core queue flows
  exactly where the current behavior treats them as optional.
- Do not treat existing reference-number and daily-ticket concurrency behavior
  as fixed until a separately approved behavior-changing batch addresses it.

## Batch Order and Status

| Batch | Scope | Status | Primary verification |
| --- | --- | --- | --- |
| 0 | Behavioral safety net | Complete | 25 characterization tests |
| 1 | Bootstrap and shared helpers | Complete | Bootstrap symbol and full lint checks |
| 2 | Authentication helper separation | Complete | Auth contracts and characterization suite |
| 3 | Client queue/ticket workflow | Complete | Queue integration characterization |
| 4 | Staff/service-window workflow | Complete | Transaction/source checks and test suite |
| 5 | Administrator settings/services | Complete | Shared-query ownership and test suite |
| 6 | Report subsystem | Complete | All ten report contracts and date tests |
| 7 | JavaScript event lifecycle | Complete | Five syntax checks and seven runtime contracts |
| 8 | Styling architecture | Complete; visual matrix deferred | Exact cascade signatures and zero duplicate selector groups |
| 9 | Cleanup and documentation | Implemented; browser/ML gates pending | Architecture guide and complete available baseline |

## Batch 0 — Behavioral Safety Net

### Objective

Add a dependency-free test harness before moving production logic.

### Files

- `.gitignore`
- `tests/config.example.php`
- `tests/config.local.php` (local and ignored)
- `tests/php/assertions.php`
- `tests/php/bootstrap.php`
- `tests/php/run.php`
- `tests/php/unit/*_characterization_test.php`
- `tests/php/integration/*_test.php`
- `tests/contracts/http_contracts.php`
- `tests/README.md`

### Exact safety behavior

- Test credentials come only from test configuration or
  `SMARTQMS_TEST_DB_*` variables.
- Connections are rejected unless both configured and connected database names
  end in `_test` or `_testing`.
- Data-changing fixtures use `characterization_` identifiers and roll back.
- Tests do not send email/SMS, create QR files, or contact the ML service.
- Schema, queue priority/FIFO, active tickets, wait prediction, report aliases,
  dates, output envelopes, and HTTP contracts are characterized.

### Exit result

The current expanded suite passes 71 tests with no committed characterization
fixtures.

## Batch 1 — Bootstrap and Shared Helper Separation

### Objective

Keep `config/config.php` and `config/helpers.php` as compatibility entry points
while giving each procedural helper category one owner.

### Files and ownership

- `config/config.php`: constants, session initialization, and security headers.
- `config/helpers.php`: compatibility include aggregator only.
- `modules/shared/http.php`: response, redirect, request, and form feedback.
- `modules/shared/security.php`: login, CSRF, and authentication throttling.
- `modules/shared/validation.php`: phone normalization/validation.
- `modules/shared/activity.php`: activity-log writes.
- `modules/shared/settings.php`: system-setting access.
- `modules/shared/assets.php`: application and cache-busted asset URLs.
- `modules/shared/schema_compat.php`: retained runtime legacy schema guards.
- `modules/queue/queue_queries.php`: queue/reference lookups.
- `modules/queue/prediction.php`: prediction features and fallback/ML estimates.
- `modules/service_window/window_queries.php`: staff/window lookups.
- Notification/auth files: remove moved schema helpers and make the SMS database
  dependency explicit.

### Exit result

All moved symbols load once through the compatibility bootstrap; tests and full
syntax checks pass.

## Batch 2 — Authentication Helper Separation

### Objective

Separate mixed authentication utilities without changing any handler route,
OTP flow, session contract, email behavior, or role redirect.

### Files and ownership

- `modules/auth/auth_utils.php`: compatibility aggregator.
- `modules/auth/auth_queries.php`: authentication user lookups.
- `modules/auth/otp_service.php`: OTP generation, hashing, persistence, and mail
  queue coordination.
- `modules/auth/auth_session.php`: OTP and authenticated session lifecycle.
- `modules/auth/auth_redirects.php`: role destination mapping.
- Auth characterization test: points to the new implementation owners.

### Exit result

OTP/session keys and the three role destinations remain unchanged; all tests
pass.

## Batch 3 — Client Queue and Ticket Workflow

### Objective

Make the join-queue endpoint an orchestration layer and give transactional
ticket creation and live-status read models focused owners.

### Files and ownership

- `modules/queue/queue_queries.php`: adds daily ticket-number generation.
- `modules/queue/ticket_service.php`: ticket, QR path, prediction log, activity,
  commit, rollback, and rethrow behavior.
- `modules/queue/join_queue.php`: authorization, request validation, business
  decision redirects, optional alert call, and final redirect.
- `modules/queue/status_queries.php`: display token and live queue read models.
- `modules/queue/status.php`: JSON controller only.

### Exit result

Priority/FIFO, active-ticket, prediction keys, transaction boundary, and live
status envelope remain characterized and passing.

## Batch 4 — Staff and Service-Window Workflow

### Objective

Centralize repeated serving-ticket/window queries and transactional call,
skip, and complete mutations.

### Files and ownership

- `modules/service_window/window_queries.php`: staff/window/serving-ticket
  lookups and window-status updates.
- `modules/service_window/ticket_actions.php`: transactional call-next, skip,
  and complete procedures.
- `call_next.php`, `skip_ticket.php`, `complete_ticket.php`, and
  `window_status.php`: role/CSRF/request validation and JSON responses.

### Exit result

`FOR UPDATE`, priority ordering, status/timestamp writes, wait-time metrics,
notifications, activity logs, commits, rollbacks, and JSON keys are preserved.

## Batch 5 — Administrator Settings and Health Services

> Historical batch record: the administrator Settings UI and settings JSON
> controller completed here were explicitly retired on July 27, 2026. Health
> service management remains active, and runtime setting reads remain for
> compatibility.

### Objective

Remove repeated SQL shared by admin HTML pages and JSON endpoints.

### Files and ownership

- `modules/settings/health_service_queries.php`: list, lookup, activation,
  ticket-history detection, and delete-or-deactivate behavior.
- `modules/settings/health_services_mgmt.php`: JSON validation/controller.
- `views/admin/services.php`: form validation, workflow messages, and HTML.
- `modules/shared/settings.php`: runtime setting lookup procedure.

### Exit result

Delete-versus-deactivate rules, messages, response envelopes, activity logs,
and settings behavior remain unchanged.

## Batch 6 — Report Subsystem

### Objective

Decompose report metadata, query execution, presentation contracts, HTTP
handling, and builders.

### Files and ownership

- `report_catalog.php`: definitions, aliases, normalization, and date range.
- `report_queries.php`: prepared report execution.
- `report_presenters.php`: metrics, tables, empty states, and charts.
- `report_http.php`: authorization and JSON exception mapping.
- `report_utils.php`: compatibility entry point, ten builders, and dispatcher.

### Exit result

All ten reports, aliases, inclusive dates, invalid/unknown exceptions, chart
keys, table keys, empty states, and CSV guard pass.

## Batch 7 — JavaScript Event Lifecycle

### Objective

Make startup behavior named, event-driven, and declarative without changing
script paths, selectors, endpoint URLs, form fields, timers, or UI behavior.

### Files and ownership

- `assets/js/main.js`: shared form, validation, OTP, password, and logout
  behavior.
- `assets/js/client.js`: client prediction, queue, notification, feedback, and
  ticket behavior.
- `assets/js/staff.js`: staff actions, status, timeout, and theme behavior.
- `assets/js/admin.js`: admin theme, chart, navigation, modal, and pagination
  behavior.
- `assets/js/display.js`: named clock/queue timer lifecycle.

### Exit result

All five scripts pass syntax checks and seven dependency-free runtime contracts.
Interactive browser verification remains environment-blocked.

## Batch 8 — Styling Architecture (Added to the Combined Plan)

### Objective

Refactor all `assets/css` files conservatively, retaining selectors, asset
paths, computed values, responsive behavior, themes, and role-specific layouts.

### Files and ownership

- `assets/css/style.css`: public/client/staff component and responsive styles;
  one canonical shared loading animation.
- `assets/css/admin.css`: admin components and one canonical light/dark token
  definition for sidebar/theme values.
- `assets/css/display.css`: display-board layout with semantic color tokens.

### Accessibility and cascade constraints

- Retain visible `:focus-visible` rings.
- Retain the 44px touch-target token.
- Retain `prefers-reduced-motion` overrides.
- Preserve light/dark token parity and the final cascade values.
- Do not split physical stylesheets further without browser screenshot or
  visual-regression coverage.

### Exit result

CSS braces balance, all 61 same-context duplicate selector groups are removed,
and exact before/after selector-property signatures match. Backend and
JavaScript checks remain green. Visual comparison is deferred because no
browser backend was available during Batch 8.

## Batch 9 — Cleanup and Documentation

### Objective

Remove only proven stale remnants, document module ownership, and execute the
complete available final baseline. No production code is removed without the
required browser and access-history evidence.

### Files

- `README.md`: link the architecture, tests, and plan documentation.
- `docs/ARCHITECTURE.md`: document the resulting implementation and extension
  points.
- `docs/SMARTQMS_INCREMENTAL_REFACTORING_PLAN.md`: this consolidated plan.

### Exit criteria

- Full PHP lint passes.
- 71 PHP characterization/integration tests pass against the isolated database.
- Five JavaScript syntax checks and seven runtime contracts pass.
- Composer validates with only the known Bootstrap warning.
- CSS structure/tokens remain valid; browser-only asset and visual checks are
  recorded separately when browser access is available.
- No test fixtures remain committed.
- Final diff review identifies pre-existing unrelated work separately.

### Exit result

- No production code or public endpoint was removed because browser coverage
  and Apache access-history evidence did not satisfy the approved deletion
  threshold.
- The architecture guide documents bootstrap, modules, sessions, queue and
  staff lifecycles, predictions, notifications, admin/report ownership,
  JavaScript, CSS, database compatibility, tests, ML restoration, and future
  extension points.
- All 71 PHP tests, 132 PHP lint checks, five JavaScript syntax checks, seven
  JavaScript runtime contracts, both concurrency suites, Composer validation,
  documentation links, CSS structure checks, and local HTTP asset checks pass.
- Authenticated browser and visual matrices remain environment-blocked because
  the browser runtime exposes no backend.
- ML tests remain environment-blocked because `ml/venv/pyvenv.cfg` references a
  removed Python 3.12 installation. Requirements and the virtual environment
  were not modified.

## Deferred, Separately Approved Follow-Ups

These are not part of the behavior-preserving batches above:

1. Add browser screenshot/interaction coverage, then physically split the large
   role stylesheets by base, component, page, theme, and responsive layers.
2. Fix concurrent reference/daily ticket generation with a database-backed
   sequence or uniqueness-and-retry design.
3. Replace runtime schema compatibility DDL with versioned migrations after all
   deployed databases are inventoried.
4. Run the ML tests when the expected Python environment is restored.
