<?php

function customerBookingSource(string $path): string {
    $source = file_get_contents(SMARTQMS_ROOT . '/' . $path);
    if ($source === false) {
        failTest('Unable to read ' . $path . '.');
    }
    return $source;
}

testCase('customer booking input normalizes identity phone and classification', function (): void {
    $input = smartqmsCustomerBookingInput([
        'service_id' => ' 7 ',
        'branch_id' => ' local-branch-1 ',
        'first_name' => ' Ana ',
        'last_name' => ' Santos ',
        'phone_number' => '0917 123 4567',
        'client_type' => 'senior',
    ]);

    assertSameValue('7', $input['service_id']);
    assertSameValue('local-branch-1', $input['branch_id']);
    assertSameValue('Ana', $input['first_name']);
    assertSameValue('Santos', $input['last_name']);
    assertSameValue('09171234567', $input['phone_number']);
    assertSameValue('senior', $input['client_type']);
});

testCase('customer booking requires service location identity and a Philippine mobile number', function (): void {
    $errors = smartqmsCustomerBookingErrors(smartqmsCustomerBookingInput([]));
    foreach (['service_id', 'branch_id', 'first_name', 'last_name', 'phone_number'] as $field) {
        assertTrueValue(isset($errors[$field]), 'Expected a booking error for ' . $field . '.');
    }

    $valid = smartqmsCustomerBookingErrors(smartqmsCustomerBookingInput([
        'service_id' => '1',
        'branch_id' => 'local-branch-1',
        'first_name' => 'Ana',
        'last_name' => 'Santos',
        'phone_number' => '09171234567',
        'client_type' => 'pwd',
    ]));
    assertSameValue([], $valid);
});

testCase('public no-account booking collects the guided reservation fields', function (): void {
    $source = customerBookingSource('queue/join/index.php');
    foreach (['Choose a service', 'Appointment details', 'Review and confirm', 'Confirm Appointment'] as $stage) {
        assertStringContains($stage, $source);
    }
    assertSameValue(3, substr_count($source, 'data-booking-step="'));
    assertStringContains('aria-valuemax="3"', $source);
    assertFalseValue(str_contains($source, 'Step <?= $initialStep ?> of 5'));
    assertFalseValue(str_contains($source, 'data-booking-step="4"'));
    foreach (['service_id', 'visit_date', 'first_name', 'last_name', 'phone_number', 'consent'] as $field) {
        assertStringContains('name="' . $field . '"', $source);
    }
    $detailsStart = strpos($source, 'data-booking-step="2"');
    $reviewStart = strpos($source, 'data-booking-step="3"');
    assertTrueValue($detailsStart !== false && $reviewStart !== false && $reviewStart > $detailsStart);
    $details = substr($source, $detailsStart, $reviewStart - $detailsStart);
    foreach (['visit_date', 'first_name', 'last_name', 'phone_number', 'consent'] as $field) {
        assertStringContains('name="' . $field . '"', $details);
    }
    assertStringContains('pattern="09[0-9]{9}"', $source);
    assertFalseValue(str_contains($source, 'name="classification"'));
    assertFalseValue(str_contains($source, 'name="client_type"'));
    assertStringContains("'online'", $source);
    assertStringContains('data-booking-service-input', $source);
    assertStringContains('data-public-datepicker', $source);
    assertStringContains('data-booking-error-summary', $source);
    assertStringContains('data-booking-processing', $source);
    assertStringContains('container-xl public-form-container', $source);
    assertStringContains('row-cols-1 row-cols-md-2 row-cols-xl-3', $source);
    assertStringContains('row g-4 public-booking-details-grid', $source);
    assertStringContains('col-12 col-md-6', $source);
    assertStringContains('col-12 col-lg-6', $source);
    assertStringContains('col-12 col-lg-8', $source);
    assertStringContains('col-12 col-lg-4', $source);
    assertStringContains('list-group public-booking-review-list', $source);
    assertStringContains("'created' => '1'", $source);
});

