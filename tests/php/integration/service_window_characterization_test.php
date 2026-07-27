<?php

require_once SMARTQMS_ROOT . '/modules/service_window/ticket_actions.php';

testCase('Dashboard and My Window retain one shared SQL-free context adapter', function (): void {
    $dashboard = file_get_contents(SMARTQMS_ROOT . '/views/staff/dashboard.php');
    $window = file_get_contents(SMARTQMS_ROOT . '/views/staff/window.php');
    $context = file_get_contents(SMARTQMS_ROOT . '/views/staff/includes/context.php');

    assertStringContains("require_once __DIR__ . '/includes/context.php'", $dashboard);
    assertStringContains("require_once __DIR__ . '/includes/context.php'", $window);
    assertStringContains('getStaffWindowCurrentTicket', $context);
    assertStringContains('getStaffWindowWaitingTickets', $context);
    assertFalseValue((bool) preg_match('/\\b(SELECT|UPDATE|INSERT|DELETE)\\b/i', $context));
});

function characterizationServiceWindowFixtures(
    mysqli $conn,
    string $windowStatus = 'open',
    bool $createWindow = true,
    bool $assignService = true
): array {
    $passwordHash = password_hash('characterization_password', PASSWORD_BCRYPT);
    $staffEmail = testFixturePrefix() . 'window_staff@example.test';
    $firstName = 'Characterization';
    $lastName = 'Window Staff';
    $role = ROLE_STAFF;
    $verified = 1;
    $stmt = $conn->prepare("
        INSERT INTO users (first_name, last_name, email, password_hash, role, is_verified)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('sssssi', $firstName, $lastName, $staffEmail, $passwordHash, $role, $verified);
    $stmt->execute();
    $staffUserId = (int) $conn->insert_id;

    $department = 'Characterization';
    $stmt = $conn->prepare('INSERT INTO staff (user_id, department) VALUES (?, ?)');
    $stmt->bind_param('is', $staffUserId, $department);
    $stmt->execute();
    $staffId = (int) $conn->insert_id;

    $users = [];
    foreach (['regular', 'senior', 'pwd'] as $clientType) {
        $email = testFixturePrefix() . 'window_' . $clientType . '@example.test';
        $clientLastName = ucfirst($clientType);
        $clientRole = ROLE_CLIENT;
        $stmt = $conn->prepare("
            INSERT INTO users (first_name, last_name, email, password_hash, role, is_verified)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('sssssi', $firstName, $clientLastName, $email, $passwordHash, $clientRole, $verified);
        $stmt->execute();
        $users[$clientType] = (int) $conn->insert_id;
    }

    $serviceCode = 'CHAR-W';
    $serviceName = 'Characterization Window Service';
    $serviceEncoded = 119;
    $displayOrder = 119;
    $stmt = $conn->prepare("
        INSERT INTO health_services
          (service_code, service_name, service_encoded, display_order)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param('ssii', $serviceCode, $serviceName, $serviceEncoded, $displayOrder);
    $stmt->execute();
    $serviceId = (int) $conn->insert_id;

    $windowId = 0;
    if ($createWindow) {
        $windowName = 'Characterization Window';
        $assignedServiceId = $assignService ? $serviceId : null;
        $stmt = $conn->prepare("
            INSERT INTO service_windows (window_name, service_id, staff_id, status)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param('siis', $windowName, $assignedServiceId, $staffId, $windowStatus);
        $stmt->execute();
        $windowId = (int) $conn->insert_id;
    }

    return [
        'staff_user_id' => $staffUserId,
        'staff_id' => $staffId,
        'users' => $users,
        'service_id' => $serviceId,
        'window_id' => $windowId,
    ];
}

function characterizationWindowTicket(
    mysqli $conn,
    int $userId,
    int $serviceId,
    string $suffix,
    string $clientType,
    int $priority,
    string $status = 'waiting',
    int $windowId = 0,
    string $issuedAt = '2038-01-01 08:00:00'
): int {
    $reference = 'BHC-2098-' . $suffix;
    $ticketNumber = 'W-' . $suffix;
    $nullableWindowId = $windowId > 0 ? $windowId : null;
    $stmt = $conn->prepare("
        INSERT INTO queue_tickets
          (user_id, window_id, service_id, reference_number, ticket_number,
           client_type, priority_level, status, issued_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'iiisssiss',
        $userId,
        $nullableWindowId,
        $serviceId,
        $reference,
        $ticketNumber,
        $clientType,
        $priority,
        $status,
        $issuedAt
    );
    $stmt->execute();
    return (int) $conn->insert_id;
}

function withCharacterizationStaffSession(array $fixtures, callable $callback): mixed {
    $originalSession = $_SESSION;
    try {
        $_SESSION['user_id'] = (int) $fixtures['staff_user_id'];
        $_SESSION['staff_id'] = (int) $fixtures['staff_id'];
        $_SESSION['role'] = ROLE_STAFF;
        return $callback();
    } finally {
        $_SESSION = $originalSession;
    }
}

testCase('staff lookup retains no-profile behavior', function (): void {
    withTestTransaction(function (mysqli $conn): void {
        $passwordHash = password_hash('characterization_password', PASSWORD_BCRYPT);
        $firstName = 'Characterization';
        $lastName = 'No Profile';
        $email = testFixturePrefix() . 'staff_without_profile@example.test';
        $role = ROLE_STAFF;
        $verified = 1;
        $stmt = $conn->prepare("
            INSERT INTO users (first_name, last_name, email, password_hash, role, is_verified)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('sssssi', $firstName, $lastName, $email, $passwordHash, $role, $verified);
        $stmt->execute();

        $originalSession = $_SESSION;
        try {
            $_SESSION = [
                'user_id' => (int) $conn->insert_id,
                'role' => ROLE_STAFF,
            ];
            assertSameValue(null, getCurrentStaffId($conn));
        } finally {
            $_SESSION = $originalSession;
        }
    });
});

testCase('Call Next retains no-window closed unassigned and empty-queue outcomes', function (): void {
    withTestTransaction(function (mysqli $conn): void {
        $fixtures = characterizationServiceWindowFixtures($conn, 'open', false);
        $result = withCharacterizationStaffSession(
            $fixtures,
            static fn(): array => callNextTicketForStaff($conn, (int) $fixtures['staff_id'])
        );
        assertSameValue('no_window', $result['status']);
    });

    withTestTransaction(function (mysqli $conn): void {
        $fixtures = characterizationServiceWindowFixtures($conn, 'closed');
        $result = withCharacterizationStaffSession(
            $fixtures,
            static fn(): array => callNextTicketForStaff($conn, (int) $fixtures['staff_id'])
        );
        assertSameValue('closed_window', $result['status']);
        assertSameValue('closed', getStaffWindow($conn, (int) $fixtures['staff_id'])['status']);
    });

    withTestTransaction(function (mysqli $conn): void {
        $fixtures = characterizationServiceWindowFixtures($conn, 'open', true, false);
        $result = withCharacterizationStaffSession(
            $fixtures,
            static fn(): array => callNextTicketForStaff($conn, (int) $fixtures['staff_id'])
        );
        assertSameValue('unassigned_service', $result['status']);
        assertSameValue('open', getStaffWindow($conn, (int) $fixtures['staff_id'])['status']);
    });

    withTestTransaction(function (mysqli $conn): void {
        $fixtures = characterizationServiceWindowFixtures($conn);
        $result = withCharacterizationStaffSession(
            $fixtures,
            static fn(): array => callNextTicketForStaff($conn, (int) $fixtures['staff_id'])
        );
        assertSameValue('empty_queue', $result['status']);
        assertSameValue('open', getStaffWindow($conn, (int) $fixtures['staff_id'])['status']);
    });
});

testCase('Call Next retains priority FIFO and prevents duplicate serving tickets', function (): void {
    withTestTransaction(function (mysqli $conn): void {
        $fixtures = characterizationServiceWindowFixtures($conn);
        characterizationWindowTicket(
            $conn,
            $fixtures['users']['regular'],
            $fixtures['service_id'],
            '9701',
            'regular',
            0,
            'waiting',
            0,
            '2038-01-01 08:00:00'
        );
        $seniorId = characterizationWindowTicket(
            $conn,
            $fixtures['users']['senior'],
            $fixtures['service_id'],
            '9702',
            'senior',
            1,
            'waiting',
            0,
            '2038-01-01 09:00:00'
        );

        withCharacterizationStaffSession($fixtures, function () use ($conn, $fixtures, $seniorId): void {
            $called = callNextTicketForStaff($conn, (int) $fixtures['staff_id']);
            assertSameValue('success', $called['status']);
            assertSameValue($seniorId, (int) $called['ticket']['ticket_id']);

            $ticket = $conn->query("SELECT * FROM queue_tickets WHERE ticket_id={$seniorId}")->fetch_assoc();
            assertSameValue('serving', $ticket['status']);
            assertSameValue((int) $fixtures['window_id'], (int) $ticket['window_id']);
            assertTrueValue(!empty($ticket['called_at']));
            assertTrueValue(!empty($ticket['served_at']));
            assertSameValue('busy', getStaffWindow($conn, (int) $fixtures['staff_id'])['status']);

            $duplicate = callNextTicketForStaff($conn, (int) $fixtures['staff_id']);
            assertSameValue('already_serving', $duplicate['status']);
            $servingCount = (int) $conn->query("
                SELECT COUNT(*) AS cnt
                FROM queue_tickets
                WHERE window_id=" . (int) $fixtures['window_id'] . " AND status='serving'
            ")->fetch_assoc()['cnt'];
            assertSameValue(1, $servingCount);

            $activity = $conn->query("
                SELECT action, details
                FROM activity_logs
                WHERE ticket_id={$seniorId}
                LIMIT 1
            ")->fetch_assoc();
            assertSameValue('ticket_called', $activity['action']);
            assertSameValue('Called ticket W-9702', $activity['details']);
        });
    });
});

testCase('manual window status changes retain activity messages and locked state updates', function (): void {
    withTestTransaction(function (mysqli $conn): void {
        $fixtures = characterizationServiceWindowFixtures($conn);
        withCharacterizationStaffSession($fixtures, function () use ($conn, $fixtures): void {
            $busy = setServiceWindowStatusForStaff($conn, (int) $fixtures['staff_id'], 'busy');
            assertSameValue('success', $busy['status']);
            assertSameValue('busy', getStaffWindow($conn, (int) $fixtures['staff_id'])['status']);

            $closed = setServiceWindowStatusForStaff($conn, (int) $fixtures['staff_id'], 'closed');
            assertSameValue('success', $closed['status']);
            assertSameValue('closed', getStaffWindow($conn, (int) $fixtures['staff_id'])['status']);

            $activities = $conn->query("
                SELECT action, details
                FROM activity_logs
                WHERE user_id=" . (int) $fixtures['staff_user_id'] . "
                ORDER BY log_id
            ")->fetch_all(MYSQLI_ASSOC);
            assertSameValue('window_opened', $activities[0]['action']);
            assertSameValue('Window set to busy', $activities[0]['details']);
            assertSameValue('window_closed', $activities[1]['action']);
            assertSameValue('Window set to closed', $activities[1]['details']);
        });
    });
});

testCase('Complete retains metrics feedback notification and window reopening', function (): void {
    withTestTransaction(function (mysqli $conn): void {
        $fixtures = characterizationServiceWindowFixtures($conn);
        $ticketId = characterizationWindowTicket(
            $conn,
            $fixtures['users']['regular'],
            $fixtures['service_id'],
            '9711',
            'regular',
            0,
            'waiting',
            0,
            date('Y-m-d H:i:s', time() - 600)
        );
        $stmt = $conn->prepare('INSERT INTO wait_time_logs (ticket_id, predicted_wait_min) VALUES (?, 5)');
        $stmt->bind_param('i', $ticketId);
        $stmt->execute();

        withCharacterizationStaffSession($fixtures, function () use ($conn, $fixtures, $ticketId): void {
            assertSameValue('success', callNextTicketForStaff($conn, (int) $fixtures['staff_id'])['status']);
            $result = completeTicketForStaff($conn, (int) $fixtures['staff_id'], $ticketId);
            assertSameValue('success', $result['status']);

            $ticket = $conn->query("SELECT * FROM queue_tickets WHERE ticket_id={$ticketId}")->fetch_assoc();
            assertSameValue('completed', $ticket['status']);
            assertTrueValue(!empty($ticket['completed_at']));
            assertSameValue('open', getStaffWindow($conn, (int) $fixtures['staff_id'])['status']);

            $log = $conn->query("SELECT * FROM wait_time_logs WHERE ticket_id={$ticketId}")->fetch_assoc();
            assertTrueValue((float) $log['actual_wait_min'] >= 9);
            assertTrueValue((int) $log['actual_service_dur'] >= 0);
            assertSameValue((int) $fixtures['staff_id'], (int) $log['staff_id']);

            $notification = $conn->query("
                SELECT type, channel, delivery_status, is_read
                FROM notifications
                WHERE ticket_id={$ticketId}
                LIMIT 1
            ")->fetch_assoc();
            assertSameValue('feedback_prompt', $notification['type']);
            assertSameValue('browser', $notification['channel']);
            assertSameValue('pending', $notification['delivery_status']);
            assertSameValue(0, (int) $notification['is_read']);

            $losingSkip = skipTicketForStaff($conn, (int) $fixtures['staff_id'], $ticketId);
            assertSameValue('ticket_not_found', $losingSkip['status']);
            assertSameValue('completed', $conn->query("SELECT status FROM queue_tickets WHERE ticket_id={$ticketId}")->fetch_assoc()['status']);
        });
    });
});

testCase('Skip retains reason timestamp activity and prevents later completion', function (): void {
    withTestTransaction(function (mysqli $conn): void {
        $fixtures = characterizationServiceWindowFixtures($conn);
        $ticketId = characterizationWindowTicket(
            $conn,
            $fixtures['users']['pwd'],
            $fixtures['service_id'],
            '9721',
            'pwd',
            1
        );

        withCharacterizationStaffSession($fixtures, function () use ($conn, $fixtures, $ticketId): void {
            assertSameValue('success', callNextTicketForStaff($conn, (int) $fixtures['staff_id'])['status']);
            $result = skipTicketForStaff($conn, (int) $fixtures['staff_id'], $ticketId);
            assertSameValue('success', $result['status']);

            $ticket = $conn->query("SELECT * FROM queue_tickets WHERE ticket_id={$ticketId}")->fetch_assoc();
            assertSameValue('skipped', $ticket['status']);
            assertSameValue('Client did not appear', $ticket['voided_reason']);
            assertTrueValue(!empty($ticket['voided_at']));
            assertSameValue('open', getStaffWindow($conn, (int) $fixtures['staff_id'])['status']);

            $losingComplete = completeTicketForStaff($conn, (int) $fixtures['staff_id'], $ticketId);
            assertSameValue('ticket_not_found', $losingComplete['status']);
            assertSameValue('skipped', $conn->query("SELECT status FROM queue_tickets WHERE ticket_id={$ticketId}")->fetch_assoc()['status']);
        });
    });
});

testCase('Manual staff void is atomic, scoped to the serving window, and notifies the client once', function (): void {
    withTestTransaction(function (mysqli $conn): void {
        $fixtures = characterizationServiceWindowFixtures($conn);
        $ticketId = characterizationWindowTicket(
            $conn,
            $fixtures['users']['regular'],
            $fixtures['service_id'],
            '9722',
            'regular',
            0
        );

        withCharacterizationStaffSession($fixtures, function () use ($conn, $fixtures, $ticketId): void {
            assertSameValue('success', callNextTicketForStaff($conn, (int) $fixtures['staff_id'])['status']);

            $result = voidTicketForStaff($conn, (int) $fixtures['staff_id'], $ticketId);
            assertSameValue('success', $result['status']);
            assertSameValue($ticketId, (int) $result['ticket_id']);
            assertSameValue('W-9722', $result['ticket_number']);

            $ticket = $conn->query("SELECT * FROM queue_tickets WHERE ticket_id={$ticketId}")->fetch_assoc();
            assertSameValue('voided', $ticket['status']);
            assertSameValue('Voided manually by staff', $ticket['voided_reason']);
            assertTrueValue(!empty($ticket['voided_at']));
            assertSameValue('open', getStaffWindow($conn, (int) $fixtures['staff_id'])['status']);

            $notification = $conn->query("
                SELECT message, type, channel, delivery_status, is_read
                FROM notifications
                WHERE ticket_id={$ticketId}
                ORDER BY notif_id DESC
                LIMIT 1
            ")->fetch_assoc();
            assertSameValue('turn_void', $notification['type']);
            assertSameValue('browser', $notification['channel']);
            assertSameValue('pending', $notification['delivery_status']);
            assertSameValue(0, (int) $notification['is_read']);
            assertTrueValue(str_contains($notification['message'], 'W-9722 was voided by staff'));

            $activity = $conn->query("
                SELECT action, details, user_id, role
                FROM activity_logs
                WHERE ticket_id={$ticketId} AND action='ticket_voided'
                ORDER BY log_id DESC
                LIMIT 1
            ")->fetch_assoc();
            assertSameValue('ticket_voided', $activity['action']);
            assertSameValue('Voided ticket W-9722 manually', $activity['details']);
            assertSameValue((int) $fixtures['staff_user_id'], (int) $activity['user_id']);
            assertSameValue(ROLE_STAFF, $activity['role']);

            $losingComplete = completeTicketForStaff($conn, (int) $fixtures['staff_id'], $ticketId);
            $losingSkip = skipTicketForStaff($conn, (int) $fixtures['staff_id'], $ticketId);
            $duplicateVoid = voidTicketForStaff($conn, (int) $fixtures['staff_id'], $ticketId);
            assertSameValue('ticket_not_found', $losingComplete['status']);
            assertSameValue('ticket_not_found', $losingSkip['status']);
            assertSameValue('ticket_not_found', $duplicateVoid['status']);
            assertSameValue(
                1,
                (int) $conn->query("SELECT COUNT(*) AS total FROM notifications WHERE ticket_id={$ticketId} AND type='turn_void'")
                    ->fetch_assoc()['total']
            );
            assertSameValue(
                1,
                (int) $conn->query("SELECT COUNT(*) AS total FROM activity_logs WHERE ticket_id={$ticketId} AND action='ticket_voided'")
                    ->fetch_assoc()['total']
            );

            $foreignWindowName = 'Characterization Foreign Window';
            $foreignStatus = 'busy';
            $foreignStaffId = null;
            $foreignServiceId = (int) $fixtures['service_id'];
            $foreignWindow = $conn->prepare("
                INSERT INTO service_windows (window_name, service_id, staff_id, status)
                VALUES (?, ?, ?, ?)
            ");
            $foreignWindow->bind_param(
                'siis',
                $foreignWindowName,
                $foreignServiceId,
                $foreignStaffId,
                $foreignStatus
            );
            $foreignWindow->execute();
            $foreignWindowId = (int) $conn->insert_id;
            $foreignTicketId = characterizationWindowTicket(
                $conn,
                $fixtures['users']['senior'],
                $fixtures['service_id'],
                '9723',
                'senior',
                1,
                'serving',
                $foreignWindowId
            );
            $foreignResult = voidTicketForStaff($conn, (int) $fixtures['staff_id'], $foreignTicketId);
            assertSameValue('ticket_not_found', $foreignResult['status']);
            assertSameValue(
                'serving',
                $conn->query("SELECT status FROM queue_tickets WHERE ticket_id={$foreignTicketId}")
                    ->fetch_assoc()['status']
            );
            assertSameValue(
                0,
                (int) $conn->query("SELECT COUNT(*) AS total FROM notifications WHERE ticket_id={$foreignTicketId}")
                    ->fetch_assoc()['total']
            );
        });
    });
});

testCase('automatic void retains timeout notification activity and race protection', function (): void {
    withTestTransaction(function (mysqli $conn): void {
        $fixtures = characterizationServiceWindowFixtures($conn);
        $ticketId = characterizationWindowTicket(
            $conn,
            $fixtures['users']['senior'],
            $fixtures['service_id'],
            '9731',
            'senior',
            1
        );

        withCharacterizationStaffSession($fixtures, function () use ($conn, $fixtures, $ticketId): void {
            assertSameValue('success', callNextTicketForStaff($conn, (int) $fixtures['staff_id'])['status']);
            $conn->query("UPDATE queue_tickets SET called_at=DATE_SUB(NOW(), INTERVAL 15 MINUTE) WHERE ticket_id={$ticketId}");
            $result = voidExpiredTicketsForStaff($conn, (int) $fixtures['staff_id'], 10);
            assertSameValue('success', $result['status']);
            assertSameValue(1, $result['voided']);

            $ticket = $conn->query("SELECT * FROM queue_tickets WHERE ticket_id={$ticketId}")->fetch_assoc();
            assertSameValue('voided', $ticket['status']);
            assertSameValue('Client did not appear within timeout', $ticket['voided_reason']);
            assertTrueValue(!empty($ticket['voided_at']));
            assertSameValue('open', getStaffWindow($conn, (int) $fixtures['staff_id'])['status']);

            $notification = $conn->query("SELECT type FROM notifications WHERE ticket_id={$ticketId} LIMIT 1")->fetch_assoc();
            assertSameValue('turn_void', $notification['type']);

            $losingComplete = completeTicketForStaff($conn, (int) $fixtures['staff_id'], $ticketId);
            assertSameValue('ticket_not_found', $losingComplete['status']);
            assertSameValue('voided', $conn->query("SELECT status FROM queue_tickets WHERE ticket_id={$ticketId}")->fetch_assoc()['status']);
        });
    });
});

testCase('staff context queries retain twenty-ticket priority order labels and timeout math', function (): void {
    withTestTransaction(function (mysqli $conn): void {
        $fixtures = characterizationServiceWindowFixtures($conn);
        for ($index = 1; $index <= 21; $index++) {
            $isPriority = $index === 21;
            characterizationWindowTicket(
                $conn,
                $isPriority ? $fixtures['users']['senior'] : $fixtures['users']['regular'],
                $fixtures['service_id'],
                (string) (9800 + $index),
                $isPriority ? 'senior' : 'regular',
                $isPriority ? 1 : 0,
                'waiting',
                0,
                date('Y-m-d H:i:s', strtotime('2038-01-01 08:00:00') + $index)
            );
        }

        $waiting = getStaffWindowWaitingTickets($conn, (int) $fixtures['service_id'], 50);
        assertSameValue(20, count($waiting));
        assertSameValue('senior', $waiting[0]['client_type']);
        assertSameValue('Senior Citizen', staffClientTypeLabel('senior'));
        assertSameValue('PWD', staffClientTypeLabel('pwd'));
        assertSameValue('Regular', staffClientTypeLabel('regular'));

        $calledAt = date('Y-m-d H:i:s', 1000);
        assertSameValue(300, staffVoidRemainingSeconds($calledAt, 10, 1300));
        assertSameValue(0, staffVoidRemainingSeconds($calledAt, 10, 1700));
        assertSameValue(null, staffVoidRemainingSeconds(null, 10, 1300));
    });
});
