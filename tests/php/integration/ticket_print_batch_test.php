<?php

require_once SMARTQMS_ROOT . '/modules/queue/print_batch_service.php';

testCase('ticket print batches reserve non-overlapping current-day numbers and render a PDF', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $password = password_hash('characterization_password', PASSWORD_BCRYPT);
        $email = 'characterization_batch_staff@example.test';
        $first = 'Characterization';
        $last = 'Batch Staff';
        $role = ROLE_STAFF;
        $verified = 1;
        $user = $connection->prepare('INSERT INTO users (first_name,last_name,email,password_hash,role,is_verified) VALUES (?,?,?,?,?,?)');
        $user->bind_param('sssssi', $first, $last, $email, $password, $role, $verified);
        $user->execute();
        $userId = (int) $connection->insert_id;
        $staff = $connection->prepare('INSERT INTO staff (user_id) VALUES (?)');
        $staff->bind_param('i', $userId);
        $staff->execute();
        $staffId = (int) $connection->insert_id;

        $code = 'CHAR-BATCH';
        $name = 'Characterization Batch Printing';
        $encoded = 126;
        $order = 126;
        $service = $connection->prepare('INSERT INTO health_services (service_code,service_name,service_encoded,display_order) VALUES (?,?,?,?)');
        $service->bind_param('ssii', $code, $name, $encoded, $order);
        $service->execute();
        $serviceId = (int) $connection->insert_id;

        $batch = createTicketPrintBatch($connection, $staffId, $serviceId, 41, 48);
        assertSameValue(8, count($batch['numbers']));
        assertSameValue('C-041', $batch['numbers'][0]);
        assertSameValue('C-048', $batch['numbers'][7]);
        $reserved = (int) $connection->query('SELECT COUNT(*) AS total FROM ticket_number_reservations WHERE batch_id=' . (int) $batch['batch_id'])->fetch_assoc()['total'];
        assertSameValue(8, $reserved);

        assertThrowsException(
            fn () => createTicketPrintBatch($connection, $staffId, $serviceId, 45, 52),
            DomainException::class,
            'already reserved'
        );
        assertThrowsException(
            fn () => createTicketPrintBatch($connection, $staffId, $serviceId, 1, 201),
            InvalidArgumentException::class,
            'limited to 200'
        );

        $reference = 'BHC-2098-BATCH';
        $ticket = $connection->prepare("INSERT INTO queue_tickets (service_id,reference_number,ticket_number,status,lifecycle_status,checked_in_at) VALUES (?, ?, NULL, 'waiting', 'waiting', NOW())");
        $ticket->bind_param('is', $serviceId, $reference);
        $ticket->execute();
        $ticketId = (int) $connection->insert_id;
        $locked = $connection->query('SELECT * FROM queue_tickets WHERE ticket_id=' . $ticketId . ' FOR UPDATE')->fetch_assoc();
        $allocated = allocateQueueNumberForLockedTicket($connection, $locked, date('Y-m-d'));
        assertSameValue('C-041', $allocated['ticket_number']);

        $pdf = renderTicketPrintBatchPdf($batch);
        assertSameValue('%PDF', substr($pdf, 0, 4));
        assertTrueValue(strlen($pdf) > 1000, 'Expected a non-empty generated PDF.');
    });
});

testCase('ticket print batches reject numbers already assigned on the same service day', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $password = password_hash('characterization_password', PASSWORD_BCRYPT);
        $email = 'characterization_assigned_batch_staff@example.test';
        $first = 'Characterization';
        $last = 'Assigned Batch Staff';
        $role = ROLE_STAFF;
        $verified = 1;
        $user = $connection->prepare('INSERT INTO users (first_name,last_name,email,password_hash,role,is_verified) VALUES (?,?,?,?,?,?)');
        $user->bind_param('sssssi', $first, $last, $email, $password, $role, $verified);
        $user->execute();
        $userId = (int) $connection->insert_id;
        $staff = $connection->prepare('INSERT INTO staff (user_id) VALUES (?)');
        $staff->bind_param('i', $userId);
        $staff->execute();
        $staffId = (int) $connection->insert_id;
        $code = 'CHAR-ASSIGNED';
        $name = 'Characterization Assigned Number';
        $encoded = 127;
        $order = 127;
        $service = $connection->prepare('INSERT INTO health_services (service_code,service_name,service_encoded,display_order) VALUES (?,?,?,?)');
        $service->bind_param('ssii', $code, $name, $encoded, $order);
        $service->execute();
        $serviceId = (int) $connection->insert_id;
        $reference = 'BHC-2098-ASSIGNED';
        $number = 'C-060';
        $ticket = $connection->prepare("INSERT INTO queue_tickets (service_id,reference_number,ticket_number,status,lifecycle_status,checked_in_at) VALUES (?, ?, ?, 'waiting', 'waiting', NOW())");
        $ticket->bind_param('iss', $serviceId, $reference, $number);
        $ticket->execute();

        assertThrowsException(
            fn () => createTicketPrintBatch($connection, $staffId, $serviceId, 60, 62),
            DomainException::class,
            'already been assigned'
        );
    });
});
