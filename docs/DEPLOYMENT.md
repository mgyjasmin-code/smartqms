# SmartQMS Production Deployment

1. Serve only approved public entry points and static assets. If a legacy
   repository-root deployment is temporarily unavoidable, keep the root
   `.htaccess` restrictions enabled.
2. Inject the variables listed in `.env.example` from a secret manager. Never
   put populated values in the repository, browser configuration, screenshots,
   logs, or release archives.
3. Apply ordered files from `database/migrations/` using a migration identity.
   Give the application runtime identity only `SELECT`, `INSERT`, `UPDATE`, and
   `DELETE` on the application schema. Do not grant `CREATE`, `ALTER`, `DROP`,
   `INDEX`, `GRANT OPTION`, or global privileges. The readiness command checks
   the effective runtime grants.
4. Run `php scripts/check_production_readiness.php`. The release must fail when
   HTTPS, credentials, migrations, or demo-account checks fail.
5. On a new database, run `php scripts/bootstrap_admin.php` once. The generated
   administrator must rotate the deployment password at first login and then
   complete emailed OTP verification on every login.
6. Run PHP tests, JavaScript contracts, PHP syntax checks, Composer audit,
   Python dependency audit, secret scanning, accessibility checks, and staging
   security tests before promotion.
7. Bind the ML API to a private interface and configure `PYTHON_ML_TOKEN`; the
   service rejects prediction requests when the token is absent.
8. Configure encrypted backups, perform a restoration test, and record the
   recovery result before launch.

The repository contains tests, documentation, ML training inputs, and developer
tools that are not production web content. Exclude them from release artifacts.