testCase('appointment QR generation remains server side without a duplicate scanner dependency', function (): void {
    $composer = customerBookingSource('composer.json');
    $generator = customerBookingSource('modules/queue/qr_generate.php');
    $booking = customerBookingSource('queue/join/index.php');
    $staffHeader = customerBookingSource('views/staff/includes/header.php');
    $staffCheckIn = customerBookingSource('views/staff/check_in.php');

    assertStringContains('endroid/qr-code', $composer);
    assertStringContains('SvgWriter', $generator);
    assertStringContains('publicTokenTrackingUrl', $generator);
    assertStringContains('generateTokenQR', $generator);
    assertStringContains('assets/vendor/instascan/instascan.min.js', $staffCheckIn);
    assertStringContains('assets/js/staff_arrival_scanner.js', $staffCheckIn);
    assertFalseValue(str_contains(strtolower($booking . $staffHeader . $staffCheckIn), 'html5-qrcode'));
});

testCase('customer booking controller validates each step and preserves native form submission', function (): void {
    $source = customerBookingSource('assets/js/client.js');
    assertStringContains('function initQueueBooking(form)', $source);
    assertStringContains('data-booking-next', $source);
    assertStringContains('data-booking-back', $source);
    assertStringContains('invalid.reportValidity?.()', $source);
    assertStringContains('prioritySelectionAllowed', $source);
    assertStringContains('updateReview', $source);
    assertStringContains('document.querySelectorAll(\'[data-queue-booking]\').forEach(initQueueBooking)', $source);
    assertFalseValue(str_contains($source, 'form.submit()'));
});

testCase('queue creation retains the existing endpoint and updates verified customer details transactionally', function (): void {
    $handler = customerBookingSource('modules/queue/join_queue.php');
    assertStringContains('requirePostRequest', $handler);
    assertStringContains('requireValidCsrf', $handler);
    assertStringContains('smartqmsCustomerBookingErrors', $handler);
    assertStringContains('smartqmsCustomerBookingBranch', $handler);
    assertStringContains('smartqmsCustomerPhoneBelongsToAnotherUser', $handler);
    assertStringContains('$booking', $handler);
    assertStringContains("redirectTo('views/client/ticket.php')", $handler);

    $service = customerBookingSource('modules/queue/ticket_service.php');
    assertStringContains('?array $customerProfile = null', $service);
    assertStringContains('smartqmsUpdateCustomerBookingProfile', $service);
    assertTrueValue(
        strpos($service, 'smartqmsUpdateCustomerBookingProfile') < strpos($service, '$conn->commit()'),
        'Customer details must be updated before the ticket transaction commits.'
    );
});

testCase('scheduled confirmation displays appointment details beside a private QR and prints cleanly', function (): void {
    $source = customerBookingSource('views/public/tracker.php');
    foreach (['Appointment confirmed', 'Appointment reference', 'Full name', 'Chosen service', 'Appointment date', 'Check-in period', 'queue number is assigned after arrival', 'Print Appointment', 'Return to Home', 'Manage or reschedule this appointment'] as $label) {
        assertStringContains($label, $source);
    }
    assertStringContains("\$_GET['created']", $source);
    assertStringContains("\$projection['client_name']", $source);
    assertStringContains('public-confirmation-layout', $source);
    assertStringContains('public-confirmation-qr-panel', $source);
    assertFalseValue(str_contains($source, 'Step 5 of 5'), 'Confirmation is a result state, not another booking step.');
    $confirmationStart = strpos($source, '<?php elseif ($showConfirmation): ?>');
    $trackerStart = strpos($source, '<?php else: ?>', $confirmationStart);
    assertTrueValue($confirmationStart !== false && $trackerStart !== false);
    $confirmation = substr($source, $confirmationStart, $trackerStart - $confirmationStart);
    assertFalseValue(str_contains($confirmation, '<dt>Mobile'), 'The confirmation must not expose the mobile number.');
    assertStringContains('qr_code_path', $source);

    $css = customerBookingSource('assets/css/style.css');
    foreach (['@page', 'size: A4 portrait', '.public-confirmation-print-brand', '.public-confirmation-layout', 'width: 50mm'] as $contract) {
        assertStringContains($contract, $css);
    }
});

testCase('customer booking styling is theme-token driven and responsive', function (): void {
    $source = customerBookingSource('assets/css/style.css');
    foreach ([
        '.client-queue-overview', '.client-overview-card', '.booking-stepper',
        '.booking-step-panel[hidden]', '.branch-card', '.booking-review-grid',
        '.booking-step-actions',
    ] as $selector) {
        assertStringContains($selector, $source);
    }
    assertStringContains('var(--admin-surface)', $source);
    assertStringContains('@media (max-width: 767.98px)', $source);
    assertStringContains('@media (max-width: 420px)', $source);
});
