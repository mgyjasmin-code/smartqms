# Smart QMS Final Implementation Specification

**Project:** Smart Queue Management System with Machine Learning-Based Waiting Time Prediction  
**Primary deployment:** Small Barangay Health Center / barangay service facility  
**Stack:** PHP 8.x, MySQL 8.x, Bootstrap 5, custom CSS, vanilla JavaScript, separate Python Random Forest ML service  
**Document status:** Final implementation source of truth based on the approved brainstorming decisions  

> When this file conflicts with an older Smart QMS prompt or specification, follow this file for queue architecture and business rules. Continue using `SmartQMS_UIUX_Design_Guide.md` as the visual-design reference.

---

## 1. Purpose

This document translates the final Smart QMS decisions into implementable requirements. It defines:

- what existing features must be retained;
- what behaviors must be revised;
- what new features and database changes must be added;
- what overly complicated or unsafe behaviors must be removed;
- how the Central Shared Queue and Specialized Queue operate together;
- how staff, windows, health services, tickets, notifications, reports, and ML prediction interact;
- the recommended implementation phases, backend rules, and acceptance tests.

The goal is a QMS that is simple enough for a small barangay facility but flexible enough to support one or more specialized services such as Dental.

---

## 2. Final Architecture Decision

Smart QMS will use a **Hybrid Queue Architecture**.

### 2.1 Central Shared Queue

The Central Queue contains services that any general staff member at any shared window can accommodate.

Examples:

- General Consultation
- Vaccination, only if all general staff are allowed to perform it
- Maternal Care assistance, only if all general staff are allowed to perform it
- Document or general barangay-health assistance

All waiting tickets under Central services enter one pooled queue. Any open shared window calls the next eligible Central ticket.

### 2.2 Specialized Queue

A Specialized Queue is used when a service requires a qualified staff member, special equipment, or a dedicated room/window.

Example:

- Dental Services
- Staff John is qualified for Dental
- Window 3 / Dental Room handles Dental
- General staff at Window 1 and Window 2 cannot call Dental tickets

Each Specialized service has its own filtered queue, but it remains part of the same Smart QMS application, reporting system, notification service, public display, and ML dataset.

### 2.3 Core routing principle

```text
Central service
→ Central pooled queue
→ Any open shared window

Specialized service
→ Queue filtered by specialized service
→ Specialized window
→ Staff qualified for that service
```

### 2.4 Example facility

| Window | Window type | Current staff | Queue handled |
|---|---|---|---|
| Window 1 | Shared | Staff Mark | Central Queue |
| Window 2 | Shared | Staff Ana | Central Queue |
| Window 3 / Dental Room | Specialized | Staff John | Dental Queue only |

All three windows belong to one small barangay facility. The specialized queue does not require a separate application.

---

## 3. Key Concepts That Must Stay Separate

| Concept | Meaning | Example |
|---|---|---|
| Health Service | What the client needs | General Consultation, Dental |
| Queue Mode | How tickets for the service are routed | Central or Specialized |
| Service Window | Physical place where the client will be served | Window 1, Dental Room |
| Staff Capability | Specialized service the staff is qualified to handle | Staff John → Dental |
| Runtime Window Assignment | Window currently operated by a staff member during a shift | Staff John currently opens Window 3 |

Do not treat staff capability as a permanent daily window assignment.

- Capability answers: **What specialized service can this staff member handle?**
- Runtime assignment answers: **Where is this staff member working now?**

---

## 4. Final Scope Matrix

### 4.1 Retain

Retain these existing or planned features:

- Unified login page for Client, Staff, and Admin
- No role selector on login
- Client registration and OTP verification
- Admin-created Staff accounts only
- Role-based access control
- Health Services management
- Service Windows management
- Regular, Senior Citizen, and PWD classifications
- Priority ordering
- One active ticket per client
- Virtual ticket and reference number
- QR-based ticket tracking
- People-ahead calculation
- ML-based predicted waiting time
- Browser and SMS notifications
- Staff actions: Call Next, Skip, Complete, Void
- Recall action, after backend implementation
- Public display board
- Feedback after completed service
- Reports and analytics
- Activity logs
- System settings
- ML comparison logs and accuracy reports

### 4.2 Revise

Revise these behaviors:

| Current behavior | Final behavior |
|---|---|
| Admin permanently assigns every staff member to a window | Staff selects and opens an eligible available window during the shift |
| Admin permanently assigns every window to a general service | Shared windows serve the Central Queue; only specialized windows require a service |
| Admin manually chooses Open, Busy, or Closed during window creation | Admin enables/disables the window; Staff opens/closes it; system automatically sets Busy while serving |
| Every service has a separate queue | Central services share one pooled queue; specialized services use filtered queues |
| Client ticket receives a window during queue creation | `window_id` remains `NULL` until a staff member calls the ticket |
| Active-ticket check covers only `waiting` and `serving` | Active-ticket check covers `waiting`, `serving`, and `skipped` |
| Admin manually enters ML value | ML category value is generated and maintained by the system; Admin sees it read-only |
| Service availability depends only on `is_active` | Join availability also requires an appropriate open or busy window |

