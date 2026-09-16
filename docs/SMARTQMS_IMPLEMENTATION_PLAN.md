# SmartQMS Implementation Plan

This plan implements the agreed guest reservation, check-in, FIFO queue, staff workspace, feedback, and simplified Admin configuration model. It is deliberately incremental: preserve working queue behavior while separating reservations from live tickets.

## Guiding constraints

- PHP 8.x, MySQL/MariaDB, Bootstrap 5, and vanilla JavaScript remain the stack.
- Clients do not create accounts or sign in.
- A reservation is not a queue ticket.
- FIFO begins at physical check-in, not at online pre-registration.
- Service availability depends on service configuration, reservation capacity, and an enabled counter that supports the service.
- Do not delete historical records that reports depend on.

## Phase 1 — Domain and database foundation

Create a dedicated `visit_reservations` record rather than reusing `queue_tickets` for pre-registration.

Required concepts:

- `reservation_id`, human-readable `reference_number`, first name, last name, mobile number, service ID, visit date, consent timestamp, status, created/updated timestamps;
- reservation states: `confirmed`, `cancelled`, `expired`;
- reschedule audit history: old date, new date, time, and action source;
- a unique active-reservation rule for mobile number and visit date;
- `queue_tickets.reservation_id` nullable foreign key for checked-in bookings;
- random `tracking_token` or equivalent non-guessable ticket tracker credential;
- unique feedback row per `ticket_id`.

Add configuration values:

- booking horizon in days;
- check-in opening and cutoff time;
- center closing time;
- near-turn threshold, initially two tickets ahead.

Migration checks:

- retain existing ticket and feedback history;
- give old tickets no reservation link;
- use transactions and unique indexes to protect duplicate reservations, counter claims, and Call Next.

## Phase 2 — Simplify Admin configuration

### Staff Accounts

Replace the current setup with:

- first name, last name, required unique email, temporary password;
- optional job title and mobile number;
- Active/Inactive status;
- force-password-change flag and password reset action.

Remove from the Staff Account create/edit workflow:

- optional username;
- permanent window assignment;
- specialized capability configuration;
- live counter-state controls.

The list displays staff member, email, job title, status, current counter as read-only runtime data, and actions. Deactivate rather than delete accounts with history.

### Health Services

Use these Admin fields:

- service name;
- client-facing description;
- maximum reservations per visit date;
- estimated service duration for prediction fallback;
- Available to clients toggle;
- optional display order.

Generate system-only service code and ML category values. Do not expose them as editable fields in the normal form. Replace destructive delete with archive/hide once a service has historical bookings or tickets.

### Service Windows

Use these Admin fields:

- counter label;
- optional location/direction;
- multi-select services this counter can serve;
- Enabled for staff selection toggle.

Remove Shared/Specialized type and Central Queue configuration from the initial UI. Runtime status, current staff, and current ticket are read-only monitors. A staff member claims a counter during their shift.

## Phase 3 — Guest reservation and public management

Build the guest journey:

1. Select health service and visit date.
2. Enter first name, last name, mobile number, and consent.
3. Validate booking date, capacity, availability, and one-active-reservation rule.
4. Save a Confirmed reservation and show the reference number.
5. Public status lookup accepts reference only and returns only safe status text.
6. Cancel/reschedule accepts reference plus last four digits of the booked mobile number; rate-limit failures and use a neutral error message.
7. Expire un-checked-in reservations after their visit date; do not send a required notification.

No Client login, registration, password-reset, or account dashboard is part of this journey.

## Phase 4 — Staff arrival and live queue workflow

- Give staff an arrival lookup by booking reference, with name/mobile confirmation.
- Check-in converts a valid Confirmed reservation into one Waiting queue ticket and assigns the next FIFO number.
- Keep a staff walk-in flow that directly creates a Waiting ticket.
- Claiming a counter must atomically verify that the counter is enabled and unoccupied.
- Call Next must atomically claim the earliest eligible Waiting ticket supported by the selected counter.
- Current ticket action rules:
  - Calling: Recall, Start Service, Skip, Void.
  - In Service: Complete, Void.
  - Waiting: only the earliest eligible row has an enabled Call Next action.
- Log every state change.

## Phase 5 — QR tracker, notification, and satisfaction feedback

- Generate one QR at check-in; it opens a tracker URL using the tracking token.
- The tracker polls or subscribes to ticket status and shows the estimate only after check-in.
- On the near-turn threshold, insert a notification log and send SMS when enabled. Browser permission is optional and must not block the core flow.
- Once Completed, expose the feedback form on the same tracker URL.
- Save rating, optional comment, optional tags, service ID, counter/window ID, and timestamp. Never expose the client identity in Staff or Admin feedback views.

