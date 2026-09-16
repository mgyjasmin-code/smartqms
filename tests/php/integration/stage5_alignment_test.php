<?php

require_once SMARTQMS_ROOT . '/modules/queue/service_availability.php';

testCase('legacy unmapped shared counters remain available while explicit mappings stay supported', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $fixtures = characterizationServiceWindowFixtures($connection, 'open', true, true);
        $service = ['service_id' => $fixtures['service_id'], 'queue_mode' => 'central'];

        assertSameValue(1, countApplicableActiveWindows($connection, $service));

        $mapping = $connection->prepare('INSERT INTO counter_services (counter_id, service_id) VALUES (?, ?)');
        $mapping->bind_param('ii', $fixtures['window_id'], $fixtures['service_id']);
        $mapping->execute();

        assertSameValue(1, countApplicableActiveWindows($connection, $service));
    });
});

testCase('Scheduled appointments are excluded from operational report volume', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $code = 'CHAR-8J-V';
        $name = 'Characterization Stage 8J Volume';
        $encoded = 124;
        $service = $connection->prepare('INSERT INTO health_services (service_code, service_name, service_encoded) VALUES (?, ?, ?)');
        $service->bind_param('ssi', $code, $name, $encoded);
        $service->execute();
        $serviceId = (int) $connection->insert_id;

        $scheduledReference = 'BHC-2039-8101';
        $scheduled = $connection->prepare("INSERT INTO queue_tickets (service_id, reference_number, ticket_number, status, lifecycle_status, issued_at, scheduled_expires_at) VALUES (?, ?, NULL, 'waiting', 'scheduled', '2039-02-10 07:00:00', '2039-02-10 23:59:59')");
        $scheduled->bind_param('is', $serviceId, $scheduledReference);
        $scheduled->execute();

        $waitingReference = 'BHC-2039-8102';
        $waitingNumber = 'Z-8102';
        $waiting = $connection->prepare("INSERT INTO queue_tickets (service_id, reference_number, ticket_number, status, lifecycle_status, issued_at, checked_in_at) VALUES (?, ?, ?, 'waiting', 'waiting', '2039-02-10 06:00:00', '2039-02-10 08:00:00')");
        $waiting->bind_param('iss', $serviceId, $waitingReference, $waitingNumber);
        $waiting->execute();

        $range = ['from' => '2039-02-10', 'to' => '2039-02-10'];
        $summary = buildQueueSummaryReport($connection, $range);
        assertSameValue(1, (int) $summary['metrics'][0]['value']);
        assertSameValue('waiting', $summary['table']['rows'][0]['status']);

        $peak = buildPeakHourReport($connection, $range);
        assertSameValue(1, (int) $peak['series'][0]['value']);
        assertSameValue('08:00', $peak['series'][0]['label']);

        $daily = buildDailyMonthlyStatsReport($connection, $range);
        assertSameValue(1, (int) $daily['metrics'][0]['value']);
    });
});

testCase('Predicted versus actual wait is measured from check-in to service start', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $code = 'CHAR-8J-W';
        $name = 'Characterization Stage 8J Wait';
        $encoded = 125;
        $service = $connection->prepare('INSERT INTO health_services (service_code, service_name, service_encoded) VALUES (?, ?, ?)');
        $service->bind_param('ssi', $code, $name, $encoded);
        $service->execute();
        $serviceId = (int) $connection->insert_id;

        $reference = 'BHC-2039-8201';
        $number = 'Y-8201';
        $ticket = $connection->prepare("INSERT INTO queue_tickets (service_id, reference_number, ticket_number, status, lifecycle_status, issued_at, checked_in_at, called_at, served_at, started_at, completed_at) VALUES (?, ?, ?, 'completed', 'completed', '2039-01-01 07:00:00', '2039-03-02 09:00:00', '2039-03-02 09:03:00', '2039-03-02 09:04:00', '2039-03-02 09:04:00', '2039-03-02 09:10:00')");
        $ticket->bind_param('iss', $serviceId, $reference, $number);
        $ticket->execute();
        $ticketId = (int) $connection->insert_id;

        $predicted = 5.0;
        $queueLength = 0;
        $hour = 9;
        $day = 3;
        $clientType = 0;
        $windows = 1;
        $averageService = 10.0;
        $log = $connection->prepare('INSERT INTO wait_time_logs (ticket_id, predicted_wait_min, queue_length, hour_of_day, day_of_week, service_type_encoded, client_type_encoded, active_windows, avg_service_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $log->bind_param('idiiiiiid', $ticketId, $predicted, $queueLength, $hour, $day, $encoded, $clientType, $windows, $averageService);
        $log->execute();

        $report = buildPredictedVsActualReport($connection, ['from' => '2039-03-02', 'to' => '2039-03-02']);
        assertSameValue(1, count($report['table']['rows']));
        assertEqualsValue(4.0, (float) $report['table']['rows'][0]['actual_wait_min']);
        assertEqualsValue(1.0, (float) $report['table']['rows'][0]['error_min']);
    });
});