### 4.3 Add

Add these features:

- `central` and `specialized` queue modes
- `shared` and `specialized` window types
- Specialized staff capability mapping
- Dynamic staff-to-window assignment during a shift
- Dynamic service joinability based on active windows
- Client ticket cancellation
- Recall workflow
- No-show reporting through standardized `voided_reason`
- Automatic end-of-day expiration of unresolved tickets
- Secure QR tracking contract
- Staff first-login temporary-password change
- Atomic Call Next transaction to prevent duplicate calls by two windows
- Fallback waiting-time calculation when ML data is unavailable

### 4.4 Remove from required UI

Remove these required inputs or behaviors:

- Permanent Staff → Window assignment during Staff registration
- Permanent Central Service → Staff assignment
- Permanent Central Service → Shared Window assignment
- Admin-selected `busy` status
- Admin-editable ML value
- Allowing a client to join a specialized service when no qualified specialized window is operating
- Allowing staff to manually choose any waiting ticket without respecting the queue rule

### 4.5 Defer

Keep these out of the first implementation unless specifically approved later:

- Multi-barangay switcher or multi-tenant interface
- Multi-branch enterprise management
- Appointment scheduling
- Fully remote queue joining with arrival confirmation
- One ticket moving across several service stages
- WhatsApp integration
- Native mobile application
- Automated AI staff scheduling
- Complex priority ratios or configurable fairness algorithms

---

## 5. Roles and Permissions

### 5.1 Client

The Client can:

- register and verify an account;
- log in using phone number and password;
- view active health services;
- see whether a service is currently available;
- select one service;
- confirm Regular, Senior Citizen, or PWD classification for the visit;
- join an eligible queue;
- view one active ticket;
- view people ahead and approximate waiting time;
- track the ticket through QR code;
- receive browser/SMS notifications;
- cancel a `waiting` ticket;
- submit one feedback entry after completion.

The Client cannot:

- create another ticket while an existing ticket is `waiting`, `serving`, or `skipped`;
- select hidden/inactive services;
- join a specialized service without active qualified capacity;
- modify priority level directly;
- access another client's ticket;
- cancel a ticket already being served.

### 5.2 Staff

The Staff can:

- log in using an Admin-created account;
- change temporary password on first login;
- select an eligible available window;
- open or close the selected window;
- view the queue allowed for that window;
- call the next eligible ticket;
- recall a skipped ticket;
- skip, complete, or void the current ticket;
- view personal activity logs.

The Staff cannot:

- self-register;
- open two windows simultaneously;
- open a window already operated by another staff member;
- open a specialized window without the required capability;
- call a specialized ticket from a shared window;
- call a Central ticket from a specialized-only window;
- manually bypass the queue order without an approved, logged override;
- access Admin settings or user-management pages.

### 5.3 Administrator

The Admin can:

- create/deactivate Staff accounts;
- reset a staff temporary password;
- assign specialized capabilities to staff;
- manage Health Services;
- configure queue mode per Health Service;
- create and enable/disable Service Windows;
- configure specialized window-service mapping;
- monitor queue and priority activity;
- configure queue, SMS, display, and ML settings;
- view reports and system-wide activity logs.

The Admin should not routinely operate live staff window states. Operational states belong to Staff/system behavior.

---

## 6. Administrator Configuration

## 6.1 Staff Accounts Page

### Required fields

```text
First Name
Last Name
Email
Phone Number
Temporary Password
Account Status: Active / Inactive
```

The role is automatically `staff`; do not show a role selector on the Add Staff page.

### Optional specialized capabilities

Show an optional section:

```text
Specialized Services This Staff Can Handle
[ ] Dental Services
[ ] Other approved specialized service
```

Do not list Central services here because all general staff are assumed to handle the Central Queue. If a service cannot be handled by all general staff, configure it as Specialized.

### Account rules

- Phone number must be unique and match `^09\d{9}$`.
- Email must be unique if required by the deployment.
- Hash temporary passwords with `password_hash()`; never store plaintext.
- Set `must_change_password = 1` for new Staff accounts.
- On first successful login, redirect Staff to Change Password.
- After a successful change, set `must_change_password = 0`.
- Deactivate instead of delete, preserving reports and activity history.
- Staff creation and deactivation must be written to `activity_logs`.

### Remove from this page

Do not require:

```text
Permanent Assigned Window
Permanent Central Service
Current Window Status
```

---

## 6.2 Health Services Page

### Final fields

| Field | Behavior |
|---|---|
| Service Name | Client-facing name |
| Client Description | Plain-language explanation shown to clients |
| Service Code | Stable unique internal code; auto-generated, editable only before first use |
| Queue Handling | Central Shared Queue or Specialized Queue |
| ML Category ID | System-generated, read-only |
| Display Order | Controls service order on Client page |
| Available for Queueing | Maps to `is_active`; does not delete historical records |
| Priority Only | Optional advanced setting; default Off |

