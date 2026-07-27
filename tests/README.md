# SmartQMS Characterization Tests

These dependency-free tests document current SmartQMS behavior before the
incremental procedural refactor. They must never use the development database.

## Safety rules

- Test credentials come only from `tests/config.local.php` or
  `SMARTQMS_TEST_DB_*` environment variables.
- The configured database name must end in `_test` or `_testing`.
- The bootstrap verifies `SELECT DATABASE()` after connecting.
- Integration fixtures run inside transactions and are rolled back.
- Tests do not send email/SMS, create QR files, or contact the ML service.

## Create the isolated database

The canonical schema intentionally contains `CREATE DATABASE smartqms` and
`USE smartqms`. Do not import it unchanged for tests. In PowerShell, substitute
only those two statements while streaming the schema to MySQL:

```powershell
$schema = Get-Content -LiteralPath database\smartqms_final_v4.sql -Raw
$schema = $schema.Replace(
  'CREATE DATABASE IF NOT EXISTS smartqms',
  'CREATE DATABASE IF NOT EXISTS smartqms_test'
).Replace('USE smartqms;', 'USE smartqms_test;')
$schema | & 'C:\xampp\mysql\bin\mysql.exe' --user=root
```

Copy the example configuration:

```powershell
Copy-Item tests\config.example.php tests\config.local.php
```

`tests/config.local.php` is ignored by Git.

## Run

```powershell
php tests\php\run.php
```

The runner executes unit tests first, then integration tests in deterministic
filename order. It exits with status `1` when any test fails.

## Existing project checks

```powershell
Get-ChildItem -Recurse -Filter *.php -File |
  Where-Object { $_.FullName -notmatch '\\vendor\\' } |
  ForEach-Object { php -l $_.FullName }

composer validate --no-check-publish
node --check assets\js\main.js
node --check assets\js\admin.js
node --check assets\js\display.js
```

Python/ML tests remain separately blocked until a working Python runtime is
available. Do not change `ml/requirements.txt` to work around a missing runtime.
