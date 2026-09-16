<?php
/** Create the first administrator. This command is intentionally CLI-only. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$email = normalizeEmail((string) getenv('SMARTQMS_BOOTSTRAP_ADMIN_EMAIL'));
$password = (string) getenv('SMARTQMS_BOOTSTRAP_ADMIN_PASSWORD');
$firstName = trim((string) (getenv('SMARTQMS_BOOTSTRAP_ADMIN_FIRST_NAME') ?: 'System'));
$lastName = trim((string) (getenv('SMARTQMS_BOOTSTRAP_ADMIN_LAST_NAME') ?: 'Administrator'));

if (!isValidEmail($email)) {
    fwrite(STDERR, "SMARTQMS_BOOTSTRAP_ADMIN_EMAIL must be a valid email address.\n");
    exit(1);
}
if (strlen($password) < 14
    || !preg_match('/[a-z]/', $password)
    || !preg_match('/[A-Z]/', $password)
    || !preg_match('/\d/', $password)
    || !preg_match('/[^A-Za-z0-9]/', $password)) {
    fwrite(STDERR, "SMARTQMS_BOOTSTRAP_ADMIN_PASSWORD must be at least 14 characters and include upper, lower, number, and symbol.\n");
    exit(1);
}
if (in_array(strtolower($email), ['admin@smartqms.local', 'admin@example.com'], true)) {
    fwrite(STDERR, "Known demonstration administrator identities are not allowed.\n");
    exit(1);
}

$existing = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin'")->fetch_assoc();
if ((int) ($existing['total'] ?? 0) > 0) {
    fwrite(STDERR, "An administrator already exists; bootstrap was not performed.\n");
    exit(2);
}

$username = $email;
$hash = password_hash($password, PASSWORD_BCRYPT);
$role = ROLE_ADMIN;
$title = 'System Administrator';
$verified = 1;
$mustChange = 1;
$stmt = $conn->prepare(
    'INSERT INTO users (username, first_name, last_name, email, password_hash, must_change_password, role, job_title, is_verified) '
    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->bind_param('sssssissi', $username, $firstName, $lastName, $email, $hash, $mustChange, $role, $title, $verified);
$stmt->execute();

fwrite(STDOUT, "The first administrator was created and must change the password at first sign-in.\n");