### Service Code

Examples:

```text
General Consultation → CONSULT
Vaccination → VACC
Dental Services → DENTAL
```

Use it for internal consistency, exports, optional ticket prefixes, and integrations. Do not display it prominently to clients.

### ML Category ID

`service_encoded` is maintained by the system.

- Admin must not type or change it manually.
- It remains stable after tickets reference the service.
- A newly added service may not have enough historical data.
- Until retraining and enough records are available, use a non-ML fallback estimate.

### Display Order

Keep the value but provide user-friendly controls:

```text
Move Up
Move Down
```

or a keyboard-accessible ordering control. The backend still stores the numeric order.

### Available for Queueing

`is_active = 1` means the barangay currently offers the service in general. It does not guarantee that the service is immediately joinable.

Actual joinability is calculated dynamically:

```text
Service is active
AND queue is open
AND daily capacity is not full
AND at least one appropriate window is open or busy
```

### Priority Only

Do not use this field to represent normal Senior/PWD priority. Normal priority applies through `client_type` and `priority_level`.

Use `priority_only = 1` only if a real service is exclusively available to Senior/PWD clients. Otherwise keep it Off.

---

## 6.3 Service Windows Page

### Final Admin fields

| Field | Required? | Behavior |
|---|---:|---|
| Window Name | Yes | Physical counter/room name |
| Location / Description | Optional | Helps Staff/Admin identify the physical location |
| Window Type | Yes | Shared or Specialized |
| Specialized Service | Conditional | Required only when Window Type is Specialized |
| Enabled | Yes | Admin configuration state |

### Shared window

```text
Window Name: Window 1
Window Type: Shared
Specialized Service: Not applicable
Enabled: Yes
```

A Shared window calls tickets from the Central Queue.

### Specialized window

```text
Window Name: Window 3 / Dental Room
Window Type: Specialized
Specialized Service: Dental Services
Enabled: Yes
```

A Specialized window calls only tickets belonging to its configured specialized service.

### Remove from Admin create/edit form

Do not require:

```text
Permanent Staff Assignment
Open / Busy / Closed status selection
Central service assignment for shared windows
```

### Runtime status ownership

| State | Controlled by | Meaning |
|---|---|---|
| Enabled / Disabled | Admin | Whether the window can be used |
| Closed | Staff/system | No Staff currently accepts tickets |
| Open | Staff | Ready to call the next ticket |
| Busy | System | A ticket is currently being served |

---

## 7. Staff Window Workflow

### 7.1 Start shift

1. Staff logs in.
2. System checks active status and first-login password requirement.
3. Staff opens `My Window`.
4. System lists only enabled, closed, unoccupied windows the staff can operate.
5. Shared windows are available to any active general staff.
6. Specialized windows are available only if Staff has the matching capability.
7. Staff selects a window and presses `Open Window`.
8. Backend atomically sets:

```text
service_windows.staff_id = current staff_id
service_windows.status = open
```

9. Write an `open_window` activity log.

### 7.2 While serving

- `Call Next` selects the next ticket according to the window type.
- On success, window becomes `busy`.
- On Complete, Skip, or Void, the window returns to `open` unless the Staff closes it.

### 7.3 Close window

Staff may close only when no ticket is currently `serving` at the window.

On close:

```text
service_windows.status = closed
service_windows.staff_id = NULL
```

Write a `close_window` activity log.

### 7.4 Concurrency rules

- One Staff cannot operate more than one `open` or `busy` window.
- One Window cannot be operated by more than one Staff.
- Opening a window must use a transaction and recheck availability.
- Calling a ticket must lock the selected ticket to prevent two windows from calling the same ticket.

---

## 8. Queue Availability Rules

### 8.1 Central service availability

A Central service is joinable when:

```text
health_services.is_active = 1
AND health_services.queue_mode = central
AND queue operating hours permit joining
AND daily queue maximum is not reached
AND EXISTS at least one enabled shared window with status IN (open, busy)
```

### 8.2 Specialized service availability

A Specialized service is joinable when:

```text
health_services.is_active = 1
AND health_services.queue_mode = specialized
AND queue operating hours permit joining
AND daily queue maximum is not reached
AND EXISTS an enabled specialized window for the service
AND that window status IN (open, busy)
AND that window has a current staff member
AND that staff has an active capability for the service
```

### 8.3 Client UI states

| Condition | Client display |
|---|---|
| Service active and capacity available | `Available` with Join control |
| Service active but no appropriate window | `Temporarily unavailable — no staff/window currently available` |
| Service inactive | Hidden or `Not offered today`, depending on Admin preference |
| Queue closed | `Queue opens at [time]` or `Queue is closed for today` |
| Daily maximum reached | `Daily queue limit reached` |

