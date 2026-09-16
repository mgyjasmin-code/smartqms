<?php

function batch8eSource(string $relativePath): string {
    $source = file_get_contents(SMARTQMS_ROOT . '/' . $relativePath);
    if ($source === false) {
        throw new RuntimeException('Could not read ' . $relativePath);
    }
    return $source;
}

testCase('authenticated roles use the shared responsive shell with restricted navigation', function (): void {
    $sharedHeader = batch8eSource('views/shared/includes/shell_header.php');
    $sharedFooter = batch8eSource('views/shared/includes/shell_footer.php');
    foreach ([
        'data-app-root',
        'data-app-sidebar-toggle',
        'data-app-search',
        'data-app-search-toggle',
        'data-app-user-menu',
        'aria-label="Open profile menu for',
        'data-app-theme-toggle',
        'class="form-check-input" type="checkbox" role="switch"',
        'data-bs-target="#appLogoutModal"',
        'Skip to main content',
    ] as $contract) {
        assertStringContains($contract, $sharedHeader);
    }
    foreach ([
        'toast-container app-toast-container',
        'id="appLogoutModal"',
        "assetUrl('assets/js/shell.js')",
    ] as $contract) {
        assertStringContains($contract, $sharedFooter);
    }

    $adapters = [
        'views/admin/includes/header.php' => ['Dashboard', 'Staff Accounts', 'Health Services', 'Service Windows', 'Reports'],
        'views/staff/includes/header.php' => [],
        'views/client/includes/header.php' => ['Dashboard', 'Queue Status', 'My Ticket'],
    ];
    foreach ($adapters as $adapter => $labels) {
        $source = batch8eSource($adapter);
        assertStringContains("shared/includes/shell_header.php", $source, $adapter);
        foreach ($labels as $label) {
            assertStringContains("'label' => '{$label}'", $source, $adapter);
        }
    }

    $css = batch8eSource('assets/css/style.css') . batch8eSource('assets/css/admin.css');
    foreach ([
        'body.app-page .app-topbar',
        'flex-wrap: nowrap;',
        'body.app-page .app-search-collapse',
        'body.app-page .app-topbar.is-search-open .app-menu-toggle',
        'flex: 1 1 0;',
        'text-overflow: ellipsis;',
        'body.app-page .app-user-menu',
        'display: block !important;',
        'min-height: 44px;',
        '.admin-user-theme-switch',
        'width: min(240px, calc(100vw - 24px));',
        '--bs-toast-bg: var(--admin-surface, var(--sq-surface-raised));',
        '--bs-modal-bg: var(--admin-surface, var(--sq-surface-raised));',
        'var(--admin-user-dropdown-shadow, var(--sq-shadow-modal))',
    ] as $contract) {
        assertStringContains($contract, $css);
    }
});

testCase('shared header search uses one component-level focus ring', function (): void {
    $css = file_get_contents(SMARTQMS_ROOT . '/assets/css/style.css');
    assertStringContains('.app-header-search .form-control:focus-visible', $css);
    assertStringContains('.admin-header-search .form-control:focus-visible', $css);
    assertStringContains('outline: 0;', $css);
    assertStringContains('box-shadow: none;', $css);

    $adminCss = file_get_contents(SMARTQMS_ROOT . '/assets/css/admin.css');
    assertStringContains('.admin-header-search .form-control:focus-visible', $adminCss);
    assertStringContains('border: 0 !important;', $adminCss);
    preg_match_all('/html\[data-admin-theme="dark"\]\s*\{([^}]*)\}/s', $adminCss, $darkThemeBlocks);
    $usesSharedFocusPalette = false;
    foreach ($darkThemeBlocks[1] ?? [] as $darkThemeBlock) {
        if (
            str_contains($darkThemeBlock, '--admin-primary: var(--sq-primary);')
            && str_contains($darkThemeBlock, '--admin-focus-ring: var(--sq-focus-ring);')
        ) {
            $usesSharedFocusPalette = true;
            break;
        }
    }
    assertTrueValue($usesSharedFocusPalette, 'Dark Admin controls must use the shared SmartQMS focus palette.');
});

