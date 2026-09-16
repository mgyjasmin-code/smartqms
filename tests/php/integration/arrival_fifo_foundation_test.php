<?php

require_once SMARTQMS_ROOT . '/modules/service_window/ticket_actions.php';
require_once SMARTQMS_ROOT . '/modules/queue/public_intake.php';

testCase('Scheduled tickets are excluded from Call Next and checked-in tickets are strict FIFO', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $serviceCode = 'CHAR-FIFO';
        $serviceName = 'Characterization FIFO';
        $serviceEncoded = 121;
        $displayOrder = 121;
        $service = $connection->prepare('INSERT INTO health_services (service_code, service_name, service_encoded, display_order) VALUES (?, ?, ?, ?)');
        $service->bind_param('ssii', $serviceCode, $serviceName, $serviceEncoded, $displayOrder);
        $service->execute();
        $serviceId = (int) $connection->insert_id;

        $insert = $connection->prepare("
            INSERT INTO queue_tickets
              (service_id, reference_number, ticket_number, priority_level, status,
               lifecycle_status, issued_at, checked_in_at, scheduled_expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $rows = [
            ['9701', null, 0, 'waiting', 'scheduled', '2038-01-01 07:00:00', null, '2038-01-01 23:59:59'],
            ['9702', 'C-002', 9, 'waiting', 'waiting', '2038-01-01 08:00:00', '2038-01-01 08:10:00', null],
            ['9703', 'C-003', 0, 'waiting', 'waiting', '2038-01-01 07:30:00', '2038-01-01 08:05:00', null],
        ];
        $ids = [];
        foreach ($rows as $row) {
            [$suffix, $ticketNumber, $priority, $status, $lifecycle, $issued, $checked, $expires] = $row;
            $reference = 'BHC-2098-' . $suffix;
            $insert->bind_param('ississsss', $serviceId, $reference, $ticketNumber, $priority, $status, $lifecycle, $issued, $checked, $expires);
            $insert->execute();
            $ids[] = (int) $connection->insert_id;
        }

        $next = getNextWaitingTicketForUpdate($connection, $serviceId);
        assertSameValue($ids[2], (int) ($next['ticket_id'] ?? 0));
        assertSameValue(1, peopleAhead($connection, $connection->query('SELECT * FROM queue_tickets WHERE ticket_id=' . $ids[1])->fetch_assoc()));
    });
});

testCase('per-service numbering allocates reserved numbers before automatic continuation', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $password = password_hash('characterization_password', PASSWORD_BCRYPT);
        $email = 'characterization_fifo_staff@example.test';
        $first = 'Characterization';
        $last = 'FIFO Staff';
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

        $code = 'CHAR-NUM';
        $name = 'Characterization Numbering';
        $encoded = 122;
        $order = 122;
        $service = $connection->prepare('INSERT INTO health_services (service_code,service_name,service_encoded,display_order) VALUES (?,?,?,?)');
        $service->bind_param('ssii', $code, $name, $encoded, $order);
        $service->execute();
        $serviceId = (int) $connection->insert_id;

        $today = date('Y-m-d');
        $start = 25;
        $end = 26;
        $batch = $connection->prepare('INSERT INTO ticket_print_batches (service_id,service_date,start_number,end_number,created_by) VALUES (?,?,?,?,?)');
        $batch->bind_param('isiii', $serviceId, $today, $start, $end, $staffId);
        $batch->execute();
        $batchId = (int) $connection->insert_id;
        $reservation = $connection->prepare('INSERT INTO ticket_number_reservations (batch_id,service_id,service_date,sequence_number) VALUES (?,?,?,?)');
        foreach ([25, 26] as $sequence) {
            $reservation->bind_param('iisi', $batchId, $serviceId, $today, $sequence);
            $reservation->execute();
        }

        $numbers = [];
        for ($index = 1; $index <= 3; $index++) {
            $reference = 'BHC-2098-98' . $index;
            $ticket = $connection->prepare("INSERT INTO queue_tickets (service_id,reference_number,ticket_number,status,lifecycle_status,checked_in_at) VALUES (?, ?, NULL, 'waiting', 'waiting', NOW())");
            $ticket->bind_param('is', $serviceId, $reference);
            $ticket->execute();
            $row = $connection->query('SELECT * FROM queue_tickets WHERE ticket_id=' . (int) $connection->insert_id . ' FOR UPDATE')->fetch_assoc();
            $numbers[] = allocateQueueNumberForLockedTicket($connection, $row, $today)['ticket_number'];
        }

        assertSameValue(['C-025', 'C-026', 'C-027'], $numbers);
    });
});