Do not let the Client submit a disabled service by manually changing the form value. Repeat all checks on the server.

---

## 9. Queue Joining Workflow

### 9.1 Client flow

1. Client logs in or completes registration/OTP.
2. Client opens Get Queue Number.
3. Backend returns active services with calculated joinability.
4. Client selects an available service.
5. Client confirms visit classification: Regular, Senior Citizen, or PWD.
6. Client sees current queue length and approximate waiting time.
7. Client confirms queue joining.

### 9.2 Server validations

Before insert, validate inside a transaction:

- authenticated role is Client;
- service exists and is active;
- queue mode is valid;
- appropriate capacity exists;
- queue is inside operating hours;
- daily maximum is not reached;
- priority-only eligibility, if applicable;
- client has no ticket with status in `waiting`, `serving`, or `skipped`;
- submitted classification is one of `regular`, `senior`, `pwd`.

### 9.3 Ticket creation

Store:

```text
user_id
service_id
queue_mode snapshot
window_id = NULL
reference_number
ticket_number
client_type
priority_level
status = waiting
issued_at
qr tracking information
```

The window is assigned only after Call Next.

### 9.4 Ticket numbering

Recommended visible numbering:

```text
Central Queue: A-001, A-002, A-003
Dental Queue: D-001, D-002, D-003
```

`reference_number` remains globally unique, such as `BHC-2026-0024`. The visible ticket number may reset daily according to queue rules.

---

## 10. Call Next Routing

## 10.1 Central Shared Queue

For a Shared window, select from all Central services:

```sql
SELECT qt.ticket_id
FROM queue_tickets qt
JOIN health_services hs ON hs.service_id = qt.service_id
WHERE qt.status = 'waiting'
  AND qt.queue_mode = 'central'
  AND hs.is_active = 1
ORDER BY qt.priority_level DESC, qt.issued_at ASC
LIMIT 1
FOR UPDATE SKIP LOCKED;
```

The service selected by the Client remains attached to the ticket for Staff context, reports, feedback, and ML features.

## 10.2 Specialized Queue

For a Specialized window, select only its configured service:

```sql
SELECT qt.ticket_id
FROM queue_tickets qt
WHERE qt.status = 'waiting'
  AND qt.queue_mode = 'specialized'
  AND qt.service_id = :window_service_id
ORDER BY qt.priority_level DESC, qt.issued_at ASC
LIMIT 1
FOR UPDATE SKIP LOCKED;
```

Before selection, verify that the current Staff has the matching capability.

## 10.3 Atomic Call Next transaction

The Call Next backend must:

```text
START TRANSACTION
1. Lock and re-read the current window.
2. Verify window is enabled, open, and operated by current Staff.
3. Verify there is no existing serving ticket at the window.
4. Select and lock the correct waiting ticket using queue rules.
5. Update ticket: status=serving, window_id=current window, called_at=NOW(), served_at=NOW().
6. Update window: status=busy.
7. Insert activity log.
8. Create browser/SMS called notification.
COMMIT
```

If there is no eligible ticket, keep the window `open` and return a clear empty-queue response.

---

## 11. Priority Rules

### 11.1 Classification

```text
Regular → lower/default priority level
Senior Citizen → priority level
PWD → priority level
```

### 11.2 Ordering

All Central and Specialized queue selections follow:

```sql
ORDER BY priority_level DESC, issued_at ASC
```

Priority affects order only within the queue where the ticket belongs.

- A Dental PWD ticket is prioritized within the Dental queue.
- It does not enter the Central queue.
- A Central Senior/PWD ticket is prioritized within the Central pooled queue.

### 11.3 Security

Never trust a submitted numeric `priority_level` from the browser. Calculate it on the server from the validated visit classification and system policy.

---

## 12. Ticket Lifecycle

### 12.1 Status definitions

| Status | Active? | Meaning |
|---|---:|---|
| `waiting` | Yes | Client is waiting and may be called |
| `serving` | Yes | Ticket has been called and assigned to a window |
| `skipped` | Yes | Client temporarily missed the call and may be recalled |
| `completed` | No | Service finished successfully |
| `voided` | No | Ticket ended without normal completion |

### 12.2 Allowed transitions

```text
waiting → serving
waiting → voided (client_cancelled, expired, admin_action)
serving → completed
serving → skipped
serving → voided
skipped → serving (recall)
skipped → voided (no_show, expired)
```

Do not allow arbitrary status changes outside this transition list.

### 12.3 Skip and Recall

Skip flow:

1. Staff confirms Skip.
2. Ticket changes `serving → skipped`.
3. Window returns `busy → open`.
4. Record `skip_ticket` activity.
5. Notify Client that the ticket was skipped and may be recalled.

Recall flow:

1. Staff selects an eligible skipped ticket belonging to the same queue/window service rules.
2. Backend verifies the ticket remains `skipped`.
3. Ticket changes `skipped → serving`.
4. Assign current window and update call time.
5. Window becomes `busy`.
6. Notify Client again.

