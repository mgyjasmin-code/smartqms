SmartQMS Database Setup
=======================

Fresh local install
-------------------
1. Start Apache and MySQL in XAMPP.
2. Open http://localhost/phpmyadmin.
3. Create or select the smartqms database.
4. Import database/smartqms_final_v4.sql.

The fresh schema includes:
- SmartQMS tables
- Auth throttling table
- Email job queue table
- Report views
- Default health services
- Default system settings
- Local demo admin account

Local demo admin login:
  No default administrator is installed. Use the CLI-only
  scripts/bootstrap_admin.php command with deployment environment variables.

This password is for local team/demo machines only. Change it before any shared
or production-like use.

Existing local install
----------------------
1. Back up your current smartqms database.
2. Run database/upgrade_stabilization_2026_07_10.sql against the existing DB.
3. Confirm the local demo admin login works.

The upgrade script adds missing runtime schema, refreshes report views, ensures
display/ML settings exist, and updates the local demo admin password hash.
