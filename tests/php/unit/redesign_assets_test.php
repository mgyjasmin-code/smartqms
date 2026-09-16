<?php

testCase('Bootstrap 5.3.8 is served through verified public assets without opening Composer internals', function (): void {
    $copies = [
        'vendor/twbs/bootstrap/dist/css/bootstrap.min.css' => 'assets/vendor/bootstrap/bootstrap.min.css',
        'vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js' => 'assets/vendor/bootstrap/bootstrap.bundle.min.js',
        'vendor/twbs/bootstrap/LICENSE' => 'assets/vendor/bootstrap/LICENSE',
    ];
    foreach ($copies as $original => $public) {
        assertSameValue(hash_file('sha256', SMARTQMS_ROOT . '/' . $original), hash_file('sha256', SMARTQMS_ROOT . '/' . $public));
        if (str_ends_with($public, '.css') || str_ends_with($public, '.js')) {
            assertStringContains('v5.3.8', file_get_contents(SMARTQMS_ROOT . '/' . $public));
            assertStringContains(APP_URL . '/' . $public . '?v=', assetUrl($original));
        }
    }
    $manifest = json_decode(file_get_contents(SMARTQMS_ROOT . '/assets/vendor/bootstrap/manifest.json'), true);
    assertSameValue('5.3.8', $manifest['version'] ?? null);
    assertTrueValue(is_file(SMARTQMS_ROOT . '/scripts/publish_bootstrap.php'));
    assertStringContains('assets/css/style.css?v=', assetUrl('assets/css/style.css'));
    assertStringContains('|vendor|', file_get_contents(SMARTQMS_ROOT . '/.htaccess'));
});

testCase('stylesheet ownership and Bootstrap theme synchronization are role aware', function (): void {
    $header = file_get_contents(SMARTQMS_ROOT . '/views/shared/includes/shell_header.php');
    $shellScript = file_get_contents(SMARTQMS_ROOT . '/assets/js/shell.js');
    $display = file_get_contents(SMARTQMS_ROOT . '/views/display/board.php');
    $publicDisplay = file_get_contents(SMARTQMS_ROOT . '/public-display/index.php');
    $styleCss = file_get_contents(SMARTQMS_ROOT . '/assets/css/style.css');
    $adminCss = file_get_contents(SMARTQMS_ROOT . '/assets/css/admin.css');
    $displayCss = file_get_contents(SMARTQMS_ROOT . '/assets/css/display.css');

    assertStringContains("if (\$appRole === 'admin')", $header);
    assertStringContains("assetUrl('assets/css/admin.css')", $header);
    assertStringContains("setAttribute('data-bs-theme', theme)", $header);
    assertStringContains("setAttribute('data-bs-theme', normalized)", $shellScript);
    assertStringContains('data-bs-theme="dark"', $display);
    assertStringContains("assetUrl('assets/css/display.css')", $display);
    assertStringContains("assetUrl('assets/css/display.css')", $publicDisplay);
    assertStringContains('body.app-staff-page.staff-page .app-shell', $styleCss);
    assertFalseValue(str_contains($adminCss, '.app-staff-page'));
    assertStringContains('.public-display-shell', $displayCss);
    assertFalseValue(str_contains($styleCss, '.public-display-shell'));
});
