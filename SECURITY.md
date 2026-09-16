# SmartQMS Security Policy

## Supported deployment

Only the latest deployed release is supported. Production must use HTTPS, a
non-root runtime database identity, server-side secrets, the recorded schema
migrations, and an allow-listed public document root. Supabase and shadow modes
remain disabled until their row-level policies and rollback path are separately
validated.

## Security properties

- Administrator access requires password plus emailed OTP.
- Authentication sessions expire after 30 minutes of inactivity and after an
  eight-hour absolute lifetime, and rotate identifiers every 15 minutes.
- Password-reset and reservation-management authority uses random, hashed,
  expiring capabilities. References and phone suffixes are not authorization.
- Queue ordering remains strict FIFO after physical check-in.
- Public displays and coarse compatibility status never expose names, phone
  numbers, private tokens, or clinical information.
- Credentials, reset/management tokens, session identifiers, OTPs, provider
  keys, and backup keys must not enter logs, screenshots, URLs other than the
  initial single-purpose capability exchange, database exports, or source.

## Reporting

Report suspected vulnerabilities privately to the designated deployment
security contact. Include the affected route, observed behavior, and a minimal
reproduction. Do not test against real client data or disrupt queue operations.

## Operations

Run `php scripts/check_production_readiness.php` before each release. Revoke and
rotate exposed secrets, preserve redacted audit evidence, restore from a tested
encrypted backup when necessary, and document incident decisions and timing.