testCase('scheduled expiry is idempotent and synchronizes the legacy status', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $serviceId = (int) $connection->query('SELECT service_id FROM health_services ORDER BY service_id LIMIT 1')->fetch_assoc()['service_id'];
        $reference = 'BHC-2098-9991';
        $expires = '2020-01-01 23:59:59';
        $ticket = $connection->prepare("INSERT INTO queue_tickets (service_id,reference_number,ticket_number,status,lifecycle_status,scheduled_expires_at) VALUES (?, ?, NULL, 'waiting', 'scheduled', ?)");
        $ticket->bind_param('iss', $serviceId, $reference, $expires);
        $ticket->execute();
        $ticketId = (int) $connection->insert_id;

        assertSameValue(1, expireScheduledQueueTickets($connection, '2020-01-02 00:00:00'));
        assertSameValue(0, expireScheduledQueueTickets($connection, '2020-01-02 00:00:00'));
        $row = $connection->query('SELECT status,lifecycle_status,voided_reason FROM queue_tickets WHERE ticket_id=' . $ticketId)->fetch_assoc();
        assertSameValue('voided', $row['status']);
        assertSameValue('void', $row['lifecycle_status']);
        assertSameValue('Scheduled ticket expired before check-in', $row['voided_reason']);
    });
});

testCase('consecutive arrival confirmations retain one Waiting transition and one queue number', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $password = password_hash('characterization_password', PASSWORD_BCRYPT);
        $email = 'characterization_checkin_staff@example.test';
        $staffFirst = 'Characterization';
        $staffLast = 'Check-In Staff';
        $staffRole = ROLE_STAFF;
        $verified = 1;
        $user = $connection->prepare('INSERT INTO users (first_name,last_name,email,password_hash,role,is_verified) VALUES (?,?,?,?,?,?)');
        $user->bind_param('sssssi', $staffFirst, $staffLast, $email, $password, $staffRole, $verified);
        $user->execute();
        $staffUserId = (int) $connection->insert_id;
        $staff = $connection->prepare('INSERT INTO staff (user_id) VALUES (?)');
        $staff->bind_param('i', $staffUserId);
        $staff->execute();
        $staffId = (int) $connection->insert_id;

        $code = 'CHAR-IN';
        $name = 'Characterization Check In';
        $encoded = 123;
        $order = 123;
        $service = $connection->prepare('INSERT INTO health_services (service_code,service_name,service_encoded,display_order) VALUES (?,?,?,?)');
        $service->bind_param('ssii', $code, $name, $encoded, $order);
        $service->execute();
        $serviceId = (int) $connection->insert_id;

        $reference = 'BHC-2098-9992';
        $token = bin2hex(random_bytes(32));
        $firstName = 'Characterization';
        $lastName = 'Arrival';
        $clientName = $firstName . ' ' . $lastName;
        $expires = date('Y-m-d 23:59:59');
        $ticket = $connection->prepare("
            INSERT INTO queue_tickets
              (ticket_token,client_first_name,client_last_name,client_name,service_id,
               reference_number,ticket_number,status,lifecycle_status,scheduled_expires_at)
            VALUES (?,?,?,?,?,?,NULL,'waiting','scheduled',?)
        ");
        $ticket->bind_param('ssssiss', $token, $firstName, $lastName, $clientName, $serviceId, $reference, $expires);
        $ticket->execute();
        $ticketId = (int) $connection->insert_id;

        $first = checkInScheduledQueueTicket($connection, $staffId, $ticketId, 'reference');
        $second = checkInScheduledQueueTicket($connection, $staffId, $ticketId, 'qr');

        assertSameValue('checked_in', $first['status']);
        assertSameValue('already_checked_in', $second['status']);
        assertSameValue($first['ticket_number'], $second['ticket_number']);
        $stored = $connection->query('SELECT * FROM queue_tickets WHERE ticket_id=' . $ticketId)->fetch_assoc();
        assertSameValue('waiting', $stored['status']);
        assertSameValue('waiting', $stored['lifecycle_status']);
        assertSameValue('reference', $stored['check_in_method']);
        assertSameValue($staffId, (int) $stored['checked_in_by']);
        assertSameValue(0, (int) $stored['priority_level']);
        assertSameValue(1, (int) $connection->query('SELECT COUNT(*) AS total FROM wait_time_logs WHERE ticket_id=' . $ticketId)->fetch_assoc()['total']);
    });
});

