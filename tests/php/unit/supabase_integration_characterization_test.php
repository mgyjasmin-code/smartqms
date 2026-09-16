<?php

testCase('Supabase URLs require HTTPS except for local development', function (): void {
    assertTrueValue(smartqmsValidSupabaseUrl('https://example.supabase.co'));
    assertTrueValue(smartqmsValidSupabaseUrl('http://localhost:54321'));
    assertTrueValue(smartqmsValidSupabaseUrl('http://127.0.0.1:54321'));
    assertFalseValue(smartqmsValidSupabaseUrl('http://example.supabase.co'));
    assertFalseValue(smartqmsValidSupabaseUrl('not-a-url'));
});

testCase('Browser runtime configuration never projects the service-role credential', function (): void {
    $runtime = smartqmsBrowserRuntimeConfig();
    assertArrayHasKeys(['provider', 'baseUrl', 'supabase', 'endpoints'], $runtime);
    assertArrayHasKeys(['enabled', 'url', 'publishableKey'], $runtime['supabase']);
    assertFalseValue(array_key_exists('service_role_key', $runtime));
    assertFalseValue(array_key_exists('serviceRoleKey', $runtime['supabase']));
    assertFalseValue(str_contains(json_encode($runtime), 'SUPABASE_SERVICE_ROLE_KEY'));
});

testCase('Provider role and ticket-status translations preserve local contracts', function (): void {
    assertSameValue('customer', smartqmsRoleToProvider('client'));
    assertSameValue('customer', smartqmsRoleToProvider('customer'));
    assertSameValue(ROLE_CLIENT, smartqmsRoleFromProvider('customer'));
    assertSameValue(ROLE_ADMIN, smartqmsRoleFromProvider('super_admin'));
    assertSameValue('done', smartqmsTicketStatusToProvider('completed'));
    assertSameValue('completed', smartqmsTicketStatusFromProvider('done'));
    assertSameValue('voided', smartqmsTicketStatusFromProvider('voided'));
});

testCase('Provider service rows retain legacy-compatible aliases', function (): void {
    $normalized = smartqmsNormalizeProviderService([
        'id' => 'c1780a78-c313-4ed5-9890-4f4647294c1c',
        'legacy_id' => 7,
        'code' => 'SVC-007',
        'name' => 'Senior Citizen Services',
        'description' => 'Priority care',
        'ml_value' => 7,
        'queue_mode' => 'specialized',
        'priority_only' => true,
        'active' => true,
        'display_order' => 7,
    ]);

    assertArrayHasKeys([
        'id', 'legacy_id', 'service_id', 'code', 'service_code', 'name',
        'service_name', 'ml_value', 'service_encoded', 'priority_only',
        'active', 'is_active', 'display_order',
    ], $normalized);
    assertSameValue(7, $normalized['service_id']);
    assertSameValue('SVC-007', $normalized['service_code']);
    assertSameValue('Senior Citizen Services', $normalized['service_name']);
    assertSameValue(1, $normalized['is_active']);
});

testCase('Supabase response normalization exposes stable success and error envelopes', function (): void {
    $success = smartqmsNormalizeSupabaseResponse([
        'status' => 200,
        'data' => [['id' => 'service-id']],
    ]);
    assertSameValue(true, $success['ok']);
    assertSameValue('', $success['error']);

    $failure = smartqmsNormalizeSupabaseResponse([
        'status' => 409,
        'data' => ['message' => 'Conflict'],
    ]);
    assertSameValue(false, $failure['ok']);
    assertSameValue(409, $failure['status']);
    assertSameValue('Conflict', $failure['error']);
});

testCase('Supabase development schema defines the required normalized model and RLS boundary', function (): void {
    $schemaPath = SMARTQMS_ROOT . '/database/supabase/20260820_initial_schema.sql';
    assertTrueValue(is_file($schemaPath), 'Supabase schema migration is missing.');
    $schema = (string) file_get_contents($schemaPath);

    foreach ([
        'profiles', 'user_roles', 'services', 'branches', 'staff_branch_assignments',
        'counters', 'staff_service_capabilities', 'tickets', 'ticket_predictions',
        'queue_events', 'notifications', 'feedback',
    ] as $table) {
        assertStringContains('create table if not exists public.' . $table, $schema);
        assertStringContains('alter table public.' . $table . ' enable row level security', $schema);
    }

    assertStringContains("role in ('customer', 'staff', 'admin', 'super_admin')", $schema);
    assertStringContains("status in ('waiting', 'serving', 'done', 'skipped', 'voided')", $schema);
    assertStringContains('create or replace function public.create_queue_ticket', $schema);
    assertStringContains('create or replace function public.get_public_live_queue', $schema);
    assertStringContains('grant execute on function public.create_queue_ticket', $schema);
    assertStringContains('grant execute on function public.get_public_live_queue', $schema);
    assertStringContains('ticket_id uuid not null unique', $schema);
});

testCase('Browser data adapters expose the approved provider-neutral contracts', function (): void {
    $adapterRoot = SMARTQMS_ROOT . '/assets/js/client/services';
    $contracts = [
        'permissions.js' => ['runtimeConfig', 'normalizeRole', 'requestJson', 'supabaseJson'],
        'auth.js' => ['signIn', 'register', 'getCurrentUser', 'signOut'],
        'services.js' => ['getServices'],
        'branches.js' => ['getBranches'],
        'queue.js' => ['createTicket', 'getTicket', 'getLiveQueue', 'callNextTicket', 'completeTicket', 'skipTicket', 'voidTicket', 'setCounterStatus'],
        'realtime.js' => ['subscribeToQueue', 'unsubscribe'],
    ];

    foreach ($contracts as $file => $symbols) {
        $path = $adapterRoot . '/' . $file;
        assertTrueValue(is_file($path), 'Missing browser adapter: ' . $file);
        $source = (string) file_get_contents($path);
        foreach ($symbols as $symbol) {
            assertStringContains($symbol, $source, $file . ' must expose ' . $symbol . '.');
        }
        assertFalseValue(str_contains($source, 'SERVICE_ROLE'), $file . ' must not contain a service-role key.');
    }
});

testCase('Catalog HTTP facades remain provider-neutral and read-only', function (): void {
    foreach (['services.php', 'branches.php'] as $endpoint) {
        $source = (string) file_get_contents(SMARTQMS_ROOT . '/api/' . $endpoint);
        assertStringContains("REQUEST_METHOD", $source);
        assertStringContains("!== 'GET'", $source);
        assertStringContains("'provider' => smartqmsDataProviderMode()", $source);
        assertFalseValue(str_contains(strtolower($source), 'service_role_key'));
    }
});
