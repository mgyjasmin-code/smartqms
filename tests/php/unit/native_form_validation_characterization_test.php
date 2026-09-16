<?php

function nativeValidationSource(string $relativePath): string {
    $source = file_get_contents(SMARTQMS_ROOT . '/' . $relativePath);
    if ($source === false) {
        throw new RuntimeException('Could not read ' . $relativePath);
    }
    return $source;
}

testCase('production data-entry forms delegate expressible constraints to native HTML validation', function (): void {
    $dataEntryViews = [
        'login/index.php',
        'views/client/forgot_password.php',
        'queue/join/index.php',
        'views/public/tracker.php',
        'views/admin/add_staff.php',
        'views/admin/services.php',
        'views/admin/windows.php',
    ];

    foreach ($dataEntryViews as $path) {
        $source = nativeValidationSource($path);
        assertFalseValue(str_contains($source, 'novalidate'), $path . ' must allow browser constraint validation.');
        assertFalseValue(str_contains($source, 'data-validate='), $path . ' must not duplicate native rules in JavaScript metadata.');
        assertFalseValue(str_contains($source, 'data-validation-errors-only'), $path . ' must not retain the retired validation mode.');
    }
});

testCase('authentication and account forms expose required email length pattern and confirmation constraints', function (): void {
    $login = nativeValidationSource('login/index.php');
    assertStringContains('type="text" name="login_id"', $login);
    assertStringContains('placeholder="Enter your email or username" required', $login);
    assertStringContains('type="password" name="password"', $login);
    assertStringContains('placeholder="Enter your password" required', $login);

    $register = nativeValidationSource('modules/auth/register.php');
    assertStringContains('http_response_code(410)', $register);
    assertFalseValue(str_contains($register, 'createUnverifiedClient'));

    $forgot = nativeValidationSource('views/client/forgot_password.php');
    assertStringContains('type="email" name="email"', $forgot);
    assertStringContains('maxlength="6" pattern="[0-9]{6}"', $forgot);
    assertStringContains("['password', 'New password'", $forgot);
    assertStringContains("['confirm_password', 'Confirm new password'", $forgot);
    assertStringContains('required minlength="12" autocomplete="new-password"', $forgot);
    assertStringContains('data-confirm-password-for="password"', $forgot);
});

testCase('queue management and feedback forms expose native selection pattern and range contracts', function (): void {
    $queue = nativeValidationSource('queue/join/index.php');
    assertStringContains('type="radio" name="service_id"', $queue);
    assertStringContains('data-booking-service-input', $queue);
    assertStringContains('required<?= $selected', $queue);
    assertStringContains('name="phone_number"', $queue);
    assertStringContains('pattern="09[0-9]{9}"', $queue);
    assertFalseValue(str_contains($queue, 'name="classification"'));

    $feedback = nativeValidationSource('views/public/tracker.php');
    assertStringContains('name="rating"', $feedback);
    assertStringContains('value="<?= $rating ?>" required', $feedback);

    $staff = nativeValidationSource('views/admin/add_staff.php');
    assertStringContains('type="email" name="email"', $staff);
    assertStringContains('pattern="09[0-9]{9}"', $staff);
    assertStringContains('minlength="12" autocomplete="new-password"', $staff);
    assertFalseValue(str_contains($staff, 'name="phone_number" required'), 'The staff phone number remains optional.');

    $services = nativeValidationSource('views/admin/services.php');
    assertStringContains('type="hidden" name="service_encoded"', $services);
    assertStringContains('name="display_order"', $services);
    assertStringContains('type="hidden" name="display_order"', $services);
    assertStringContains('type="hidden" name="queue_mode"', $services);
    assertFalseValue(str_contains($services, 'name="description" required'), 'The service description remains optional.');

    $windows = nativeValidationSource('views/admin/windows.php');
    assertStringContains('name="window_name"', $windows);
    assertStringContains('name="counter_number"', $windows);
    assertStringContains('readonly aria-readonly="true"', $windows);
    assertStringContains('placeholder="e.g. Ground floor, beside reception" required', $windows);
    assertStringContains('type="hidden" name="window_type"', $windows);
    assertStringContains('name="service_ids[]"', $windows);
    assertStringContains('type="hidden" name="is_active"', $windows);
    assertStringContains('name="management_status"', $windows);
    assertStringContains('required>', $windows);
    assertFalseValue(str_contains($windows, 'name="staff_id"'), 'Runtime staff assignment is not an Admin form field.');
    assertFalseValue(str_contains($windows, 'name="status"'), 'Runtime status is not an Admin form field.');
});

testCase('native validation JavaScript preserves server errors and gates side effects', function (): void {
    $main = nativeValidationSource('assets/js/main.js');
    foreach ([
        'function clearServerFieldError',
        "input.classList.remove('is-invalid', 'is-valid');",
        "confirmation.setCustomValidity(mismatch ? 'Password entries do not match.' : '');",
        "typeof form.checkValidity === 'function' && !form.checkValidity()",
        'form.reportValidity?.();',
        'setSubmitBusy(form, true);',
    ] as $contract) {
        assertStringContains($contract, $main);
    }
    assertFalseValue(str_contains($main, 'function validateField'));
    assertFalseValue(str_contains($main, "classList.toggle('is-valid'"));

    $client = nativeValidationSource('assets/js/client.js');
    assertStringContains("typeof form.checkValidity === 'function' && !form.checkValidity()", $client);
    assertStringContains('form.reportValidity?.();', $client);

    $admin = nativeValidationSource('assets/js/admin.js');
    assertStringContains("form.querySelectorAll('.is-invalid, .is-valid, [aria-invalid=\"true\"]')", $admin);
    assertStringContains("form.querySelectorAll('[data-confirm-password-for]')", $admin);
    assertStringContains("field.setCustomValidity('')", $admin);
});

testCase('action-only and filter forms keep their established validation contract', function (): void {
    $header = nativeValidationSource('views/shared/includes/shell_header.php');
    assertStringContains('role="search" data-app-search novalidate', $header);

    $reports = nativeValidationSource('views/admin/reports.php');
    assertStringContains('class="admin-report-actions" method="GET"', $reports);

    $logout = nativeValidationSource('views/shared/includes/shell_footer.php');
    assertStringContains("postActionUrl('modules/auth/logout.php')", $logout);

    assertFalseValue(is_file(SMARTQMS_ROOT . '/try.php'), 'The browser-accessible SMS prototype must not be deployable.');
});
