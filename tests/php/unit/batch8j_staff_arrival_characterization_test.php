<?php

testCase('Staff arrival UI exposes QR manual and walk-in workflows without classification', function (): void {
    $view = file_get_contents(SMARTQMS_ROOT . '/views/staff/check_in.php');
    foreach ([
        'data-arrival-camera-start',
        'data-arrival-camera-select',
        'assets/vendor/instascan/instascan.min.js',
        'assets/js/staff_arrival_scanner.js',
        'data-arrival-reference-form',
        'name="lookup_value"',
        'data-staff-walk-in-form',
        'name="first_name"',
        'name="last_name"',
        'name="phone_number"',
        'name="service_id"',
        'arrivalCheckInModal',
        'Yes, check-in',
        'data-arrival-ticket-modal',
        'staff-arrival-ticket-details',
        'data-arrival-detail="visit_date"',
        'data-arrival-readiness',
    ] as $contract) {
        assertStringContains($contract, $view);
    }
    assertFalseValue(str_contains($view, 'name="classification"'));
    $script = file_get_contents(SMARTQMS_ROOT . '/assets/js/staff.js');
    $scanner = file_get_contents(SMARTQMS_ROOT . '/assets/js/staff_arrival_scanner.js');
    assertStringContains('window.SmartQmsArrivalScanner.create(scannerOptions)', $script);
    assertStringContains("document.addEventListener('visibilitychange'", $script);
    assertStringContains('global.Instascan.Scanner', $scanner);
    assertStringContains("typeof global.BarcodeDetector !== 'function'", $scanner);
    assertStringContains("facingMode: { ideal: 'environment' }", $scanner);
    assertStringContains('parsed.origin !== expectedOrigin', $scanner);
    assertStringContains("parsed.searchParams.get('reference') || parsed.searchParams.get('ref')", $scanner);
    assertStringContains("lookup_type: 'reference', lookup_value: reference", $scanner);
    assertStringContains('backgroundScan: false', $scanner);
    assertStringContains('refractoryPeriod: 5000', $scanner);
    assertStringContains('Available on Visit Date', $script);
    assertStringContains('added to the Active Waiting Queue', $script);

    $vendor = SMARTQMS_ROOT . '/assets/vendor/instascan/instascan.min.js';
    assertTrueValue(is_file($vendor));
    assertTrueValue(is_file(SMARTQMS_ROOT . '/assets/vendor/instascan/LICENSE'));
    assertSameValue('d7a7d83a6c51361096c876ab9a4cb8cb77f1cb7554bade6b92fb4f6798c62486', hash_file('sha256', $vendor));

    $header = file_get_contents(SMARTQMS_ROOT . '/views/staff/includes/header.php');
    $dashboard = file_get_contents(SMARTQMS_ROOT . '/views/staff/dashboard.php');
    assertStringContains('$staffLiveRefresh = (bool) ($staffLiveRefresh ?? false);', $header);
    assertStringContains("'staff-live-refresh' => \$staffLiveRefresh ? 'true' : 'false'", $header);
    assertStringContains('$staffLiveRefresh = true;', $dashboard);
    assertFalseValue(str_contains($view, '$staffLiveRefresh = true;'), 'Arrival forms must not be replaced by dashboard live refresh.');
});

testCase('Staff arrival endpoints retain Staff role POST CSRF and request contracts', function (): void {
    $lookup = file_get_contents(SMARTQMS_ROOT . '/modules/queue/staff_arrival_lookup.php');
    $checkIn = file_get_contents(SMARTQMS_ROOT . '/modules/queue/staff_check_in.php');
    $walkIn = file_get_contents(SMARTQMS_ROOT . '/modules/queue/staff_walk_in.php');

    foreach ([$lookup, $checkIn, $walkIn] as $endpoint) {
        assertStringContains('ROLE_STAFF', $endpoint);
        assertStringContains('requirePostRequest(true)', $endpoint);
        assertStringContains('requireValidCsrf', $endpoint);
    }
    foreach (['lookup_type', 'lookup_value'] as $field) {
        assertStringContains($field, $lookup);
    }
    assertStringContains('catch (DomainException $error)', $lookup);
    assertStringContains("jsonResponse(false, ['error' => 'No appointment matched that ticket reference.'], 404)", $lookup);
    foreach (['ticket_id', 'check_in_method'] as $field) {
        assertStringContains($field, $checkIn);
    }
    assertStringContains('getWindowServiceIds($conn, $staffWindow)', $walkIn);
    assertStringContains('Claim a service counter before registering a walk-in client.', $walkIn);
    assertFalseValue(str_contains($walkIn, 'classification'));
});

