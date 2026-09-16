# Smart QMS Complete Incremental Refactoring Plan

## 1. Objective

Incrementally refactor the existing Smart QMS application—not rewrite it—to improve:

- Readability and onboarding
- Procedural modularity
- Separation of concerns
- Reusability
- Testability
- Database safety
- Event-driven JavaScript organization
- CSS maintainability across every application surface
- Reliability of queue and service-window state transitions

The current working tree is the behavioral source of truth. Existing uncommitted changes must be preserved.

## 2. Non-Negotiable Compatibility Requirements

The refactor must not change:

- Application routes or public PHP endpoint paths
- Form actions or field names
- Session keys or role values
- Client, staff, and administrator redirects
- JSON property names or successful response shapes
- Database schema or existing data
- Ticket/reference formats
- Regular and Senior Citizen/PWD priority behavior
- User workflows
- Display-board token URL behavior
- Report wrapper URLs and aliases
- Email, SMS, QR, or ML integration contracts
- Current visual direction, responsive behavior, themes, or print layouts

Implementation constraints:

- No application classes, inheritance, interfaces, service containers, repositories implemented as objects, or other OOP architecture.
- Existing vendor classes used by PHPMailer and the QR package may remain.
- No new dependencies, frameworks, CSS preprocessors, or build tools.
- No dependency upgrades or removals without separate approval.
- No code will be removed merely because it has no source reference; public endpoints require stronger verification.
- Each batch must be independently reviewable and leave the application working.

## 3. Phase 1 Audit Findings

### Current structure

- `index.php` is the login entry point and role router.
- PHP page views frequently act as controller, validator, data-access layer, and renderer.
- `modules/` contains authentication, queue, service-window, notification, feedback, settings, and reporting endpoints.
- `config/config.php` starts the session, applies headers, defines constants, and loads shared helpers.
- `config/database.php` creates the local `mysqli` connection exposed as `$conn`.
- JavaScript behavior is driven by PHP-rendered data attributes and direct endpoint URLs.
- Flask provides optional waiting-time predictions; PHP supplies the operational fallback.

### Existing strengths to preserve

- Role checks are present across protected workflows.
- State-changing requests generally use CSRF validation.
- Passwords and OTPs are hashed.
- Most parameterized queries use prepared statements.
- User-visible output is generally HTML-escaped.
- CSV export protects against spreadsheet formula injection.
- The display board requires a configured token.
- Queue priority consistently uses `priority_level DESC, issued_at ASC`.
- Staff completion and skip actions validate the assigned service window.
- ML unavailability does not prevent queue entry.

### Main structural problems

- `config/helpers.php` is 535 lines and mixes HTTP, CSRF, flash feedback, throttling, validation, database access, staff lookup, queue calculations, settings, activity logging, assets, and ML communication.
- `modules/reports/report_utils.php` is 743 lines and contains definitions, SQL helpers, ten report builders, chart mapping, dispatch, and JSON output.
- `assets/js/main.js` is 893 lines and combines auth, validation, queue status, prediction, notifications, email dispatch, staff actions, timers, logout, and staff themes.
- `assets/css/style.css` is 4,372 lines and contains multiple appended design/polish layers.
- `assets/css/admin.css` is 2,594 lines and contains a second admin refresh layer that overrides earlier canonical rules.
- Exact CSS selectors are repeatedly declared, including staff shell, navigation, digital ticket, admin shell, report, modal, and responsive selectors.
- Admin service, window, and staff pages combine SQL, transactions, validation, feedback, and HTML.
- Health-service and system-setting behavior exists in both HTML handlers and JSON endpoints with different accepted fields.
- Runtime request helpers execute `CREATE TABLE` and `ALTER TABLE`, hiding database compatibility work inside ordinary requests.
- `sendSMS()` depends on `global $conn`.
- Queue identifiers are generated with `COUNT(*) + 1`.
- Active-ticket prevention occurs before the ticket transaction.
- Staff ticket actions use check-then-update flows vulnerable to concurrent requests.
- Near-turn notification processing performs repeated queries per ticket.
- Unauthorized endpoint behavior is inconsistent: some endpoints return JSON while others redirect through `requireLogin()`.
- Five legacy functions at the beginning of `main.js` have no in-repository callers.
- The ten report wrapper endpoints have no in-repository callers or observed matches in the available Apache access log, but remain compatibility surfaces.
- Composer installs Bootstrap 5.0.2 while pages load Bootstrap 5.3.0 from a CDN.
- PHP and JavaScript lack automated behavioral tests.
- The ignored Python environment points to a missing Python 3.12 installation.

### Recorded baseline

- PHP 8.2.12
- 71 application PHP files pass `php -l`
- `main.js`, `admin.js`, and `display.js` pass `node --check`
- Composer metadata is valid, with one warning about the exact Bootstrap constraint
- MariaDB 10.4.32 is reachable
- Local database contains the expected 14 tables and 3 views
- ML tests cannot currently start because the Python runtime is unavailable
- Authenticated browser workflows were not executed during the audit because no isolated test database exists
- The working tree already contains 32 modified tracked files with approximately 4,548 additions and 662 deletions, plus untracked work

## 4. Target Procedural Structure

Existing public entry points remain in place. New files are internal procedural modules.

### Shared modules

`config/config.php`

- Remains the application bootstrap.
- Defines constants, runtime paths, timezone, sessions, and security headers.
- Loads the compatibility helper aggregator.

`config/helpers.php`

- Becomes a compatibility aggregator.
- Requires focused shared modules so existing callers continue to receive the same function names.

Add:

- `modules/shared/http.php`
  - JSON output
  - redirects
  - flash form feedback
  - request-method checks
- `modules/shared/security.php`
  - CSRF generation/validation
  - authentication/role guards
  - request IP handling
- `modules/shared/validation.php`
  - shared normalization and validators
- `modules/shared/activity.php`
  - activity-log writes
- `modules/shared/settings.php`
  - setting lookup helpers
- `modules/shared/assets.php`
  - asset and action URL helpers
- `modules/shared/schema_compat.php`
  - isolated existing auth/email schema compatibility checks

### Authentication modules

Keep public handlers in `modules/auth/`.

Use `modules/auth/auth_utils.php` for:

- Authentication throttling
- User lookup
- OTP session state
- OTP issue/verification
- Login session establishment
- Role redirection
- Password-reset flow helpers

### Queue modules

Add:

- `modules/queue/queue_queries.php`
  - active-ticket lookup
  - service lookup
  - people-ahead calculation
  - queue and display projections
- `modules/queue/prediction.php`
  - prediction features
  - fallback estimate
  - ML request validation
- `modules/queue/queue_service.php`
  - transactional ticket issuance
  - reference/ticket-number allocation
  - QR coordination
  - notification coordination

Existing queue endpoint filenames remain thin public adapters.

### Service-window modules

Add:

- `modules/service_window/window_queries.php`
  - staff/window/current-ticket/waiting-list reads
- `modules/service_window/window_service.php`
  - Call Next
  - Complete
  - Skip
  - Void
  - window-status transitions

Existing public service-window endpoints remain in place.

### Administrator modules

Add:

- `modules/settings/service_catalog.php`
  - health-service input, validation, reads, and writes
- `modules/admin/staff_accounts.php`
  - staff validation and transactional account creation
- `modules/admin/window_admin.php`
  - service-window form validation, reads, and writes
