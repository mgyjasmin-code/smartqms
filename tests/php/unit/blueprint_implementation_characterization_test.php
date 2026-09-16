<?php

function blueprintSource(string $relativePath): string {
    $source = file_get_contents(SMARTQMS_ROOT . '/' . $relativePath);
    if ($source === false) {
        throw new RuntimeException('Could not read ' . $relativePath . '.');
    }
    return $source;
}

testCase('blueprint compatibility schema preserves legacy entities and adds required aliases', function (): void {
    $schema = blueprintSource('database/smartqms_final_v4.sql');
    foreach ([
        'username       VARCHAR(100)',
        'job_title      VARCHAR(100)',
        'fallback_duration_mins',
        'is_hidden',
        'counter_number',
        'CREATE TABLE IF NOT EXISTS counter_services',
        'ticket_token',
        "ENUM('walk-in','online')",
        "ENUM('scheduled','waiting','calling','in-progress','completed','void')",
        'started_at',
    ] as $contract) {
        assertStringContains($contract, $schema);
    }
    assertStringContains('CREATE TABLE IF NOT EXISTS queue_tickets', $schema);
    assertStringContains('CREATE TABLE IF NOT EXISTS service_windows', $schema);

    $migration = blueprintSource('database/upgrade_blueprint_2026_08_21.sql');
    assertStringContains('IMPORTANT: back up the database', $migration);
    assertStringContains('CREATE TABLE counter_services', $migration);
    assertStringContains('MODIFY COLUMN user_id INT NULL', $migration);
});

testCase('unified login and public queue routes retain role and token safety', function (): void {
    foreach ([
        'login/index.php',
        'queue/join/index.php',
        'queue/ticket/index.php',
        'track/index.php',
        'public-display/index.php',
    ] as $path) {
        assertTrueValue(is_file(SMARTQMS_ROOT . '/' . $path), $path . ' must exist.');
    }

    $login = blueprintSource('modules/auth/login.php');
    assertStringContains('authUserByLoginId', $login);
    $redirects = blueprintSource('modules/auth/auth_redirects.php');
    assertStringContains("'staff/select-counter/'", $redirects);

    $intake = blueprintSource('modules/queue/public_intake.php');
    assertStringContains('bin2hex(random_bytes(32))', $intake);
    assertStringContains("[a-f0-9]{32}|[a-f0-9]{64}", $intake);
    assertStringContains("'walk-in'", $intake);
    assertStringContains("'scheduled'", $intake);
    assertStringContains('generateTokenQR', $intake);

    $tracker = blueprintSource('assets/js/public_queue.js');
    assertStringContains('window.setTimeout(poll, 5000)', $tracker);
    foreach (['calling', 'in-progress', 'completed', 'data-feedback-gate'] as $contract) {
        assertStringContains($contract, $tracker);
    }
});

testCase('staff workstation exposes arrival check-in lifecycle and FIFO queue monitor zones', function (): void {
    $dashboard = blueprintSource('views/staff/dashboard.php');
    foreach ([
        'recall_ticket.php',
        'start_service.php',
        'complete_ticket.php',
        'void_ticket.php',
        'Active Waiting Queue',
        'staff-kpi-grid',
        'staff-fifo-table',
    ] as $contract) {
        assertStringContains($contract, $dashboard);
    }

    $arrival = blueprintSource('views/staff/check_in.php');
    foreach (['data-arrival-camera-start', 'data-arrival-reference-form', 'data-staff-walk-in-form', 'arrivalCheckInModal'] as $contract) {
        assertStringContains($contract, $arrival);
    }
    assertFalseValue(str_contains($arrival, 'name="classification"'));

    $actions = blueprintSource('modules/service_window/ticket_actions.php');
    foreach (["lifecycle_status = 'calling'", "lifecycle_status = 'in-progress'", "lifecycle_status = 'completed'", "lifecycle_status = 'void'"] as $contract) {
        assertStringContains($contract, $actions);
    }
    $queries = blueprintSource('modules/service_window/window_queries.php');
    assertStringContains('FOR UPDATE', $queries);
    assertStringContains('Voided manually by staff', $actions);
    assertStringContains('getSetting($conn, \'void_timeout_minutes\', \'5\')', $actions);
    assertStringContains("lifecycle_status = 'waiting'", $actions);
    assertStringContains('checked_in_at IS NOT NULL', $actions);

    $claim = blueprintSource('modules/service_window/counter_claim.php');
    assertStringContains('FOR UPDATE', $claim);
    assertStringContains('staff_id IS NULL', $claim);
});