testCase('Staff arrival lookup is exact and returns review details without the private token', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $serviceCode = 'CHAR-LOOKUP';
        $serviceName = 'Characterization Arrival Lookup';
        $encoded = 124;
        $order = 124;
        $service = $connection->prepare('INSERT INTO health_services (service_code,service_name,service_encoded,display_order) VALUES (?,?,?,?)');
        $service->bind_param('ssii', $serviceCode, $serviceName, $encoded, $order);
        $service->execute();
        $serviceId = (int) $connection->insert_id;

        $reference = 'BHC-2098-9993';
        $token = bin2hex(random_bytes(32));
        $firstName = 'Characterization';
        $lastName = 'Lookup Client';
        $clientName = $firstName . ' ' . $lastName;
        $phone = '09123456789';
        $expires = date('Y-m-d 23:59:59');
        $ticket = $connection->prepare("
            INSERT INTO queue_tickets
              (ticket_token,client_first_name,client_last_name,client_name,phone_number,
               service_id,reference_number,status,lifecycle_status,scheduled_expires_at)
            VALUES (?,?,?,?,?,?,?,'waiting','scheduled',?)
        ");
        $ticket->bind_param('sssssiss', $token, $firstName, $lastName, $clientName, $phone, $serviceId, $reference, $expires);
        $ticket->execute();
        $ticketId = (int) $connection->insert_id;

        $byReference = findStaffArrivalTicket($connection, 'reference', strtolower($reference));
        $byToken = findStaffArrivalTicket($connection, 'token', strtoupper($token));
        assertSameValue($ticketId, (int) $byReference['ticket_id']);
        assertSameValue($byReference['ticket_id'], $byToken['ticket_id']);
        assertSameValue($clientName, $byReference['client_name']);
        assertSameValue($phone, $byReference['phone_number']);
        assertFalseValue(array_key_exists('ticket_token', $byReference));
        assertThrowsException(
            fn () => findStaffArrivalTicket($connection, 'reference', 'BHC-2098-DOES-NOT-EXIST'),
            DomainException::class,
            'could not be found'
        );
    });
});

testCase('future Scheduled arrivals expose their visit date and remain unchanged before that date', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $password = password_hash('characterization_password', PASSWORD_BCRYPT);
        $email = 'characterization_future_arrival_staff@example.test';
        $first = 'Characterization';
        $last = 'Future Staff';
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

        $serviceId = (int) $connection->query('SELECT service_id FROM health_services WHERE is_active=1 ORDER BY service_id LIMIT 1')->fetch_assoc()['service_id'];
        $visitDate = date('Y-m-d', strtotime('+1 day'));
        $expires = $visitDate . ' 23:59:59';
        $reference = 'BHC-2098-9994';
        $ticket = $connection->prepare("INSERT INTO queue_tickets (service_id,reference_number,status,lifecycle_status,scheduled_expires_at) VALUES (?,?,'waiting','scheduled',?)");
        $ticket->bind_param('iss', $serviceId, $reference, $expires);
        $ticket->execute();
        $ticketId = (int) $connection->insert_id;

        $projection = staffArrivalTicketById($connection, $ticketId);
        assertSameValue($visitDate, $projection['visit_date']);
        assertFalseValue($projection['is_for_today']);
        assertFalseValue($projection['can_check_in']);
        assertThrowsException(
            fn () => checkInScheduledQueueTicket($connection, $staffId, $ticketId, 'reference'),
            DomainException::class,
            'another visit date'
        );
        $stored = $connection->query('SELECT ticket_number,lifecycle_status,checked_in_at FROM queue_tickets WHERE ticket_id=' . $ticketId)->fetch_assoc();
        assertSameValue('scheduled', $stored['lifecycle_status']);
        assertSameValue(null, $stored['ticket_number']);
        assertSameValue(null, $stored['checked_in_at']);
    });
});