- `modules/admin/users.php`
  - user listing and activation changes

The formerly planned `modules/settings/settings_store.php` adapter was
implemented, then removed with the administrator Settings feature after
explicit approval on July 27, 2026.

HTML and JSON entry points call these functions through adapters preserving their current contracts.

### Report modules

Keep `modules/reports/report_utils.php` as a compatibility aggregator.

Add:

- `modules/reports/report_core.php`
  - definitions, aliases, date ranges, query helpers, columns, metrics, empty reports
- `modules/reports/report_chart.php`
  - chart projections
- `modules/reports/builders/*.php`
  - one focused builder for each existing report

Existing report endpoint filenames and `export_csv.php` remain unchanged.

## 5. Implementation Batches

### Batch 0 — Behavioral Safety Net

#### Changes

Create:

- `tests/config.example.php`
- `tests/php/bootstrap.php`
- `tests/php/assertions.php`
- `tests/php/run.php`
- `tests/php/unit/`
- `tests/php/integration/`

Add `tests/config.local.php` to `.gitignore`.

Test bootstrap requirements:

- Refuse to run unless the configured database name ends in `_test`.
- Use the existing `database/smartqms_final_v4.sql` schema unchanged.
- Never fall back to `config/database.php`.
- Wrap integration scenarios in transactions and roll them back when practical.
- Use deterministic fixture emails and ticket references.

Characterize:

- Shared validators and flash feedback
- Role redirects
- Session-key creation and cleanup
- OTP flow selection
- Queue priority ordering
- Active-ticket rules
- Prediction fallback
- Staff transition rules
- Report date parsing, aliases, and output keys
- CSV escaping
- Existing JSON payload structures

Restore the ignored ML environment from the existing `ml/requirements.txt` once Python is available; do not change versions.

#### Exit criteria

- Baseline tests pass before production code is moved.
- Test code cannot connect to the development database.
- Existing PHP and JavaScript syntax baselines still pass.

Risk: Low.

### Batch 1 — Bootstrap and Shared Helpers

#### Changes

Move existing helper implementations into the exact shared modules listed above.

Preserve existing function names through `config/helpers.php` so callers do not need to migrate simultaneously.

Explicit dependency rules:

- Database functions accept `mysqli $conn`.
- Functions do not access `global $conn`.
- Request/response functions may access request/session superglobals because that is their explicit responsibility.
- Pure validators do not access request/session/database state.
- Feature query functions do not emit redirects or JSON.
- Public handlers remain responsible for choosing HTML redirect versus JSON response.

Move `sendSMS()` to:

```php
sendSMS(mysqli $conn, string $phone, string $message, string $type = 'notification', int $userId = 0): bool
```

Update its internal callers.

Move runtime DDL functions into `schema_compat.php` but retain their names and behavior for compatibility. Do not add new request-time DDL.

#### Preserved behavior

- Session path and cookie settings
- Security headers
- CSRF token format
- Flash session structure
- Redirect URL construction
- JSON envelope format
- Activity log contents
- Asset cache-busting URLs
- Database configuration path and `$conn`

#### Verification

- Full PHP lint
- Shared helper unit tests
- Session/cookie/header smoke tests
- Login and display entry-point smoke tests
- Diff review for accidental route or output changes

Risk: Medium.

### Batch 2 — Validation and Feedback

#### Changes

Move these into shared procedural validators:

- Email normalization and validation
- Philippine mobile normalization and validation
- Required string validation
- Password minimum length
- Password confirmation
- Six-digit OTP validation
- Rating range
- Positive identifier validation
- Service encoded value range
- Service display-order range
- Window status allowlist
- Report date validation

Maintain feature-specific messages through parameters or thin feature validators.

Rules:

- Passwords, OTPs, tokens, and secrets are never placed in old input.
- Safe text/select values remain preserved after validation failure.
- HTML adapters produce existing session flash structures.
- JSON adapters retain existing `error` and `field_errors` keys.
- No new validation restrictions are imposed on system-setting values in this refactor.

#### Verification

- Login and registration validation
- OTP and reset forms
- Queue service/classification errors
- Feedback rating
- Staff account form
- Service and window forms
- Report date errors
- Client-side/server-side validation alignment

Risk: Medium.

### Batch 3 — Authentication

#### Changes

Refactor these handlers without changing filenames:

- `login.php`
- `register.php`
- `verify_otp.php`
- `resend_otp.php`
- `forgot_password.php`
- `logout.php`

Separate operations into functions:

- Normalize credentials
- Check throttling
- Record/clear attempts
- Load user
- Validate password/account status
- Establish OTP flow
- Issue/cancel OTP jobs
- Verify OTP hash and expiry
- Establish authenticated session
- Reset password
- Clear transient OTP/reset session state
- Select role destination

Preserve session keys:

- `user_id`
- `role`
- `name`
- `phone`
- `email`
- `staff_id`
- `otp_flow`
- `otp_user_id`
- `otp_started_at`
- `pending_user_id`
- `pending_login_user_id`
- `otp_last_sent_at`
- `reset_user_id`
- `reset_verified_user_id`
- `csrf_token`
- `form_feedback`

Do not change current generic login-failure disclosure behavior.

#### Verification

- Unknown email
- Wrong password
- Inactive account
- Unverified client
- Client login OTP
- Registration OTP
- OTP resend cooldown
- OTP throttling
- Wrong and expired OTP
- Password-reset request/verification/change
- Staff/admin direct login
- Session regeneration
- Role redirection
- Logout and session destruction
- CSRF failure

Risk: High.

### Batch 4 — Queue, Tickets, Predictions, QR, and Notifications

#### Transactional ticket issuance

1. Normalize and validate service/classification.
2. Load the prediction snapshot.
3. Request the optional ML estimate before acquiring long-lived locks.
4. Start a transaction.
5. Lock the client’s `users` row with `FOR UPDATE`.
6. Recheck for `waiting` or `serving` tickets.
7. Acquire `GET_LOCK('smartqms:ticket-issue:{year}', 5)`.
8. Calculate the next yearly reference suffix from the current maximum suffix, not `COUNT(*)`.
9. Calculate the current daily display ticket number while holding the issuance lock.
10. Insert the ticket.
11. Generate the QR file.
12. Update the QR path.
13. Insert the wait-time log.
14. Write the activity log.
15. Commit.
16. Release the advisory lock in `finally`.
17. Process near-turn alerts after commit.
18. If the transaction fails, roll back and remove only the QR file created by that attempt.

Preserve:

- `BHC-YYYY-NNNN` references
- Daily `A-NNN` ticket numbers
- One active ticket per client
- `regular`, `senior`, and `pwd` values
- Priority level `1` for Senior/PWD
- Priority-only services
- Existing redirect destinations and messages

#### Prediction work

- Keep current feature names and encoding.
- Keep current fallback formula and limits.
- Keep the two-second maximum PHP-side ML request behavior.
- Reject malformed/unreasonable ML output as unavailable.
- Preserve prediction JSON fields and `ml`/`fallback` source labels.

#### Notification work

- Make SMS connection explicit.
- Replace dynamic notification `IN (...)` updates with typed prepared placeholders.
- Fetch data required for near-turn evaluation in fewer queries without changing `peopleAhead()` semantics.
- Preserve browser/SMS channel and delivery-status behavior.
- Preserve one alert per ticket/type.

#### Verification

