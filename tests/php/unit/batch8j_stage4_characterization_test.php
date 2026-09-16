<?php

testCase('Batch 8J Admin forms expose the approved simplified configuration fields', function (): void {
    $staff = file_get_contents(SMARTQMS_ROOT . '/views/admin/add_staff.php');
    foreach (['name="username"', 'name="job_title"', 'Midwife', 'Nurse', 'Doctor', 'Barangay Health Worker', 'Other', 'name="is_active"'] as $contract) {
        assertStringContains($contract, $staff);
    }
    assertStringContains('data-admin-custom-job-title', $staff);

    $services = file_get_contents(SMARTQMS_ROOT . '/views/admin/services.php');
    foreach (['name="service_code"', 'type="hidden" name="service_encoded"', 'name="fallback_duration_mins"', 'name="is_hidden"'] as $contract) {
        assertStringContains($contract, $services);
    }
    assertStringContains('value="<?= htmlspecialchars($suggestedServiceCode, ENT_QUOTES) ?>"', $services);
    assertStringContains('readonly aria-readonly="true"', $services, 'System-managed service codes are visible and explicitly read-only.');
    assertFalseValue(str_contains($services, 'This code cannot be edited.'), 'Internal codes are not Admin form content.');
    assertFalseValue(str_contains($services, 'Estimated Service Duration'));
    assertFalseValue(str_contains($services, '<th>Estimated duration</th>'));
    assertFalseValue(str_contains($services, 'name="action" value="reorder"'));
    assertFalseValue(str_contains($services, 'Queue Handling'));
    assertFalseValue(str_contains($services, 'Priority clients only'));

    $windows = file_get_contents(SMARTQMS_ROOT . '/views/admin/windows.php');
    assertStringContains('name="counter_number"', $windows);
    assertStringContains('readonly aria-readonly="true"', $windows);
    assertStringContains('name="service_ids[]"', $windows);
    assertStringContains('Services Offered', $windows);
    assertStringContains('name="management_status"', $windows);
    assertStringContains('Under maintenance', $windows);
    assertFalseValue(str_contains($windows, '<th>Configuration</th>'));
    assertFalseValue(str_contains($windows, '<label class="form-label" for="window_type">'));
});

testCase('Staff batch printing is a constrained native form backed by a direct PDF endpoint', function (): void {
    $view = file_get_contents(SMARTQMS_ROOT . '/views/staff/batch_printing.php');
    foreach (['name="service_id"', 'name="start_number"', 'name="end_number"', 'max="9999"', 'Generate PDF', 'two columns by four rows'] as $contract) {
        assertStringContains($contract, $view);
    }
    $endpoint = file_get_contents(SMARTQMS_ROOT . '/modules/queue/create_print_batch.php');
    foreach (['ROLE_STAFF', 'requireValidCsrf', 'createTicketPrintBatch', 'renderTicketPrintBatchPdf', 'application/pdf'] as $contract) {
        assertStringContains($contract, $endpoint);
    }
    $composer = json_decode(file_get_contents(SMARTQMS_ROOT . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    assertTrueValue(isset($composer['require']['dompdf/dompdf']));
});

testCase('Public display exposes a fixed privacy-minimized serving and waiting board', function (): void {
    $view = file_get_contents(SMARTQMS_ROOT . '/public-display/index.php');
    foreach ([
        'public-display-document',
        'data-display-time',
        'data-display-date',
        'data-display-featured',
        'data-display-active-list',
        'data-display-active-overflow',
        'data-display-waiting',
        'data-display-waiting-overflow',
        '>Now Serving<',
        '>Waiting Queue<',
        'row g-3',
        'col-12 col-md-7',
        'col-12 col-md-5',
    ] as $contract) {
        assertStringContains($contract, $view);
    }
    foreach (['data-display-connection', 'data-display-recent', 'Recently Called', 'language_control.php', 'language.js', 'data-display-sound', 'Enable announcements'] as $removed) {
        assertFalseValue(str_contains($view, $removed), "Public display must remove {$removed}.");
    }

    $script = file_get_contents(SMARTQMS_ROOT . '/assets/js/public_display.js');
    foreach (['visibleCapacity', 'ResizeObserver', "waitingRows.slice(0, capacity)", "'public-display-next-label', 'Next'", '+${hiddenCount} more waiting', "timeZone: 'Asia/Manila'", 'window.setInterval(poll, 3000)'] as $contract) {
        assertStringContains($contract, $script);
    }
    foreach (['AudioContext', 'SpeechSynthesisUtterance', 'speechSynthesis', 'localStorage', 'renderRecent', 'recentRows', 'waitingPageIndex'] as $removed) {
        assertFalseValue(str_contains($script, $removed), "Public display runtime must remove {$removed}.");
    }

    $css = file_get_contents(SMARTQMS_ROOT . '/assets/css/display.css');
    assertStringContains('html.public-display-document', $css);
    assertStringContains('height: 100dvh;', $css);
    assertStringContains('body.public-display-page', $css);
    assertStringContains('overflow: hidden;', $css);
    assertStringContains('grid-template-columns: minmax(0, 1fr);', $css);
    assertStringContains('grid-template-rows: minmax(0, 1.12fr) minmax(0, .88fr);', $css);
    assertStringContains('grid-template-columns: minmax(0, 7fr) minmax(0, 5fr);', $css);
    assertStringContains('.public-display-shell.has-new-call .public-display-featured', $css);

    $endpoint = file_get_contents(SMARTQMS_ROOT . '/modules/queue/public_display_status.php');
    assertStringContains("'serving'", $endpoint);
    assertStringContains("'waiting'", $endpoint);
    assertStringContains("'recent'", $endpoint);
    assertStringContains("lifecycle_status IN ('calling','in-progress')", $endpoint);
    assertStringContains("lifecycle_status = 'waiting'", $endpoint);
    assertFalseValue(str_contains($endpoint, 'client_name'));
    assertFalseValue(str_contains($endpoint, 'phone_number'));
    assertFalseValue(str_contains($endpoint, 'ticket_token'));
});
