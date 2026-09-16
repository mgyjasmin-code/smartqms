<?php
/**
 * SmartQMS -- Unified Login Handler
 * Handles POST from the unified Staff/Admin login form.
 * Detects role from database -> redirects to correct dashboard.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/auth_utils.php';

requirePostRequest(false, 'login/', 'login', [], 'Please sign in using the form.');

$credentials = normalizedLoginCredentials($_POST);
$loginId = $credentials['login_id'];
$email = $credentials['email'];
$password = $credentials['password'];
$oldInput = ['login_id' => $loginId];
$fieldErrors = [];

requireValidCsrf('login/', 'login', $oldInput);

if (!hasRequiredText($loginId)) {
    $fieldErrors['login_id'] = 'Email address or username is required.';
} elseif (!isValidEmail($loginId) && !preg_match('/^[A-Za-z0-9._-]{3,100}$/', $loginId)) {
    $fieldErrors['login_id'] = 'Enter a valid email address or username.';
}

if (!hasRequiredText($password, false)) {
    $fieldErrors['password'] = 'Password is required.';
}

if ($fieldErrors) {
    redirectWithFormFeedback('login/', 'login', $fieldErrors, $oldInput);
}

$loginThrottle = authThrottleStatus($conn, 'login', $email, LOGIN_ATTEMPT_LIMIT, LOGIN_ATTEMPT_WINDOW_SECONDS);
if (!$loginThrottle['allowed']) {
    redirectWithFormFeedback('login/', 'login', [], $oldInput, authThrottleMessage((int) $loginThrottle['retry_after']));
}

if (smartqmsDataProviderMode() === 'supabase') {
    $authResponse = smartqmsSupabaseAuthRequest(
        'POST', '/auth/v1/token?grant_type=password', ['email' => $email, 'password' => $password]
    );
    $authSession = is_array($authResponse['data']) ? $authResponse['data'] : [];
    $principal = $authResponse['ok']
        ? smartqmsSupabasePrincipal((string) ($authSession['access_token'] ?? ''))
        : null;
    if (!$principal) {
        recordAuthAttempt($conn, 'login', $email, LOGIN_ATTEMPT_LIMIT, LOGIN_ATTEMPT_WINDOW_SECONDS);
        redirectWithFormFeedback('login/', 'login', [], $oldInput, 'Email or password is incorrect.');
    }
    try {
        smartqmsCompleteSupabaseLogin($principal, $authSession);
    } catch (Throwable $error) {
        redirectWithFormFeedback('login/', 'login', [], $oldInput, 'This account is not ready for SmartQMS access.');
    }
    if (!in_array((string) ($_SESSION['role'] ?? ''), [ROLE_ADMIN, ROLE_STAFF], true)) {
        destroyAuthenticatedSession();
        redirectWithFormFeedback('login/', 'login', [], $oldInput, 'Client accounts no longer sign in. Use the public queue instead.');
    }
    clearOtpSession();
    clearAuthAttempts($conn, 'login', $email);
    redirectAfterLogin((string) $_SESSION['role']);
}

$user = authUserByLoginId($conn, $loginId);
$loginStatus = authLoginStatus($user, $password);
$invalidLoginMessage = 'Email, username, or password is incorrect.';

if ($loginStatus === 'invalid_credentials') {
    recordSecurityEvent($conn, 'authentication_failed', 'denied', 'account', null, ['reason' => 'invalid_credentials']);
    recordAuthAttempt($conn, 'login', $email, LOGIN_ATTEMPT_LIMIT, LOGIN_ATTEMPT_WINDOW_SECONDS);
    redirectWithFormFeedback('login/', 'login', [], $oldInput, $invalidLoginMessage);
}

if ($loginStatus === 'inactive') {
    recordSecurityEvent($conn, 'authentication_failed', 'denied', 'account', null, ['reason' => 'inactive']);
    recordAuthAttempt($conn, 'login', $email, LOGIN_ATTEMPT_LIMIT, LOGIN_ATTEMPT_WINDOW_SECONDS);
    redirectWithFormFeedback('login/', 'login', [], $oldInput, 'This account cannot sign in right now.');
}

if (!in_array((string) $user['role'], [ROLE_ADMIN, ROLE_STAFF], true)) {
    redirectWithFormFeedback('login/', 'login', [], $oldInput, 'Client accounts no longer sign in. Use the public queue instead.');
}

clearAuthAttempts($conn, 'login', $email);
// Password authentication completes both Staff and Administrator sign-ins.
// OTP remains available only for account recovery and legacy registration.
clearOtpSession();
completeLogin($conn, $user);
if ((int) ($user['must_change_password'] ?? 0) === 1) {
    redirectTo('change-password/');
}
redirectAfterLogin($_SESSION['role']);
?>
