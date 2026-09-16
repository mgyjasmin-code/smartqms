<?php
require_once SMARTQMS_ROOT . '/modules/settings/service_catalog.php';
require_once SMARTQMS_ROOT . '/modules/admin/staff_accounts.php';
require_once SMARTQMS_ROOT . '/modules/admin/window_admin.php';
require_once SMARTQMS_ROOT . '/modules/admin/users.php';

testCase('service adapters enforce system-managed identifiers and queue modes', function (): void {
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
    $html['service_code'] = '';
    assertSameValue([], validateServiceHtmlInput($html, true), 'A posted service code is not part of validation because it is server-managed.');
    assertSameValue('1', $html['priority_only']);
    assertSameValue(['service_name' => 'Service name is required.'], validateServiceJsonAdd([]));
    assertSameValue(
        ['service_id' => 'Service id is required.', 'service_name' => 'Service name is required.'],
        validateServiceJsonEdit([])
    );
});

testCase('staff and window validation retain account rules and configuration-only window fields', function (): void {
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
        'job_title' => 'Nurse',
        'phone_number' => '12345',
        'password' => 'password123',
    ]));
    assertArrayHasKeys(['phone_number'], $invalidPhoneErrors);

    $editErrors = validateStaffAccountInput(staffAccountInput([
        'staff_id' => '7',
        'first_name' => 'Test',
        'last_name' => 'Staff',
        'email' => 'staff@example.test',
        'job_title' => 'Nurse',
        'phone_number' => '',
        'password' => '',
    ]), true);
    assertSameValue([], $editErrors, 'Staff edits may retain the existing password and omit an optional phone.');

    assertSameValue([], validateWindowAdminInput(windowAdminInput([
        'counter_number' => '9',
        'window_name' => 'Window Test',
        'window_type' => 'shared',
        'service_ids' => ['3'],
        'management_status' => 'maintenance',
    ])));
    $maintenanceWindow = windowAdminInput([
        'counter_number' => '9',
        'window_type' => 'shared',
        'service_ids' => ['3'],
        'management_status' => 'maintenance',
    ]);
    assertSameValue('Window 9', $maintenanceWindow['window_name']);
    assertSameValue('2', $maintenanceWindow['is_active']);
    assertArrayHasKeys(['window_name', 'counter_number', 'service_ids'], validateWindowAdminInput(windowAdminInput([
        'window_type' => 'specialized',
    ])));
});