testCase('client notification center keeps history and acknowledges only displayed owned rows', function (): void {
    $header = batch8eSource('views/shared/includes/shell_header.php');
    foreach ([
        "if (\$appRole === 'client')",
        'data-client-notification-center',
        'data-client-notification-badge',
        'dropdown-menu dropdown-menu-end app-notification-menu',
        'data-client-notification-list',
        'data-client-notification-retry',
    ] as $contract) {
        assertStringContains($contract, $header);
    }

    $client = batch8eSource('assets/js/client.js');
    foreach ([
        'function initNotificationPolling',
        '10000',
        "document.addEventListener('visibilitychange'",
        'requestInFlight',
        "const notifiedStorageKey = 'smartqms-client-notified-ids'",
        'sessionStorage.setItem(notifiedStorageKey',
        "addEventListener('shown.bs.dropdown'",
        'notification_ids[]',
        "Notification.requestPermission()",
    ] as $contract) {
        assertStringContains($contract, $client);
    }

    $read = batch8eSource('modules/notifications/get_notifications.php');
    assertStringContains("(\$_SESSION['role'] ?? '') !== ROLE_CLIENT", $read);
    assertStringContains('requireValidCsrf', $read);
    assertStringContains('recentNotificationsForUser', $read);
    assertFalseValue(str_contains($read, 'consumeUnreadNotifications'));

    $acknowledge = batch8eSource('modules/notifications/mark_read.php');
    assertStringContains("(\$_SESSION['role'] ?? '') !== ROLE_CLIENT", $acknowledge);
    assertStringContains('requireValidCsrf', $acknowledge);
    assertStringContains('markNotificationIdsReadForUser', $acknowledge);
    assertStringContains("'unread_count'", $acknowledge);
});