testCase('legacy shared counters surface registered walk-ins in their waiting table KPI and Call Next', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $password = password_hash('characterization_password', PASSWORD_BCRYPT);
        $email = 'characterization_walk_in_staff@example.test';
        $staffFirst = 'Characterization';
        $staffLast = 'Walk-In Staff';
        $role = ROLE_STAFF;
        $verified = 1;
        $user = $connection->prepare('INSERT INTO users (first_name,last_name,email,password_hash,role,is_verified) VALUES (?,?,?,?,?,?)');
        $user->bind_param('sssssi', $staffFirst, $staffLast, $email, $password, $role, $verified);
        $user->execute();
        $staffUserId = (int) $connection->insert_id;
        $staff = $connection->prepare('INSERT INTO staff (user_id) VALUES (?)');
        $staff->bind_param('i', $staffUserId);
        $staff->execute();
        $staffId = (int) $connection->insert_id;

        $code = 'CHAR-WALK';
        $name = 'Characterization Walk-In';
        $encoded = 126;
        $order = 126;
        $service = $connection->prepare('INSERT INTO health_services (service_code,service_name,service_encoded,display_order) VALUES (?,?,?,?)');
        $service->bind_param('ssii', $code, $name, $encoded, $order);
        $service->execute();
        $serviceId = (int) $connection->insert_id;

        $counterNumber = testNextCounterNumber($connection);
        $windowName = 'Characterization Shared Walk-In';
        $windowType = 'shared';
        $windowStatus = 'open';
        $window = $connection->prepare("INSERT INTO service_windows (counter_number,window_name,window_type,service_id,staff_id,status,is_active) VALUES (?,?,?,NULL,?,?,1)");
        $window->bind_param('issis', $counterNumber, $windowName, $windowType, $staffId, $windowStatus);
        $window->execute();
        $windowId = (int) $connection->insert_id;
        $windowRow = $connection->query('SELECT * FROM service_windows WHERE window_id=' . $windowId)->fetch_assoc();

        assertTrueValue(in_array($serviceId, getWindowServiceIds($connection, $windowRow), true));
        $created = createWalkInTicketForStaff($connection, $staffId, 'Waiting', 'Client', '09171234567', $serviceId);
        assertSameValue('waiting', $created['lifecycle_status']);
        assertTrueValue(trim((string) $created['ticket_number']) !== '');

        $waiting = getStaffWindowWaitingTicketsForWindow($connection, $windowRow, 20);
        assertSameValue(1, count($waiting));
        assertSameValue((int) $created['ticket_id'], (int) $waiting[0]['ticket_id']);
        assertSameValue(1, getStaffWindowKpis($connection, $windowRow)['waiting']);

        $next = callNextTicketForStaff($connection, $staffId);
        assertSameValue('success', $next['status']);
        assertSameValue((int) $created['ticket_id'], (int) $next['ticket']['ticket_id']);
    });
});

testCase('walk-ins require a claimed counter and respect explicit service mappings', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $password = password_hash('characterization_password', PASSWORD_BCRYPT);
        $email = 'characterization_scoped_walk_in_staff@example.test';
        $first = 'Characterization';
        $last = 'Scoped Walk-In';
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

        $services = $connection->query('SELECT service_id FROM health_services WHERE is_active=1 ORDER BY service_id LIMIT 2')->fetch_all(MYSQLI_ASSOC);
        $mappedServiceId = (int) $services[0]['service_id'];
        $otherServiceId = (int) $services[1]['service_id'];
        assertThrowsException(
            fn () => createWalkInTicketForStaff($connection, $staffId, 'No', 'Counter', '09171234567', $mappedServiceId),
            DomainException::class,
            'Claim a service counter'
        );

        $counterNumber = testNextCounterNumber($connection);
        $windowName = 'Characterization Scoped Counter';
        $windowType = 'shared';
        $windowStatus = 'open';
        $window = $connection->prepare("INSERT INTO service_windows (counter_number,window_name,window_type,service_id,staff_id,status,is_active) VALUES (?,?,?,NULL,?,?,1)");
        $window->bind_param('issis', $counterNumber, $windowName, $windowType, $staffId, $windowStatus);
        $window->execute();
        $windowId = (int) $connection->insert_id;
        $mapping = $connection->prepare('INSERT INTO counter_services (counter_id,service_id) VALUES (?,?)');
        $mapping->bind_param('ii', $windowId, $mappedServiceId);
        $mapping->execute();

        assertThrowsException(
            fn () => createWalkInTicketForStaff($connection, $staffId, 'Wrong', 'Service', '09171234567', $otherServiceId),
            DomainException::class,
            'assigned to your active counter'
        );
    });
});

