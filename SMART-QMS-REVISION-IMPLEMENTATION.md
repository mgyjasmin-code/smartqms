# Smart QMS Revision and Supabase Integration Plan

> Implementation status (2026-08-20): Phases 1–6 are implemented behind the
> fail-safe provider adapter. Local automated gates pass. Applying migrations,
> exercising Supabase Auth/RLS/Realtime, and live Python inference require the
> development environment described in `docs/SUPABASE_ROLLOUT_RUNBOOK.md`.

## 1. Objective

Revise the Smart QMS client-side experience so it can be compared side by side with the reference SmartQ project while preserving the existing Smart QMS staff and admin pages and dashboards.

The revision should reproduce the reference project's customer flow, queue behavior, estimated waiting time, authentication, role protection, and live status updates. Supabase will provide the shared backend for database storage, authentication, authorization, security policies, and realtime updates.

## 2. Non-negotiable constraints

- Preserve the existing Smart QMS admin page and staff dashboard layout.
- Preserve existing Smart QMS routes unless a new route is required for the customer flow.
- Add reference-project behavior through adapters, services, and reusable components rather than replacing existing dashboards.
- Keep client-side styling compatible with the current Smart QMS design system.
- Do not expose a Supabase service-role/secret key in browser code.
- Use Row Level Security (RLS) for all client-accessible tables.
- Keep the first implementation compatible with HTML, Bootstrap, JavaScript, PHP, and Python ML.

## 3. Reference flow to reproduce

```text
Client homepage
  -> Get Queue Number
  -> Select service
  -> Enter customer details (first name, last name, phone number)
  -> Review confirmation
  -> Create queue ticket
  -> Show ticket number, service, position, and estimated wait
  -> Track live queue
  -> Receive service when staff calls the ticket
```

### Customer screens

1. **Homepage**
   - Show current waiting count, serving count, and available counters.
   - Provide `Get Queue Number` and `View Live Queue` actions.

2. **Book Queue**
   - Step 1: Select service.
   - Step 2: Select branch or location.
   - Step 3: Enter full name, phone number, and any Smart QMS-specific fields.
   - Step 4: Confirm service, branch, name, people ahead, and predicted waiting time.

3. **Ticket Confirmation**
   - Show ticket/queue number.
   - Show service type, customer name, branch, queue position, estimated wait, and QR code if required.
   - Provide a link to the live queue tracker.

4. **Live Queue**
   - Show now-serving tickets, waiting tickets, positions, open counters, and estimated wait times.
   - Refresh from Supabase Realtime instead of browser-only state.

## 4. Side-by-side comparison matrix

| Reference behavior | Smart QMS implementation | Compatibility rule |
|---|---|---|
| Get Queue Number | Existing or new Smart QMS client CTA | Keep current branding and layout |
| Multi-step booking | Add a Bootstrap stepper or preserve existing booking form | Do not remove existing fields |
| Service selection | Map reference services to Smart QMS service records | Use database-driven services |
| Branch selection | Map to Smart QMS locations/branches | Keep existing location terminology |
| Ticket confirmation | Extend the current confirmation page | Preserve existing ticket details |
| Live tracker | Connect current tracker to Supabase Realtime | Avoid polling where Realtime is available |
| Staff queue controls | Keep current dashboard and connect its actions to Supabase | Do not redesign the dashboard initially |
| Admin management | Keep current admin pages and add Supabase-backed data | Add features incrementally |
| Staff role | Map to `staff` or the existing Smart QMS staff role | Use one canonical role name |
| Admin role | Map to `admin` or `super_admin` | Document the chosen mapping |
| Estimated wait | Use Smart QMS Python ML endpoint | Store prediction with the ticket and refresh it when needed |

## 5. Recommended architecture

```text
Browser client
  HTML + Bootstrap + JavaScript
        |
        | publishable/anon key + authenticated user session
        v
Supabase Auth / REST / Realtime
        |
        v
Supabase PostgreSQL + RLS

PHP application server
  - server-side validation
  - privileged operations
  - staff/admin APIs
  - service-role key kept server-side

Python ML service
  - receives queue features
  - returns estimated wait and confidence
  - never receives browser secrets
```

### Responsibility split

- **JavaScript:** UI state, form navigation, client validation, Realtime subscriptions, rendering updates.
- **PHP:** authenticated API endpoints, authorization checks, ticket creation, staff actions, audit logging, and calls to Supabase or Python.
- **Supabase:** Auth, PostgreSQL, RLS, database functions, storage if needed, and Realtime.
- **Python:** prediction model and analytics calculations.
- **Bootstrap/HTML:** presentation and responsive layout.