testCase('admin management exposes blueprint staff service and counter fields', function (): void {
    $staff = blueprintSource('views/admin/add_staff.php');
    foreach (['name="username"', 'name="job_title"', 'name="is_active"'] as $contract) {
        assertStringContains($contract, $staff);
    }

    $services = blueprintSource('views/admin/services.php');
    assertStringContains('name="fallback_duration_mins"', $services);
    assertStringContains('name="is_hidden"', $services);

    $windows = blueprintSource('views/admin/windows.php');
    assertStringContains('name="counter_number"', $windows);
    assertStringContains('name="service_ids[]"', $windows);
    $admin = blueprintSource('modules/admin/window_admin.php');
    assertStringContains('syncCounterServices', $admin);
});

testCase('public display is unauthenticated read only and updates calling tickets silently', function (): void {
    $page = blueprintSource('public-display/index.php');
    assertFalseValue(str_contains($page, 'requireLogin'));
    assertStringContains('data-public-display', $page);
    $endpoint = blueprintSource('modules/queue/public_display_status.php');
    assertStringContains("qt.lifecycle_status IN ('calling','in-progress')", $endpoint);
    assertStringContains("qt.lifecycle_status = 'waiting'", $endpoint);
    assertFalseValue(str_contains($endpoint, 'client_name'));
    $script = blueprintSource('assets/js/public_display.js');
    assertStringContains('window.setInterval(poll, 3000)', $script);
    assertStringContains("root.classList.add('has-new-call')", $script);
    assertStringContains('waitingRows.slice(0, capacity)', $script);
    assertStringContains('servingRows.slice(1)', $script);
    assertFalseValue(str_contains($script, 'AudioContext'));
    assertFalseValue(str_contains($script, 'SpeechSynthesisUtterance'));
    assertFalseValue(str_contains($script, 'payload.recent'));
});

testCase('ML training uses exported real queue history and provides FastAPI compatibility', function (): void {
    $exporter = blueprintSource('scripts/export_ml_history.php');
    assertStringContains("qt.status = 'completed'", $exporter);
    assertStringContains('actual_wait_minutes', $exporter);
    assertStringContains('fputcsv', $exporter);

    $pipeline = blueprintSource('ml/data_pipeline.py');
    assertStringContains('ml/dataset/queue_data.csv', $pipeline);
    assertStringContains('must be generated by scripts/export_ml_history.php', $pipeline);
    assertFalseValue(str_contains($pipeline, 'build_model_frame'));
    $training = blueprintSource('ml/training.py');
    assertStringContains('RandomForestRegressor', $training);
    assertStringContains('n_estimators=200', $training);
    $fastApi = blueprintSource('ml/main.py');
    assertStringContains('FastAPI(', $fastApi);
    assertStringContains('@api.post("/predict")', $fastApi);
    assertStringContains('PYTHON_ML_TOKEN', $fastApi);

    $generator = blueprintSource('ml/generate_dataset.py');
    assertStringContains('Synthetic dataset generation is disabled', $generator);
    assertFalseValue(str_contains($training, 'synthetic_queue_data.csv'));
});

testCase('blueprint HTTP inventory records public and staff compatibility endpoints', function (): void {
    $contracts = blueprintSource('tests/contracts/http_contracts.php');
    foreach ([
        'login/index.php',
        'queue/join/index.php',
        'modules/queue/public_ticket_status.php',
        'modules/feedback/public_submit.php',
        'public-display/index.php',
        'modules/service_window/claim_counter.php',
        'modules/service_window/recall_ticket.php',
        'modules/service_window/start_service.php',
        'modules/queue/staff_walk_in.php',
    ] as $path) {
        assertStringContains($path, $contracts);
    }
});