If the Client still does not respond, Staff may Void with:

```text
voided_reason = no_show
```

### 12.4 Complete

Only a `serving` ticket at the current Staff window may be completed.

On Complete:

- set `status = completed`;
- set `completed_at = NOW()`;
- calculate actual waiting and service duration;
- write `wait_time_logs`;
- set window `busy → open`;
- create completion notification;
- make feedback available;
- write activity log.

### 12.5 Client cancellation

Client may cancel only a `waiting` ticket.

On cancellation:

```text
status = voided
voided_at = NOW()
voided_reason = client_cancelled
```

The action must be confirmed and logged.

### 12.6 End-of-day expiration

At configured queue close/end-of-day time, unresolved tickets may be voided:

```text
waiting/skipped → voided
voided_reason = expired
```

Do not automatically void a ticket currently being served. The Staff must complete or explicitly void it.

### 12.7 Standard void reasons

Use controlled values:

```text
client_cancelled
no_show
expired
duplicate_ticket
service_unavailable
staff_error
admin_action
other
```

For `other`, require a written reason.

---

## 13. Dynamic Queue Counts and People Ahead

### 13.1 Central ticket

People ahead includes waiting Central tickets that would be selected before the current ticket under priority and issue-time rules.

### 13.2 Specialized ticket

People ahead includes only waiting tickets for the same Specialized service that would be selected first.

### 13.3 Exclusions

Do not count:

- completed tickets;
- voided tickets;
- tickets in another specialized service;
- Central tickets when calculating a specialized queue position;
- specialized tickets when calculating a Central queue position.

---

## 14. Notifications

### Required transactional notifications

| Event | Channel | Content |
|---|---|---|
| OTP | SMS/browser where applicable | Verification code and expiry |
| Password recovery | SMS/email as configured | Secure reset instruction |
| Ticket created | Browser/SMS | Ticket number, service, tracking link |
| Turn is near | Browser/SMS | Ask Client to return to waiting area |
| Ticket called | Browser/SMS | Ticket number and assigned window |
| Ticket skipped | Browser/SMS | Explain that Staff may recall or void |
| Ticket completed | Browser | Completion and feedback link |
| Ticket voided | Browser/SMS | Human-readable reason and next step |

### Near-turn rule

Use a system setting such as `near_turn_threshold`. Example:

```text
Send near-turn notification when people_ahead <= 3
```

Send once per ticket/event. Do not resend on every status poll.

### Failure handling

- Store delivery result in `notifications` and `sms_logs`.
- A failed SMS must not break queue processing.
- Client page and public display remain fallback channels.

---

## 15. Public Display Board

The display board shows:

- Window name
- Now-serving ticket number
- Service name
- Next waiting ticket numbers where appropriate
- Updated timestamp

Do not show:

- Client full name
- Phone number
- Email
- Medical condition
- Private account information

Both shared and specialized windows appear on the same display:

```text
Window 1 — A-024 — General Consultation
Window 2 — A-025 — Vaccination
Dental Room — D-011 — Dental Services
```

Refresh every 10 seconds without a full page reload.

---

## 16. ML Waiting-Time Prediction

### 16.1 Inputs

Recommended Random Forest inputs:

```text
queue length for the ticket's queue
hour of day
day of week
service type encoded
client type encoded
queue mode encoded
number of active applicable windows
average historical service duration
```

### 16.2 Applicable active windows

For a Central ticket:

```text
active_windows = number of enabled shared windows with status open or busy
```

For a Specialized ticket:

```text
active_windows = number of enabled specialized windows for that service
                 with status open or busy and qualified current staff
```

### 16.3 Service encoding

- Keep `service_encoded` stable and system-managed.
- Admin cannot manually edit it.
- Store the exact encoding used in `wait_time_logs`.
- Retraining must preserve a versioned mapping or dataset description.

### 16.4 New-service fallback

If a service has insufficient training data or the ML service fails, calculate:

```text
fallback_wait = ceil(effective_queue_ahead / max(active_windows, 1))
                × fallback_average_service_time
```

Label the result honestly:

```text
Estimated waiting time
About 18 minutes
This estimate may change based on queue activity and available windows.
```

Never show `0 minutes` merely because prediction failed.

### 16.5 Logging

After completion, record:

- predicted wait;
- actual wait;
- actual service duration;
- queue length;
- applicable active windows;
- service/client/queue-mode encodings;
- algorithm used;
- timestamp.

### 16.6 Evaluation

Retain MAE, RMSE, R², and MAPE comparison. Display the best-performing algorithm without presenting the prediction as guaranteed.

---

## 17. Database Migration Plan

Review the actual database constraints before applying. Back up the database first and adapt foreign-key names where needed.

### 17.1 Users

Add a default client classification and first-login password flag:

