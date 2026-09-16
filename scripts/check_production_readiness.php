<?php
/** Fail-fast release check. This command never prints secret values. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$errors = [];
$required = ['SMARTQMS_APP_URL', 'SMARTQMS_DB_HOST', 'SMARTQMS_DB_USER', 'SMARTQMS_DB_PASS', 'SMARTQMS_DB_NAME'];
foreach ($required as $name) {
    if (trim((string) getenv($name)) === '') {
        $errors[] = $name . ' is not configured.';
    }
}
if (strtolower((string) parse_url((string) getenv('SMARTQMS_APP_URL'), PHP_URL_SCHEME)) !== 'https') {
    $errors[] = 'SMARTQMS_APP_URL must use HTTPS.';
}
if (strtolower(trim((string) getenv('SMARTQMS_DB_USER'))) === 'root') {
    $errors[] = 'The runtime database identity must not be root.';
}
if (!in_array(strtolower(trim((string) (getenv('SMARTQMS_DATA_PROVIDER') ?: 'local'))), ['', 'local'], true)) {
    $errors[] = 'The first production release requires SMARTQMS_DATA_PROVIDER=local.';
}
if ((getenv('SMARTQMS_TRUST_PROXY') ?: '0') === '1'
    && trim((string) getenv('SMARTQMS_TRUSTED_PROXY_IPS')) === '') {
    $errors[] = 'SMARTQMS_TRUSTED_PROXY_IPS is required when proxy trust is enabled.';
}
$mlUrl = trim((string) (getenv('SMARTQMS_ML_URL') ?: getenv('PYTHON_ML_URL')));
if (trim((string) getenv('PYTHON_ML_TOKEN')) === '' && $mlUrl !== '') {
    $errors[] = 'PYTHON_ML_TOKEN is required when the ML service is enabled.';
}
$emailMode = strtolower(trim((string) getenv('EMAIL_DELIVERY_MODE')));
if ($emailMode !== 'smtp') {
    $errors[] = 'Production requires EMAIL_DELIVERY_MODE=smtp.';
} else {
    foreach (['EMAIL_SMTP_USERNAME', 'EMAIL_SMTP_PASSWORD', 'EMAIL_FROM'] as $name) {
        if (trim((string) getenv($name)) === '') {
            $errors[] = $name . ' is required when SMTP delivery is enabled.';
        }
    }
}
foreach (['try.php', 'index.html'] as $forbidden) {
    if (is_file(__DIR__ . '/../' . $forbidden)) {
        $errors[] = $forbidden . ' must not be deployed.';
    }
}
foreach (['email.local.php', 'sms.local.php', 'supabase.local.php', 'ml.local.php'] as $localSecretFile) {
    if (is_file(__DIR__ . '/../config/' . $localSecretFile)) {
        $errors[] = 'config/' . $localSecretFile . ' must not be included in a production artifact.';
    }
}

if (!$errors) {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    $grantRows = $conn->query('SHOW GRANTS FOR CURRENT_USER()')->fetch_all(MYSQLI_NUM);
    foreach ($grantRows as $grantRow) {
        $grant = strtoupper((string) ($grantRow[0] ?? ''));
        if (str_contains($grant, 'ALL PRIVILEGES')
            || preg_match('/\b(CREATE|ALTER|DROP|INDEX)\b/', $grant)
            || str_contains($grant, 'GRANT OPTION')) {
            $errors[] = 'The runtime database identity has schema-changing or privilege-granting permissions.';
            break;
        }
    }
    $knownEmail = 'admin@smartqms.local';
    $knownHash = '$2y$10$0dVVbYM0md/nlp53WdgET./DnO2eae3DZLJaMdU6OZeU29mXR7rHK';
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM users WHERE LOWER(email) = ? OR password_hash = ?');
    $stmt->bind_param('ss', $knownEmail, $knownHash);
    $stmt->execute();
    if ((int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0) > 0) {
        $errors[] = 'A known demonstration administrator identity or password hash exists.';
    }
    $migration = $conn->prepare('SELECT COUNT(*) AS total FROM schema_migrations WHERE migration_id = ?');
    $migrationId = '20260905_001_production_security';
    $migration->bind_param('s', $migrationId);
    $migration->execute();
    if ((int) ($migration->get_result()->fetch_assoc()['total'] ?? 0) !== 1) {
        $errors[] = 'The production security migration has not been applied.';
    }
}

if ($errors) {
    foreach ($errors as $error) {
        fwrite(STDERR, '[FAIL] ' . $error . PHP_EOL);
    }
    exit(1);
}
fwrite(STDOUT, "Production configuration passed the static readiness checks.\n");