## 6. Supabase data model

Use existing Smart QMS tables when they already exist. Add only missing fields or mapping tables.

### Core tables

#### `profiles`

- `id uuid primary key references auth.users(id) on delete cascade`
- `full_name text`
- `phone text`
- `created_at timestamptz`

#### `user_roles`

- `id uuid primary key`
- `user_id uuid references auth.users(id) on delete cascade`
- `role text` or a PostgreSQL enum
- `created_at timestamptz`
- unique constraint on `(user_id, role)`

Recommended roles:

```text
customer
staff
admin
super_admin
```

#### `services`

- `id uuid primary key`
- `name text`
- `description text`
- `prefix text`
- `active boolean`
- `priority boolean`

#### `branches`

- `id uuid primary key`
- `name text`
- `address text`
- `active boolean`

#### `counters`

- `id uuid primary key`
- `branch_id uuid`
- `name text`
- `status text` such as `open`, `paused`, or `closed`
- `service_id uuid nullable`
- `avg_service_minutes numeric`

#### `tickets`

- `id uuid primary key`
- `ticket_number text unique`
- `service_id uuid`
- `branch_id uuid`
- `customer_id uuid nullable`
- `customer_name text`
- `customer_phone text`
- `status text` such as `waiting`, `serving`, `done`, or `skipped`
- `priority boolean`
- `created_at timestamptz`
- `called_at timestamptz nullable`
- `served_at timestamptz nullable`
- `counter_id uuid nullable`
- `predicted_wait_minutes numeric nullable`
- `prediction_confidence numeric nullable`

#### `queue_events`

Use this table for audit history and ML training data:

- `id uuid primary key`
- `ticket_id uuid`
- `event_type text`
- `actor_user_id uuid nullable`
- `created_at timestamptz`
- `metadata jsonb`

## 7. Authentication and authorization

### Customer

- Customer can register and sign in through Supabase Auth.
- New users receive the `customer` role.
- Customers can create tickets and read their own ticket information.

### Staff

- Staff can view and operate queues for permitted branches.
- Staff can call next, mark serving, complete, skip, and pause/resume counters.
- Staff cannot manage global users or security settings.

### Admin

- Admin can manage services, branches, counters, staff assignments, reports, and queue settings.
- Super Admin can manage roles and high-risk settings.

### Secure role check

Do not trust a role supplied by the browser. Check the authenticated user and role on the PHP server and enforce the same rules in Supabase RLS policies.

## 8. Row Level Security policy goals

Enable RLS on every application table.

Minimum policy behavior:

- Public/anonymous users may read only intentionally public service, branch, and live queue information.
- Authenticated customers may create tickets for themselves.
- Customers may read only their own tickets.
- Staff may read and update tickets belonging to their assigned branch.
- Admins may manage branch configuration and staff assignments.
- Only Super Admin may grant or revoke elevated roles.
- No browser client may insert directly into `user_roles` unless the policy explicitly requires a verified Super Admin role.

Use database functions with `security definer` only when necessary, and restrict their execute permissions to the correct role.

## 9. Client implementation plan

### Phase 1: Inventory existing Smart QMS

- List current client routes and pages.
- List current staff routes and dashboard components.
- List current admin routes and dashboard components.
- Identify existing API endpoints and database tables.
- Record existing field names for service, branch, customer, ticket, queue, counter, and status.
- Create a mapping document before changing database names.

### Phase 2: Add a client-side adapter layer

Create a JavaScript service layer so pages do not call Supabase directly everywhere:

```text
client/services/auth.js
client/services/queue.js
client/services/services.js
client/services/branches.js
client/services/realtime.js
client/services/permissions.js
```

The adapter should expose functions such as:

```javascript
getServices()
getBranches()
createTicket(payload)
getTicket(ticketId)
getLiveQueue(branchId)
subscribeToQueue(branchId, callback)
callNextTicket(counterId)
completeTicket(ticketId)
skipTicket(ticketId)
```

This allows the existing Smart QMS dashboards to remain intact while their data source changes from mock data or old APIs to Supabase.

### Phase 3: Reproduce the customer flow

- Add or update the `Get Queue Number` action.
- Implement the service, branch, details, and confirmation steps.
- Validate required fields before creating a ticket.
- Send the booking request to PHP or a protected Supabase endpoint.
- Redirect to the existing Smart QMS ticket confirmation page.

### Phase 4: Connect existing staff and admin dashboards

Keep the existing visual layout and replace only data and action handlers:

- Dashboard queue list reads from Supabase.
- Call-next button invokes a protected PHP endpoint.
- Complete/skip buttons create `queue_events` records.
- Counter controls update `counters` through authorized server logic.
- Admin tables read and write existing Smart QMS entities through the adapter.

