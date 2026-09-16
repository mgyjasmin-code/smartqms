<?php

testCase('Supabase Auth verification uses the publishable key and never service role credentials', function (): void {
    $client = (string) file_get_contents(SMARTQMS_ROOT . '/modules/integrations/supabase_client.php');
    assertStringContains('function smartqmsSupabaseAuthRequest', $client);
    assertStringContains("'apikey: ' . \$config['publishable_key']", $client);
    $authSection = substr($client, strpos($client, 'function smartqmsSupabaseAuthRequest'));
    assertFalseValue(str_contains($authSection, "\$config['service_role_key']"));

    $auth = (string) file_get_contents(SMARTQMS_ROOT . '/modules/integrations/supabase_auth.php');
    assertStringContains("'/auth/v1/user'", $auth);
    assertStringContains("'/rest/v1/user_roles?select=role", $auth);
    assertStringContains('array_intersect($allowedProviderRoles', $auth);
    assertStringContains('function smartqmsCompleteSupabaseLogin', $auth);
    assertStringContains('grant_type=refresh_token', $auth);

    $login = (string) file_get_contents(SMARTQMS_ROOT . '/modules/auth/login.php');
    $register = (string) file_get_contents(SMARTQMS_ROOT . '/modules/auth/register.php');
    $logout = (string) file_get_contents(SMARTQMS_ROOT . '/modules/auth/logout.php');
    assertStringContains('grant_type=password', $login);
    assertStringContains('http_response_code(410)', $register);
    assertFalseValue(str_contains($register, "'/auth/v1/signup'"));
    assertStringContains("'/auth/v1/logout'", $logout);
});

testCase('Server ticket API derives prediction inputs and rejects browser supplied predictions', function (): void {
    $source = (string) file_get_contents(SMARTQMS_ROOT . '/api/tickets.php');
    foreach (['queue_length', 'hour_of_day', 'day_of_week', 'service_type_encoded',
              'client_type_encoded', 'active_windows', 'avg_service_time'] as $feature) {
        assertStringContains("'{$feature}'", $source);
    }
    assertStringContains('requestMlPrediction($features, 2)', $source);
    assertStringContains("'/rest/v1/rpc/server_create_queue_ticket'", $source);
    assertFalseValue(str_contains($source, "\$payload['predicted_wait_minutes']"));
    assertFalseValue(str_contains($source, "\$payload['prediction_confidence']"));
});

testCase('Branch and elevated-role mutations are service-role only and audited', function (): void {
    $sql = strtolower((string) file_get_contents(
        SMARTQMS_ROOT . '/database/supabase/20260820_branch_role_operations.sql'
    ));
    assertStringContains('function public.admin_save_branch', $sql);
    assertStringContains('function public.super_admin_set_role', $sql);
    assertStringContains('function public.server_finalize_customer_profile', $sql);
    assertStringContains("ur.role = 'super_admin'", $sql);
    assertStringContains('cannot revoke their own elevated role', $sql);
    assertStringContains('insert into public.queue_events', $sql);
    assertStringContains('from public, anon, authenticated', $sql);
    assertStringContains('to service_role', $sql);
});

testCase('Compatibility APIs enforce method role and stable JSON boundaries', function (): void {
    $contracts = [
        'api/auth/profile.php' => ['smartqmsSupabasePrincipal', "'POST'"],
        'api/tickets.php' => ['smartqmsApiPrincipal', "['customer']"],
        'api/queue.php' => ['smartqmsApiPrincipal', 'get_live_queue_snapshot'],
        'api/admin/reports.php' => ['smartqmsApiPrincipal', 'buildReport'],
        'api/admin/branches.php' => ['smartqmsApiPrincipal', 'smartqmsAdminSaveBranch'],
        'api/admin/roles.php' => ["['super_admin']", 'smartqmsAdminSetRole'],
    ];
    foreach ($contracts as $path => $needles) {
        assertTrueValue(is_file(SMARTQMS_ROOT . '/' . $path), 'Missing API: ' . $path);
        $source = (string) file_get_contents(SMARTQMS_ROOT . '/' . $path);
        foreach ($needles as $needle) assertStringContains($needle, $source, $path);
        assertStringContains('jsonResponse(', $source, $path);
    }
});

testCase('Supabase rollout documents migration order secrets cutover and rollback', function (): void {
    $runbook = (string) file_get_contents(SMARTQMS_ROOT . '/docs/SUPABASE_ROLLOUT_RUNBOOK.md');
    foreach (['initial_schema.sql', 'staff_queue_operations.sql', 'admin_catalog_operations.sql',
              'realtime_prediction_operations.sql', 'branch_role_operations.sql'] as $migration) {
        assertStringContains($migration, $runbook);
    }
    assertStringContains('SMARTQMS_DATA_PROVIDER=shadow', $runbook);
    assertStringContains('SMARTQMS_DATA_PROVIDER=local', $runbook);
    assertStringContains('Never import password hashes', $runbook);
});
