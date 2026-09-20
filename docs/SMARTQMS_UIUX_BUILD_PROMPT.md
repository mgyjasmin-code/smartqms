# SmartQMS UI/UX Build Prompt

> Superseded by `SMARTQMS_COMPLETE_UIUX_PROMPT_ARCHITECTURE.md`, which contains the finalized healthcare palette, Poppins typography, responsive architecture, complete flows, Bootstrap inventory, and focused workspace prompts.

Use this prompt when implementing the final guest client journey, staff workspace, and simplified Admin configuration. The project is PHP 8.x + MySQL/MariaDB + Bootstrap 5 + vanilla JavaScript. Preserve existing security, server-side validation, audit logging, and transactional queue actions.

## Product model

SmartQMS is a barangay health-center queue system. Guest pre-registration reserves a visit date only. It does not assign a queue number or give priority. Physical check-in assigns the FIFO ticket.

Client lifecycle:

```text
Confirmed reservation → checked in / Waiting → Calling → In Service → Completed
                     ├→ Cancelled
                     └→ Expired
```

## Global design rules

- Use the existing SmartQMS civic-healthcare tokens: calm light canvas, white surfaces, navy structure, blue primary action, and semantic success/warning/danger states.
- Use Poppins for headings and queue numbers; Inter for body/interface text.
- Use Lucide consistently for interface icons. Do not mix icon libraries or use emoji as structural icons.
- Use a 4/8px spacing system, 12px control radius, 16px card radius, visible 3px focus rings, and restrained shadows.
- One filled primary action per screen or ticket state. Secondary actions are outlined; destructive actions are visually separated and confirmed.
- All actions are at least 44px high; fields have visible labels; errors appear beside the relevant field; busy buttons prevent duplicate submission.
- Status is always text plus color/icon. Never use color alone.
- Support 390px, 768px, and 1440px layouts; 200% zoom; keyboard use; reduced motion; and both light/dark theme tokens.

## Client: guest pre-registration

Create a mobile-first guest form. Do not show account creation, Client login, password fields, or a Client dashboard.

Fields:

- First name \*
- Last name \*
- Mobile number \*
- Health service \*
- Visit date \*
- Consent checkbox \*

The health-service card/list shows service name, plain-language description, remaining capacity/availability, and unavailable reason when relevant. Do not expose service code, ML category ID, staff, or counter information.

The successful confirmation screen shows:

- booking reference in large copyable text;
- selected service and visit date;
- check-in period, e.g. `Check in from 8:00 AM to 3:30 PM`;
- note that queue number is assigned on physical arrival;
- `Track reservation` and `Cancel reservation` actions.

## Client: status and cancellation

Reference-only lookup is allowed for safe status messages only. Do not show name, mobile, medical details, or staff name.

For cancellation, require:

- the private management link issued with the reservation;

Use one neutral failure message: `We could not verify this reservation.` Rate-limit repeated failed attempts.

On cancellation, use a confirmation dialog that names the visit date and service, then show a clear success state.

## Client: QR ticket tracker and feedback

At staff check-in, generate one QR code that opens a secure, tokenized tracker URL. The tracker has lifecycle-specific content:

- **Waiting:** queue number, service, people ahead, estimated wait, last updated time, and next instruction.
- **Calling:** large `Proceed to Counter X` instruction.
- **In Service:** concise in-service confirmation.
- **Completed:** show an anonymous feedback form.

The completed feedback form contains a required 1–5 rating, optional comment, optional tags (Waiting time, Staff assistance, Service process, Facility), and a privacy reminder not to include medical or personal information. Allow one submission per ticket. Do not display feedback identity to staff or admins.

## Staff workspace

Remove the staff sidebar from the live service workspace. Use a minimal top bar with SmartQMS identity, staff name, date/time, a compact Tools menu for Arrival Check-In and Batch Printing, and Logout.

Layout:

```text
Top bar
Four operational cards: Completed Today | Serving | Waiting | Voided / Skipped
Counter-control rail on desktop; above tables on mobile
Now Serving / Calling table
Active Waiting Queue table
```

Counter-control rail:

- select an enabled, available counter;
- show counter label, location, services it can serve, and current runtime state;
- block switching while Calling or In Service;
- make counter claim/release feedback visible.

Now Serving / Calling table columns:

- Queue number
- Service category
- Booking type: Walk-in or Pre-registered
- Arrived/checked-in time
- Called time
- Status
- Actions

Action visibility follows the lifecycle:

- Calling: Recall, Start Service, Skip, Void.
- In Service: Complete, Void.
- Do not show unrelated disabled actions.

Active Waiting Queue columns:

- FIFO position
- Queue number
- Service category
- Booking type
- Arrived/checked-in time
- Action

Only the first eligible row may show enabled `Call Next`. Other rows state that they are waiting for the previous ticket. A successful call updates both tables and shows a top-right toast such as `Calling A-001 — Counter 1`. The toast uses `aria-live` and does not replace the visible state change.

## Admin configuration

Keep the existing Admin sidebar, top bar, dark-mode support, management pages, and table-first layout. Simplify the forms and tables below.

### Staff Accounts

Create/edit form:

- First name \*
- Last name \*
- Email \* (unique staff sign-in)
- Temporary password \* on create; reset password action later
- Mobile number (optional)
- Account status: Active / Inactive

Do not include username, permanent counter assignment, specialized capability, or live counter state controls.

Table columns:

```text
Staff member | Email | Job title | Status | Current counter | Actions
```

Current counter is read-only runtime information. Deactivate, do not delete, staff with historical activity. Use accessible labeled or tooltip-supported action controls and confirmation dialogs.

### Health Services

Create/edit form:

- Service name \*
- Client-facing description \*
- Maximum reservations per visit date \*
- Estimated service duration in minutes \*
- Available to clients toggle
- Display order (optional)

System-generated service code and ML category ID must not appear as editable normal fields. Hide/archive services with history rather than deleting them. Warn before making a service unavailable when future reservations exist.

Table columns:

```text
Service | Daily capacity | Reserved today | Supported counters | Client availability | Actions
```

### Service Windows

Create/edit form:

- Counter label \*
- Location/direction (optional)
- Services this counter can serve \* — searchable multi-select
- Enabled for staff selection toggle

Do not ask the Admin to configure Shared/Specialized type, Central Queue, current staff, or Open/Busy/Closed state. Those are either unnecessary complexity or runtime staff data.

Table columns:

```text
Counter | Location | Services served | Current staff | Runtime status | Configuration | Actions
```

The service multi-select should be a readable checklist with search and selected count, not a long unstructured modal grid. Runtime status and current staff are read-only. Warn before disabling a counter with an active ticket or future service demand.

## Definition of done

- Guest client flow contains no account sign-in or password screens.
- Online reservations and live tickets are visibly and technically distinct.
- QR tracking and feedback use one secure tracker URL per ticket.
- Staff workspace has no navigation sidebar and makes the next safe queue action obvious.
- Admin configuration does not leak runtime responsibility to Admins or permanent assignment to staff.
- All forms and tables are usable by keyboard, on mobile, and at 200% zoom.