### Phase 5: Add live updates

Subscribe to changes in `tickets`, `counters`, and relevant `queue_events` records.

When a change arrives:

1. Update the local UI state.
2. Recalculate queue position.
3. Request or calculate a refreshed wait prediction.
4. Update customer, staff, and admin displays without a full page reload.

## 10. Waiting-time prediction integration

The current reference project uses an inline linear-regression formula. Smart QMS should replace that demo logic with the existing Python ML model or a new Python endpoint.

### Prediction inputs

```json
{
  "branch_id": "...",
  "service_id": "...",
  "people_ahead": 5,
  "open_counters": 3,
  "average_service_minutes": 7.5,
  "hour_of_day": 14,
  "priority_count": 1
}
```

### Prediction response

```json
{
  "estimated_wait_minutes": 18,
  "confidence": 0.91,
  "model_version": "qms-wait-v1"
}
```

PHP should validate the input, call Python, validate the response, and store the result in `tickets.predicted_wait_minutes`.

Do not allow arbitrary browser input to control model parameters without server validation.

## 11. Suggested PHP endpoints

```text
POST   /api/auth/profile
GET    /api/services
GET    /api/branches
POST   /api/tickets
GET    /api/tickets/{id}
GET    /api/queue?branch_id={id}
POST   /api/staff/tickets/{id}/call
POST   /api/staff/tickets/{id}/complete
POST   /api/staff/tickets/{id}/skip
PATCH  /api/staff/counters/{id}
GET    /api/admin/reports
POST   /api/admin/roles
DELETE /api/admin/roles/{id}
```

Each protected endpoint must:

1. Validate the Supabase access token.
2. Resolve the authenticated user.
3. Load roles from the database.
4. Check branch and role permissions.
5. Validate the request body.
6. Execute the database operation.
7. Write an audit event for staff/admin actions.

## 12. Environment configuration

### Browser-safe values

```env
SUPABASE_URL=https://your-project.supabase.co
SUPABASE_PUBLISHABLE_KEY=your_publishable_or_anon_key
```

Only expose the publishable/anon key to browser JavaScript.

### Server-only values

```env
SUPABASE_SERVICE_ROLE_KEY=your_secret_service_role_key
PYTHON_ML_URL=http://localhost:8000
PYTHON_ML_TOKEN=server_only_token
```

Never place `SUPABASE_SERVICE_ROLE_KEY` or a secret ML token in HTML, JavaScript bundles, or public `.env` files.

## 13. Migration and rollout strategy

1. Back up the existing Smart QMS database.
2. Create a Supabase development project.
3. Apply database migrations in development.
4. Import non-sensitive reference data such as services and branches.
5. Add Supabase configuration to the PHP server.
6. Add the JavaScript adapter without removing current dashboards.
7. Connect customer booking first.
8. Connect live queue updates.
9. Connect staff actions.
10. Connect admin management.
11. Add Python prediction integration.
12. Test role security with Customer, Staff, Admin, and Super Admin accounts.
13. Run side-by-side comparison and record differences.
14. Promote the tested schema and configuration to production.

## 14. Acceptance criteria

### Client

- Customer can complete the booking flow without using staff/admin screens.
- Customer receives a unique ticket number.
- Customer sees service, name, branch, position, and estimated wait.
- Customer can refresh the page and still retrieve the ticket.
- Queue changes appear without a full-page reload.

### Staff

- Existing staff dashboard layout remains available.
- Staff can see the correct branch queue.
- Staff can call next, complete, skip, and pause/resume counters.
- Every staff action is permission checked and logged.

### Admin

- Existing admin dashboard layout remains available.
- Admin can manage services, branches, counters, and staff assignments.
- Super Admin can grant/revoke roles.
- Customer accounts cannot access admin operations.

### Security

- RLS is enabled on all application tables.
- Service-role/secret keys never reach the browser.
- API endpoints reject missing, invalid, or insufficient roles.
- Customer data is not exposed in public queue responses.
- Audit events exist for staff and admin actions.

### ML

- Prediction response includes minutes and confidence.
- Prediction inputs are generated from server/database state.
- Prediction failures produce a safe fallback estimate and do not prevent ticket creation.
- Model version is stored for later comparison and retraining.

## 15. Definition of done

The revision is complete when Smart QMS preserves its existing staff/admin pages, supports the reference project's customer booking and live-queue flow, uses Supabase for shared persistence/auth/security/realtime, and shows the Python-generated waiting-time prediction consistently to customers, staff, and admins.
