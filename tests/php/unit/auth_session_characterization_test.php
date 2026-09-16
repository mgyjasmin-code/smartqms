<?php

require_once SMARTQMS_ROOT . '/modules/auth/auth_utils.php';

testCase('auth utilities declare the current OTP session keys', function (): void {
    $source = file_get_contents(SMARTQMS_ROOT . '/modules/auth/auth_session.php');
    assertTrueValue(is_string($source));

    foreach ([
        'otp_flow',
        'otp_user_id',
        'otp_started_at',
        'pending_user_id',
        'otp_last_sent_at',
        'reset_user_id',
        'reset_capability',
    ] as $sessionKey) {
        assertStringContains("['" . $sessionKey . "']", $source, 'Missing auth session contract.');
    }
});

testCase('login completion declares the authenticated session contract', function (): void {
    $source = file_get_contents(SMARTQMS_ROOT . '/modules/auth/auth_session.php');
    foreach (['user_id', 'role', 'name', 'phone', 'email', 'staff_id'] as $sessionKey) {
        assertStringContains("['" . $sessionKey . "']", $source, 'Missing authenticated session key.');
    }
    assertStringContains('session_regenerate_id(true)', $source);
});

testCase('role redirection retains Staff and Admin destinations while clients return public', function (): void {
    $source = file_get_contents(SMARTQMS_ROOT . '/modules/auth/auth_redirects.php');
    assertStringContains('views/admin/dashboard.php', $source);
    assertStringContains('staff/select-counter/', $source);
    assertStringContains("return '';", $source);
});

testCase('login credential normalization preserves raw passwords', function (): void {
    $credentials = normalizedLoginCredentials([
        'login_id' => '  Client@Example.TEST ',
        'password' => '  secret value  ',
    ]);

    assertSameValue('Client@Example.TEST', $credentials['login_id']);
    assertSameValue('client@example.test', $credentials['email']);
    assertSameValue('  secret value  ', $credentials['password']);
});

testCase('login account status preserves generic credential disclosure', function (): void {
    $passwordHash = password_hash('correct-password', PASSWORD_BCRYPT);
    $active = ['password_hash' => $passwordHash, 'is_active' => 1, 'is_verified' => 0];
    $inactive = ['password_hash' => $passwordHash, 'is_active' => 0, 'is_verified' => 1];

    assertSameValue('invalid_credentials', authLoginStatus(null, 'correct-password'));
    assertSameValue('invalid_credentials', authLoginStatus($active, 'wrong-password'));
    assertSameValue('inactive', authLoginStatus($inactive, 'correct-password'));
    assertSameValue('accepted', authLoginStatus($active, 'correct-password'));
});

testCase('authentication password hashing retains bcrypt verification', function (): void {
    $hash = hashAuthPassword('characterization-password');
    assertTrueValue(password_verify('characterization-password', $hash));
    assertFalseValue(password_verify('wrong-password', $hash));
});

testCase('OTP hash and expiry checks retain wrong expired and valid states', function (): void {
    $hash = password_hash('123456', PASSWORD_BCRYPT);

    assertSameValue('wrong', otpVerificationStatus(null, '123456', 1000));
    assertSameValue('wrong', otpVerificationStatus([
        'otp_hash' => $hash,
        'otp_expires_at' => date('Y-m-d H:i:s', 2000),
    ], '654321', 1000));
    assertSameValue('expired', otpVerificationStatus([
        'otp_hash' => $hash,
        'otp_expires_at' => date('Y-m-d H:i:s', 999),
    ], '123456', 1000));
    assertSameValue('valid', otpVerificationStatus([
        'otp_hash' => $hash,
        'otp_expires_at' => date('Y-m-d H:i:s', 1001),
    ], '123456', 1000));
});

testCase('OTP flow metadata is limited to recovery and legacy registration', function (): void {
    assertSameValue('Your SmartQMS password reset code is', otpMessagePrefixForFlow('reset'));
    assertSameValue('Your SmartQMS verification code is', otpMessagePrefixForFlow('register'));

    assertSameValue([
        'target' => 'forgot-password/',
        'form_key' => 'forgot_password_otp',
    ], otpFlowFormContext('reset'));
    assertTrueValue(otpResendCooldownActive(1000, 1119));
    assertFalseValue(otpResendCooldownActive(1000, 1120));
    assertFalseValue(otpResendCooldownActive(0, 1000));
});

testCase('OTP session flow helpers retain all transient keys and cleanup', function (): void {
    $originalSession = $_SESSION;
    try {
        $_SESSION = [];
        startRegistrationOtpSession(11);
        assertSameValue(11, $_SESSION['pending_user_id']);
        assertSameValue('register', $_SESSION['otp_flow']);
        assertSameValue(11, $_SESSION['otp_user_id']);
        assertTrueValue(isset($_SESSION['otp_started_at']));

        startPasswordResetOtpSession(13);
        markOtpSent();
        assertSameValue(13, $_SESSION['reset_user_id']);
        $_SESSION['reset_capability'] = str_repeat('a', 64);
        assertTrueValue(isset($_SESSION['otp_last_sent_at']));

        clearOtpSession();
        foreach ([
            'otp_flow',
            'otp_user_id',
            'otp_started_at',
            'pending_user_id',
            'pending_login_user_id',
            'otp_last_sent_at',
            'reset_user_id',
            'reset_capability',
        ] as $sessionKey) {
            assertFalseValue(array_key_exists($sessionKey, $_SESSION));
        }
    } finally {
        $_SESSION = $originalSession;
    }
});

testCase('administrator password login bypasses the retired OTP challenge', function (): void {
    $login = (string) file_get_contents(SMARTQMS_ROOT . '/modules/auth/login.php');
    assertStringContains('clearOtpSession();', $login);
    assertStringContains('completeLogin($conn, $user);', $login);
    foreach (['startLoginOtpSession', 'issueOtp(', "redirectTo('verify-login/'"] as $retiredLoginOtpContract) {
        assertFalseValue(str_contains($login, $retiredLoginOtpContract));
    }

    $legacyPage = (string) file_get_contents(SMARTQMS_ROOT . '/verify-login/index.php');
    assertStringContains('clearOtpSession();', $legacyPage);
    assertStringContains("redirectTo('login/');", $legacyPage);
    foreach (['name="otp_code"', 'Verify and continue', 'Resend security code'] as $retiredControl) {
        assertFalseValue(str_contains($legacyPage, $retiredControl));
    }

    $verification = (string) file_get_contents(SMARTQMS_ROOT . '/modules/auth/verify_otp.php');
    $resend = (string) file_get_contents(SMARTQMS_ROOT . '/modules/auth/resend_otp.php');
    assertStringContains('if ($flow === \'login\')', $verification);
    assertStringContains('if ($flow === \'login\')', $resend);
});

testCase('role destination selection retains Staff and Admin while client roles use the landing page', function (): void {
    assertSameValue('views/admin/dashboard.php', roleDestinationPath(ROLE_ADMIN));
    assertSameValue('staff/select-counter/', roleDestinationPath(ROLE_STAFF));
    assertSameValue('', roleDestinationPath(ROLE_CLIENT));
    assertSameValue('', roleDestinationPath('unknown'));
});
