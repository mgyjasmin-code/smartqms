<?php

function hybridQueueCreateUser(mysqli $conn, string $label, string $role): int {
    $first = 'Characterization';
    $last = ucfirst($label);
    $email = 'characterization_hybrid_' . $label . '@example.test';
    $password = password_hash('characterization_password', PASSWORD_BCRYPT);
    $verified = 1;
    $stmt = $conn->prepare("
        INSERT INTO users (first_name, last_name, email, password_hash, role, is_verified)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('sssssi', $first, $last, $email, $password, $role, $verified);
    $stmt->execute();
    return (int) $conn->insert_id;
}

function hybridQueueCreateService(
    mysqli $conn,
    string $code,
    string $name,
    string $queueMode,
    int $encoded
): int {
    $stmt = $conn->prepare("
        INSERT INTO health_services
          (service_code, service_name, service_encoded, queue_mode, display_order)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('ssisi', $code, $name, $encoded, $queueMode, $encoded);
    $stmt->execute();
    return (int) $conn->insert_id;
}

function hybridQueueCreateStaff(mysqli $conn, int $adminId, string $label): array {
    $userId = hybridQueueCreateUser($conn, $label, ROLE_STAFF);
    $stmt = $conn->prepare('INSERT INTO staff (user_id, added_by) VALUES (?, ?)');
    $stmt->bind_param('ii', $userId, $adminId);
    $stmt->execute();
    return ['user_id' => $userId, 'staff_id' => (int) $conn->insert_id];
}

function hybridQueueCreateWindow(
    mysqli $conn,
    string $name,
    string $type,
    ?int $serviceId,
    int $staffId
): int {
    $status = 'open';
    $counterNumber = testNextCounterNumber($conn);
    $stmt = $conn->prepare("
        INSERT INTO service_windows
          (counter_number, window_name, window_type, service_id, staff_id, status, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->bind_param('issiis', $counterNumber, $name, $type, $serviceId, $staffId, $status);
    $stmt->execute();
    return (int) $conn->insert_id;
}

function hybridQueueMapService(mysqli $conn, int $windowId, int $serviceId): void {
    $stmt = $conn->prepare('INSERT INTO counter_services (counter_id, service_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $windowId, $serviceId);
    $stmt->execute();
}

testCase('hybrid routing normalizers and operating-hour boundaries are stable', function (): void {
    assertSameValue('central', normalizeQueueMode(null));
    assertSameValue('central', normalizeQueueMode('unexpected'));
    assertSameValue('specialized', normalizeQueueMode('specialized'));
    assertSameValue('shared', normalizeWindowType('unexpected'));
    assertSameValue('specialized', normalizeWindowType('specialized'));

    $hours = ['open' => '07:00', 'close' => '17:00'];
    assertFalseValue(queueIsOpenAt($hours, new DateTimeImmutable('2037-01-01 06:59:00')));
    assertTrueValue(queueIsOpenAt($hours, new DateTimeImmutable('2037-01-01 07:00:00')));
    assertTrueValue(queueIsOpenAt($hours, new DateTimeImmutable('2037-01-01 16:59:00')));
    assertFalseValue(queueIsOpenAt($hours, new DateTimeImmutable('2037-01-01 17:00:00')));
    assertTrueValue(queueIsOpenAt(
        ['open' => '22:00', 'close' => '06:00'],
        new DateTimeImmutable('2037-01-01 23:00:00')
    ));
});

testCase('counter mappings are authoritative while legacy unmapped shared counters serve all active services', function (): void {
    withTestTransaction(function (mysqli $conn): void {
        $adminId = hybridQueueCreateUser($conn, 'admin', ROLE_ADMIN);
        $sharedStaff = hybridQueueCreateStaff($conn, $adminId, 'shared_staff');
        $specialist = hybridQueueCreateStaff($conn, $adminId, 'specialist');
        $centralOne = hybridQueueCreateService($conn, 'CH-C01', 'Hybrid Central One', 'central', 101);
        $centralTwo = hybridQueueCreateService($conn, 'CH-C02', 'Hybrid Central Two', 'central', 102);
        $specialized = hybridQueueCreateService($conn, 'CH-S01', 'Hybrid Specialized', 'specialized', 103);

        $sharedWindow = hybridQueueCreateWindow($conn, 'Characterization Shared', 'shared', null, $sharedStaff['staff_id']);
        $specializedWindow = hybridQueueCreateWindow(
            $conn,
            'Characterization Specialized',
            'specialized',
            $specialized,
            $specialist['staff_id']
        );

        $centralServiceOne = findServiceForQueueing($conn, $centralOne);
        $centralServiceTwo = findServiceForQueueing($conn, $centralTwo);
        $specializedService = findServiceForQueueing($conn, $specialized);
        assertSameValue(1, countApplicableActiveWindows($conn, $centralServiceOne));
        assertSameValue(1, countApplicableActiveWindows($conn, $centralServiceTwo));
        assertSameValue(2, countApplicableActiveWindows($conn, $specializedService));

        hybridQueueMapService($conn, $sharedWindow, $centralOne);
        hybridQueueMapService($conn, $specializedWindow, $specialized);

        assertSameValue(1, countApplicableActiveWindows($conn, $centralServiceOne));
        assertSameValue(0, countApplicableActiveWindows($conn, $centralServiceTwo));
        hybridQueueMapService($conn, $sharedWindow, $centralTwo);
        assertSameValue(1, countApplicableActiveWindows($conn, $centralServiceTwo));
        assertSameValue(1, countApplicableActiveWindows($conn, $specializedService));

        $noon = new DateTimeImmutable(date('Y-m-d') . ' 12:00:00');
        assertTrueValue(serviceJoinAvailability($conn, $centralServiceOne, $noon)['available']);
        assertTrueValue(serviceJoinAvailability($conn, $specializedService, $noon)['available']);

        $conn->query("UPDATE service_windows SET status='closed', staff_id=NULL WHERE window_id={$specializedWindow}");
        $availability = serviceJoinAvailability($conn, $specializedService, $noon);
        assertFalseValue($availability['available']);
        assertStringContains('qualified specialized window', (string) $availability['reason']);
    });
});

testCase('FIFO people-ahead spans compatible tickets while prediction queue length remains service scoped', function (): void {
    withTestTransaction(function (mysqli $conn): void {
        $adminId = hybridQueueCreateUser($conn, 'scope_admin', ROLE_ADMIN);
        $owner = hybridQueueCreateUser($conn, 'scope_owner', ROLE_CLIENT);
        $ahead = hybridQueueCreateUser($conn, 'scope_ahead', ROLE_CLIENT);
        $isolated = hybridQueueCreateUser($conn, 'scope_isolated', ROLE_CLIENT);
        $centralOne = hybridQueueCreateService($conn, 'CH-P01', 'Pool One', 'central', 111);
        $centralTwo = hybridQueueCreateService($conn, 'CH-P02', 'Pool Two', 'central', 112);
        $specialized = hybridQueueCreateService($conn, 'CH-P03', 'Pool Specialized', 'specialized', 113);
        $staff = hybridQueueCreateStaff($conn, $adminId, 'scope_staff');
        hybridQueueCreateWindow($conn, 'Characterization Pool', 'shared', null, $staff['staff_id']);

        characterizationInsertTicket($conn, $ahead, $centralTwo, '9811', 'regular', 0, 'waiting', '2037-01-01 08:00:00');
        $targetId = characterizationInsertTicket($conn, $owner, $centralOne, '9812', 'regular', 0, 'waiting', '2037-01-01 09:00:00');
        $specializedId = characterizationInsertTicket($conn, $isolated, $specialized, '9813', 'senior', 1, 'waiting', '2037-01-01 07:00:00');
        $conn->query("UPDATE queue_tickets SET queue_mode='specialized' WHERE ticket_id={$specializedId}");

        $ticket = $conn->query("SELECT * FROM queue_tickets WHERE ticket_id={$targetId}")->fetch_assoc();
        assertSameValue(1, peopleAhead($conn, $ticket));
        assertSameValue(1, queueLengthForService($conn, findServiceForQueueing($conn, $centralOne)));
        assertSameValue(1, queueLengthForService($conn, findServiceForQueueing($conn, $specialized)));
    });
});
