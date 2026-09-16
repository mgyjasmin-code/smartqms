<?php

function authUiSource(string $relativePath): string {
    $source = file_get_contents(SMARTQMS_ROOT . '/' . $relativePath);
    if ($source === false) {
        throw new RuntimeException('Could not read ' . $relativePath);
    }
    return $source;
}

testCase('Staff and Admin login uses an unmodified Bootstrap composition and accessible controls', function (): void {
    $login = authUiSource('login/index.php');
    assertStringContains('class="bg-light"', $login);
    assertStringContains('container min-vh-100 d-flex align-items-center justify-content-center py-5', $login);
    assertStringContains('col-12 col-sm-10 col-md-8 col-lg-7 col-xl-6 col-xxl-5', $login);
    assertStringContains('class="card shadow-sm"', $login);
    assertFalseValue(str_contains($login, 'class="card-header'), 'The login card must not render a branded header.');
    assertStringContains('card-body p-4 p-sm-5', $login);
    assertStringContains('class="card-title h2 text-center mb-4">Login', $login);
    assertStringContains('class="form-floating mb-3"', $login);
    assertStringContains('class="form-floating position-relative"', $login);
    assertStringContains('class="d-grid"', $login);
    assertStringContains("vendor/twbs/bootstrap/dist/css/bootstrap.min.css", $login);
    assertFalseValue(str_contains($login, 'assets/css/style.css'));
    assertFalseValue(str_contains($login, 'includes/public_header.php'));
    assertFalseValue(str_contains($login, 'includes/public_footer.php'));
    assertFalseValue(str_contains($login, 'theme_boot.php'));
    assertFalseValue(str_contains($login, 'assets/js/theme.js'));
    assertFalseValue(str_contains($login, 'assets/js/language.js'));
    assertFalseValue(str_contains($login, 'assets/vendor/lucide'));
    assertFalseValue(str_contains($login, 'Staff and administration'));
    assertFalseValue(str_contains($login, 'Remember me'), 'The UI must not promise unsupported persistent authentication.');
    assertStringContains('class="js-auth-form"', $login);
    assertStringContains('autocomplete="username"', $login);
    assertStringContains('autocomplete="current-password"', $login);
    assertStringContains('data-password-show-icon', $login);
    assertStringContains('data-password-hide-icon', $login);
    assertStringContains('aria-controls="password"', $login);
    assertStringContains('aria-pressed="false"', $login);

    $css = authUiSource('assets/css/style.css');
    assertStringContains('.public-auth-composition {', $css);
    assertStringContains('.public-auth-context {', $css);
    assertStringContains('.public-auth-form-panel {', $css);

    $register = authUiSource('views/client/register.php');
    assertStringContains("redirectTo('queue/join/')", $register);
    assertFalseValue(str_contains($register, '<form'), 'Retired client registration has no account form.');

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
    assertStringContains('public-recovery-steps', $forgotPassword);
    assertStringContains('Step <?= $recoveryStage ?> of 3', $forgotPassword);

    $css = authUiSource('assets/css/style.css');
    assertStringContains('.auth-form-stable-errors .field-error {', $css);
    assertStringContains('min-height: 18px;', $css);
    assertStringContains('.auth-form-stable-errors .field-error:empty {', $css);
    assertStringContains('visibility: hidden;', $css);
});