- Regular queue join
- Senior and PWD queue join
- Priority-only service
- Existing active ticket
- Two concurrent joins for the same client
- Concurrent issuance for different clients
- Reference uniqueness
- Daily ticket numbering
- QR creation and rollback cleanup
- Ticket lookup
- Queue status
- Display-board status
- Stored prediction
- Live service prediction
- ML unavailable/malformed/valid
- Near-turn notification
- Notification read marking

Risk: High.

### Batch 5 — Staff and Service-Window State

#### Call Next

Within one transaction:

1. Resolve staff ID.
2. Lock the assigned active window.
3. Reject a closed or unassigned window.
4. Check for an existing serving ticket in that window.
5. Select the next eligible waiting ticket using priority/FIFO ordering and `FOR UPDATE`.
6. Update the ticket to `serving`.
7. Set `called_at`, `served_at`, and `window_id`.
8. Set the window to `busy`.
9. Write the activity log.
10. Commit.
11. Process near-turn alerts after commit.

#### Complete

Within one transaction:

1. Lock the assigned window.
2. Lock the requested serving ticket belonging to that window.
3. Update it to `completed`.
4. Calculate current wait/service metrics using existing definitions.
5. Update the wait-time log.
6. Set the window to `open`.
7. Add the feedback notification.
8. Write the activity log.
9. Commit.
10. Process near-turn alerts.

#### Skip and Void

- Lock and revalidate the serving ticket.
- Include the expected current status in updates.
- Set the existing reason/timestamp fields.
- Reopen the window.
- Preserve notifications and logs.
- Prevent Complete, Skip, and Void from overwriting one another.

#### Shared staff context

Replace SQL in `views/staff/includes/context.php` with functions from `window_queries.php`.

Preserve:

- Maximum 20 waiting tickets in current staff views
- Priority ordering
- Current client-type labels
- Void-timeout calculation
- Existing action URLs and JSON
- Existing activity messages

#### Verification

- No staff profile
- No active window
- Closed window
- No assigned service
- Empty queue
- Priority ordering
- Successful Call Next
- Duplicate concurrent Call Next
- Complete
- Skip
- Automatic void
- Complete versus timeout race
- Skip versus complete race
- Window state after success/failure
- Dashboard/My Window context consistency

Risk: High.

### Batch 6 — Administrator and Reports

#### Services

Move form parsing, validation, duplicate checks, CRUD, soft deletion, and listing into `service_catalog.php`.

Keep two adapters:

- HTML adapter used by `views/admin/services.php`
- JSON adapter used by `modules/settings/health_services_mgmt.php`

Preserve the current difference in accepted update fields between the two interfaces unless characterization proves they are already equivalent.

#### Staff accounts

Move validation, duplicate lookup, transactional user/staff insert, logging, and listing into `staff_accounts.php`.

Preserve:

- Staff role
- Verified status
- Existing password requirement
- Existing form fields
- Existing success/error behavior

#### Windows

Move input validation, assignment reads, create/update, edit lookup, and listing into `window_admin.php`.

Preserve:

- `open`, `busy`, and `closed`
- Optional service/staff assignment
- Existing form fields and page URL

#### Users

Move listing and activation changes into `modules/admin/users.php`.

Preserve self-deactivation prevention.

#### Settings

Historical implementation: grouping and updates were moved into
`settings_store.php` while preserving HTML and JSON interfaces.

Superseded July 27, 2026: explicit product approval removed the administrator
Settings page, its JSON endpoint, and the admin-facing setting projection/write
procedures. Runtime `getSetting()` access and the `system_settings` table remain
for queue, notification, and display-board compatibility.

#### Reports

Split each builder into its own file:

- Queue summary
- Predicted versus actual
- Peak hour
- Counter performance
- Turnaround time
- No show
- Staff productivity
- ML accuracy
- Daily/monthly stats
- Satisfaction

Preserve:

- Report keys and aliases
- Default date range
- Table column keys
- Metrics and insight text
- Chart JSON
- Empty-state behavior
- Wrapper endpoint URLs
- CSV filename and injection protection

#### Verification

- Every service action
- Service with/without ticket history
- Duplicate service code
- Staff creation and duplicate email
- Window creation/editing
- User activation/deactivation
- Runtime setting reads
- Dashboard metrics
- All ten report pages/endpoints
- Report aliases
- Invalid date ranges
- Empty report data
- CSV output and dangerous cell prefixes
- Admin authorization

Risk: Medium.

### Batch 7 — JavaScript

#### Final script ownership

`assets/js/main.js`

- Shared field validation
- Submit/busy state
- CSRF token lookup
- Password toggles
- OTP resend countdown
- Email-job dispatch
- Shared logout confirmation
- Shared safe HTML escaping

Add `assets/js/client.js`

- Service prediction
- Client queue status
- Notification polling
- Feedback AJAX submission
- Ticket printing

Add `assets/js/staff.js`

- Staff fetch actions
- Flash status
- Void countdown/checker
- Staff theme

Keep `assets/js/admin.js`

- Admin theme
- Charts
- Sidebar
- Reports menu
- Print
- Confirmation
- Logout modal
- User menu
- Pagination

Keep `assets/js/display.js`

- Display clock
- Display status polling
- Window/ticker rendering

#### Event rules

- One DOM-ready initializer per script.
- Each root uses a data attribute and an initialized flag to prevent duplicate listener attachment.
- Feature scripts return immediately when their root is absent.
- Polling functions keep an in-flight guard.
- Timers are cleared on `pagehide`.
- Fetch wrappers preserve credentials and CSRF headers.
- JSON parse failures produce the current user-facing fallback.
- No inline `onclick`, form-submit script, or feedback script remains in PHP views.
- Existing data attributes remain the DOM contract.

#### Legacy removal

Remove these only after source/browser verification:

- `startQueuePolling`
- `loadPredictedWaitTime`
- `startVoidCountdown`
- `startVoidChecker`
- `startNotificationPoller`
- `showBrowserNotification` if it remains reachable only through the removed poller

#### Script loading

- Auth pages: `main.js`
- Client queue/status/ticket/feedback pages: `main.js`, then `client.js`
- Staff layout: `main.js`, then `staff.js`
- Admin layout: `main.js`, then `admin.js`
- Display board: `display.js`

#### Verification

- `node --check` for every script
- No duplicate listeners/submissions
- Auth validation and password toggles
- OTP countdown and dispatch
- Prediction cancellation
- Queue refresh/retry
- Notifications
- Feedback errors/success
- Ticket print
- Staff actions and timer cleanup
- Admin themes/charts/sidebar/modals/pagination
- Display refresh and escaping
- Browser back-forward submit-button reset

Risk: Medium–High.

### Batch 8 — Complete CSS Refactor

Only the three existing stylesheet entry points remain:

- `assets/css/style.css`
- `assets/css/admin.css`
- `assets/css/display.css`

No `@import`, CSS build process, preprocessor, CSS framework, or new stylesheet URL will be introduced.

#### CSS consolidation procedure

For each stylesheet:

1. Capture screenshots of all affected pages and states.
2. Inventory selectors, media queries, custom properties, and state classes.
3. Identify exact duplicate selectors within the same cascade/media context.
4. Determine their current final computed values.
5. Move final effective values into one canonical declaration.
6. Keep declarations separate when media, theme, specificity, or state context differs.
7. Remove the superseded declaration only after computed-style comparison.
8. Preserve source-order dependencies.
9. Run page screenshots again after each surface group, not only after the complete rewrite.

