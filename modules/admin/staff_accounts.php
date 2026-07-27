<?php
/**
 * Staff-account validation, persistence, and administration reads.
 */

function staffAccountDefaults(): array {
    return [
        'staff_id' => '0',
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'phone_number' => '',
        'password' => '',
    ];
}

function staffAccountInput(array $source): array {
    return [
        'staff_id' => (string) (int) ($source['staff_id'] ?? 0),
        'first_name' => trim((string) ($source['first_name'] ?? '')),
        'last_name' => trim((string) ($source['last_name'] ?? '')),
        'email' => normalizeEmail((string) ($source['email'] ?? '')),
        'phone_number' => normalizePhone((string) ($source['phone_number'] ?? '')),
        'password' => (string) ($source['password'] ?? ''),
    ];
}

function staffAccountRowToForm(array $staff): array {
    return [
        'staff_id' => (string) (int) $staff['staff_id'],
        'first_name' => (string) $staff['first_name'],
        'last_name' => (string) $staff['last_name'],
        'email' => (string) $staff['email'],
        'phone_number' => (string) ($staff['phone_number'] ?? ''),
        'password' => '',
    ];
}

function validateStaffAccountInput(array $input, bool $isUpdate = false): array {
    $errors = [];
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
    if (!$isUpdate && !hasRequiredText($input['password'], false)) {
        $errors['password'] = 'Password is required.';
    } elseif ($input['password'] !== '' && !hasMinimumLength($input['password'], 8)) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }
    return $errors;
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
        $stmt = $conn->prepare("
            INSERT INTO users (first_name, last_name, phone_number, email, password_hash, role, is_verified)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('ssssssi', $firstName, $lastName, $phoneNumber, $email, $hash, $role, $verified);
        $stmt->execute();
        $userId = (int) $conn->insert_id;

        $stmt = $conn->prepare("INSERT INTO staff (user_id, added_by) VALUES (?, ?)");
        $stmt->bind_param('ii', $userId, $addedBy);
        $stmt->execute();
        logActivity($conn, 'staff_created', 'Created staff account for ' . $input['email']);
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
    $stmt = $conn->prepare("
        SELECT s.staff_id, s.user_id, s.added_by, s.added_at,
               u.first_name, u.last_name, u.email, u.phone_number, u.is_active
        FROM staff s
        JOIN users u ON u.user_id = s.user_id
        WHERE s.staff_id = ? AND u.role = ?
        LIMIT 1
    ");
    $role = ROLE_STAFF;
    $stmt->bind_param('is', $staffId, $role);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
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

        if ($input['password'] !== '') {
            $hash = password_hash($input['password'], PASSWORD_BCRYPT);
            $stmt = $conn->prepare("
                UPDATE users
                SET first_name = ?, last_name = ?, phone_number = ?, email = ?, password_hash = ?
                WHERE user_id = ? AND role = ?
            ");
            $role = ROLE_STAFF;
            $stmt->bind_param('sssssis', $firstName, $lastName, $phoneNumber, $email, $hash, $userId, $role);
        } else {
            $stmt = $conn->prepare("
                UPDATE users
                SET first_name = ?, last_name = ?, phone_number = ?, email = ?
                WHERE user_id = ? AND role = ?
            ");
            $role = ROLE_STAFF;
            $stmt->bind_param('ssssis', $firstName, $lastName, $phoneNumber, $email, $userId, $role);
        }
        $stmt->execute();

        logActivity(
            $conn,
            'staff_updated',
            'Updated staff account for ' . $input['email'] . ' by admin user_id=' . $updatedBy
        );
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
        $hadHistory = staffAccountHasHistory($conn, $staffId, $userId);
        $softDeleted = $hadHistory;

        $stmt = $conn->prepare("UPDATE service_windows SET staff_id = NULL WHERE staff_id = ?");
        $stmt->bind_param('i', $staffId);
        $stmt->execute();

        if ($hadHistory) {
            $inactive = 0;
            $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
            $stmt->bind_param('ii', $inactive, $userId);
            $stmt->execute();
        } else {
            try {
                $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role = ?");
                $role = ROLE_STAFF;
                $stmt->bind_param('is', $userId, $role);
                $stmt->execute();
            } catch (Throwable $error) {
                $inactive = 0;
                $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
                $stmt->bind_param('ii', $inactive, $userId);
                $stmt->execute();
                $softDeleted = true;
            }
        }

        logActivity(
            $conn,
            'staff_deleted',
            'Removed staff account for ' . $staff['email'] . ' by admin user_id=' . $deletedBy
        );
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
    return $conn->query("
        SELECT s.staff_id, s.added_at, u.user_id, u.first_name, u.last_name,
               u.email, u.phone_number, u.is_active, sw.window_name, sw.status AS window_status,
               hs.service_name
        FROM staff s
        JOIN users u ON u.user_id = s.user_id
        LEFT JOIN service_windows sw ON sw.staff_id = s.staff_id AND sw.is_active = 1
        LEFT JOIN health_services hs ON hs.service_id = sw.service_id
        ORDER BY s.added_at DESC
    ")->fetch_all(MYSQLI_ASSOC);
}
