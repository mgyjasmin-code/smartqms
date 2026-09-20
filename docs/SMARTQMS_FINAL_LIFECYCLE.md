# SmartQMS Final Lifecycle

## Purpose

SmartQMS is a barangay health-center queue system. It reduces registration friction and makes the visit understandable without giving online pre-registration priority over people who arrive in person.

## Final operating model

1. A client pre-registers as a guest for a future visit date; no Client account, password, login, or registration verification is required.
2. The client receives a human-readable booking reference.
3. The client arrives during the configured check-in period. Staff locate the reference and confirm the client details before checking the client in.
4. Physical check-in creates the queue ticket and assigns the next FIFO queue number.
5. The client receives one QR code. It opens the private ticket tracker and later the feedback form; it is not a second booking QR code.
6. Staff claim an available counter, then call the earliest eligible checked-in ticket in FIFO order.
7. The client follows the live ticket tracker and receives one near-turn notification when two clients are ahead.
8. When service is completed, the tracker offers a one-time anonymous satisfaction rating.

## Reservation rules

### Guest pre-registration

The guest form collects only:

- first name;
- last name;
- Philippine mobile number;
- health service;
- visit date; and
- consent to the stated data use.

There is no exact appointment time. The reservation is for a visit date only.

- Only one active reservation may exist for one mobile number on one visit date.
- The date must fall within the Admin-configured booking horizon.
- The selected service must be available and below its configured daily reservation capacity.
- A reservation never has a FIFO position and never reserves a queue number.

### Reservation access and changes

- A reference alone may show a privacy-safe public status; it must not show name, mobile number, or medical detail.
- Cancellation requires the private management link issued with the reservation.
- Self-service cancellation is available only while the reservation is Confirmed and before the configured check-in cutoff.
- A reservation that is not checked in before its visit date ends becomes Expired quietly. It remains in history for reporting and audit.

## Check-in and ticket rules

- Check-in is permitted only during configured hours, for example 08:00–15:30 while the center closes at 16:00.
- Staff confirm a booking reference with the client's name and mobile number before check-in.
- A walk-in is created directly by staff, then enters the same FIFO ordering at the time of check-in.
- Online pre-registration has no priority over walk-ins.
- A client can hold only one active queue ticket at a time.
- The actual ticket is separate from the pre-registration. A reservation can exist without a ticket; a walk-in ticket can exist without a reservation.

## Counter and queue rules

- Admins configure which health services each physical counter can serve.
- Any active staff member can claim one enabled, unoccupied counter at runtime.
- A claimed counter may serve multiple configured categories.
- Call Next selects the earliest checked-in Waiting ticket whose health service is supported by the claimed counter.
- Only one staff member can claim a counter at a time. The claim and Call Next selection are transactional.
- Staff cannot change counters while a ticket is Calling or In Service.
- Admins configure counters but do not set their live Open, Busy, or Closed state during normal operations.

## State model

```text
Reservation
Confirmed ──> Cancelled
     │
     ├──> Expired
     │
     └──> Check in ──> Queue ticket

Queue ticket
Waiting ──> Calling ──> In Service ──> Completed
                    ├──> Skipped
                    └──> Voided
```

## QR tracking, notifications, and feedback

- The tracking QR contains only an unguessable ticket-tracking token or URL. It must not encode a name, phone number, or health details.
- The tracker displays queue number, service, lifecycle status, people ahead, estimated wait, called counter, and next instruction.
- An SMS and/or in-browser alert is sent once when two tickets are ahead. Notification failures never block queue operations.
- When the ticket is Completed, the tracker changes to a rating form: 1–5 stars required; comment and experience tags optional.
- Feedback is linked internally to the ticket, but reports and staff views show no client identity. Enforce one feedback record per completed ticket.

## Simplified Admin configuration model

### Staff accounts

Admins configure people, not runtime counters.

- Required: first name, last name, unique email, temporary password.
- Optional: job title and mobile number.
- Active/Inactive status is supported.
- Do not collect usernames, permanent counter assignments, or specialized capabilities in the initial model.
- Existing staff with history are deactivated rather than deleted.

### Health services

Admins configure what clients may reserve.

- Required: service name, client-facing description, daily reservation capacity, estimated service duration, availability to clients.
- Internal service codes and ML category IDs are generated by the system and are not normal form fields.
- A service with historical reservations/tickets is archived or hidden, not permanently deleted.

### Service windows

Admins configure physical service points; staff operate them.

- Required: counter label and one or more supported health services.
- Optional: location/direction description.
- Configuration: Enabled/Disabled for staff selection.
- Runtime-only fields: current staff, current ticket, and status.
- Do not require a Shared/Specialized type or a Central Queue setting in the initial model.

## Reporting requirements

All reports distinguish reservation outcomes from ticket outcomes where relevant.

- Reservations: confirmed, cancelled, expired.
- Queue: waiting, calling, in service, completed, skipped, voided.
- Satisfaction: response count, response rate, average rating, rating distribution, rating by health service, and anonymized comments.