#### `style.css` canonical order

1. File documentation and cascade rules
2. Root design tokens
3. Dark-theme staff tokens
4. Reset and document defaults
5. Typography
6. Accessibility, skip links, focus, reduced motion
7. Shared layout utilities
8. Shared buttons
9. Shared forms and validation
10. Shared cards, badges, tables, alerts, empty/loading states
11. Authentication pages
12. Client navigation and shell
13. Client service selection
14. Prediction states
15. Digital ticket
16. Public ticket lookup
17. Queue status
18. Feedback
19. Staff shell/sidebar/top bar
20. Staff current-ticket and action controls
21. Staff waiting queue
22. Staff window page
23. Staff activity log
24. Shared logout modal
25. Desktop responsive adjustments
26. Tablet responsive adjustments
27. Mobile responsive adjustments
28. Narrow-mobile adjustments
29. Reduced-motion overrides
30. Print rules

Consolidate repeated canonical selectors, including:

- `.auth-submit`
- `.auth-form-stable-errors .field-error`
- `.client-shell`
- `.digital-ticket-card`
- `.digital-ticket-layout`
- `.digital-ticket-number`
- `.public-ticket-card`
- `.staff-page`
- `.staff-app-shell`
- `.staff-sidebar`
- `.staff-nav`
- `.staff-nav-link`
- `.staff-topbar`
- `.staff-main`
- `.staff-action-status`
- `.staff-empty-state`
- `.staff-user-chip`
- `.logout-modal`
- `.logout-modal-actions`

#### `admin.css` canonical order

1. Root design tokens
2. Dark-theme token overrides
3. Admin reset/base behavior
4. Accessibility and focus
5. Shell and panel layout
6. Sidebar, navigation, and submenu
7. Top bar and user menu
8. Shared buttons, controls, forms, alerts, badges, and pills
9. Cards and metrics
10. Tables and pagination
11. Charts
12. Modal/dialog components
13. Dashboard
14. Services
15. Staff accounts
16. Windows
17. Users
18. Reports
19. ML logs
20. Desktop responsive rules
21. Tablet navigation/layout
22. Mobile tables/forms/layout
23. Narrow-mobile rules
24. Reduced-motion rules
25. Print rules

Merge the appended admin “refresh” layer into canonical declarations.

Consolidate repeated selectors, including:

- `.admin-main`
- `.admin-sidebar`
- `.admin-topbar`
- `.admin-topbar-actions`
- `.admin-action-button`
- `.admin-report-shell`
- `.admin-report-actions`
- `.admin-report-toolbar`
- `.admin-data-table td`
- `.admin-management-grid`
- `.admin-row-action`
- `.admin-modal-scrim`
- `.admin-logout-modal`
- `.admin-logout-modal-actions`
- `.admin-user-button`
- `.admin-model-metrics`

#### `display.css` canonical order

1. Display-specific tokens
2. Document/reset
3. Board layout
4. Header and clock
5. Service-window grid/cards
6. Ticket status and classification
7. Ticker and update state
8. Empty/loading/error states
9. HD display sizing
10. Smaller display sizing
11. Reduced motion

#### Token policy

Normalize repeated values into custom properties for:

- Colors
- Text colors
- Borders
- Backgrounds
- Spacing
- Radius
- Shadows
- Typography
- Transition duration
- Z-index

A value may be tokenized only if all affected declarations currently resolve to the same visual value. Similar-looking but behaviorally distinct values remain separate.

#### Visual compatibility matrix

Capture and compare these surfaces:

- Login
- Registration
- OTP verification
- Forgot/reset password
- Client dashboard
- Service selection
- Prediction loading/ML/fallback/error
- Digital ticket
- Public ticket lookup
- Queue status
- Feedback
- Staff dashboard
- Staff window
- Staff activity
- Admin dashboard
- Services
- Staff accounts
- Windows
- Users
- Reports
- ML logs
- Display board

Required viewport checks:

- 1440×900
- 1024×768
- 768×1024
- 390×844
- 360×800
- Display board at 1920×1080 and 1366×768
- A4 print preview for ticket and reports

Required state checks:

- Default
- Hover
- Keyboard focus
- Disabled
- Loading
- Empty
- Error
- Success
- Priority
- Waiting
- Serving/busy
- Completed
- Modal open
- Mobile navigation open
- Light/dark staff theme
- Light/dark admin theme
- Reduced motion
- Print

#### Exit criteria

- No page loses required styles.
- Existing stylesheet URLs and load order remain unchanged.
- No visible redesign occurs.
- All removed selectors have been reference-checked.
- Responsive, theme, accessibility, and print comparisons pass.
- CSS size and duplicate-selector counts are materially reduced.

Risk: High visual-regression risk.

### Batch 8A — Admin Management Table-First Bootstrap Modal Workflow

This explicitly approved UI revision is implemented and verified before the
final Batch 9 cleanup. It is a scoped workflow redesign rather than part of the
visual-equivalence CSS refactor.

#### Changes

- Convert Staff Accounts, Health Services, and Service Windows to full-width,
  table-first admin pages.
- Move each existing add/edit form into an accessible, responsive Bootstrap
  modal while keeping its management table visible at full width.
- Add responsive table toolbars with Add Staff, Add Service, and Add Window
  controls using native `data-bs-toggle="modal"` and page-specific
  `data-bs-target` contracts.
- Keep service and window edit deep links compatible and automatically open
  their server-populated modal forms.
- Submit valid staff, service, and window add/edit forms directly from their
  modals through the existing PHP POST workflow and shared busy state.
- Retain the reusable Bootstrap confirmation modal for service Hide, Restore,
  and Delete actions only.
- Reset dismissed add forms, return dismissed edit/error states to the clean
  page URL, and focus the first invalid or editable field when a modal opens.
- Preserve every existing route, POST field, CSRF token, session, role check,
  database operation, and server-side validation rule.
- Never redisplay, summarize, or place a staff password in the staff table or
  client-side state.
- Use the existing admin design tokens for Bootstrap forms, tables, cards,
  modal surfaces, focus states, light/dark themes, and responsive behavior.

#### Files

- `views/admin/add_staff.php`
- `views/admin/services.php`
- `views/admin/windows.php`
- `views/admin/includes/management_confirmation_modal.php`
- `assets/js/admin.js`
- `assets/css/admin.css`
- `tests/php/unit/admin_management_ui_characterization_test.php`
- `tests/js/run.js`

#### Exit criteria

- All three tables use the full available admin content width.
- Add forms are modal-hidden by default and open through native Bootstrap
  trigger attributes.
- Service and window edit URLs load authoritative values and automatically
  open the same modal used for creation.
- Server validation errors reopen the modal with values retained and focus the
  first invalid field.
- Valid add/edit forms submit exactly once without a secondary review modal.
- Service Hide, Restore, and Delete use the same accessible confirmation
  component without `window.confirm`.
- Staff passwords remain absent from tables, summaries, and repopulated input
  values.
- Desktop, tablet, and mobile layouts work in light and dark themes.
- PHP characterization, lint, Composer, JavaScript syntax/runtime, and
  authenticated browser checks pass.

### Batch 8B — Responsive Administrator Header Search