```sql
ALTER TABLE users
  ADD COLUMN client_type ENUM('regular','senior','pwd')
    NOT NULL DEFAULT 'regular' AFTER role,
  ADD COLUMN must_change_password TINYINT(1)
    NOT NULL DEFAULT 0 AFTER password_hash;
```

For Client registration, store the selected default classification in `users.client_type`. Copy it to `queue_tickets.client_type` for every visit so history remains stable.

### 17.2 Health services

```sql
ALTER TABLE health_services
  ADD COLUMN queue_mode ENUM('central','specialized')
    NOT NULL DEFAULT 'central' AFTER service_encoded;
```

Keep:

```text
service_code
service_name
service_encoded
description
priority_only
is_active
display_order
```

### 17.3 Service windows

```sql
ALTER TABLE service_windows
  ADD COLUMN window_type ENUM('shared','specialized')
    NOT NULL DEFAULT 'shared' AFTER window_name,
  ADD COLUMN location_description VARCHAR(255)
    NULL AFTER window_type,
  MODIFY COLUMN service_id INT NULL,
  MODIFY COLUMN staff_id INT NULL;
```

Rules:

- Shared window: `service_id IS NULL`.
- Specialized window: `service_id` is required and points to a service with `queue_mode='specialized'`.
- `staff_id` stores only the current runtime operator and is cleared when closed.

### 17.4 Staff specialized capabilities

```sql
CREATE TABLE staff_service_capabilities (
  capability_id INT AUTO_INCREMENT PRIMARY KEY,
  staff_id INT NOT NULL,
  service_id INT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  assigned_by INT NOT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_staff_service_capability (staff_id, service_id),
  CONSTRAINT fk_capability_staff
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id),
  CONSTRAINT fk_capability_service
    FOREIGN KEY (service_id) REFERENCES health_services(service_id),
  CONSTRAINT fk_capability_admin
    FOREIGN KEY (assigned_by) REFERENCES users(user_id)
);
```

Only Specialized services need capability mappings in the first version.

### 17.5 Queue tickets

```sql
ALTER TABLE queue_tickets
  ADD COLUMN queue_mode ENUM('central','specialized')
    NOT NULL DEFAULT 'central' AFTER service_id,
  MODIFY COLUMN window_id INT NULL;
```

`queue_mode` is a snapshot copied from the Health Service when the ticket is created. This prevents historical tickets from changing meaning if an Admin later changes the service configuration.

### 17.6 Recommended indexes

```sql
CREATE INDEX idx_queue_call_central
  ON queue_tickets (status, queue_mode, priority_level, issued_at);

CREATE INDEX idx_queue_call_specialized
  ON queue_tickets (status, queue_mode, service_id, priority_level, issued_at);

CREATE INDEX idx_queue_user_active
  ON queue_tickets (user_id, status);

CREATE INDEX idx_window_runtime
  ON service_windows (is_active, window_type, service_id, status, staff_id);
```

### 17.7 Existing-data migration

Before enforcing the final rules:

1. Set existing normal services to `queue_mode='central'`.
2. Set existing normal windows to `window_type='shared'` and `service_id=NULL`.
3. Create/configure Dental as `queue_mode='specialized'`, if included.
4. Set Dental Room to `window_type='specialized'` and Dental `service_id`.
5. Add Staff John → Dental capability.
6. Copy current service queue mode into historical ticket `queue_mode` where possible.
7. Close all windows and clear runtime `staff_id` before enabling the new workflow.

---

## 18. Backend Module Changes

### 18.1 Retain and revise

| Existing module | Required revision |
|---|---|
| `modules/auth/login.php` | Enforce active account and first-login password change |
| `modules/auth/register.php` | Store validated default client classification |
| `modules/queue/join_queue.php` | Implement dynamic availability, queue mode snapshot, active status including skipped, `window_id=NULL` |
| `modules/queue/get_prediction.php` | Calculate queue-specific features and fallback |
| `modules/queue/status.php` | Return queue-mode-aware people ahead, status, window, notifications |
| `modules/service_window/call_next.php` | Implement shared vs specialized routing and atomic locking |
| `modules/service_window/window_status.php` | Replace loose status changes with guarded open/close behavior |
| `modules/service_window/skip_ticket.php` | Enforce `serving → skipped`, release window |
| `modules/service_window/complete_ticket.php` | Log actual metrics and release window |
| `modules/settings/health_services_mgmt.php` | Add queue mode and system-managed encoding rules |

### 18.2 Add

Recommended new modules:

```text
modules/auth/change_temporary_password.php

modules/queue/cancel_ticket.php
modules/queue/expire_tickets.php
modules/queue/service_availability.php

modules/service_window/open_window.php
modules/service_window/close_window.php
modules/service_window/recall_ticket.php
modules/service_window/void_ticket.php

modules/settings/service_windows_mgmt.php
modules/settings/staff_capabilities_mgmt.php
```

`expire_tickets.php` may be run by a scheduled task or a controlled end-of-day Admin operation. It must be idempotent.