## Phase 6 — Reporting

Reports are generated from normal reservation, check-in, staff-action, and feedback activity. Staff must not manually enter report values. Use the selected reporting date range consistently, and make the report's date basis clear in the UI: `checked_in_at` for arrival volume, `completed_at` for completed-service outcomes, and `visit_date` for reservations.

| Report | Primary data source | Calculation and inclusion rule |
|---|---|---|
| Queue Summary | `queue_tickets`, `health_services`, `service_windows` | Count tickets by lifecycle status, service, counter, and selected date. Include waiting, calling, in-progress, completed, skipped, and voided outcomes; do not treat an un-checked-in reservation as a live queue ticket. |
| Predicted vs Actual Wait | `wait_time_logs`, `queue_tickets` | Save the predicted minutes, input snapshot, confidence, and model version at physical check-in. When service starts, set actual wait to `started_at - checked_in_at`; compare these values only for tickets with a real prediction and actual wait. |
| Peak Hour Analysis | `queue_tickets.checked_in_at` | Group physical check-ins by hour and day. This measures arrivals, not online booking creation, and should support a day-by-hour heatmap. |
| Counter Performance | completed `queue_tickets`, `service_windows`, `ticket_events` | For each counter, count completed tickets and calculate average wait (`started_at - checked_in_at`), average service duration (`completed_at - started_at`), and skipped/voided count. Use the counter recorded on the ticket/event at the time of service, not its current assignment. |
| Turnaround Time | completed `queue_tickets` | Calculate total visit time as `completed_at - checked_in_at`; also report waiting and service portions. Exclude reservations that never checked in and tickets that are not completed. |
| No-Show Report | `visit_reservations`, `queue_tickets`, `ticket_events` | Report three separate outcomes: reservations expired without check-in, clients skipped after being called and not responding, and staff-voided tickets. Store a reason for skip/void; never merge these categories into one unexplained total. |
| Staff Productivity | completed `queue_tickets`, `ticket_events`, `staff_counter_sessions` | Per staff member, count completed tickets, average service duration, average handled wait, skips, and voids. Persist the staff member who performed each action; never infer historical productivity from the staff member's current counter. |
| ML Accuracy | `wait_time_logs`, `ml_comparison_logs` | On real completed tickets, calculate prediction error using predicted versus actual wait (for example MAE and RMSE), grouped by model version and period. Separately show offline model-run metrics, data-set name, sample size, and selected model. Clearly label synthetic/testing data until real data is sufficient. |
| Daily/Monthly Stats | `queue_tickets`, `visit_reservations` | Group actual physical check-ins, completed tickets, outcome counts, and walk-in versus online-origin tickets by day or month. Reservation totals use `visit_date`; live queue totals use `checked_in_at`. |
| Satisfaction Report | `feedback`, completed `queue_tickets`, `health_services`, `service_windows` | Show total eligible completed tickets, feedback submissions, response rate, average rating, rating distribution, rating by service/counter/date, and anonymized comments. Response rate is `feedback submissions / eligible completed tickets`. Enforce one feedback response per ticket. |

### Reporting data safeguards

- `queue_tickets` must retain the reservation link (when one exists), service, ticket origin, assigned counter, and lifecycle timestamps: `checked_in_at`, `called_at`, `started_at`, `completed_at`, `voided_at`.
- `wait_time_logs` must be immutable per ticket once the prediction is saved, except for filling the actual outcome at service start/completion. Store the model version with every prediction.
- Add an append-only `ticket_events` table for Call, Recall, Start, Complete, Skip, Void, and the required reason where applicable. Each event stores ticket, staff, counter, timestamp, and event type.
- Add `staff_counter_sessions` for counter claims/releases so counter and staff reports remain historically accurate even after later shifts.
- Preserve cancelled and expired reservations rather than deleting them. Preserve ticket and feedback history when services, counters, or staff are deactivated.
- If a report has no valid records for its calculation, show an explicit “No data yet” state rather than a zero-value chart that implies a measured result.

## Phase 7 — Verification

Required acceptance tests:

- a mobile number cannot create two active reservations for the same date;
- a future reservation cannot check in outside configured hours;
- cancellation/reschedule rejects mismatched reference and last-four-digit pairs;
- checked-in reservations and walk-ins follow one FIFO ordering;
- two staff cannot claim the same counter or call the same ticket;
- a counter only calls tickets for its configured services;
- a public reference lookup exposes no client identity;
- a QR token cannot be guessed from ticket IDs;
- completed ticket feedback can be submitted once only;
- deactivating a service/counter with future work gives a clear protected workflow;
- keyboard, 200% zoom, 390px mobile, reduced motion, and screen-reader flows remain usable.