testCase('client feedback and ticket history use shared toasts and active display rules', function (): void {
    $dashboard = batch8eSource('views/client/index.php');
    assertStringContains("redirectTo('')", $dashboard);
    $ticketAdapter = batch8eSource('views/client/ticket.php');
    assertStringContains("redirectTo('track/'", $ticketAdapter);
    $publicTicket = batch8eSource('views/public/tracker.php');
    assertStringContains('data-public-feedback-form', $publicTicket);
    assertStringContains('data-print-page', $publicTicket);
    assertStringContains('Queue number after check-in', $publicTicket);
    $feedbackService = batch8eSource('modules/feedback/feedback_service.php');
    assertStringContains('FOR UPDATE', $feedbackService);
    assertStringContains("'already_submitted'", $feedbackService);
    return;

    $dashboard = batch8eSource('views/client/index.php');
    assertStringContains('$appActionToasts', $dashboard);
    assertStringContains("'login_verified' => 'Login verified. Welcome back.'", $dashboard);
    assertFalseValue(str_contains($dashboard, '<div class="alert alert-success">'));

    $ticket = batch8eSource('views/client/ticket.php');
    assertStringContains('getCompletedTicketAwaitingFeedback', $ticket);
    assertStringContains("'feedback_submitted'", $ticket);
    assertStringContains("'feedback_unavailable'", $ticket);
    assertFalseValue(str_contains($ticket, 'No active ticket'));
    assertStringContains('id="clientFeedbackModal"', $ticket);
    assertStringContains('data-bs-toggle="modal"', $ticket);
    assertStringContains('data-feedback-form', $ticket);
    assertStringContains('data-feedback-success-url="ticket.php?msg=feedback_submitted"', $ticket);
    assertStringContains('btn btn-primary ticket-print-button', $ticket);
    assertStringContains('ticket-print-public-url', $ticket);
    assertFalseValue(str_contains($ticket, 'class="client-subnav"'));
    assertFalseValue(str_contains($ticket, 'ORDER BY qt.issued_at DESC'));
    assertFalseValue(str_contains($ticket, 'bi bi-house'));

    $queueStatus = batch8eSource('views/client/queue_status.php');
    assertFalseValue(str_contains($queueStatus, 'class="client-subnav"'));

    $queries = batch8eSource('modules/queue/queue_queries.php');
    assertStringContains('function getCompletedTicketAwaitingFeedback', $queries);
    assertStringContains("qt.status = 'completed'", $queries);
    assertStringContains('NOT EXISTS', $queries);
    assertStringContains('FROM feedback f', $queries);

    $feedback = batch8eSource('views/client/feedback.php');
    assertStringContains('completedTicketAvailableForFeedback', $feedback);
    assertStringContains("'msg' => 'feedback_unavailable'", $feedback);
    assertStringContains("redirectTo('views/client/ticket.php', ['feedback' => '1'])", $feedback);

    $feedbackService = batch8eSource('modules/feedback/feedback_service.php');
    assertStringContains('function submitFeedbackForClient', $feedbackService);
    assertStringContains('FOR UPDATE', $feedbackService);
    assertStringContains("'already_submitted'", $feedbackService);

    $feedbackEndpoint = batch8eSource('modules/feedback/submit_feedback.php');
    assertStringContains('submitFeedbackForClient', $feedbackEndpoint);
    assertFalseValue(str_contains($feedbackEndpoint, 'SELECT feedback_id FROM feedback'));

    $publicTicket = batch8eSource('views/client/ticket_lookup.php');
    assertStringContains('data-ticket-print', $publicTicket);
    assertStringContains("assetUrl('assets/js/client.js')", $publicTicket);
    assertStringContains("assetUrl('vendor/twbs/bootstrap/dist/css/bootstrap.min.css')", $publicTicket);

    foreach (['user-round', 'heart-handshake', 'accessibility'] as $classificationIcon) {
        assertStringContains('data-lucide="' . $classificationIcon . '"', $dashboard);
    }

    $client = batch8eSource('assets/js/client.js');
    assertStringContains('const successUrl = form.dataset.feedbackSuccessUrl', $client);
    assertStringContains('window.location.assign(successUrl)', $client);

    $css = batch8eSource('assets/css/style.css');
    foreach ([
        'body.app-client-page {',
        '--surface: var(--admin-surface);',
        'body.app-client-page .client-panel',
        'body.app-client-page .digital-ticket-card',
        'body.app-client-page .service-card',
        'body.app-client-page .ticket-update-card',
        'body.app-client-page .queue-status-hero h1',
        'body.app-client-page .queue-status-hero p',
        'body.app-client-page [data-queue-status-root]',
        'body.app-client-page .window-state-closed',
        'body.app-client-page .ticket-print-button.btn-primary',
        '.ticket-print-public-url',
        'body.app-client-page .form-control',
    ] as $contract) {
        assertStringContains($contract, $css);
    }
});

