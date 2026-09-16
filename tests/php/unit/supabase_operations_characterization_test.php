<?php

testCase('Supabase staff operations are transactional and service-role only', function (): void {
    $path = SMARTQMS_ROOT . '/database/supabase/20260820_staff_queue_operations.sql';
    assertTrueValue(is_file($path));
    $sql = (string) file_get_contents($path);
    foreach (['staff_call_next', 'staff_finish_ticket', 'staff_set_counter_status'] as $operation) {
        assertStringContains('function public.' . $operation, $sql);
        assertStringContains('grant execute on function public.' . $operation, $sql);
    }
    assertStringContains('for update skip locked', strtolower($sql));
    assertStringContains("insert into public.queue_events", strtolower($sql));
    assertStringContains("p_action not in ('complete', 'skip', 'void')", strtolower($sql));
    assertStringContains("'turn_void', 'browser', 'pending', false", strtolower($sql));
    assertStringContains('from public, anon, authenticated', strtolower($sql));
});

testCase('Protected staff endpoints preserve contracts and delegate to provider gateway', function (): void {
    $contracts = [
        'call_next.php' => 'smartqmsCallNextForStaff',
        'complete_ticket.php' => 'smartqmsCompleteForStaff',
        'skip_ticket.php' => 'smartqmsSkipForStaff',
        'void_ticket.php' => 'smartqmsVoidForStaff',
        'window_status.php' => 'smartqmsSetCounterStatusForStaff',
    ];
    foreach ($contracts as $file => $gateway) {
        $source = (string) file_get_contents(SMARTQMS_ROOT . '/modules/service_window/' . $file);
        assertStringContains('requireLogin(ROLE_STAFF)', $source);
        assertStringContains('requireValidCsrf', $source);
        assertStringContains($gateway, $source);
    }
});

testCase('Staff pages load data adapters before staff behavior and use declarative provider actions', function (): void {
    $header = (string) file_get_contents(SMARTQMS_ROOT . '/views/staff/includes/header.php');
    $permissionsPosition = strpos($header, 'permissions.js');
    $queuePosition = strpos($header, 'queue.js');
    $staffPosition = strpos($header, 'staff.js');
    assertTrueValue($permissionsPosition !== false && $queuePosition !== false && $staffPosition !== false);
    assertTrueValue($permissionsPosition < $queuePosition && $queuePosition < $staffPosition);

    $dashboard = (string) file_get_contents(SMARTQMS_ROOT . '/views/staff/dashboard.php');
    assertStringContains('data-provider-action="callNextTicket"', $dashboard);
    assertStringContains('data-provider-action="setCounterStatus"', $dashboard);
    assertStringContains('data-provider-action="completeTicket"', $dashboard);
    assertStringContains('data-provider-action="skipTicket"', $dashboard);
});

testCase('Admin management pages use the provider-neutral server adapter', function (): void {
    $contracts = [
        'services.php' => ['smartqmsAdminListServices', 'smartqmsAdminCreateService', 'smartqmsAdminUpdateService'],
        'windows.php' => ['smartqmsAdminListWindows', 'smartqmsAdminSaveWindow'],
        'add_staff.php' => ['smartqmsAdminListStaff', 'smartqmsAdminCreateStaff', 'smartqmsAdminUpdateStaff'],
    ];
    foreach ($contracts as $file => $symbols) {
        $source = (string) file_get_contents(SMARTQMS_ROOT . '/views/admin/' . $file);
        foreach ($symbols as $symbol) assertStringContains($symbol, $source);
    }
});

testCase('Admin service ordering is authorized and transactional in Supabase', function (): void {
    $path = SMARTQMS_ROOT . '/database/supabase/20260820_admin_catalog_operations.sql';
    assertTrueValue(is_file($path));
    $sql = strtolower((string) file_get_contents($path));
    assertStringContains('function public.admin_move_service', $sql);
    assertStringContains("ur.role in ('admin', 'super_admin')", $sql);
    assertStringContains('for update', $sql);
    assertStringContains('to service_role', $sql);
});

testCase('Queue browser adapter exposes every staff mutation used by the dashboard', function (): void {
    $source = (string) file_get_contents(SMARTQMS_ROOT . '/assets/js/client/services/queue.js');
    foreach (['callNextTicket', 'completeTicket', 'skipTicket', 'voidTicket', 'setCounterStatus'] as $symbol) {
        assertStringContains($symbol, $source);
    }
    assertFalseValue(str_contains($source, 'SERVICE_ROLE'));
});
