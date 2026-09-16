<?php

function characterizationTableColumns(mysqli $connection, string $table): array {
    $statement = $connection->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $statement->bind_param('s', $table);
    $statement->execute();
    return array_column($statement->get_result()->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME');
}

function characterizationUniqueIndexColumns(mysqli $connection, string $table): array {
    $statement = $connection->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0
    ");
    $statement->bind_param('s', $table);
    $statement->execute();
    return array_column($statement->get_result()->fetch_all(MYSQLI_ASSOC), 'COLUMN_NAME');
}

testCase('test database contains the required SmartQMS tables', function (): void {
    $connection = testDatabaseConnection();
    $tables = array_column($connection->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetch_all(MYSQLI_NUM), 0);
    foreach ([
        'users', 'staff', 'health_services', 'service_windows', 'counter_services', 'queue_tickets',
        'staff_service_capabilities', 'ticket_print_batches', 'ticket_number_reservations',
        'wait_time_logs', 'system_settings', 'notifications', 'sms_logs',
        'email_jobs', 'feedback', 'activity_logs', 'auth_attempts', 'ml_comparison_logs',
    ] as $table) {
        assertContainsValue($table, $tables, 'Missing required table.');
    }
});

testCase('critical authentication and queue columns remain available', function (): void {
    $connection = testDatabaseConnection();
    $userColumns = characterizationTableColumns($connection, 'users');
    foreach (['username', 'email', 'password_hash', 'client_type', 'must_change_password', 'role', 'job_title', 'is_verified', 'otp_hash', 'otp_expires_at', 'is_active'] as $column) {
        assertContainsValue($column, $userColumns, 'Missing users column.');
    }

    $ticketColumns = characterizationTableColumns($connection, 'queue_tickets');
    foreach (['user_id', 'ticket_token', 'client_first_name', 'client_last_name', 'client_name', 'phone_number', 'window_id', 'service_id', 'queue_mode', 'reference_number', 'ticket_number', 'entry_type', 'client_type', 'priority_level', 'status', 'lifecycle_status', 'issued_at', 'checked_in_at', 'scheduled_expires_at', 'check_in_method', 'checked_in_by', 'called_at', 'served_at', 'started_at', 'completed_at', 'voided_at'] as $column) {
        assertContainsValue($column, $ticketColumns, 'Missing queue_tickets column.');
    }

    $serviceColumns = characterizationTableColumns($connection, 'health_services');
    foreach (['queue_mode', 'fallback_duration_mins', 'is_hidden'] as $column) {
        assertContainsValue($column, $serviceColumns, 'Missing health service compatibility column.');
    }

    $windowColumns = characterizationTableColumns($connection, 'service_windows');
    foreach (['counter_number', 'window_type', 'location_description', 'service_id', 'staff_id', 'status', 'is_active'] as $column) {
        assertContainsValue($column, $windowColumns, 'Missing service window routing column.');
    }

    $waitColumns = characterizationTableColumns($connection, 'wait_time_logs');
    foreach (['ticket_id', 'predicted_wait_min', 'actual_wait_min', 'actual_service_dur', 'active_windows', 'avg_service_time'] as $column) {
        assertContainsValue($column, $waitColumns, 'Missing wait_time_logs column.');
    }
});

testCase('arrival check-in indexes and nullable scheduled queue numbers are available', function (): void {
    $connection = testDatabaseConnection();
    $column = $connection->query("SHOW COLUMNS FROM queue_tickets LIKE 'ticket_number'")->fetch_assoc();
    assertSameValue('YES', (string) ($column['Null'] ?? ''));

    $indexes = array_column(
        $connection->query("SHOW INDEX FROM queue_tickets")->fetch_all(MYSQLI_ASSOC),
        'Key_name'
    );
    foreach (['idx_queue_scheduled_expiry', 'idx_queue_fifo', 'idx_queue_service_fifo'] as $index) {
        assertContainsValue($index, $indexes);
    }
});

testCase('critical unique indexes remain available', function (): void {
    $connection = testDatabaseConnection();
    assertContainsValue('email', characterizationUniqueIndexColumns($connection, 'users'));
    assertContainsValue('username', characterizationUniqueIndexColumns($connection, 'users'));
    assertContainsValue('reference_number', characterizationUniqueIndexColumns($connection, 'queue_tickets'));
    assertContainsValue('ticket_token', characterizationUniqueIndexColumns($connection, 'queue_tickets'));
    assertContainsValue('ticket_id', characterizationUniqueIndexColumns($connection, 'feedback'));
    assertContainsValue('staff_id', characterizationUniqueIndexColumns($connection, 'service_windows'));
    assertContainsValue('counter_number', characterizationUniqueIndexColumns($connection, 'service_windows'));
});