This approved navigation enhancement adds a shared Bootstrap search component
to the administrator header without introducing a search endpoint or changing
database behavior.

#### Changes

- Add a Bootstrap horizontal Collapse search field to the shared administrator
  header.
- Search all active administrator pages and the ten canonical report
  destinations using the existing report definitions.
- Support pointer selection, Enter submission, Arrow Up/Down navigation,
  Escape dismissal, clearing, empty results, and accessible listbox state.
- Keep the search visible in the desktop header and expand it horizontally
  within the existing topbar row through a 44px search button on small
  screens, without pushing page content downward.
- Keep the administrator account control visible beside the expanded search at
  tablet and standard phone widths; only sub-360px layouts temporarily
  prioritize the search field.
- Move theme switching into the System Administrator menu beside Logout while
  retaining the stored theme, adaptive icon, accessible pressed state, and
  light/dark label.
- Use existing `--admin-*` semantic tokens for light/dark surfaces, borders,
  text, focus, hover, and result states.
- Exclude the retired Activity Logs and Settings destinations.

#### Files

- `views/admin/includes/header.php`
- `assets/js/admin.js`
- `assets/css/admin.css`
- `tests/php/unit/admin_management_ui_characterization_test.php`

#### Exit criteria

- Search is available on every administrator page.
- Every active page and canonical report destination can be found and opened.
- Keyboard and pointer workflows are equivalent.
- Desktop, tablet, and narrow-mobile layouts remain operable in both themes.
- Initialization remains idempotent and causes no additional HTTP requests.
- PHP, JavaScript, and authenticated browser verification pass.

### Batch 8C — Horizontally Scrollable Report Charts

This approved report usability enhancement keeps dense charts readable without
changing report queries, projections, date filtering, exports, or table
alternatives.

#### Changes

- Wrap the shared Chart.js canvas in one keyboard-focusable horizontal scroll
  region used by all ten administrator reports.
- Derive the canvas minimum width from the number of chart labels and the
  longest label length, with separate density bounds for line and bar charts.
- Keep short datasets fitted to the available card width while allowing dense
  or long-labeled datasets to scroll without compressing axis labels.
- Preserve touch momentum scrolling, visible keyboard focus, reduced-motion
  behavior, light/dark chart tokens, insights, and the accessible report table.
- Keep horizontal overflow contained inside the chart card so the report page
  itself does not acquire a new horizontal scrollbar.

#### Files

- `views/admin/reports.php`
- `assets/js/admin.js`
- `assets/css/admin.css`
- `tests/php/unit/admin_reports_ui_characterization_test.php`

#### Exit criteria

- Every report with chartable data renders through the shared scroll region.
- Dense charts have a scroll width greater than their viewport width.
- Short charts remain fitted when their calculated width is smaller than the
  viewport.
- The chart region is reachable by keyboard and has an accessible scroll label.
- Chart.js theme refresh and report tables continue to work without data,
  route, or export changes.
- PHP, JavaScript, and authenticated responsive browser verification pass.

### Batch 8D — Login and Registration Form Vertical Rhythm

This approved UI correction keeps login and registration inline validation
space reserved while reducing excess space between consecutive fields.

#### Files

- `index.php`
- `views/client/register.php`
- `assets/css/style.css`
- `tests/php/unit/auth_ui_characterization_test.php`

#### Implementation

- Add one shared compact-feedback form hook without changing field names,
  validation attributes, endpoints, or authentication behavior.
- Keep field-level validation feedback close to its associated input and
  reserve a compact feedback slot to avoid noticeable layout movement.
- Use the existing authentication spacing scale so the email-to-password
  and registration field rhythm remains consistent on desktop and mobile.
- Remove the extra mobile gap between stacked first-name and last-name fields
  while retaining the desktop two-column layout.

#### Exit criteria

- Login and registration controls retain visible labels and validation.
- Consecutive field spacing is compact and visually consistent.
- Empty validation feedback remains reserved without introducing a large gap.
- Login authentication, registration OTP issuance, redirects, password
  visibility, and responsive layout remain unchanged.
- PHP characterization, lint, JavaScript syntax, and browser checks pass.

### Batch 8E — Shared Role Shell, Client Notifications, and Staff Manual Void

This approved feature batch shares role-neutral dashboard behavior without
copying administrator business capabilities into Staff or Client workflows.
It is implemented and verified as three gated stages.

#### Stage 1 — Shared authenticated shell

- `views/shared/includes/shell_header.php` and `shell_footer.php` own the
  authenticated Bootstrap sidebar, topbar, role navigation, search, profile,
  theme, logout modal, toast host, skip link, and semantic main region.
- Existing Admin and Staff include files remain compatibility/configuration
  adapters. Client receives matching include adapters.
- `assets/js/shell.js` owns idempotent role-neutral shell behavior. Admin,
  Staff, and Client scripts retain only role-specific features.
- The shared `smartqms-theme` key reads legacy Admin and Staff theme values as
  migration fallbacks.
- Narrow search expands horizontally within the topbar. It may hide the Client
  notification bell temporarily, but never replaces or covers the profile
  control and never creates a second header row.
- Search destinations remain restricted to the current role's authorized page
  set.

#### Stage 2 — Persistent Client notification center

- Authenticated Client pages display a Bootstrap notification dropdown in the
  shared topbar with latest-ten deterministic history and an unread badge.
- `get_notifications.php` is a non-consuming Client-only POST read.
- `mark_read.php` acknowledges only supplied notification IDs owned by the
  signed-in Client.
- Client polling starts immediately, repeats every ten seconds, pauses while
  hidden, prevents overlapping requests, and resumes on visibility restore.
- Opening the dropdown acknowledges only displayed unread entries. Failed
  acknowledgement preserves the badge.
- Supplemental browser notifications require a Client interaction and are
  deduplicated by notification ID in session storage. In-page history remains
  available when browser notification permission is unavailable or denied.
- Existing near-turn behavior remains two or fewer people ahead, once per
  ticket; existing SMS attempts and queue priority rules remain unchanged.

#### Stage 3 — Staff manual Void and shared confirmation

- `voidTicketForStaff()` locks the Staff member's assigned service window and
  verifies the requested ticket is currently serving at that window.
- A successful transition atomically sets `voided`, the current timestamp, and
  `Voided manually by staff`; reopens the window; records one activity entry;
  and creates one unread `turn_void` browser notification for the ticket owner.
- `modules/service_window/void_ticket.php` preserves Staff-role, POST, CSRF,
  JSON-envelope, and near-turn-processing conventions.
- Staff Skip and manual Void share one Bootstrap confirmation modal. Cancel,
  close, Escape, and backdrop do not mutate state; confirmation submits once
  and returns focus to its origin.
- Complete and Call Next retain their direct action workflows. Automatic
  timeout voiding remains separate and unchanged.
- Multi-process verification races manual Void against Complete, Skip, and
  automatic timeout and requires exactly one terminal transition.

#### Files

- `views/shared/includes/shell_header.php`
- `views/shared/includes/shell_footer.php`
- Admin, Staff, and Client authenticated include adapters and page entry points
- `views/staff/dashboard.php`
- `assets/js/shell.js`
- `assets/js/client.js`
- `assets/js/staff.js`
- `assets/css/style.css`
- `modules/notifications/notification_queries.php`
- `modules/notifications/get_notifications.php`
- `modules/notifications/mark_read.php`
- `modules/service_window/ticket_actions.php`
- `modules/service_window/void_ticket.php`
- notification, service-window, JavaScript, HTTP-contract, and concurrency
  characterization files

