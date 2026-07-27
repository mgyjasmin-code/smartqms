<?php

function authUiSource(string $relativePath): string {
    $source = file_get_contents(SMARTQMS_ROOT . '/' . $relativePath);
    if ($source === false) {
        throw new RuntimeException('Could not read ' . $relativePath);
    }
    return $source;
}

testCase('authentication forms preserve stable spacing and requested content', function (): void {
    $login = authUiSource('index.php');
    assertStringContains('class="auth-form auth-form-compact-feedback auth-form-stable-errors auth-form-polished js-auth-form"', $login);

    $css = authUiSource('assets/css/style.css');
    assertStringContains('.auth-form-polished {', $css);
    assertStringContains('gap: 8px;', $css);
    assertStringContains('.auth-form-polished .auth-label {', $css);
    assertStringContains('margin-bottom: 8px;', $css);
    assertStringContains('.auth-form-compact-feedback {', $css);
    assertStringContains('.auth-form-compact-feedback.auth-form-stable-errors .field-error {', $css);
    assertStringContains('.auth-form-compact-feedback .auth-grid-2 {', $css);
    assertStringContains('min-height: 14px;', $css);
    assertStringContains('margin-top: 2px;', $css);

    $register = authUiSource('views/client/register.php');
    assertStringContains('class="auth-form auth-form-compact-feedback auth-form-stable-errors auth-form-polished js-auth-form"', $register);
    assertFalseValue(str_contains($register, 'password-helper'), 'Registration no longer renders the password helper.');
    assertFalseValue(str_contains($register, 'Your password is never included in email messages.'));
    assertFalseValue(str_contains($register, 'auth-admin-note'), 'Registration no longer renders the client-only note.');
    assertFalseValue(str_contains($register, 'This form creates client accounts only.'));

    assertFalseValue(str_contains($css, '.auth-helper {'), 'Unused registration helper styling was removed.');
    assertFalseValue(str_contains($css, '.auth-admin-note {'), 'Unused registration note styling was removed.');
});

testCase('forgot password request reserves validation feedback height', function (): void {
    $forgotPassword = authUiSource('views/client/forgot_password.php');
    assertStringContains(
        'name="action" value="request_otp"',
        $forgotPassword
    );
    assertStringContains(
        'class="auth-form auth-form-stable-errors js-auth-form"',
        $forgotPassword
    );

    $css = authUiSource('assets/css/style.css');
    assertStringContains('.auth-form-stable-errors .field-error {', $css);
    assertStringContains('min-height: 18px;', $css);
    assertStringContains('.auth-form-stable-errors .field-error:empty {', $css);
    assertStringContains('visibility: hidden;', $css);
});
