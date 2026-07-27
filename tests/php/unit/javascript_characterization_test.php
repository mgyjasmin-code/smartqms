<?php

function batch7Source(string $relativePath): string {
    $source = file_get_contents(SMARTQMS_ROOT . '/' . $relativePath);
    if ($source === false) {
        throw new RuntimeException('Could not read ' . $relativePath);
    }
    return $source;
}

testCase('JavaScript ownership is separated across shared client staff admin and display files', function (): void {
    $main = batch7Source('assets/js/main.js');
    $shell = batch7Source('assets/js/shell.js');
    $client = batch7Source('assets/js/client.js');
    $staff = batch7Source('assets/js/staff.js');

    foreach (['initValidatedForms', 'initOtpResendCountdown', 'initPasswordToggles', 'initEmailDispatch', 'escapeHtml'] as $function) {
        assertStringContains("function {$function}", $main);
    }
    assertFalseValue(str_contains($main, 'function initLogoutConfirmation'), 'Bootstrap logout behavior belongs to the shared shell.');
    foreach (['initServicePrediction', 'initQueueStatus', 'initNotificationPolling', 'initFeedbackForm', 'initTicketPrinting'] as $function) {
        assertStringContains("function {$function}", $client);
        assertFalseValue(str_contains($main, "function {$function}"), $function . ' must not remain in main.js.');
    }
    foreach (['initStaffActions', 'initVoidCountdown'] as $function) {
        assertStringContains("function {$function}", $staff);
        assertFalseValue(str_contains($main, "function {$function}"), $function . ' must not remain in main.js.');
    }
    assertFalseValue(str_contains($staff, 'function initStaffTheme'), 'Theme behavior belongs to shell.js.');
    foreach (['initTheme', 'initSidebar', 'initSubmenus', 'initUserMenu', 'initSearch', 'initToasts'] as $function) {
        assertStringContains("function {$function}", $shell);
        assertFalseValue(str_contains($main, "function {$function}"), $function . ' must not be duplicated in main.js.');
    }
    foreach (['initThemeToggle', 'initSidebar', 'initReportMenu', 'initAdminSearch', 'initUserMenu'] as $function) {
        assertFalseValue(str_contains(batch7Source('assets/js/admin.js'), "function {$function}"), $function . ' must not be duplicated in admin.js.');
    }
});

testCase('feature scripts use one DOM-ready initializer guards and pagehide cleanup', function (): void {
    foreach (['main', 'shell', 'client', 'staff', 'admin', 'display'] as $script) {
        $source = batch7Source("assets/js/{$script}.js");
        assertSameValue(1, substr_count($source, "addEventListener('DOMContentLoaded'"), "{$script}.js DOM-ready count");
        assertStringContains('Initialized', $source, "{$script}.js initialization guard");
    }
    foreach (['main', 'client', 'staff', 'display'] as $script) {
        assertStringContains("addEventListener('pagehide'", batch7Source("assets/js/{$script}.js"), "{$script}.js cleanup");
    }
});

testCase('client and staff fetches retain credentials CSRF and in-flight guards', function (): void {
    foreach (['client', 'staff'] as $script) {
        $source = batch7Source("assets/js/{$script}.js");
        assertStringContains("credentials: 'same-origin'", $source);
        assertStringContains("'X-CSRF-Token'", $source);
        assertStringContains('requestInFlight', $source);
    }
});

testCase('page script loading follows authentication client staff admin and display ownership', function (): void {
    $clientViews = [
        'views/client/index.php',
        'views/client/queue_status.php',
        'views/client/ticket.php',
    ];
    foreach ($clientViews as $view) {
        $source = batch7Source($view);
        assertStringContains("includes/header.php", $source, $view);
        assertStringContains("includes/footer.php", $source, $view);
    }

    $shared = batch7Source('views/shared/includes/shell_footer.php');
    assertTrueValue(strpos($shared, 'assets/js/main.js') < strpos($shared, 'assets/js/shell.js'));
    assertStringContains("'scripts' => ['assets/js/client.js']", batch7Source('views/client/includes/header.php'));
    assertStringContains("'scripts' => ['assets/js/staff.js']", batch7Source('views/staff/includes/header.php'));
    assertStringContains("'scripts' => ['assets/js/admin.js']", batch7Source('views/admin/includes/header.php'));
    $display = batch7Source('views/display/board.php');
    assertStringContains('assets/js/display.js', $display);
    assertFalseValue(str_contains($display, 'assets/js/main.js'));
});

testCase('inline click and feedback handlers are removed from PHP views', function (): void {
    $views = glob(SMARTQMS_ROOT . '/views/*/*.php') ?: [];
    foreach ($views as $view) {
        $source = file_get_contents($view) ?: '';
        assertFalseValue((bool) preg_match('/\son(?:click|submit)\s*=/i', $source), basename($view));
    }
    $ticket = batch7Source('views/client/ticket.php');
    assertFalseValue(str_contains($ticket, 'feedbackForm.addEventListener'));
    assertStringContains('data-feedback-form', $ticket);
    assertStringContains('data-ticket-print', $ticket);
});

testCase('removed legacy JavaScript globals have no source definitions', function (): void {
    $sources = batch7Source('assets/js/main.js')
        . batch7Source('assets/js/client.js')
        . batch7Source('assets/js/staff.js');
    foreach ([
        'startQueuePolling',
        'loadPredictedWaitTime',
        'startVoidCountdown',
        'startVoidChecker',
        'startNotificationPoller',
    ] as $legacyFunction) {
        assertFalseValue(str_contains($sources, "function {$legacyFunction}"));
    }
});
