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
$email = normalizeEmail((string) ($_POST['email'] ?? ''));
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

if (!hasRequiredText($firstName)) {
    $fieldErrors['first_name'] = 'First name is required.';
}
if (!hasRequiredText($lastName)) {
    $fieldErrors['last_name'] = 'Last name is required.';
}
if (!hasRequiredText($email)) {
    $fieldErrors['email'] = 'Email is required.';
} elseif (!isValidEmail($email)) {
    $fieldErrors['email'] = 'Enter a valid email address.';
}
if (!hasRequiredText($password, false)) {
    $fieldErrors['password'] = 'Password is required.';
} elseif (!hasMinimumLength($password, 8)) {
    $fieldErrors['password'] = 'Password must be at least 8 characters.';
}

if ($fieldErrors) {
    redirectWithFormFeedback('views/client/register.php', 'register', $fieldErrors, $oldInput);
}

if (authEmailExists($conn, $email)) {
    redirectWithFormFeedback('views/client/register.php', 'register', [
        'email' => 'That email is already registered.',
    ], $oldInput);
}

$hash = hashAuthPassword($password);
$userId = createUnverifiedClient($conn, $firstName, $lastName, $email, $hash);

startRegistrationOtpSession($userId);
$queued = issueOtp($conn, $userId, 'Your SmartQMS verification code is', 'otp');
if ($queued) {
    markOtpSent();
}
logActivity($conn, 'register', 'Client registered and OTP was generated', null, $userId, ROLE_CLIENT);

if (!$queued) {
    redirectWithFormFeedback('views/client/verify_otp.php', 'verify_otp', [], [], 'Account created, but OTP email could not be queued. Please try resending the code.');
}

redirectTo('views/client/verify_otp.php', ['msg' => 'otp_sent']);
?>
