<?php
/**
 * Provider-neutral customer booking input and local homepage projections.
 */

function smartqmsCustomerBookingProfile(mysqli $conn, int $userId): array {
    $stmt = $conn->prepare("
        SELECT first_name, last_name, phone_number, client_type
        FROM users
        WHERE user_id = ? AND role = 'client'
        LIMIT 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();

    return [
        'first_name' => trim((string) ($profile['first_name'] ?? '')),
        'last_name' => trim((string) ($profile['last_name'] ?? '')),
        'phone_number' => normalizePhone((string) ($profile['phone_number'] ?? '')),
        'client_type' => normalizeQueueClientType((string) ($profile['client_type'] ?? 'regular')),
    ];
}

function smartqmsCustomerBookingInput(array $source, array $fallback = []): array {
    return [
        'service_id' => trim((string) ($source['service_id'] ?? '')),
        'branch_id' => trim((string) ($source['branch_id'] ?? '')),
        'first_name' => trim((string) ($source['first_name'] ?? $fallback['first_name'] ?? '')),
        'last_name' => trim((string) ($source['last_name'] ?? $fallback['last_name'] ?? '')),
        'phone_number' => normalizePhone((string) ($source['phone_number'] ?? $fallback['phone_number'] ?? '')),
        'client_type' => normalizeQueueClientType((string) ($source['client_type'] ?? $fallback['client_type'] ?? 'regular')),
    ];
}

function smartqmsCustomerBookingErrors(array $input): array {
    $errors = [];
    if (!isPositiveIdentifier((int) ($input['service_id'] ?? 0))) {
        $errors['service_id'] = 'Choose an active health service.';
    }
    if (trim((string) ($input['branch_id'] ?? '')) === '') {
        $errors['branch_id'] = 'Choose a health-center location.';
    }
    if (!hasRequiredText((string) ($input['first_name'] ?? ''))) {
        $errors['first_name'] = 'First name is required.';
    } elseif (strlen((string) $input['first_name']) > 50) {
        $errors['first_name'] = 'First name must be 50 characters or fewer.';
    }
    if (!hasRequiredText((string) ($input['last_name'] ?? ''))) {
        $errors['last_name'] = 'Last name is required.';
    } elseif (strlen((string) $input['last_name']) > 50) {
        $errors['last_name'] = 'Last name must be 50 characters or fewer.';
    }
    if (!isValidPhMobile((string) ($input['phone_number'] ?? ''))) {
        $errors['phone_number'] = 'Enter an 11-digit Philippine mobile number beginning with 09.';
    }

    return $errors;
}

function smartqmsCustomerBookingBranch(array $branches, string $branchId): ?array {
    foreach ($branches as $branch) {
        if (hash_equals((string) ($branch['id'] ?? ''), $branchId) && !empty($branch['active'])) {
            return $branch;
        }
    }
    return null;
}

function smartqmsCustomerPhoneBelongsToAnotherUser(mysqli $conn, string $phone, int $userId): bool {
    $stmt = $conn->prepare('SELECT user_id FROM users WHERE phone_number = ? AND user_id <> ? LIMIT 1');
    $stmt->bind_param('si', $phone, $userId);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}

function smartqmsCustomerHomepageCounts(mysqli $conn): array {
    $ticketCounts = $conn->query("
        SELECT
          SUM(status = 'waiting') AS waiting_count,
          SUM(status = 'serving') AS serving_count
        FROM queue_tickets
    ")->fetch_assoc() ?: [];
    $counter = $conn->query("
        SELECT COUNT(*) AS available_counters
        FROM service_windows
        WHERE is_active = 1 AND status IN ('open', 'busy')
    ")->fetch_assoc() ?: [];

    return [
        'waiting' => (int) ($ticketCounts['waiting_count'] ?? 0),
        'serving' => (int) ($ticketCounts['serving_count'] ?? 0),
        'available_counters' => (int) ($counter['available_counters'] ?? 0),
    ];
}

function smartqmsUpdateCustomerBookingProfile(mysqli $conn, int $userId, array $input): void {
    $stmt = $conn->prepare("
        UPDATE users
        SET first_name = ?, last_name = ?, phone_number = ?, client_type = ?
        WHERE user_id = ? AND role = 'client'
    ");
    $firstName = (string) $input['first_name'];
    $lastName = (string) $input['last_name'];
    $phone = (string) $input['phone_number'];
    $clientType = normalizeQueueClientType((string) $input['client_type']);
    $stmt->bind_param('ssssi', $firstName, $lastName, $phone, $clientType, $userId);
    $stmt->execute();
    if ($stmt->affected_rows < 0) {
        throw new RuntimeException('Customer profile could not be updated.');
    }
}
