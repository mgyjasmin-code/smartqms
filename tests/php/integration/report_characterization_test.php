<?php

function characterizationReportFixture(mysqli $connection): void {
    $password = password_hash('characterization_password', PASSWORD_BCRYPT);
    $firstName = 'Characterization';
    $lastName = 'Report';
    $email = 'characterization_report@example.test';
    $role = ROLE_CLIENT;
    $verified = 1;
    $user = $connection->prepare('INSERT INTO users (first_name, last_name, email, password_hash, role, is_verified) VALUES (?, ?, ?, ?, ?, ?)');
    $user->bind_param('sssssi', $firstName, $lastName, $email, $password, $role, $verified);
    $user->execute();
    $userId = $connection->insert_id;

    $code = 'CHAR-R';
    $name = 'Characterization Reports';
    $encoded = 121;
    $service = $connection->prepare('INSERT INTO health_services (service_code, service_name, service_encoded) VALUES (?, ?, ?)');
    $service->bind_param('ssi', $code, $name, $encoded);
    $service->execute();
    $serviceId = $connection->insert_id;

    $tickets = [
        ['9401', '2037-01-01 09:00:00'],
        ['9402', '2037-01-31 09:00:00'],
        ['9403', '2037-02-01 09:00:00'],
    ];
    foreach ($tickets as [$suffix, $issuedAt]) {
        characterizationInsertTicket($connection, $userId, $serviceId, $suffix, 'regular', 0, 'waiting', $issuedAt);
    }
}

testCase('every report builder retains its top-level and chart contracts', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $range = ['from' => '2037-01-01', 'to' => '2037-01-31'];
        foreach (array_keys(reportDefinitions()) as $key) {
            $report = buildReport($connection, $key, $range);
            assertArrayHasKeys(['key', 'title', 'description', 'range', 'metrics', 'series', 'chart', 'table', 'insight'], $report, $key);
            assertArrayHasKeys(['type', 'labels', 'datasets', 'unit', 'summary'], $report['chart'], $key);
            assertArrayHasKeys(['columns', 'rows'], $report['table'], $key);
        }
    });
});

testCase('report date filtering remains inclusive at both boundaries', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        characterizationReportFixture($connection);
        $report = buildDailyMonthlyStatsReport($connection, ['from' => '2037-01-01', 'to' => '2037-01-31']);
        $dailyRows = array_values(array_filter($report['table']['rows'], static fn(array $row): bool => $row['period_type'] === 'daily'));
        $total = array_sum(array_map(static fn(array $row): int => (int) $row['tickets'], $dailyRows));
        assertSameValue(2, $total);
        assertSameValue(['2037-01-31', '2037-01-01'], array_column($dailyRows, 'period_label'));
    });
});

testCase('unknown reports retain current exception behavior', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        assertThrowsException(
            static fn() => buildReport($connection, 'characterization_unknown', ['from' => '2037-01-01', 'to' => '2037-01-31']),
            InvalidArgumentException::class,
            'Unknown report type'
        );
    });
});

testCase('empty report datasets retain explicit empty states', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $report = buildReport($connection, 'queue_summary', ['from' => '2036-01-01', 'to' => '2036-01-31']);
        assertSameValue([], $report['table']['rows']);
        assertSameValue([], $report['chart']['labels']);
        assertStringContains('No records', $report['insight']);
    });
});
