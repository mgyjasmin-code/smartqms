<?php
/**
 * SmartQMS -- Client Self-Registration Handler
 * Handles POST from the client registration form.
 * Flow: submit form -> email OTP -> verify OTP -> client dashboard.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/auth_utils.php';

requirePostRequest(false, 'views/client/register.php', 'register');

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$oldInput = [
    'first_name' => $firstName,
    'last_name' => $lastName,
    'email' => $email,
];
$fieldErrors = [];

requireValidCsrf('views/client/register.php', 'register', $oldInput);
$registerThrottle = authThrottleStatus($conn, 'register', 'client-registration', REGISTER_ATTEMPT_LIMIT, REGISTER_ATTEMPT_WINDOW_SECONDS);
if (!$registerThrottle['allowed']) {
    redirectWithFormFeedback('views/client/register.php', 'register', [], $oldInput, authThrottleMessage((int) $registerThrottle['retry_after']));
}
recordAuthAttempt($conn, 'register', 'client-registration', REGISTER_ATTEMPT_LIMIT, REGISTER_ATTEMPT_WINDOW_SECONDS);

if ($firstName === '') {
    $fieldErrors['first_name'] = 'First name is required.';
}
if ($lastName === '') {
    $fieldErrors['last_name'] = 'Last name is required.';
}
if ($email === '') {
    $fieldErrors['email'] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $fieldErrors['email'] = 'Enter a valid email address.';
}
if ($password === '') {
    $fieldErrors['password'] = 'Password is required.';
} elseif (strlen($password) < 8) {
    $fieldErrors['password'] = 'Password must be at least 8 characters.';
}

if ($fieldErrors) {
    redirectWithFormFeedback('views/client/register.php', 'register', $fieldErrors, $oldInput);
}

$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()) {
    redirectWithFormFeedback('views/client/register.php', 'register', [
        'email' => 'That email is already registered.',
    ], $oldInput);
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$role = ROLE_CLIENT;
$verified = 0;

$insert = $conn->prepare("
    INSERT INTO users (first_name, last_name, email, password_hash, role, is_verified)
    VALUES (?, ?, ?, ?, ?, ?)
");
$insert->bind_param('sssssi', $firstName, $lastName, $email, $hash, $role, $verified);
$insert->execute();
$userId = $conn->insert_id;

$_SESSION['pending_user_id'] = $userId;
setOtpSession('register', $userId);
$queued = issueOtp($conn, $userId, 'Your SmartQMS verification code is', 'otp');
if ($queued) {
    $_SESSION['otp_last_sent_at'] = time();
}
logActivity($conn, 'register', 'Client registered and OTP was generated', null, $userId, ROLE_CLIENT);

if (!$queued) {
    redirectWithFormFeedback('views/client/verify_otp.php', 'verify_otp', [], [], 'Account created, but OTP email could not be queued. Please try resending the code.');
}

redirectTo('views/client/verify_otp.php', ['msg' => 'otp_sent']);
?>
