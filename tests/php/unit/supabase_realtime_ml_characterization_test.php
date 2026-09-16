<?php

testCase('Supabase queue realtime subscribes to the required tables with safe lifecycle fallbacks', function (): void {
    $source = (string) file_get_contents(SMARTQMS_ROOT . '/assets/js/client/services/realtime.js');
    foreach (['tickets', 'counters', 'queue_events'] as $table) {
        assertStringContains("'{$table}'", $source);
    }
    assertStringContains("message?.event === 'postgres_changes'", $source);
    assertStringContains('visibilitychange', $source);
    assertStringContains('startSafetyPolling', $source);
    assertStringContains('return function unsubscribe()', $source);
    assertStringContains("message?.payload?.status === 'error'", $source);
});

testCase('Role dashboards consume queue updates without full-page reloads', function (): void {
    $client = (string) file_get_contents(SMARTQMS_ROOT . '/assets/js/client.js');
    assertStringContains('SmartQmsData.subscribeToQueue', $client);
    assertFalseValue(str_contains($client, 'window.location.reload()'));

    $staff = (string) file_get_contents(SMARTQMS_ROOT . '/assets/js/staff.js');
    assertStringContains('initStaffRealtime', $staff);
    assertStringContains('refreshStaffContent', $staff);
    assertStringContains("current.innerHTML = incoming.innerHTML", $staff);
    assertFalseValue(str_contains($staff, 'window.location.reload()'));

    $admin = (string) file_get_contents(SMARTQMS_ROOT . '/assets/js/admin.js');
    assertStringContains('initAdminLiveDashboard', $admin);
    assertStringContains('refreshAdminDashboard', $admin);
    assertStringContains("current.innerHTML = incoming.innerHTML", $admin);

    $dashboard = (string) file_get_contents(SMARTQMS_ROOT . '/views/admin/dashboard.php');
    assertStringContains('data-admin-live-dashboard', $dashboard);
});

testCase('Public live snapshot is redacted and realtime replication is explicit', function (): void {
    $sql = strtolower((string) file_get_contents(
        SMARTQMS_ROOT . '/database/supabase/20260820_realtime_prediction_operations.sql'
    ));
    $initialSchema = strtolower((string) file_get_contents(
        SMARTQMS_ROOT . '/database/supabase/20260820_initial_schema.sql'
    ));
    assertStringContains('function public.get_live_queue_snapshot', $sql);
    assertStringContains("'ticket_number'", $sql);
    assertStringContains("'people_ahead'", $sql);
    assertStringContains('grant execute on function public.get_live_queue_snapshot(uuid) to anon, authenticated', $sql);
    foreach (['tickets', 'counters', 'queue_events'] as $table) {
        assertStringContains('alter publication supabase_realtime add table public.' . $table, $initialSchema);
    }
});

testCase('ML response contract is authenticated, bounded, and stored with provenance', function (): void {
    $config = (string) file_get_contents(SMARTQMS_ROOT . '/config/ml.php');
    assertStringContains('PYTHON_ML_URL', $config);
    assertStringContains('PYTHON_ML_TOKEN', $config);
    assertStringContains("['localhost', '127.0.0.1', '::1']", $config);

    $php = (string) file_get_contents(SMARTQMS_ROOT . '/modules/queue/prediction.php');
    assertStringContains('Authorization: Bearer ', $php);
    assertStringContains("'estimated_wait_minutes'", $php);
    assertStringContains("'confidence'", $php);
    assertStringContains("'model_version'", $php);
    assertStringContains('$minutes > 480', $php);

    $python = (string) file_get_contents(SMARTQMS_ROOT . '/ml/app.py');
    assertStringContains('request_is_authorized', $python);
    assertStringContains('hmac.compare_digest', $python);
    assertStringContains('"estimated_wait_minutes": minutes', $python);
    assertStringContains('"confidence": prediction_confidence(minutes)', $python);
    assertStringContains('"model_version": model_version', $python);

    $schema = strtolower((string) file_get_contents(SMARTQMS_ROOT . '/database/smartqms_final_v4.sql'));
    assertStringContains('prediction_confidence decimal(6,5)', $schema);
    assertStringContains('model_version        varchar(100)', $schema);
    $ticketService = (string) file_get_contents(SMARTQMS_ROOT . '/modules/queue/ticket_service.php');
    assertStringContains("smartqmsTableHasColumn(\$conn, 'wait_time_logs', 'prediction_confidence')", $ticketService);
});

testCase('Server-only prediction secrets are absent from browser runtime adapters', function (): void {
    $paths = glob(SMARTQMS_ROOT . '/assets/js/client/services/*.js') ?: [];
    foreach ($paths as $path) {
        $source = (string) file_get_contents($path);
        assertFalseValue(str_contains($source, 'PYTHON_ML_TOKEN'));
        assertFalseValue(str_contains($source, 'SERVICE_ROLE_KEY'));
    }
    $runtime = (string) file_get_contents(SMARTQMS_ROOT . '/config/helpers.php');
    assertFalseValue(str_contains($runtime, "'service_role_key'"));
    assertFalseValue(str_contains($runtime, "'ml_token'"));
});