#### Exit criteria

- Shared shell navigation, search, profile, theme, logout, toast, responsive,
  keyboard, and role-restriction contracts pass.
- Client notification history is non-consuming, acknowledgement is
  ownership-safe, and polling is idempotent.
- Manual Void is atomic and cannot mutate a foreign or terminal ticket.
- Competing terminal requests create no duplicate notification or activity
  entry.
- Existing authenticated routes, sessions, queue ordering, reports, Staff
  actions, Client workflows, and database schema remain compatible.
- PHP, JavaScript, isolated database, concurrency, and responsive browser
  verification pass or any environment limitation is recorded explicitly.

### Batch 9 — Cleanup and Documentation

#### Cleanup

Remove only code verified by:

- Repository-wide reference search
- Runtime/page characterization
- Browser workflow coverage
- Available Apache access history
- Compatibility review

Retain:

- Report wrapper endpoints
- Queue ticket JSON endpoint
- Any other public PHP endpoint without explicit removal approval
- Composer Bootstrap dependency until separately approved
- Runtime schema compatibility behavior until deployed databases are verified upgraded

Explicitly removed July 27, 2026:

- Administrator Activity Logs page and sidebar entry
- Administrator Settings page and sidebar entry
- Settings JSON read/write endpoint
- Admin-only setting projection and write procedures

The `activity_logs` and `system_settings` database tables, activity-write
procedures, and runtime setting lookup remain because active operational
workflows depend on them.

#### Documentation

Update `README.md` and add an architecture guide documenting:

- Bootstrap and configuration
- Shared procedural modules
- Authentication and session keys
- Queue/ticket lifecycle
- Priority behavior
- Prediction and fallback boundary
- Notifications
- Staff state transitions
- Admin modules
- Report builders
- JavaScript ownership and data attributes
- CSS organization and token ownership
- Database setup and compatibility
- Test database safety
- ML environment restoration
- Where future developers should add validation, queries, handlers, scripts, and styles

#### Final verification

- Full PHP lint
- Composer validation
- All PHP unit/integration tests
- All JavaScript syntax checks
- ML tests
- Test-database integration suite
- Concurrency tests
- Authenticated client/staff/admin browser matrix
- Display-board verification
- All report and CSV checks
- Responsive/theme/print styling matrix
- Repository-wide route, field, session, JSON, selector, and endpoint compatibility audit
- Final working-tree diff review

Risk: Low–Medium.

### Batch 8F — Client Feedback Modal and Real-Data ML Reporting

Status: Implemented and verified after Batch 8E.

#### Client ticket lifecycle

- My Ticket renders no page content when the client has never joined the queue
  or has no active/pending-feedback ticket.
- A completed ticket remains available only while its one allowed feedback
  response is pending.
- Submit Feedback uses a centered, scrollable Bootstrap modal on My Ticket.
- The existing feedback page route remains as a compatibility adapter that
  reopens the ticket modal.
- Successful feedback uses the shared toast and returns My Ticket to its empty
  state.

#### Real-data prediction pipeline

- Model training reads the stored feature snapshot and completed ticket
  timestamps from `wait_time_logs` and `queue_tickets`; CSV input is retired.
- The target is issue-to-service-start wait time, limited to the supported
  0–480 minute operating range.
- Training uses a chronological holdout, zero-safe MAPE, atomic model
  replacement, environment-based database configuration, and a minimum real
  sample threshold.
- Model artifacts carry source, feature, algorithm, sample, and metric
  metadata. The inference API rejects legacy or synthetic/unverified artifacts
  so the PHP fallback remains authoritative until enough live observations
  exist.

#### Reports

- Predicted vs Actual Wait derives actual wait from ticket timestamps and
  excludes invalid/outlier observations.
- ML Accuracy excludes synthetic comparison rows, prefers the latest verified
  live-data training run, and otherwise reports observed accuracy from valid
  completed tickets.
- Training writes real comparison metrics to `ml_comparison_logs` and updates
  the existing ML system-setting metadata without a schema change.

Risk: Medium until the database accumulates the configured minimum number of
valid completed tickets for a verified model training run.

#### Batch 8F approved follow-up — queue_data.csv accuracy source

- The approved ML source is now `ml/dataset/queue_data.csv`;
  `synthetic_queue_data.csv` remains excluded.
- Arrival, start, finish, wait, and queue-length fields are validated. Invalid,
  negative, non-finite, and out-of-range observations are excluded.
- Hour, weekday, and service duration are derived from recorded timestamps.
  Neutral constants preserve the prediction interface for service, client,
  and active-window dimensions that this CSV does not contain.
- ML Accuracy uses verified comparison rows marked with this CSV source.
  Before Python training is available, it reports a clearly labelled
  queue-length baseline from an 80/20 chronological holdout of the same file.
- The Admin dashboard uses bounded issue-to-service timestamp calculations,
  preventing legacy multi-day ticket durations from distorting actual waits.

Remaining risk: the verified four-algorithm comparison cannot be produced until
the local Python runtime is restored. The CSV lacks service type, client
classification, and active-window history, so per-category accuracy must not be
inferred from it.

### Batch 8G — Project-Wide Native HTML5 Form Validation

Status: Implemented and verified.

#### Native constraint contract

- Production data-entry forms use browser constraint validation instead of the
  retired JavaScript rule metadata and generated success states.
- Login, registration, OTP verification, password reset, queue entry, Staff,
  Health Service, Service Window, and Client feedback retain their established
  routes, methods, field names, CSRF inputs, retained values, and PHP validation.
- Required, email, minimum-length, six-digit OTP, Philippine mobile-number,
  numeric-range, and required service-selection rules are expressed in HTML.
- Optional staff phone, service description, and window assignments remain
  valid when empty.
- Password confirmation is the only cross-field browser rule and uses
  `setCustomValidity()` because HTML cannot express equality between controls.

#### Shared behavior and feedback

- `assets/js/main.js` clears server-rendered field errors after edits, gates the
  confirmation and busy-state flows behind `checkValidity()`, and delegates the
  first-invalid-control tooltip and focus to the browser.
- Client feedback checks native validity before beginning its AJAX request.
- Admin modal reset clears PHP invalid state and custom password validity while
  preserving Bootstrap modal focus and dismissal behavior.
- Application-generated green valid states and their unused CSS were removed.
  PHP errors remain inline and authoritative after a server round trip.
- Search, report filters, OTP resend, logout, destructive confirmations, and
  other action-only forms retain their existing behavior. The unlinked
  `try.php` SMS prototype remains outside this production UI contract.

Risk: Low. Native tooltip wording and presentation vary by browser and operating
system language; server validation remains the security and business-rule
authority.

### Batch 8H — Secret-Safe FMCSMS Provider Adapter

Status: Implemented; live delivery verification awaits local credentials.

- The SMS boundary supports the approved FMCSMS HTTPS/JSON contract while
  retaining Semaphore as a compatibility adapter and simulation as the default.
- API credentials and the registered sending number are loaded only from the
  ignored `config/sms.local.php` file or `SMARTQMS_SMS_*` environment variables.
- Provider requests use TLS verification, bounded timeouts and response size,
  deterministic Philippine mobile normalization, and no redirect following.