testCase('system-wide calling timeout performs one terminal transition and reopens the counter', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $password = password_hash('characterization_password', PASSWORD_BCRYPT);
        $email = 'characterization_timeout_staff@example.test';
        $first = 'Characterization';
        $last = 'Timeout Staff';
        $role = ROLE_STAFF;
        $verified = 1;
        $user = $connection->prepare('INSERT INTO users (first_name,last_name,email,password_hash,role,is_verified) VALUES (?,?,?,?,?,?)');
        $user->bind_param('sssssi', $first, $last, $email, $password, $role, $verified);
        $user->execute();
        $staffUserId = (int) $connection->insert_id;
        $staff = $connection->prepare('INSERT INTO staff (user_id) VALUES (?)');
        $staff->bind_param('i', $staffUserId);
        $staff->execute();
        $staffId = (int) $connection->insert_id;

        $code = 'CHAR-TIMEOUT';
        $name = 'Characterization Timeout';
        $encoded = 125;
        $order = 125;
        $service = $connection->prepare('INSERT INTO health_services (service_code,service_name,service_encoded,display_order) VALUES (?,?,?,?)');
        $service->bind_param('ssii', $code, $name, $encoded, $order);
        $service->execute();
        $serviceId = (int) $connection->insert_id;

        $counterNumber = testNextCounterNumber($connection);
        $windowName = 'Characterization Timeout Window';
        $status = 'busy';
        $window = $connection->prepare('INSERT INTO service_windows (counter_number,window_name,service_id,staff_id,status) VALUES (?,?,?,?,?)');
        $window->bind_param('isiis', $counterNumber, $windowName, $serviceId, $staffId, $status);
        $window->execute();
        $windowId = (int) $connection->insert_id;

        $reference = 'BHC-2098-9994';
        $number = 'C-991';
        $ticket = $connection->prepare("
            INSERT INTO queue_tickets
              (service_id,window_id,reference_number,ticket_number,status,lifecycle_status,
               checked_in_at,called_at)
            VALUES (?,?,?,?, 'serving','calling',DATE_SUB(NOW(), INTERVAL 10 MINUTE),DATE_SUB(NOW(), INTERVAL 10 MINUTE))
        ");
        $ticket->bind_param('iiss', $serviceId, $windowId, $reference, $number);
        $ticket->execute();
        $ticketId = (int) $connection->insert_id;

        $firstRun = voidExpiredCallingTicketsSystemWide($connection, 5);
        $secondRun = voidExpiredCallingTicketsSystemWide($connection, 5);
        assertSameValue(1, $firstRun['voided']);
        assertSameValue(0, $secondRun['voided']);
        $stored = $connection->query('SELECT status,lifecycle_status,voided_reason FROM queue_tickets WHERE ticket_id=' . $ticketId)->fetch_assoc();
        assertSameValue('voided', $stored['status']);
        assertSameValue('void', $stored['lifecycle_status']);
        assertStringContains('five-minute', $stored['voided_reason']);
        $windowStatus = $connection->query('SELECT status FROM service_windows WHERE window_id=' . $windowId)->fetch_assoc()['status'];
        assertSameValue('open', $windowStatus);
        $activityCount = (int) $connection->query("SELECT COUNT(*) AS total FROM activity_logs WHERE ticket_id={$ticketId} AND action='ticket_voided'")->fetch_assoc()['total'];
        assertSameValue(1, $activityCount);
    });
});
