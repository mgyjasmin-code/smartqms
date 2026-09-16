<?php
require_once SMARTQMS_ROOT . '/modules/settings/service_catalog.php';
require_once SMARTQMS_ROOT . '/modules/admin/staff_accounts.php';
require_once SMARTQMS_ROOT . '/modules/admin/window_admin.php';
require_once SMARTQMS_ROOT . '/modules/admin/users.php';

function characterizationAdminUser(mysqli $conn, string $suffix, string $role = ROLE_ADMIN): int {
    $email = testFixturePrefix() . 'admin_' . $suffix . '@example.test';
    $hash = password_hash('characterization-password', PASSWORD_BCRYPT);
    $verified = 1;
    $stmt = $conn->prepare("
        INSERT INTO users (first_name, last_name, email, password_hash, role, is_verified)
        VALUES ('Characterization', 'Administrator', ?, ?, ?, ?)
    ");
    $stmt->bind_param('sssi', $email, $hash, $role, $verified);
    $stmt->execute();
    return (int) $conn->insert_id;
}

function withCharacterizationAdminSession(int $userId, callable $callback): mixed {
    $previous = $_SESSION;
    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = ROLE_ADMIN;
    try {
        return $callback();
    } finally {
        $_SESSION = $previous;
    }
}

testCase('service catalog keeps ML identity system managed and supports queue mode and ordering', function (): void {
    withTestTransaction(function (mysqli $conn): void {
        $adminId = characterizationAdminUser($conn, 'service');
        $expectedCode = nextHealthServiceCode($conn);
        $expectedEncoded = nextHealthServiceEncoded($conn);
        $serviceId = createHealthService(
            $conn,
            'TAMPERED-CODE',
            'Characterization Service',
            99,
            'Original',
            0,
            20,
            $adminId
        );
        assertTrueValue(duplicateHealthServiceCode($conn, $expectedCode, 0));
        assertFalseValue(duplicateHealthServiceCode($conn, $expectedCode, $serviceId));
        assertSameValue($expectedCode, findHealthService($conn, $serviceId)['service_code']);
        assertSameValue($expectedEncoded, (int) findHealthService($conn, $serviceId)['service_encoded']);

        $html = serviceHtmlInput([
            'service_id' => $serviceId,
            'service_code' => 'chr-b602',
            'service_name' => 'HTML Service',
            'service_encoded' => '12',
            'queue_mode' => 'specialized',
            'description' => 'HTML changed',
            'priority_only' => '1',
            'is_active' => '0',
            'display_order' => '21',
        ]);
        updateHealthServiceFromHtml($conn, $html);
        $afterHtml = findHealthService($conn, $serviceId);
        assertSameValue($expectedCode, $afterHtml['service_code']);
        assertSameValue($expectedEncoded, (int) $afterHtml['service_encoded']);
        assertSameValue('specialized', $afterHtml['queue_mode']);
        assertSameValue(0, (int) $afterHtml['is_active']);

        updateHealthServiceFromJson($conn, [
            'service_id' => $serviceId,
            'service_name' => 'JSON Service',
            'queue_mode' => 'central',
            'description' => 'JSON changed',
            'priority_only' => 0,
            'display_order' => 22,
            'service_code' => 'IGNORED',
            'service_encoded' => 99,
            'is_active' => 1,
        ]);
        $afterJson = findHealthService($conn, $serviceId);
        assertSameValue('JSON Service', $afterJson['service_name']);
        assertSameValue($expectedCode, $afterJson['service_code']);
        assertSameValue($expectedEncoded, (int) $afterJson['service_encoded']);
        assertSameValue('central', $afterJson['queue_mode']);
        assertSameValue((int) $afterHtml['display_order'], (int) $afterJson['display_order']);
        assertSameValue(0, (int) $afterJson['is_active']);
        setHealthServiceActive($conn, $serviceId, 1);
        assertSameValue(1, (int) findHealthService($conn, $serviceId)['is_active']);

        $deleted = deleteOrDeactivateHealthService($conn, $serviceId);
        assertFalseValue($deleted['soft_deleted']);
        assertSameValue(null, findHealthService($conn, $serviceId));
    });
});

testCase('service catalog deactivates services that have ticket history', function (): void {
    withTestTransaction(function (mysqli $conn): void {
        $adminId = characterizationAdminUser($conn, 'service_history');
        $clientId = characterizationAdminUser($conn, 'client_history', ROLE_CLIENT);
        $serviceId = createHealthService($conn, 'CHR-B603', 'History Service', 13, '', 0, 23, $adminId);
        $stmt = $conn->prepare("
            INSERT INTO queue_tickets
                (user_id, service_id, reference_number, ticket_number, client_type, priority_level, status)
            VALUES (?, ?, 'characterization_batch6_history', 'B6-001', 'regular', 0, 'completed')
        ");
        $stmt->bind_param('ii', $clientId, $serviceId);
        $stmt->execute();

        $deleted = deleteOrDeactivateHealthService($conn, $serviceId);
        assertTrueValue($deleted['had_ticket_history']);
        assertTrueValue($deleted['soft_deleted']);
        assertSameValue(0, (int) findHealthService($conn, $serviceId)['is_active']);
    });
});

testCase('staff creation retains verified staff role duplicate lookup and transactional rows', function (): void {
    withTestTransaction(function (mysqli $conn): void {
        $adminId = characterizationAdminUser($conn, 'staff');
        withCharacterizationAdminSession($adminId, function () use ($conn, $adminId): void {
            $input = staffAccountInput([
                'first_name' => 'Batch',
                'last_name' => 'Six',
                'email' => 'CHARACTERIZATION_STAFF_B6@EXAMPLE.TEST',
                'job_title' => 'Nurse',
                'phone_number' => '09171234567',
                'password' => 'password123',
            ]);
            $userId = createStaffAccount($conn, $input, $adminId, false);
            assertTrueValue(staffEmailExists($conn, 'characterization_staff_b6@example.test'));
            assertTrueValue(staffPhoneExists($conn, '09171234567'));
            $user = $conn->query("SELECT role, is_verified, phone_number FROM users WHERE user_id={$userId}")->fetch_assoc();
            assertSameValue(ROLE_STAFF, $user['role']);
            assertSameValue(1, (int) $user['is_verified']);
            assertSameValue('09171234567', $user['phone_number']);
            $staff = $conn->query("SELECT added_by FROM staff WHERE user_id={$userId}")->fetch_assoc();
            assertSameValue($adminId, (int) $staff['added_by']);
        });
    });
});

testCase('staff administration updates safely and always deactivates to preserve history', function (): void {
    withTestTransaction(function (mysqli $conn): void {
        $adminId = characterizationAdminUser($conn, 'staff_crud');
        withCharacterizationAdminSession($adminId, function () use ($conn, $adminId): void {
            $input = staffAccountInput([
                'first_name' => 'Characterization',
                'last_name' => 'Editable',
                'email' => 'characterization_staff_editable@example.test',
                'job_title' => 'Doctor',
                'phone_number' => '09181234567',
                'password' => 'password123',
            ]);
            $userId = createStaffAccount($conn, $input, $adminId, false);
            $staff = $conn->query("SELECT staff_id FROM staff WHERE user_id={$userId}")->fetch_assoc();
            $staffId = (int) $staff['staff_id'];
            $beforeHash = (string) $conn->query("SELECT password_hash FROM users WHERE user_id={$userId}")->fetch_assoc()['password_hash'];

            $update = staffAccountInput([
                'staff_id' => (string) $staffId,
                'first_name' => 'Characterization',
                'last_name' => 'Updated',
                'email' => 'characterization_staff_updated@example.test',
                'job_title' => 'Doctor',
                'phone_number' => '',
                'password' => '',
            ]);
            assertSameValue([], validateStaffAccountInput($update, true));
            assertFalseValue(staffEmailExists($conn, $update['email'], $userId));
            assertFalseValue(staffPhoneExists($conn, '09181234567', $userId));
            assertTrueValue(updateStaffAccount($conn, $update, $adminId, false));

            $updated = findStaffAccount($conn, $staffId);
            assertSameValue('Updated', $updated['last_name']);
            assertSameValue('characterization_staff_updated@example.test', $updated['email']);
            assertSameValue(null, $updated['phone_number']);
            $afterHash = (string) $conn->query("SELECT password_hash FROM users WHERE user_id={$userId}")->fetch_assoc()['password_hash'];
            assertSameValue($beforeHash, $afterHash, 'A blank edit password must preserve the current hash.');

            $deactivated = deleteOrDeactivateStaffAccount($conn, $staffId, $adminId, false);
            assertTrueValue($deactivated['soft_deleted']);
            assertSameValue(0, (int) findStaffAccount($conn, $staffId)['is_active']);

            $historyInput = staffAccountInput([
                'first_name' => 'Characterization',
                'last_name' => 'Historical',
                'email' => 'characterization_staff_historical@example.test',
                'job_title' => 'Barangay Health Worker',
                'password' => 'password123',
            ]);
            $historyUserId = createStaffAccount($conn, $historyInput, $adminId, false);
            $historyStaffId = (int) $conn->query("SELECT staff_id FROM staff WHERE user_id={$historyUserId}")->fetch_assoc()['staff_id'];
            $role = ROLE_STAFF;
            $action = 'characterization_staff_history';
            $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, role, action) VALUES (?, ?, ?)");
            $stmt->bind_param('iss', $historyUserId, $role, $action);
            $stmt->execute();

            $softDelete = deleteOrDeactivateStaffAccount($conn, $historyStaffId, $adminId, false);
            assertTrueValue($softDelete['had_history']);
            assertTrueValue($softDelete['soft_deleted']);
            assertSameValue(0, (int) findStaffAccount($conn, $historyStaffId)['is_active']);
        });
    });
});

testCase('window administration configures shared and specialized routing without runtime ownership', function (): void {
    withTestTransaction(function (mysqli $conn): void {
        $adminId = characterizationAdminUser($conn, 'window');
        $serviceId = createHealthService($conn, 'CHR-B604', 'Window Service', 14, '', 0, 24, $adminId, 1, 'specialized');

        withCharacterizationAdminSession($adminId, function () use ($conn, $serviceId): void {
            $input = windowAdminInput([
                'counter_number' => (string) nextCounterNumber($conn),
                'window_name' => 'Characterization B6',
                'location_description' => 'North wing',
                'window_type' => 'shared',
                'service_ids' => [(string) $serviceId],
                'is_active' => '1',
            ]);
            assertSameValue([], validateWindowAdminRouting($conn, $input));
            assertFalseValue(saveAdminWindow($conn, $input));
            $windowId = (int) $conn->query("
                SELECT window_id
                FROM service_windows
                WHERE window_name='Characterization B6'
                ORDER BY window_id DESC
                LIMIT 1
            ")->fetch_assoc()['window_id'];
            $window = findAdminWindow(listAdminWindows($conn), $windowId);
            assertSameValue(null, $window['service_id']);
            assertSameValue(null, $window['staff_id']);
            assertSameValue('closed', $window['status']);
            assertSameValue('Window Service', $window['queue_handled']);

            $input['window_id'] = (string) $windowId;
            $input['window_type'] = 'specialized';
            $input['service_ids'] = [(string) $serviceId];
            $input['is_active'] = '0';
            assertSameValue([], validateWindowAdminRouting($conn, $input));
            assertTrueValue(saveAdminWindow($conn, $input));
            $window = findAdminWindow(listAdminWindows($conn), $windowId);
            assertSameValue($serviceId, (int) $window['service_id']);
            assertSameValue('specialized', $window['window_type']);
            assertSameValue('Window Service', $window['queue_handled']);
            assertSameValue(0, (int) $window['is_active']);
            assertSameValue(null, $window['staff_id']);
            assertSameValue('closed', $window['status']);
            assertTrueValue(count(listWindowServiceAssignments($conn)) > 0);
        });
    });
});

testCase('user activation prevents self-deactivation', function (): void {
    withTestTransaction(function (mysqli $conn): void {
        $adminId = characterizationAdminUser($conn, 'activation');
        $otherId = characterizationAdminUser($conn, 'activation_other', ROLE_CLIENT);
        withCharacterizationAdminSession($adminId, function () use ($conn, $adminId, $otherId): void {
            assertFalseValue(setAdminUserActive($conn, $adminId, 0, $adminId));
            assertSameValue(1, (int) $conn->query("SELECT is_active FROM users WHERE user_id={$adminId}")->fetch_assoc()['is_active']);
            assertTrueValue(setAdminUserActive($conn, $otherId, 0, $adminId));
            assertSameValue(0, (int) $conn->query("SELECT is_active FROM users WHERE user_id={$otherId}")->fetch_assoc()['is_active']);

        });
    });
});