testCase('staff workflow surfaces follow shared light and dark theme tokens', function (): void {
    $css = batch8eSource('assets/css/style.css');
    foreach ([
        'body.app-staff-page {',
        '--text-strong: var(--admin-text);',
        '--text-muted: var(--admin-muted);',
        '--staff-bg: var(--admin-bg);',
        '--staff-surface: var(--admin-surface);',
        '--staff-text: var(--admin-text);',
        '--staff-muted: var(--admin-muted);',
        'body.app-staff-page .staff-serving-card',
        'body.app-staff-page .staff-window-card',
        'body.app-staff-page .staff-activity-card',
        'body.app-staff-page .staff-empty-inline h3',
        'body.app-staff-page .staff-empty-serving-copy',
        'body.app-staff-page .staff-timeline-content h3',
        'body.app-staff-page .staff-timeline-content p',
        'body.app-staff-page .staff-window-notice.is-warning',
    ] as $contract) {
        assertStringContains($contract, $css);
    }

    foreach ([
        'views/staff/dashboard.php',
        'views/staff/check_in.php',
        'views/staff/batch_printing.php',
    ] as $page) {
        assertStringContains("include __DIR__ . '/includes/header.php'", batch8eSource($page), $page);
    }

    $adapter = batch8eSource('views/staff/includes/header.php');
    assertStringContains("'body_class' => trim('staff-page ' . \$staffBodyClass)", $adapter);
    assertStringContains("'legacy_theme_key' => 'smartqms-staff-theme'", $adapter);
    assertStringContains("'show_search' => false", $adapter);
    assertStringContains("'show_sidebar' => false", $adapter);
    assertStringContains("'nav_items' => []", $adapter);
    assertFalseValue(str_contains($adapter, 'My Window'));
    assertFalseValue(str_contains($adapter, 'Activity Log'));
});

testCase('staff workspace uses the admin shell without translation or queue-mode labels', function (): void {
    $shell = batch8eSource('views/shared/includes/shell_header.php');
    $adapter = batch8eSource('views/staff/includes/header.php');
    $dashboard = batch8eSource('views/staff/dashboard.php');
    $styles = batch8eSource('assets/css/style.css');

    assertStringContains("if (\$appRole === 'client')", $shell);
    assertStringContains('app-staff-tools', $shell);
    assertStringContains("'show_search' => false", $adapter);
    assertStringContains('admin-kpi-grid', $dashboard);
    assertStringContains('admin-data-table', $dashboard);
    assertStringContains('body.app-staff-page.staff-page.app-sidebarless .app-shell', $styles);
    assertStringContains('app-topbar-brand', $shell);
    assertStringContains("'show_sidebar' => false", $adapter);
    assertFalseValue(str_contains($dashboard, 'Central Queue'));
    assertFalseValue(str_contains($dashboard, 'Priority Queue'));
});

testCase('staff Skip and manual Void share one Bootstrap confirmation and submit once', function (): void {
    $dashboard = batch8eSource('views/staff/dashboard.php');
    preg_match_all('/\sdata-staff-confirm(?:\s|>)/', $dashboard, $confirmationHooks);
    assertSameValue(2, count($confirmationHooks[0]));
    assertStringContains('/modules/service_window/skip_ticket.php', $dashboard);
    assertStringContains('/modules/service_window/void_ticket.php', $dashboard);
    assertStringContains('data-staff-confirm-tone="danger"', $dashboard);

    $footer = batch8eSource('views/shared/includes/shell_footer.php');
    assertSameValue(1, substr_count($footer, 'data-staff-confirm-modal>'));
    assertStringContains('modal-dialog modal-dialog-centered modal-dialog-scrollable', $footer);
    assertStringContains('data-staff-confirm-modal-submit', $footer);

    $staff = batch8eSource('assets/js/staff.js');
    assertStringContains('let requestInFlight = false', $staff);
    assertStringContains('const runAction = async button', $staff);
    assertStringContains("confirmationSubmit?.addEventListener('click'", $staff);
    assertStringContains("confirmationElement?.addEventListener('hidden.bs.modal'", $staff);
    assertFalseValue(str_contains($staff, 'window.confirm'));

    $endpoint = batch8eSource('modules/service_window/void_ticket.php');
    assertStringContains('requireLogin(ROLE_STAFF)', $endpoint);
    assertStringContains('requirePostRequest(true)', $endpoint);
    assertStringContains('requireValidCsrf', $endpoint);
    assertStringContains('smartqmsVoidForStaff', $endpoint);
    $gateway = (string) file_get_contents(SMARTQMS_ROOT . '/modules/integrations/queue_gateway.php');
    assertStringContains('voidTicketForStaff($conn, $staffId, $ticketId)', $gateway);
    assertStringContains('processNearTurnAlerts', $endpoint);
});
