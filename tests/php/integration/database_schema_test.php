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
        'users', 'staff', 'health_services', 'service_windows', 'queue_tickets',
        'wait_time_logs', 'system_settings', 'notifications', 'sms_logs',
        'email_jobs', 'feedback', 'activity_logs', 'auth_attempts', 'ml_comparison_logs',
    ] as $table) {
        assertContainsValue($table, $tables, 'Missing required table.');
    }
});

testCase('critical authentication and queue columns remain available', function (): void {
    $connection = testDatabaseConnection();
    $userColumns = characterizationTableColumns($connection, 'users');
    foreach (['email', 'password_hash', 'role', 'is_verified', 'otp_hash', 'otp_expires_at', 'is_active'] as $column) {
        assertContainsValue($column, $userColumns, 'Missing users column.');
    }

    $ticketColumns = characterizationTableColumns($connection, 'queue_tickets');
    foreach (['user_id', 'window_id', 'service_id', 'reference_number', 'ticket_number', 'client_type', 'priority_level', 'status', 'issued_at', 'called_at', 'served_at', 'completed_at', 'voided_at'] as $column) {
        assertContainsValue($column, $ticketColumns, 'Missing queue_tickets column.');
    }

    $waitColumns = characterizationTableColumns($connection, 'wait_time_logs');
    foreach (['ticket_id', 'predicted_wait_min', 'actual_wait_min', 'actual_service_dur', 'active_windows', 'avg_service_time'] as $column) {
        assertContainsValue($column, $waitColumns, 'Missing wait_time_logs column.');
    }
});

testCase('critical unique indexes remain available', function (): void {
    $connection = testDatabaseConnection();
    assertContainsValue('email', characterizationUniqueIndexColumns($connection, 'users'));
    assertContainsValue('reference_number', characterizationUniqueIndexColumns($connection, 'queue_tickets'));
    assertContainsValue('ticket_id', characterizationUniqueIndexColumns($connection, 'feedback'));
});