- A CLI-only, explicit-opt-in smoke test sends exactly one generic message and
  never prints the API key, recipient, sender number, or message body.
- Existing queue alert generation and `sms_logs` behavior remain unchanged;
  failed provider attempts now retain a non-secret diagnostic in `error_msg`.

Risk: Medium until the approved FMCSMS API key, registered `FromNumber`, and an
approved test recipient are configured locally and the one-message smoke test
is accepted by the provider and received on-device.

### SmartQMS Revision — Supabase Integration Phases 1–6

Status: Implemented with local-environment gates passing; live Supabase and
Python execution remain environment-blocked until development credentials and
Python are available.

- Phase 1 inventories and maps all current routes, fields, statuses, roles, and
  identifiers in `docs/SUPABASE_MIGRATION_MAPPING.md`.
- Phase 2 adds fail-safe `local`, `shadow`, and `supabase` provider modes, a
  server-only REST client, browser-safe adapters, a normalized PostgreSQL
  schema, RLS policies, and Realtime publication configuration.
- Phase 3 implements the database-driven four-step customer booking flow while
  preserving the existing PHP route and ticket presentation.
- Phase 4 routes Staff queue mutations and Admin service/window/staff
  operations through provider-neutral procedural gateways and transactional,
  service-role-only PostgreSQL functions.
- Phase 5 subscribes Client, Staff, and Admin surfaces to ticket, counter, and
  queue-event changes; partial main-content refresh replaces full reloads.
  Python responses now require bounded minutes, confidence, and a model version,
  and MySQL/Supabase store prediction provenance with schema compatibility.
- Phase 6 adds bearer-token verification, server-derived prediction inputs,
  compatibility APIs, audited branch and Super Admin role RPCs, and the gated
  migration/cutover/rollback runbook in `docs/SUPABASE_ROLLOUT_RUNBOOK.md`.

The existing Admin and Staff layouts, local routes, session behavior, MySQL
schema compatibility, queue ordering, and permission boundaries remain the
default. Browser code receives only the Supabase publishable key; the
service-role key and Python token remain server-only.

### Batch 8I — Barangay Health QMS Blueprint Compatibility

Status: Implemented; database migration and model retraining remain explicit
operator actions.

- The blueprint is implemented as an additive compatibility layer over the
  established `smartqms` schema. Existing `users`, `health_services`,
  `service_windows`, and `queue_tickets` remain authoritative, while username,
  job title, service fallback/visibility, counter numbering, many-to-many
  counter services, public ticket tokens, entry type, and lifecycle aliases
  provide the requested interfaces without duplicating production entities.
- Unified login accepts email or username. Staff are routed through an
  intermediate, transactional counter-claim screen before entering their
  workstation; existing Admin and Client redirects remain unchanged.
- Public and kiosk intake creates new 256-bit (64-hex-character) tokenized
  tickets while retaining compatibility with previously issued 128-bit links,
  QR tracking links, and either waiting walk-ins or scheduled online entries.
  The unauthenticated tracker polls every five seconds, exposes state-specific
  messaging, and gates completed tickets behind one feedback response.
- The Staff workstation implements the active-ticket, walk-in intake, and
  waiting-grid zones. Recall, Start Service, Complete, Skip, manual Void, and
  automatic timeout behavior share the existing transaction and permission
  boundary.
- Admin Staff management includes username/job title; Health Services includes
  fallback minutes and visibility; Service Windows includes a counter number
  and synchronized multi-service mappings.
- `/public-display/` is an unauthenticated, read-only calling board with an
  interaction-gated chime and text-to-speech announcement, a reduced-motion-
  safe visual call emphasis, and a three-second refresh interval.
- Call Next first executes the configured stale-calling cleanup (10 minutes by
  default), and its guarded transition accepts both waiting walk-ins and
  scheduled online entries without weakening priority/FIFO ordering.
- `scripts/export_ml_history.php` atomically exports completed database queue
  observations to `ml/dataset/queue_data.csv`. Random Forest remains a compared
  deployment candidate, synthetic generation stays disabled, and both Flask
  compatibility and FastAPI `/predict`/`/health` gateways enforce the same
  verified artifact and feature contract.
- Satisfaction, Predicted vs Actual, and ML Accuracy continue through the
  existing database-backed report framework and bounded real-wait filters.

Remaining risk: apply `database/upgrade_blueprint_2026_08_21.sql` only after a
verified database backup. A verified model cannot be retrained until at least
30 completed observations exist and the local Python runtime is restored.

### Batch 8J — Public Landing, Arrival Check-In, Strict FIFO, and Staff Queue Operations

Status: Implemented in five verified stages. This explicitly approved workflow
supersedes the older Client-account, priority-order, and no-schema-change
assumptions elsewhere in this historical plan.

#### Stage 1 — Queue-domain and database foundation

- `database/upgrade_arrival_checkin_fifo_2026_08_21.sql` is idempotent and the
  canonical schema includes exact client-name fields, physical check-in and
  Scheduled-expiry timestamps, check-in provenance, nullable pre-arrival queue
  numbers, FIFO indexes, print batches, and number reservations.
- Existing Client users and legacy ticket, classification, routing, and status
  columns remain available for audit and database compatibility.
- Legacy single-service counter assignments are copied idempotently into
  `counter_services`; that mapping is authoritative after migration.
- Active live rows have priority zero. Queue selection uses
  `checked_in_at ASC, ticket_id ASC` and locks rows within transactions.
- Online tickets remain Scheduled until arrival. Check-in assigns an available
  reserved number first, otherwise the next independent daily service number.
- Calling timeout is five minutes and is processed through one idempotent
  lifecycle service.

#### Stage 2 — No-account public workflow

- `/` is a responsive Bootstrap Barangay Health Center landing page with Join
  Live Queue, privacy-minimized reference lookup, and Staff/Admin Sign In.
- `/login/` is the unified Staff/Admin authentication entry point. Historical
  Client accounts remain stored but cannot register or authenticate through the
  production interface.
- `/queue/join/` collects first name, last name, Philippine mobile number, and
  visible service. It creates a same-day Scheduled record without a queue
  number or prediction and produces a 256-bit private tracking token and QR.
- Reference lookup never exposes a name, phone, token, or internal identifier.
  Private-token tracking retains live status and one-feedback-per-completed-
  ticket behavior.

#### Stage 3 — Arrival check-in and Staff lifecycle

- `/staff/check-in/` provides Scan QR, Manual Input, and Walk-In Bootstrap tabs.
  Camera access begins only after Staff interaction, prefers a front-facing
  camera, uses `BarcodeDetector`, and always exposes Manual Input fallback.
- QR and reference lookup are read-only. A shared Bootstrap confirmation locks
  and checks in an unexpired Scheduled ticket exactly once.
- Walk-In records enter Waiting immediately. Successful arrival records Staff,
  method, physical time, number, activity, and prediction snapshot atomically.
- Staff workspaces retain mandatory counter selection and expose KPI cards,
  Call Next, Recall, Start, Complete, Skip, Void, and a responsive FIFO queue
  table with the approved client and ticket columns.
- `scripts/process_queue_timeouts.php` is the CLI entry point intended for a
  once-per-minute Windows Task Scheduler job.

#### Stage 4 — Configuration, print batches, and public display

- Admin Staff fields are name, email, optional username, temporary/new
  password, required designation (including required custom Other), and active
  state.