testCase('Staff workspace exposes lifecycle KPIs and strict horizontally scrollable FIFO table', function (): void {
    $dashboard = file_get_contents(SMARTQMS_ROOT . '/views/staff/dashboard.php');
    foreach (['Completed Today', 'Currently Serving', 'Waiting in Queue', 'Voided / Skipped'] as $label) {
        assertStringContains($label, $dashboard);
    }
    foreach (['Queue #', 'Service', 'Type', 'Arrived', 'Called', 'Status', 'Actions'] as $heading) {
        assertStringContains($heading, $dashboard);
    }
    assertFalseValue(str_contains($dashboard, 'Client Name'), 'The Staff workspace must not expose client identity.');
    assertFalseValue(str_contains($dashboard, '<th>Reference</th>'), 'The operational table must not expose private references.');
    assertStringContains('staff-fifo-table', $dashboard);
    assertStringContains('Strict FIFO', $dashboard);
    assertFalseValue(str_contains($dashboard, 'name="classification"'));

    $header = file_get_contents(SMARTQMS_ROOT . '/views/staff/includes/header.php');
    $footer = file_get_contents(SMARTQMS_ROOT . '/views/shared/includes/shell_footer.php');
    $script = file_get_contents(SMARTQMS_ROOT . '/assets/js/staff.js');
    assertFalseValue(str_contains($header, 'staff-action-status'), 'Action feedback must not occupy Staff workspace layout space.');
    assertStringContains('data-staff-status-toast', $footer);
    assertStringContains('data-bs-delay="5000"', $footer);
    assertStringContains("window.bootstrap.Toast.getOrCreateInstance(statusToast, { delay: 5000 }).show()", $script);
    assertStringContains("statusToast.setAttribute('role', type === 'danger' ? 'alert' : 'status')", $script);
    assertStringContains("activeButton.classList.add('is-loading')", $script);
    assertStringContains("activeButton.setAttribute('aria-busy', 'true')", $script);
    assertFalseValue(str_contains($script, 'activeButton.textContent = activeButton.dataset.loadingText'), 'Staff action buttons must retain their visual bounds while busy.');
});

testCase('Staff workspace only renders manual void while a ticket is being called', function (): void {
    $dashboard = file_get_contents(SMARTQMS_ROOT . '/views/staff/dashboard.php');
    $actionFooterStart = strpos($dashboard, 'staff-current-actions');
    assertTrueValue($actionFooterStart !== false, 'Current-ticket actions must be reachable outside the scrollable table.');
    $callingBranchStart = strpos($dashboard, "<?php if (\$currentLifecycle === 'calling'): ?>", $actionFooterStart);
    $inProgressBranchStart = strpos($dashboard, '<?php else: ?>', $callingBranchStart);
    $branchEnd = strpos($dashboard, '<?php endif; ?>', $inProgressBranchStart);
    $voidAction = strpos($dashboard, 'data-provider-action="voidTicket"', $callingBranchStart);

    assertTrueValue($callingBranchStart !== false && $inProgressBranchStart !== false && $branchEnd !== false);
    assertTrueValue($voidAction !== false && $voidAction < $inProgressBranchStart, 'Void must remain inside the calling-only action branch.');
    assertFalseValue(str_contains(substr($dashboard, $inProgressBranchStart, $branchEnd - $inProgressBranchStart), 'voidTicket'));

    $endpoint = file_get_contents(SMARTQMS_ROOT . '/modules/service_window/void_ticket.php');
    assertStringContains("\$result['status'] === 'invalid_state'", $endpoint);
    assertStringContains('A ticket can only be voided while it is being called.', $endpoint);
});

testCase('Queue timeout maintenance is system-wide idempotent and defaults to five minutes', function (): void {
    $actions = file_get_contents(SMARTQMS_ROOT . '/modules/service_window/ticket_actions.php');
    $task = file_get_contents(SMARTQMS_ROOT . '/scripts/process_queue_timeouts.php');
    assertStringContains('voidExpiredCallingTicketsSystemWide', $actions);
    assertStringContains("lifecycle_status = 'calling'", $actions);
    assertStringContains("WHERE ticket_id = ? AND status = 'serving'", $actions);
    assertStringContains('voidExpiredCallingTicketsSystemWide($conn, $timeoutMinutes)', $task);
    assertStringContains("getSetting(\$conn, 'void_timeout_minutes', '5')", $task);
    assertStringContains("PHP_SAPI !== 'cli'", $task);
});