---

## 19. UI/Page Changes

### 19.1 Admin Staff Accounts

- Remove permanent window/service assignment fields.
- Add Active/Inactive control.
- Add optional Specialized Capabilities checkboxes.
- Show `Must change password` status.
- Show current runtime window separately as read-only operational information, not as account configuration.

### 19.2 Admin Health Services

- Add Queue Handling selector:

```text
Central Shared Queue
Specialized Queue
```

- Make ML Category ID read-only.
- Keep Service Code but explain it as an internal identifier.
- Replace raw Display Order input with reorder controls where possible.
- Rename Active/Hidden to `Available for Queueing` or similarly clear wording.

### 19.3 Admin Service Windows

- Add Window Type.
- For Specialized, require a specialized Health Service.
- Remove permanent Staff assignment.
- Remove Admin control for Open/Busy/Closed during creation/editing.
- Show runtime Staff and status in the list as read-only monitoring data.

### 19.4 Client Service Selection

Each service card shows:

```text
Service Name
Client Description
Central or Specialized label only if helpful
Available / Temporarily unavailable
Reason when unavailable
```

Do not expose ML IDs or raw service codes to clients.

### 19.5 Staff My Window

Before a window is opened:

```text
Choose an available window
Open Window
```

After opening:

- show window name and type;
- show assigned specialized service if applicable;
- show Central Queue for shared windows;
- show queue list filtered by routing rule;
- show Call Next;
- show current ticket actions;
- allow Close Window only when not serving.

### 19.6 Client active ticket

Show:

- ticket number;
- reference number;
- selected service;
- queue mode only if understandable to the Client;
- classification/priority badge;
- status;
- people ahead;
- approximate waiting time;
- QR code;
- assigned window only after being called;
- notification state;
- Cancel Ticket only while waiting.

---

## 20. Reports and Analytics

Retain the ten report groups and update filters to support queue mode.

Required report dimensions:

- Central vs Specialized
- Health Service
- Staff
- Window
- Client type
- Ticket result
- Void reason
- Date/time

### No-show report

No-show is defined as:

```text
status = voided
AND voided_reason = no_show
```

### Window performance

Shared windows aggregate Central tickets. Specialized windows report their configured service.

### ML accuracy

Allow comparison by:

- queue mode;
- service;
- date range;
- algorithm;
- predicted vs actual difference.

---

## 21. Activity Logging

Log at least:

```text
login_success
login_failed
staff_created
staff_deactivated
staff_capability_added
staff_capability_removed
service_created
service_updated
service_availability_changed
window_created
window_updated
open_window
close_window
call_ticket
skip_ticket
recall_ticket
complete_ticket
void_ticket
client_cancel_ticket
settings_updated
ml_retrain_requested
```

Include actor, role, details, related ticket when available, IP address, and timestamp. Do not log plaintext passwords, OTPs, SMS API keys, or full sensitive message contents unnecessarily.

---

## 22. Security and Data-Integrity Requirements

- Use `requireLogin()` and server-side role checks on every protected page/module.
- Use prepared statements for all database access.
- Use CSRF tokens for all state-changing POST requests.
- Hash passwords with PHP `password_hash()`.
- Rate-limit login, OTP, password recovery, and SMS-test actions.
- Verify ticket ownership before showing Client ticket data.
- QR tracking must use an unguessable token or signed reference; do not expose sequential IDs as authorization.
- Use database transactions for Join Queue, Open Window, Call Next, Complete, Skip, Recall, and Void.
- Recheck state inside the transaction; never rely only on disabled buttons.
- Do not expose SMS API keys in HTML or JavaScript.
- Public display contains ticket numbers only, not client personal information.
- Store timestamps consistently using the deployment's configured timezone.

---

## 23. Implementation Phases

### Phase 1 — Database and domain rules

- Back up current database.
- Apply reviewed migration.
- Add queue modes, window types, staff capabilities, client default type, and temporary-password flag.
- Migrate existing data.
- Implement central/specialized availability queries.

### Phase 2 — Admin configuration

- Revise Staff Accounts.
- Revise Health Services.
- Revise Service Windows.
- Add specialized capability management.
- Remove permanent assignment and Admin runtime status controls.

### Phase 3 — Staff runtime workflow

- Implement Open/Close Window.
- Implement capability checks.
- Implement Central and Specialized queue views.
- Implement atomic Call Next.
- Implement Skip, Recall, Complete, and Void transitions.

### Phase 4 — Client workflow

- Update service selection with dynamic availability.
- Update Join Queue validation.
- Assign no window until called.
- Update people-ahead calculation.
- Add Client cancellation.
- Update ticket/QR/status UI.

### Phase 5 — Notifications and display

- Update ticket-created, near-turn, called, skipped, completed, and voided messages.
- Make SMS failures non-blocking.
- Update public display for shared and specialized windows.

### Phase 6 — ML and reports

