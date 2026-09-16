<?php
/**
 * Staff-account validation, persistence, and administration reads.
 */

require_once __DIR__ . '/../settings/staff_capabilities_mgmt.php';

function staffAccountDefaults(): array {
    return [
        'staff_id' => '0',
        'username' => '',
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'phone_number' => '',
        'job_title' => '',
        'job_title_selection' => '',
        'job_title_custom' => '',
        'password' => '',
        'is_active' => '1',
        'capability_ids' => [],
    ];
}

function staffAccountInput(array $source): array {
    $email = normalizeEmail((string) ($source['email'] ?? ''));
    $username = trim((string) ($source['username'] ?? ''));
    if ($username === '') {
        // Older callers identify staff by email and do not submit a username.
        // Treat that established identifier as the username compatibility value.
        $username = $email;
    }
    $jobTitle = trim((string) ($source['job_title'] ?? ''));
    $customJobTitle = trim((string) ($source['job_title_custom'] ?? ''));
    if ($jobTitle === 'Other') {
        $jobTitle = $customJobTitle;
    }
    return [
        'staff_id' => (string) (int) ($source['staff_id'] ?? 0),
        'username' => $username,
        'first_name' => trim((string) ($source['first_name'] ?? '')),
        'last_name' => trim((string) ($source['last_name'] ?? '')),
        'email' => $email,
        'phone_number' => normalizePhone((string) ($source['phone_number'] ?? '')),
        'job_title' => $jobTitle,
        'job_title_selection' => trim((string) ($source['job_title'] ?? '')),
        'job_title_custom' => $customJobTitle,
        'password' => (string) ($source['password'] ?? ''),
        'is_active' => (string) ((int) ($source['is_active'] ?? 1) === 1 ? 1 : 0),
        'capability_ids' => normalizeCapabilityIds((array) ($source['capability_ids'] ?? [])),
    ];
}

function staffAccountRowToForm(array $staff): array {
    $standardTitles = ['Midwife', 'Nurse', 'Doctor', 'Barangay Health Worker'];
    $storedJobTitle = trim((string) ($staff['job_title'] ?? ''));
    return [
        'staff_id' => (string) (int) $staff['staff_id'],
        'username' => (string) ($staff['username'] ?? $staff['email']),
        'first_name' => (string) $staff['first_name'],
        'last_name' => (string) $staff['last_name'],
        'email' => (string) $staff['email'],
        'phone_number' => (string) ($staff['phone_number'] ?? ''),
        'job_title' => in_array($storedJobTitle, $standardTitles, true) ? $storedJobTitle : ($storedJobTitle !== '' ? 'Other' : ''),
        'job_title_selection' => in_array($storedJobTitle, $standardTitles, true) ? $storedJobTitle : ($storedJobTitle !== '' ? 'Other' : ''),
        'job_title_custom' => in_array($storedJobTitle, $standardTitles, true) ? '' : $storedJobTitle,
        'password' => '',
        'is_active' => (string) (int) ($staff['is_active'] ?? 1),
        'capability_ids' => array_map('intval', (array) ($staff['capability_ids'] ?? [])),
    ];
}

function validateStaffAccountInput(array $input, bool $isUpdate = false): array {
    $errors = [];
    if (!hasRequiredText($input['username'])) {
        $errors['username'] = 'Username is required.';
    } elseif (!preg_match('/^[A-Za-z0-9._@-]{3,100}$/', $input['username'])) {
        $errors['username'] = 'Use 3 to 100 letters, numbers, periods, underscores, @ signs, or hyphens.';
    }
    if (!hasRequiredText($input['first_name'])) {
        $errors['first_name'] = 'First name is required.';
    }
    if (!hasRequiredText($input['last_name'])) {
        $errors['last_name'] = 'Last name is required.';
    }
    if (!hasRequiredText($input['email'])) {
        $errors['email'] = 'Email is required.';
    } elseif (!isValidEmail($input['email'])) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if ($input['phone_number'] !== '' && !isValidPhMobile($input['phone_number'])) {
        $errors['phone_number'] = 'Enter a valid Philippine mobile number (e.g. 09171234567).';
    }
    if ($input['job_title'] !== '' && strlen($input['job_title']) > 100) {
        $errors['job_title'] = 'Job title must be 100 characters or fewer.';
    }
    if (!$isUpdate && !hasRequiredText($input['password'], false)) {
        $errors['password'] = 'Password is required.';
    } elseif ($input['password'] !== '' && (strlen($input['password']) < 12
        || !preg_match('/[A-Z]/', $input['password'])
        || !preg_match('/[a-z]/', $input['password'])
        || !preg_match('/\d/', $input['password']))) {
        $errors['password'] = 'Use at least 12 characters with uppercase, lowercase, and a number.';
    }
    return $errors;
}

