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
    foreach (['name="first_name"', 'name="last_name"', 'name="phone_number"', 'name="service_id"', 'name="visit_date"'] as $field) {
        assertStringContains($field, $join);
    }
    assertFalseValue(str_contains($join, 'name="consent"'));
    assertFalseValue(str_contains($join, 'name="classification"'));

    $service = publicNoAccountSource('modules/queue/public_intake.php');
    assertStringContains("'waiting', 'scheduled'", $service);
    assertStringContains('scheduled_expires_at', $service);
    assertStringContains('if ($entryType === \'walk-in\')', $service);
    assertStringContains('allocateQueueNumberForLockedTicket', $service);
    assertStringContains('queueStoreArrivalPrediction', $service);
});

testCase('public landing keeps a fixed header and viewport-height radial hero', function (): void {
    $styles = publicNoAccountSource('assets/css/style.css');
    assertStringContains('height: 72px; min-height: 72px; max-height: 72px', $styles);
    assertStringContains('.public-navbar .container { align-items: center; flex-direction: row; flex-wrap: nowrap; gap: 12px; }', $styles);
    assertFalseValue(str_contains($styles, '.public-navbar .container { align-items: stretch; flex-direction: column;'));
    assertStringContains('min-height: calc(100vh - 72px); min-height: calc(100svh - 72px)', $styles);
    assertStringContains('min-height: calc(100dvh - 72px)', $styles);
    assertStringContains('radial-gradient(ellipse 62% 68% at 50% 48%, var(--landing-hero-glow)', $styles);
    assertStringContains('.public-landing-page .public-hero-title-centered { margin-inline: auto; text-align: center; }', $styles);
    assertStringContains('--bs-offcanvas-width: min(320px, calc(100vw - 48px))', $styles);
    assertStringContains('.public-nav-tools { margin-top: 12px; padding-top: 12px;', $styles);
    assertStringContains('.public-nav-list .nav-link { display: flex; min-height: 44px;', $styles);
});

testCase('public landing keeps one primary action and presents display access as an outline button', function (): void {
    $landing = publicNoAccountSource('index.php');
    $heroStart = strpos($landing, '<section class="public-hero public-landing-hero"');
    $heroEnd = strpos($landing, '</section>', $heroStart);
    $hero = substr($landing, $heroStart, $heroEnd - $heroStart);

    assertStringContains('class="btn btn-primary btn-lg"', $hero);
    assertStringContains('>Book a Visit</a>', $hero);
    assertStringContains('class="btn btn-outline-primary btn-lg public-display-link"', $hero);
    assertStringContains('>Check Public Display</a>', $hero);
    assertStringContains('.public-landing-page .public-hero-actions .btn.btn-lg { min-height: 48px; }', publicNoAccountSource('assets/css/style.css'));
    assertStringContains('--sq-display-outline-color: var(--sq-link); border-color: var(--sq-display-outline-color); background: transparent;', publicNoAccountSource('assets/css/style.css'));
    assertStringContains('--sq-display-outline-color: var(--sq-text-strong);', publicNoAccountSource('assets/css/style.css'));
    assertFalseValue(str_contains($hero, 'data-lucide='));
});

testCase('public journey centers its introduction and renders visible Bootstrap cards', function (): void {
    $landing = publicNoAccountSource('index.php');
    $journeyStart = strpos($landing, '<section id="how-it-works"');
    $journeyEnd = strpos($landing, '</section>', $journeyStart);
    $journey = substr($landing, $journeyStart, $journeyEnd - $journeyStart);

    assertStringContains('class="public-section-heading text-center mx-auto"', $journey);
    assertStringContains('class="card public-process-card h-100"', $journey);
    assertStringContains('class="card-body p-4"', $journey);
    assertStringContains('class="public-step-number"', $journey);
    assertFalseValue(str_contains($journey, 'class="public-journey-step h-100"'));
});

testCase('landing services are centered without a duplicate booking link', function (): void {
    $landing = publicNoAccountSource('index.php');
    $servicesStart = strpos($landing, '<section id="services"');
    $servicesEnd = strpos($landing, '</section>', $servicesStart);
    $services = substr($landing, $servicesStart, $servicesEnd - $servicesStart);
    assertStringContains('class="public-section-heading text-center mx-auto"', $services);
    assertFalseValue(str_contains($services, 'public-arrow-link'));
    $styles = publicNoAccountSource('assets/css/style.css');
    assertStringContains('.public-landing-page .public-navbar::before {', $styles);
    assertStringContains('backdrop-filter: blur(18px) saturate(140%)', $styles);
});

testCase('public FAQ starts collapsed and remains Bootstrap controlled', function (): void {
    $landing = publicNoAccountSource('index.php');
    assertStringContains('class="accordion-button collapsed"', $landing);
    assertStringContains('aria-expanded="false"', $landing);
    assertStringContains('class="accordion-collapse collapse"', $landing);
    assertFalseValue(str_contains($landing, "class=\"accordion-collapse collapse<?= \$faqIndex === 0 ? ' show' : '' ?>\""));
});

testCase('compact public navigation keeps booking in the menu without a separate header button', function (): void {
    $header = publicNoAccountSource('views/shared/includes/public_header.php');
    assertFalseValue(str_contains($header, 'public-navbar-priority'));
    assertStringContains('public-navbar-toggler ms-auto', $header);
    assertStringContains('>Book a Visit</a>', $header);
    assertStringContains("window.bootstrap?.Offcanvas?.getInstance(offcanvas)?.hide();", publicNoAccountSource('assets/js/main.js'));
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
    foreach (['publicManagedReservationByToken', 'reservation_manage_ticket_id', "\$action === 'cancel'", 'Cancel Reservation', 'isValidCsrfToken'] as $contract) {
        assertStringContains($contract, $view);
    }
    assertFalseValue(str_contains($view, 'reschedule'));
    assertFalseValue(str_contains($view, 'name="visit_date"'));
    assertFalseValue(str_contains($view, 'name="mobile_last_four"'));

    $service = publicNoAccountSource('modules/queue/reservation_management.php');
    foreach (['manage_token_hash', 'manage_token_revoked_at', 'FOR UPDATE', "lifecycle_status = 'scheduled'", 'reservation_cancelled'] as $contract) {
        assertStringContains($contract, $service);
    }
    assertFalseValue(str_contains($service, 'function rescheduleSessionReservation'));
    $projectionStart = strpos($service, 'function reservationManagementProjection');
    $projectionEnd = strpos($service, 'function cancelSessionReservation', $projectionStart);
    $projection = substr($service, $projectionStart, $projectionEnd - $projectionStart);
    assertFalseValue(str_contains($projection, 'phone_number'));
    assertFalseValue(str_contains($projection, 'client_name'));
    assertFalseValue(str_contains($projection, 'ticket_token'));
});