- Add queue mode to ML feature preparation.
- Calculate applicable active windows correctly.
- Add fallback prediction.
- Update reports and filters.
- Validate model comparison and historical logging.

### Phase 7 — Security, accessibility, and QA

- Add CSRF and concurrency tests.
- Verify authorization.
- Test responsive pages and keyboard use.
- Validate public-display privacy.
- Test failures and recovery states.

---

## 24. Required Acceptance Tests

### Scenario A — Central regular client

```text
Given Window 1 is an open shared window
And General Consultation is active and Central
When a Regular client joins General Consultation
Then a Central waiting ticket is created with window_id NULL
And Window 1 can call it
And the ticket receives Window 1 only when called
```

### Scenario B — Central priority ordering

```text
Given a Regular Central ticket was issued first
And a Senior/PWD Central ticket was issued later
When a shared window calls next
Then the Senior/PWD ticket is selected according to priority policy
```

### Scenario C — Multiple shared windows

```text
Given Window 1 and Window 2 call next at almost the same time
When Central tickets are available
Then each window receives a different ticket
And no ticket is assigned twice
```

### Scenario D — Dental available

```text
Given Dental is active and Specialized
And Dental Room is enabled
And Staff John has Dental capability
And Staff John opens Dental Room
When a Client selects Dental
Then the Dental ticket is accepted
And only Dental Room can call it
```

### Scenario E — Dental unavailable

```text
Given Dental is active
But no qualified Dental window is open or busy
When Client views services
Then Dental is visible as Temporarily unavailable
And server rejects a manually submitted Dental join request
```

### Scenario F — Unauthorized specialized window

```text
Given Staff Mark has no Dental capability
When Staff Mark attempts to open Dental Room
Then the operation is rejected
And no window assignment is created
And the attempt is logged
```

### Scenario G — Duplicate active ticket

```text
Given Client already has a skipped ticket
When Client attempts to join another queue
Then creation is rejected
And Client is directed to the existing active ticket
```

### Scenario H — Skip and Recall

```text
Given a ticket is serving at Window 1
When Staff skips it
Then ticket becomes skipped and Window 1 becomes open
When eligible Staff recalls it
Then ticket becomes serving and is assigned to the recalling window
```

### Scenario I — Client cancellation

```text
Given a Client ticket is waiting
When Client confirms cancellation
Then ticket becomes voided with client_cancelled reason
And it no longer affects queue position or prediction
```

### Scenario J — End-of-day expiration

```text
Given unresolved waiting/skipped tickets remain at end of day
When expiration process runs
Then they become voided with expired reason
And running it again produces no duplicate effect
```

### Scenario K — ML failure

```text
Given the Python ML service is unavailable
When Client requests a prediction
Then a fallback estimate is shown
And queue joining remains functional
And the UI does not show a fake zero-minute estimate
```

### Scenario L — Window closure

```text
Given a window has a serving ticket
When Staff attempts to close it
Then closure is rejected until ticket is completed, skipped, or voided
```

---

## 25. Definition of Done

The implementation is complete when:

- [ ] Central services enter one pooled Central Queue.
- [ ] Specialized services enter service-specific queues.
- [ ] Shared windows call only Central tickets.
- [ ] Specialized windows call only their configured service.
- [ ] Specialized Staff capability is enforced on the server.
- [ ] Staff assignment to a window is temporary and cleared on close.
- [ ] Admin does not set permanent Staff assignments or Busy status.
- [ ] Client cannot join a service without active eligible capacity.
- [ ] Ticket window is assigned only when called.
- [ ] Priority and FIFO ordering are applied consistently.
- [ ] Call Next is concurrency-safe.
- [ ] Waiting, serving, and skipped all block duplicate active tickets.
- [ ] Skip, Recall, Complete, Void, Cancel, and Expire transitions work.
- [ ] SMS/browser notifications do not block core queue operations.
- [ ] Public display supports shared and specialized windows without personal data.
- [ ] ML uses queue-specific length and applicable active-window count.
- [ ] Fallback waiting-time prediction works.
- [ ] Reports distinguish Central/Specialized, service, window, staff, and void reason.
- [ ] Activity logs capture all sensitive operational changes.
- [ ] Role access, CSRF, prepared statements, and ticket ownership checks are verified.
- [ ] Client, Staff, and Admin pages remain responsive and accessible according to the UI/UX guide.

---

## 26. Final Implementation Summary

Implement Smart QMS as one system with two compatible routing modes:

```text
CENTRAL SHARED QUEUE
- Many general services
- One pooled waiting line
- Multiple shared windows
- Any active general staff can operate an available shared window

SPECIALIZED QUEUE
- One service-specific waiting line
- Specialized window/room
- Only staff with matching capability can operate it
```

The final architecture keeps the project simple for a small barangay while preventing Dental or another specialized service from accepting clients when nobody qualified is available. It also preserves accurate routing, reports, public-display directions, SMS notifications, and ML waiting-time features.

