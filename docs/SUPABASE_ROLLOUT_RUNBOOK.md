# SmartQMS Supabase rollout runbook

This runbook moves SmartQMS from its characterized MySQL runtime to Supabase
without exposing privileged credentials or removing the existing routes.

## Provider modes

- `local`: MySQL remains authoritative. This is the fail-safe default.
- `shadow`: MySQL remains authoritative and queue mutations mirror audit events
  to Supabase. Use this to validate identifiers and operational connectivity.
- `supabase`: catalog, customer ticket, Staff, Admin, Realtime, Auth-token API,
  and privileged RPC adapters use Supabase.

An incomplete or invalid server configuration always resolves to `local`.

## Required configuration

Copy `config/supabase.example.php` to ignored `config/supabase.local.php`, or
set these server environment values:

```text
SMARTQMS_DATA_PROVIDER=shadow
SUPABASE_URL=https://PROJECT.supabase.co
SUPABASE_PUBLISHABLE_KEY=...
SUPABASE_SERVICE_ROLE_KEY=...
SUPABASE_JWT_AUDIENCE=authenticated
```

Configure prediction separately using ignored `config/ml.local.php` or:

```text
PYTHON_ML_URL=http://127.0.0.1:5000
PYTHON_ML_TOKEN=...
PYTHON_ML_TIMEOUT_SECONDS=2
```

Only `SUPABASE_URL`, `SUPABASE_PUBLISHABLE_KEY`, and the signed-in user's
short-lived access token are projected to authenticated browser pages. The
refresh token, service-role key, and ML token remain server-only. PHP refreshes
an expiring access token before rendering the shared role shell.

## Development migration order

Apply these files to a disposable Supabase development project in order:

1. `database/supabase/20260820_initial_schema.sql`
2. `database/supabase/20260820_staff_queue_operations.sql`
3. `database/supabase/20260820_admin_catalog_operations.sql`
4. `database/supabase/20260820_realtime_prediction_operations.sql`
5. `database/supabase/20260820_branch_role_operations.sql`

The initial migration enables RLS on every application table, grants only the
minimum public catalog/live-queue reads, installs authenticated ownership and
branch policies, and adds `tickets`, `counters`, and `queue_events` to the
Realtime publication. Later migrations expose only service-role RPCs for
transactional operational mutations.

## Data migration

1. Take and verify a recoverable MySQL backup.
2. Import branches and services first, preserving `legacy_id`.
3. Create Supabase Auth users; store the former MySQL `users.user_id` in
   `profiles.legacy_id`.
4. Import roles, staff branch assignments, capabilities, and counters.
5. Import tickets, predictions, queue events, notifications, and feedback.
6. Compare row counts, unique references, active-ticket uniqueness, role
   memberships, and ticket/counter relationships.
7. Never import password hashes into Supabase Auth. Use password reset or
   invitation flows for migrated identities.

## Gated activation

1. Start the Python service and verify `/health` reports a loaded,
   `ml/dataset/queue_data.csv`-verified artifact.
2. Enable `shadow` mode and exercise Client booking plus every Staff mutation.
3. Compare MySQL records with Supabase queue events and resolve all ID gaps.
4. Test Customer, Staff, Admin, and Super Admin access tokens against the
   compatibility APIs. Missing, invalid, foreign, and insufficient-role tokens
   must be rejected.
5. Verify Realtime reconnect, page visibility, and safety-poll behavior.
6. Switch only the development environment to `supabase`.
7. Run automated checks and the role/theme/viewport browser matrix.
8. Promote the same reviewed migrations and environment contract to production.

## Compatibility APIs

| Endpoint | Contract |
|---|---|
| `POST /api/auth/profile.php` | Verify token and return scoped profile/roles |
| `GET /api/services.php` | Public active service catalog |
| `GET /api/branches.php` | Public active branch catalog |
| `GET, POST /api/tickets.php` | Own active ticket / server-owned creation |
| `GET /api/queue.php` | Authenticated redacted live snapshot |
| `GET /api/admin/reports.php` | Admin report builder façade |
| `GET, POST /api/admin/branches.php` | Admin branch management |
| `GET, POST, DELETE /api/admin/roles.php` | Super Admin role management |

Existing PHP form and Staff action routes remain compatible. In Supabase mode,
the new API endpoints require a bearer token; local mode retains PHP sessions
and CSRF protection. Ticket creation accepts only identifiers/classification;
the server derives queue length, counter capacity, service average, time, and
priority inputs before calling Python.

## Rollback

Set `SMARTQMS_DATA_PROVIDER=local`, restart PHP, and confirm the provider shown
by `/api/services.php` is `local`. This restores the existing MySQL runtime;
do not delete Supabase data during rollback. Reconcile shadow events before a
later retry.

## Environment-blocked verification

Without a configured development Supabase project, migrations, RLS behavior,
Auth tokens, Realtime delivery, and service-role RPC execution can only be
verified statically. Without Python, model retraining and the live prediction
health check remain blocked. Neither condition is worked around with production
credentials, synthetic data, or weaker policies.