function staffUsernameExists(mysqli $conn, string $username, int $excludeUserId = 0): bool {
    if (!smartqmsTableHasColumn($conn, 'users', 'username')) {
        return false;
    }
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id <> ? LIMIT 1");
    $stmt->bind_param('si', $username, $excludeUserId);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}

function staffEmailExists(mysqli $conn, string $email, int $excludeUserId = 0): bool {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1");
    $stmt->bind_param('si', $email, $excludeUserId);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}

function staffPhoneExists(mysqli $conn, string $phoneNumber, int $excludeUserId = 0): bool {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE phone_number = ? AND user_id <> ? LIMIT 1");
    $stmt->bind_param('si', $phoneNumber, $excludeUserId);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}

function assertStaffHasNoServingTicket(mysqli $conn, int $staffId): void {
    $stmt = $conn->prepare("
        SELECT qt.ticket_id
        FROM service_windows sw
        JOIN queue_tickets qt ON qt.window_id = sw.window_id AND qt.status = 'serving'
        WHERE sw.staff_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $staffId);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        throw new DomainException('Complete, skip, or void the currently serving ticket before deactivating this staff account.');
    }
}

function createStaffAccount(mysqli $conn, array $input, int $addedBy, bool $manageTransaction = true): int {
    if ($manageTransaction) {
        $conn->begin_transaction();
    }
    try {
        $hash = password_hash($input['password'], PASSWORD_BCRYPT);
        $role = ROLE_STAFF;
        $verified = 1;
        $firstName = $input['first_name'];
        $lastName = $input['last_name'];
        $email = $input['email'];
        $phoneNumber = $input['phone_number'] !== '' ? $input['phone_number'] : null;
        $username = $input['username'] !== '' ? $input['username'] : $email;
        $jobTitle = $input['job_title'] !== '' ? $input['job_title'] : null;
        $active = (int) $input['is_active'];
        $mustChange = 1;
        if (smartqmsTableHasColumn($conn, 'users', 'username')
            && smartqmsTableHasColumn($conn, 'users', 'job_title')) {
            $stmt = $conn->prepare("
                INSERT INTO users
                  (username, first_name, last_name, phone_number, email, password_hash,
                   must_change_password, role, job_title, is_verified, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                'ssssssissii',
                $username,
                $firstName,
                $lastName,
                $phoneNumber,
                $email,
                $hash,
                $mustChange,
                $role,
                $jobTitle,
                $verified,
                $active
            );
        } else {
            $stmt = $conn->prepare("
                INSERT INTO users
                  (first_name, last_name, phone_number, email, password_hash, must_change_password,
                   role, is_verified, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                'sssssisii',
                $firstName,
                $lastName,
                $phoneNumber,
                $email,
                $hash,
                $mustChange,
                $role,
                $verified,
                $active
            );
        }
        $stmt->execute();
        $userId = (int) $conn->insert_id;

        $stmt = $conn->prepare("INSERT INTO staff (user_id, added_by) VALUES (?, ?)");
        $stmt->bind_param('ii', $userId, $addedBy);
        $stmt->execute();
        $staffId = (int) $conn->insert_id;
        syncStaffCapabilities($conn, $staffId, $input['capability_ids'], $addedBy);
        logActivity($conn, 'staff_created', 'Created staff account for ' . $input['email']);
        recordSecurityEvent($conn, 'staff_account_created', 'success', 'account', (string) $userId);
        if ($manageTransaction) {
            $conn->commit();
        }
        return $userId;
    } catch (Throwable $error) {
        if ($manageTransaction) {
            $conn->rollback();
        }
        throw $error;
    }
}

function findStaffAccount(mysqli $conn, int $staffId): ?array {
    $usernameSelect = smartqmsTableHasColumn($conn, 'users', 'username')
        ? 'u.username'
        : 'u.email AS username';
    $jobTitleSelect = smartqmsTableHasColumn($conn, 'users', 'job_title')
        ? 'u.job_title'
        : 'NULL AS job_title';
    $stmt = $conn->prepare("
        SELECT s.staff_id, s.user_id, s.added_by, s.added_at,
               u.first_name, u.last_name, u.email, u.phone_number, u.is_active,
               u.must_change_password, {$usernameSelect}, {$jobTitleSelect}
        FROM staff s
        JOIN users u ON u.user_id = s.user_id
        WHERE s.staff_id = ? AND u.role = ?
        LIMIT 1
    ");
    $role = ROLE_STAFF;
    $stmt->bind_param('is', $staffId, $role);
    $stmt->execute();
    $staff = $stmt->get_result()->fetch_assoc() ?: null;
    if ($staff) {
        $staff['capability_ids'] = staffCapabilityIds($conn, $staffId);
    }
    return $staff;
}

function updateStaffAccount(
    mysqli $conn,
    array $input,
    int $updatedBy,
    bool $manageTransaction = true
): bool {
    $staffId = (int) ($input['staff_id'] ?? 0);
    $staff = findStaffAccount($conn, $staffId);
    if (!$staff) {
        return false;
    }

    if ($manageTransaction) {
        $conn->begin_transaction();
    }

    try {
        $userId = (int) $staff['user_id'];
        $firstName = $input['first_name'];
        $lastName = $input['last_name'];
        $email = $input['email'];
        $phoneNumber = $input['phone_number'] !== '' ? $input['phone_number'] : null;
        $username = $input['username'] !== '' ? $input['username'] : $email;
        $jobTitle = $input['job_title'] !== '' ? $input['job_title'] : null;
        if ((int) $input['is_active'] === 0) {
            assertStaffHasNoServingTicket($conn, $staffId);
        }

        $hasBlueprintMetadata = smartqmsTableHasColumn($conn, 'users', 'username')
            && smartqmsTableHasColumn($conn, 'users', 'job_title');
        if ($input['password'] !== '' && $hasBlueprintMetadata) {
            $hash = password_hash($input['password'], PASSWORD_BCRYPT);
            $stmt = $conn->prepare("
                UPDATE users
                SET username = ?, first_name = ?, last_name = ?, phone_number = ?, email = ?,
                    password_hash = ?, must_change_password = 1, job_title = ?, is_active = ?,
                    session_version = session_version + 1
                WHERE user_id = ? AND role = ?
            ");
            $role = ROLE_STAFF;
            $active = (int) $input['is_active'];
            $stmt->bind_param('sssssssiis', $username, $firstName, $lastName, $phoneNumber, $email, $hash, $jobTitle, $active, $userId, $role);
        } elseif ($input['password'] !== '') {
            $hash = password_hash($input['password'], PASSWORD_BCRYPT);
            $stmt = $conn->prepare("
            UPDATE users
                SET first_name = ?, last_name = ?, phone_number = ?, email = ?,
                    password_hash = ?, must_change_password = 1, is_active = ?,
                    session_version = session_version + 1
                WHERE user_id = ? AND role = ?
            ");
            $role = ROLE_STAFF;
            $active = (int) $input['is_active'];
            $stmt->bind_param('sssssiis', $firstName, $lastName, $phoneNumber, $email, $hash, $active, $userId, $role);
        } elseif ($hasBlueprintMetadata) {
            $stmt = $conn->prepare("
                UPDATE users
                SET username = ?, first_name = ?, last_name = ?, phone_number = ?, email = ?,
                    job_title = ?, is_active = ?, session_version = session_version + 1
                WHERE user_id = ? AND role = ?
            ");
            $role = ROLE_STAFF;
            $active = (int) $input['is_active'];
            $stmt->bind_param('ssssssiis', $username, $firstName, $lastName, $phoneNumber, $email, $jobTitle, $active, $userId, $role);
        } else {
            $stmt = $conn->prepare("
                UPDATE users
                SET first_name = ?, last_name = ?, phone_number = ?, email = ?, is_active = ?,
                    session_version = session_version + 1
                WHERE user_id = ? AND role = ?
            ");
            $role = ROLE_STAFF;
            $active = (int) $input['is_active'];
            $stmt->bind_param('ssssiis', $firstName, $lastName, $phoneNumber, $email, $active, $userId, $role);
        }
        $stmt->execute();
        syncStaffCapabilities($conn, $staffId, $input['capability_ids'], $updatedBy);

        logActivity(
            $conn,
            'staff_updated',
            'Updated staff account for ' . $input['email'] . ' by admin user_id=' . $updatedBy
        );
        recordSecurityEvent($conn, 'staff_account_updated', 'success', 'account', (string) $userId);
        if ($manageTransaction) {
            $conn->commit();
        }
        return true;
    } catch (Throwable $error) {
        if ($manageTransaction) {
            $conn->rollback();
        }
        throw $error;
    }
}

function staffAccountHasHistory(mysqli $conn, int $staffId, int $userId): bool {
    $queries = [
        ['SELECT COUNT(*) AS total FROM wait_time_logs WHERE staff_id = ?', $staffId],
        ['SELECT COUNT(*) AS total FROM activity_logs WHERE user_id = ?', $userId],
        ['SELECT COUNT(*) AS total FROM queue_tickets WHERE user_id = ?', $userId],
        ['SELECT COUNT(*) AS total FROM notifications WHERE user_id = ?', $userId],
        ['SELECT COUNT(*) AS total FROM feedback WHERE user_id = ?', $userId],
    ];

    foreach ($queries as [$sql, $identifier]) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $identifier);
        $stmt->execute();
        if ((int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0) > 0) {
            return true;
        }
    }
    return false;
}

function deleteOrDeactivateStaffAccount(
    mysqli $conn,
    int $staffId,
    int $deletedBy,
    bool $manageTransaction = true
): ?array {
    $staff = findStaffAccount($conn, $staffId);
    if (!$staff) {
        return null;
    }

    if ($manageTransaction) {
        $conn->begin_transaction();
    }

    try {
        $userId = (int) $staff['user_id'];
        assertStaffHasNoServingTicket($conn, $staffId);
        $hadHistory = staffAccountHasHistory($conn, $staffId, $userId);
        $softDeleted = true;

        $stmt = $conn->prepare("UPDATE service_windows SET staff_id = NULL, status = 'closed' WHERE staff_id = ?");
        $stmt->bind_param('i', $staffId);
        $stmt->execute();

        $inactive = 0;
        $stmt = $conn->prepare("UPDATE users SET is_active = ?, session_version = session_version + 1 WHERE user_id = ?");
        $stmt->bind_param('ii', $inactive, $userId);
        $stmt->execute();

        logActivity(
            $conn,
            'staff_deactivated',
            'Deactivated staff account for ' . $staff['email'] . ' by admin user_id=' . $deletedBy
        );
        recordSecurityEvent($conn, 'staff_account_deactivated', 'success', 'account', (string) $userId);
        if ($manageTransaction) {
            $conn->commit();
        }
        return [
            'staff' => $staff,
            'soft_deleted' => $softDeleted,
            'had_history' => $hadHistory,
        ];
    } catch (Throwable $error) {
        if ($manageTransaction) {
            $conn->rollback();
        }
        throw $error;
    }
}

function listStaffAccounts(mysqli $conn): array {
    $usernameSelect = smartqmsTableHasColumn($conn, 'users', 'username')
        ? 'u.username'
        : 'u.email AS username';
    $jobTitleSelect = smartqmsTableHasColumn($conn, 'users', 'job_title')
        ? 'u.job_title'
        : 'NULL AS job_title';
    return $conn->query("
        SELECT s.staff_id, s.added_at, u.user_id, u.first_name, u.last_name,
               u.email, u.phone_number, u.is_active, u.must_change_password,
               {$usernameSelect}, {$jobTitleSelect},
               sw.window_name, sw.status AS window_status,
               CASE
                 WHEN sw.window_type = 'shared' THEN 'All services'
                 ELSE runtime_service.service_name
               END AS runtime_queue,
               GROUP_CONCAT(DISTINCT capability_service.service_name
                 ORDER BY capability_service.display_order SEPARATOR ', ') AS specialized_capabilities
        FROM staff s
        JOIN users u ON u.user_id = s.user_id
        LEFT JOIN service_windows sw ON sw.staff_id = s.staff_id AND sw.is_active = 1
        LEFT JOIN health_services runtime_service ON runtime_service.service_id = sw.service_id
        LEFT JOIN staff_service_capabilities capability
          ON capability.staff_id = s.staff_id AND capability.is_active = 1
        LEFT JOIN health_services capability_service
          ON capability_service.service_id = capability.service_id
        GROUP BY s.staff_id, s.added_at, u.user_id, u.first_name, u.last_name,
                 u.email, u.phone_number, u.is_active, u.must_change_password,
                 " . (smartqmsTableHasColumn($conn, 'users', 'username') ? 'u.username, ' : '') . "
                 " . (smartqmsTableHasColumn($conn, 'users', 'job_title') ? 'u.job_title, ' : '') . "
                 sw.window_name, sw.status, sw.window_type, runtime_service.service_name
        ORDER BY s.added_at DESC
    ")->fetch_all(MYSQLI_ASSOC);
}
