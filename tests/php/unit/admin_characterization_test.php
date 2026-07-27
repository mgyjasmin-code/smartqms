<?php
require_once SMARTQMS_ROOT . '/modules/settings/service_catalog.php';
require_once SMARTQMS_ROOT . '/modules/admin/staff_accounts.php';
require_once SMARTQMS_ROOT . '/modules/admin/window_admin.php';
require_once SMARTQMS_ROOT . '/modules/admin/users.php';

testCase('service adapters retain their different validation and update contracts', function (): void {
    $html = serviceHtmlInput([
        'service_id' => '5',
        'service_code' => 'svc-100',
        'service_name' => 'Example',
        'service_encoded' => '9',
        'priority_only' => '1',
        'is_active' => '0',
        'display_order' => '8',
    ]);
    assertSameValue([], validateServiceHtmlInput($html, true));
    assertSameValue('1', $html['priority_only']);
    assertSameValue(
        ['service_code' => 'Service code is required.', 'service_name' => 'Service name is required.', 'service_encoded' => 'Encoded value is required.'],
        validateServiceJsonAdd([])
    );
    assertSameValue(
        ['service_id' => 'Service id is required.', 'service_name' => 'Service name is required.'],
        validateServiceJsonEdit([])
    );
});

testCase('staff and window validation retain existing required fields and statuses', function (): void {
    $staffErrors = validateStaffAccountInput(staffAccountInput([
        'email' => 'not-an-email',
        'password' => 'short',
    ]));
    assertArrayHasKeys(['first_name', 'last_name', 'email', 'password'], $staffErrors);
    assertFalseValue(isset($staffErrors['phone_number']), 'An omitted staff phone remains valid.');

    $invalidPhoneErrors = validateStaffAccountInput(staffAccountInput([
        'first_name' => 'Test',
        'last_name' => 'Staff',
        'email' => 'staff@example.test',
        'phone_number' => '12345',
        'password' => 'password123',
    ]));
    assertArrayHasKeys(['phone_number'], $invalidPhoneErrors);

    $editErrors = validateStaffAccountInput(staffAccountInput([
        'staff_id' => '7',
        'first_name' => 'Test',
        'last_name' => 'Staff',
        'email' => 'staff@example.test',
        'phone_number' => '',
        'password' => '',
    ]), true);
    assertSameValue([], $editErrors, 'Staff edits may retain the existing password and omit an optional phone.');

    assertSameValue([], validateWindowAdminInput(windowAdminInput([
        'window_name' => 'Window Test',
        'status' => 'busy',
    ])));
    assertArrayHasKeys(['window_name', 'status'], validateWindowAdminInput(windowAdminInput([
        'status' => 'invalid',
    ])));
});