- Counters use system-generated `CNT-###` numbers, editable labels and active
  state, and authoritative `counter_services` checkboxes.
- Services use system-generated service number and ML category plus name,
  description, standard duration, and Show/Hide/Archive. Legacy routing and
  priority fields remain stored but are absent from normal configuration.
- Staff may persist current-day, non-overlapping per-service ranges of at most
  200 and download an A4 PDF containing eight anonymous queue slips. Dompdf is
  Composer-managed; a missing batch never blocks normal numbering.
- `/public-display/` and the Staff mirror share one privacy-minimized endpoint,
  poll every three seconds, show distinct SERVING and WAITING regions, paginate
  large waiting lists, and retain chime, flash, and supported speech for new
  Calling tickets.

#### Stage 5 — ML and reporting alignment

- Active-counter prediction features count only active Staff counters mapped
  through `counter_services`. Queue length counts only checked-in Waiting rows
  for the requested service.
- Actual wait is `COALESCE(checked_in_at, issued_at)` to
  `COALESCE(started_at, served_at)`. The second value is only a historical
  compatibility fallback; call and completion timestamps are not wait labels.
- Dashboard volume, Queue Summary, Peak Hour, Daily/Monthly Stats, turnaround,
  counter performance, and staff productivity exclude Scheduled records or use
  completed lifecycle rows as appropriate.
- `scripts/export_ml_history.php` writes only valid completed database
  observations to `ml/dataset/queue_data.csv`. The Python loader rejects the
  former five-column sample/synthetic format and requires the database-export
  feature contract.
- Model training remains safely gated at 30 valid observations and never
  replaces a deployed artifact on insufficient input. PHP fallback prediction
  remains active when no verified model exists.

#### Operational follow-up

- Configure at least one active Staff counter and its assigned services before
  accepting public arrivals.
- Register `php scripts/process_queue_timeouts.php` in Windows Task Scheduler
  once per minute so calling expiry does not depend on an open browser.
- Re-export and retrain only after 30 valid completed observations exist; the
  implementation-time database export contained four valid observations, so
  retraining was correctly refused by policy.
- Camera verification still requires supported hardware, HTTPS/localhost
  permission, and an authenticated Staff session.

## 6. Required Batch Report Format

After every batch, report:

- Batch objective
- Files added
- Files modified
- Behavior that remained unchanged
- Procedural/modular/event-driven/declarative techniques applied
- Tests executed
- Exact results
- Remaining risks
- Deferred compatibility items
- Confirmation that unrelated user changes were preserved

Do not begin a later high-risk batch until the preceding batch passes its exit criteria.

## 7. Final Acceptance Criteria

The refactor is complete only when:

- Login, registration, OTP, reset, logout, and role redirects work.
- Client, staff, and administrator authorization remains enforced.
- Clients can select services and join the queue.
- Priority and priority-only rules remain correct.
- Concurrent requests cannot create duplicate active tickets.
- Ticket and reference generation remains compatible and reliable.
- QR tickets, ticket lookup, status, prediction, and display board work.
- ML predictions and PHP fallback work.
- Staff Call Next, Complete, Skip, Void, and window operations are atomic.
- Admin services, staff, windows, users, settings, dashboards, and reports work.
- All existing report URLs, aliases, JSON, charts, and CSV exports remain compatible.
- JavaScript is organized by events/features and does not attach duplicate listeners.
- PHP views contain materially less validation, SQL, and business logic.
- Database access is prepared, focused, and passed explicitly.
- Existing request-time schema compatibility is isolated and documented.
- All three CSS files are fully reorganized and deduplicated.
- Authentication, client, staff, admin, public ticket, feedback, reports, print, responsive, theme, and display styling remain visually compatible.
- No application OOP architecture is introduced.
- No production schema, dependency, route, session, form, API, or workflow changes are introduced.
- Documentation clearly explains the resulting structure and extension points.

## 8. Explicit Assumptions and Deferred Approval Items

- The current working tree is authoritative, including uncommitted files.
- “Refactor styling for all” means full structural CSS cleanup with visual equivalence, not a redesign.
- Python is included only as the prediction/test boundary; model redesign is out of scope.
- A disposable test database may use the existing schema unchanged.
- Existing dependencies may be reinstalled locally but not added, removed, or upgraded.
- Public endpoint removal is deferred.
- Composer Bootstrap removal is deferred.
- Changing unauthorized JSON/redirect behavior is deferred.
- Removing request-time schema compatibility DDL is deferred.
- Changing display-token transport is deferred.
- Any required production schema, route, API, session, or workflow change must stop implementation and request approval first.

## Batch 8K — High-Fidelity Civic Healthcare UI/UX Redesign

Status: implemented and verification-gated.

### Objective

Apply one accessible, responsive SmartQMS identity to the public, Staff, and
Admin journeys while preserving the Batch 8J queue lifecycle, strict FIFO,
database compatibility, routes, POST fields, CSRF, sessions, permissions, ML,
reports, and printing behavior.

### Implemented design contract

- A repository design system now lives under `design-system/smartqms/`, with a
  master specification and Public, Staff, and Admin page overrides.
- The approved 60/30/10 hierarchy uses `#F4FAFF`, `#113264`, and `#0090FF`
  through semantic `--sq-*` tokens. Accessible lifecycle colors remain
  explicit exceptions and never communicate state by color alone.
- Poppins owns headings and queue numbers; Inter owns interface and body copy.
  Both are locally hosted with their licenses.
- The refined heart/pulse/queue identity is one reusable local SVG. Lucide and
  Chart.js are also served locally, eliminating production visual-asset CDN
  requests from the shared application shell.
- Shared focus, 44px target, spacing, surface, form, card, modal, table, toast,
  chart, navigation, reduced-motion, and light/dark contracts are centralized
  in `assets/css/style.css`. Existing variables remain available as migration
  aliases; `admin.css` consumes the shared semantic tokens for Admin-only
  analytics, report, and management layouts.

### Wireframe gallery

`docs/ui-ux/wireframes/` is a static, non-production comparison gallery. It
contains fictional-only Public, Staff, and Admin screens with 1440px, 768px,
and 390px frame controls plus light/dark and component-state controls. It makes
no application API requests and is not linked from production navigation.

### Production adoption

- Public landing, booking, tracker, unified Staff/Admin login, and public
  display use the shared identity, local typography, responsive surfaces, and
  theme behavior. The landing page adds lifecycle, FIFO, same-day, and privacy
  guidance without altering public intake or reference lookup.
- The authenticated shell provides the same responsive navigation, search,
  profile, theme, logout, toast, focus, and brand behavior to Staff and Admin.
- Staff KPI, active-ticket, FIFO table, check-in, counter, batch, and display
  surfaces use the shared component hierarchy; Staff actions and lifecycle
  logic remain unchanged.
- Admin dashboard, management tables/modals, and report toolbars, metrics,
  scrollable charts, and exact tables consume the same tokens while retaining
  their role-specific structure and contracts.

### Compatibility and exit criteria

- No database migration, endpoint, request method, API envelope, queue rule,
  session key, role, or form field was introduced or changed by this batch.
- Native HTML validation and PHP-authoritative validation remain intact.
- Production visual assets are locally served; wireframes remain isolated.
- Characterization tests cover the design documents, target gallery frames,
  local assets, semantic token aliases, themes, and preserved login/booking
  form contracts.
