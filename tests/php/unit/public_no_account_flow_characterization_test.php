<?php

function publicNoAccountSource(string $path): string {
    $source = file_get_contents(SMARTQMS_ROOT . '/' . $path);
    if ($source === false) {
        throw new RuntimeException('Could not read ' . $path . '.');
    }
    return $source;
}

testCase('root is the public landing and login is Staff/Admin only', function (): void {
    $landing = publicNoAccountSource('index.php');
    foreach (['Book a Visit', 'Already checked in?', 'Before your visit', 'From booking to your turn', 'Care at your health center'] as $contract) {
        assertStringContains($contract, $landing);
    }
    assertFalseValue(str_contains($landing, 'Example queue display'));
    assertFalseValue(str_contains($landing, 'publicQueueTicketByReference'));
    assertFalseValue(str_contains($landing, 'name="password"'));

    $login = publicNoAccountSource('login/index.php');
    assertStringContains('<title>Staff/Admin Login — SmartQMS</title>', $login);
    assertStringContains('>Login</h1>', $login);
    assertStringContains('Staff/Admin Login', publicNoAccountSource('views/shared/includes/public_header.php'));
    assertFalseValue(str_contains($login, 'Register here'));

    $handler = publicNoAccountSource('modules/auth/login.php');
    assertStringContains('[ROLE_ADMIN, ROLE_STAFF]', $handler);
    assertStringContains('Client accounts no longer sign in', $handler);

    $registration = publicNoAccountSource('modules/auth/register.php');
    assertStringContains('http_response_code(410)', $registration);
    assertFalseValue(str_contains($registration, 'createUnverifiedClient'));
});

testCase('online booking is Scheduled and delays numbering and prediction until arrival', function (): void {
    $join = publicNoAccountSource('queue/join/index.php');
    foreach (['name="first_name"', 'name="last_name"', 'name="phone_number"', 'name="service_id"', 'name="visit_date"', 'name="consent"'] as $field) {
        assertStringContains($field, $join);
    }
    assertFalseValue(str_contains($join, 'name="classification"'));

    $service = publicNoAccountSource('modules/queue/public_intake.php');
    assertStringContains("'waiting', 'scheduled'", $service);
    assertStringContains('scheduled_expires_at', $service);
    assertStringContains('if ($entryType === \'walk-in\')', $service);
    assertStringContains('allocateQueueNumberForLockedTicket', $service);
    assertStringContains('queueStoreArrivalPrediction', $service);
});

testCase('public journey shares one shell and projects configured service duration safely', function (): void {
    $header = publicNoAccountSource('views/shared/includes/public_header.php');
    foreach (['home', 'book', 'track', 'manage', 'login'] as $activePage) {
        assertStringContains("'" . $activePage . "'", $header);
    }
    assertStringContains('navbar-expand-xl', $header);
    assertStringContains('offcanvas', $header);
    assertFalseValue(str_contains($header, 'language_control.php'));
    assertFalseValue(str_contains($header, 'public_theme_toggle.php'));

    $service = publicNoAccountSource('modules/queue/public_intake.php');
    assertStringContains("smartqmsTableHasColumn(\$conn, 'health_services', 'fallback_duration_mins')", $service);
    assertStringContains('15 AS fallback_duration_mins', $service);
    assertStringContains('COALESCE(NULLIF(fallback_duration_mins, 0), 15)', $service);

    assertTrueValue(is_file(SMARTQMS_ROOT . '/assets/vendor/vanillajs-datepicker/datepicker-full.min.js'));
    assertTrueValue(is_file(SMARTQMS_ROOT . '/assets/vendor/vanillajs-datepicker/datepicker-bs5.min.css'));
    assertTrueValue(is_file(SMARTQMS_ROOT . '/assets/vendor/vanillajs-datepicker/LICENSE'));
    $adapter = publicNoAccountSource('assets/js/public_datepicker.js');
    foreach (['data-public-datepicker', "format: 'yyyy-mm-dd'", 'minDate:', 'maxDate:', 'smartqms:languagechange', 'window.Datepicker.locales.fil'] as $contract) {
        assertStringContains($contract, $adapter);
    }
});

testCase('reference tracking projection is privacy minimized while token tracking stays private', function (): void {
    $service = publicNoAccountSource('modules/queue/public_intake.php');
    assertStringContains('function publicQueueTicketByReference', $service);
    assertStringContains('function publicReferenceStatusProjection', $service);
    $projectionStart = strpos($service, 'function publicReferenceStatusProjection');
    $projectionEnd = strpos($service, 'function publicQueueTicketProjection', $projectionStart);
    $referenceProjection = substr($service, $projectionStart, $projectionEnd - $projectionStart);
    assertFalseValue(str_contains($referenceProjection, "'client_name'"));
    assertFalseValue(str_contains($referenceProjection, "'phone_number'"));
    assertFalseValue(str_contains($referenceProjection, "'ticket_token'"));

    $endpoint = publicNoAccountSource('modules/queue/public_reference_status.php');
    assertStringContains('publicReferenceStatusProjection', $endpoint);
    assertStringContains("header('Cache-Control: no-store, private')", $endpoint);
});

testCase('reservation management requires a private token session and keeps mutations transactional', function (): void {
    $view = publicNoAccountSource('manage-reservation/index.php');
    foreach (['publicManagedReservationByToken', 'reservation_manage_ticket_id', 'Reschedule', 'Cancel Reservation', 'isValidCsrfToken'] as $contract) {
        assertStringContains($contract, $view);
    }
    assertFalseValue(str_contains($view, 'name="mobile_last_four"'));

    $service = publicNoAccountSource('modules/queue/reservation_management.php');
    foreach (['manage_token_hash', 'manage_token_revoked_at', 'FOR UPDATE', "lifecycle_status = 'scheduled'", 'reservation_rescheduled', 'reservation_cancelled'] as $contract) {
        assertStringContains($contract, $service);
    }
    $projectionStart = strpos($service, 'function reservationManagementProjection');
    $projectionEnd = strpos($service, 'function validateReservationVisitDate', $projectionStart);
    $projection = substr($service, $projectionStart, $projectionEnd - $projectionStart);
    assertFalseValue(str_contains($projection, 'phone_number'));
    assertFalseValue(str_contains($projection, 'client_name'));
    assertFalseValue(str_contains($projection, 'ticket_token'));
});
