<?php
/**
 * Health-service administration domain functions shared by HTML and JSON adapters.
 */

function serviceFormDefaults(): array {
    return [
        'service_id' => '',
        'service_code' => '',
        'service_name' => '',
        'service_encoded' => '',
        'queue_mode' => 'central',
        'description' => '',
        'fallback_duration_mins' => '15',
        'priority_only' => '0',
        'is_hidden' => '0',
        'is_active' => '1',
        'display_order' => '0',
    ];
}

function serviceRowToForm(array $service): array {
    return [
        'service_id' => (string) $service['service_id'],
        'service_code' => (string) $service['service_code'],
        'service_name' => (string) $service['service_name'],
        'service_encoded' => (string) $service['service_encoded'],
        'queue_mode' => normalizeQueueMode((string) ($service['queue_mode'] ?? 'central')),
        'description' => (string) ($service['description'] ?? ''),
        'fallback_duration_mins' => (string) (int) ($service['fallback_duration_mins'] ?? 15),
        'priority_only' => (string) (int) $service['priority_only'],
        'is_hidden' => (string) (int) ($service['is_hidden'] ?? 0),
        'is_active' => (string) (int) $service['is_active'],
        'display_order' => (string) (int) $service['display_order'],
    ];
}

function serviceHtmlInput(array $source): array {
    $input = serviceFormDefaults();
    foreach ($input as $key => $default) {
        $input[$key] = $key === 'priority_only'
            ? ((int) ($source[$key] ?? 0) === 1 ? '1' : '0')
            : trim((string) ($source[$key] ?? $default));
    }
    return $input;
}

function validateServiceHtmlInput(array $input, bool $updating = false): array {
    $errors = [];
    if ($updating && !isPositiveIdentifier((int) $input['service_id'])) {
        $errors['service_id'] = 'Choose a valid service to update.';
    }
    if (!hasRequiredText($input['service_name'])) {
        $errors['service_name'] = 'Service name is required.';
    } elseif (strlen($input['service_name']) > 100) {
        $errors['service_name'] = 'Use 100 characters or fewer.';
    }
    if (!in_array($input['queue_mode'], ['central', 'specialized'], true)) {
        $errors['queue_mode'] = 'Choose a valid service routing mode.';
    }
    if (!isServiceDisplayOrder($input['display_order'])) {
        $errors['display_order'] = 'Enter a display order from 0 to 127.';
    }
    $fallbackMinutes = filter_var($input['fallback_duration_mins'], FILTER_VALIDATE_INT);
    if ($fallbackMinutes === false || $fallbackMinutes < 1 || $fallbackMinutes > 480) {
        $errors['fallback_duration_mins'] = 'Enter a fallback duration from 1 to 480 minutes.';
    }
    return $errors;
}

function validateServiceJsonAdd(array $source): array {
    $errors = [];
    if (!hasRequiredText((string) ($source['service_name'] ?? ''))) {
        $errors['service_name'] = 'Service name is required.';
    }
    if (!in_array((string) ($source['queue_mode'] ?? 'central'), ['central', 'specialized'], true)) {
        $errors['queue_mode'] = 'Choose a valid queue mode.';
    }
    return $errors;
}

function validateServiceJsonEdit(array $source): array {
    $errors = [];
    if (!isPositiveIdentifier((int) ($source['service_id'] ?? 0))) {
        $errors['service_id'] = 'Service id is required.';
    }
    if (!hasRequiredText((string) ($source['service_name'] ?? ''))) {
        $errors['service_name'] = 'Service name is required.';
    }
    if (!in_array((string) ($source['queue_mode'] ?? 'central'), ['central', 'specialized'], true)) {
        $errors['queue_mode'] = 'Choose a valid queue mode.';
    }
    return $errors;
}

function listHealthServices(mysqli $conn, bool $activeFirst = false): array {
    $orderBy = $activeFirst
        ? 'is_active DESC, display_order, service_name'
        : 'display_order, service_name';
    return $conn->query("SELECT * FROM health_services ORDER BY {$orderBy}")->fetch_all(MYSQLI_ASSOC);
}

function findHealthService(mysqli $conn, int $serviceId): ?array {
    $stmt = $conn->prepare("SELECT * FROM health_services WHERE service_id = ? LIMIT 1");
    $stmt->bind_param('i', $serviceId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function nextHealthServiceEncoded(mysqli $conn): int {
    $next = (int) ($conn->query('SELECT COALESCE(MAX(service_encoded), 0) + 1 AS next_value FROM health_services')
        ->fetch_assoc()['next_value'] ?? 1);
    if ($next < 1 || $next > 127) {
        throw new RuntimeException('No ML category identifiers remain in the supported range.');
    }
    return $next;
}

function nextHealthServiceCode(mysqli $conn): string {
    $rows = $conn->query("SELECT service_code FROM health_services WHERE service_code REGEXP '^SVC-[0-9]+$'")
        ->fetch_all(MYSQLI_ASSOC);
    $maximum = 0;
    foreach ($rows as $row) {
        $maximum = max($maximum, (int) substr((string) $row['service_code'], 4));
    }
    return 'SVC-' . str_pad((string) ($maximum + 1), 3, '0', STR_PAD_LEFT);
}

function nextHealthServiceDisplayOrder(mysqli $conn): int {
    return min(127, (int) ($conn->query('SELECT COALESCE(MAX(display_order), 0) + 1 AS next_value FROM health_services')
        ->fetch_assoc()['next_value'] ?? 1));
}

function healthServiceCodeIsLocked(mysqli $conn, int $serviceId): bool {
    return healthServiceHasTicketHistory($conn, $serviceId);
}

function duplicateHealthServiceCode(mysqli $conn, string $code, int $excludingId = 0): bool {
    $stmt = $conn->prepare("SELECT service_id FROM health_services WHERE service_code = ? AND service_id <> ? LIMIT 1");
    $stmt->bind_param('si', $code, $excludingId);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}

function createHealthService(
    mysqli $conn,
    string $code,
    string $name,
    int $encoded,
    string $description,
    int $priorityOnly,
    int $displayOrder,
    int $createdBy,
    int $isActive = 1,
    string $queueMode = 'central',
    int $fallbackDurationMinutes = 15,
    int $isHidden = 0
): int {
    $lockName = 'smartqms_health_service_identity';
    $lock = $conn->query("SELECT GET_LOCK('{$lockName}', 5) AS acquired")->fetch_assoc();
    if ((int) ($lock['acquired'] ?? 0) !== 1) {
        throw new RuntimeException('Could not reserve the next service identifier. Please try again.');
    }

    try {
        // The legacy arguments stay in the public signature for compatibility,
        // but identifiers are always allocated authoritatively on the server.
        $code = nextHealthServiceCode($conn);
        $encoded = nextHealthServiceEncoded($conn);
        $displayOrder = nextHealthServiceDisplayOrder($conn);
        $queueMode = normalizeQueueMode($queueMode);
        if (smartqmsTableHasColumn($conn, 'health_services', 'fallback_duration_mins')) {
            $stmt = $conn->prepare("
                INSERT INTO health_services
                  (service_code, service_name, service_encoded, queue_mode, description,
                   fallback_duration_mins, priority_only, is_hidden, is_active, display_order, created_by)
                VALUES (?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?, ?)
            ");
            $fallbackDurationMinutes = max(1, min(480, $fallbackDurationMinutes));
            $stmt->bind_param('ssissiiiiii', $code, $name, $encoded, $queueMode, $description, $fallbackDurationMinutes, $priorityOnly, $isHidden, $isActive, $displayOrder, $createdBy);
            $stmt->execute();
            return (int) $conn->insert_id;
        }
        $stmt = $conn->prepare("
            INSERT INTO health_services
              (service_code, service_name, service_encoded, queue_mode, description,
               priority_only, is_active, display_order, created_by)
            VALUES (?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?)
        ");
        $stmt->bind_param('ssissiiii', $code, $name, $encoded, $queueMode, $description, $priorityOnly, $isActive, $displayOrder, $createdBy);
        $stmt->execute();
        return (int) $conn->insert_id;
    } finally {
        $conn->query("DO RELEASE_LOCK('{$lockName}')");
    }
}

function updateHealthServiceFromHtml(mysqli $conn, array $input): void {
    $serviceId = (int) $input['service_id'];
    $existing = findHealthService($conn, $serviceId);
    if (!$existing) {
        throw new RuntimeException('Health service could not be found.');
    }
    $code = (string) $existing['service_code'];
    if (smartqmsTableHasColumn($conn, 'health_services', 'fallback_duration_mins')) {
        $stmt = $conn->prepare("
            UPDATE health_services
            SET service_code = ?, service_name = ?, queue_mode = ?,
                description = NULLIF(?, ''), fallback_duration_mins = ?,
                priority_only = ?, is_hidden = ?, is_active = ?, display_order = ?
            WHERE service_id = ?
        ");
        $name = $input['service_name'];
        $queueMode = normalizeQueueMode($input['queue_mode']);
        $description = $input['description'];
        $fallback = max(1, min(480, (int) $input['fallback_duration_mins']));
        $priority = (int) $input['priority_only'];
        $hidden = (int) $input['is_hidden'];
        $active = (int) $input['is_active'];
        $order = (int) $existing['display_order'];
        $stmt->bind_param('ssssiiiiii', $code, $name, $queueMode, $description, $fallback, $priority, $hidden, $active, $order, $serviceId);
        $stmt->execute();
        return;
    }
    $stmt = $conn->prepare("
        UPDATE health_services
        SET service_code = ?, service_name = ?, queue_mode = ?,
            description = NULLIF(?, ''), priority_only = ?, is_active = ?, display_order = ?
        WHERE service_id = ?
    ");
    $name = $input['service_name'];
    $queueMode = normalizeQueueMode($input['queue_mode']);
    $description = $input['description'];
    $priority = (int) $input['priority_only'];
    $active = (int) $input['is_active'];
    $order = (int) $existing['display_order'];
    $stmt->bind_param('ssssiiii', $code, $name, $queueMode, $description, $priority, $active, $order, $serviceId);
    $stmt->execute();
}

function moveHealthService(mysqli $conn, int $serviceId, string $direction): bool {
    if (!in_array($direction, ['up', 'down'], true)) {
        return false;
    }

    $conn->begin_transaction();
    try {
        $rows = $conn->query("
            SELECT service_id
            FROM health_services
            ORDER BY display_order, service_id
            FOR UPDATE
        ")->fetch_all(MYSQLI_ASSOC);
        $ids = array_map('intval', array_column($rows, 'service_id'));
        $index = array_search($serviceId, $ids, true);
        if ($index === false) {
            $conn->rollback();
            return false;
        }
        $target = $direction === 'up' ? $index - 1 : $index + 1;
        if ($target < 0 || $target >= count($ids)) {
            $conn->rollback();
            return false;
        }
        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
        $update = $conn->prepare('UPDATE health_services SET display_order = ? WHERE service_id = ?');
        foreach ($ids as $position => $id) {
            $order = $position + 1;
            $update->bind_param('ii', $order, $id);
            $update->execute();
        }
        logActivity($conn, 'service_updated', "Moved service_id={$serviceId} {$direction}");
        $conn->commit();
        return true;
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}

function updateHealthServiceFromJson(mysqli $conn, array $source): void {
    $id = (int) ($source['service_id'] ?? 0);
    $existing = findHealthService($conn, $id);
    if (!$existing) {
        throw new RuntimeException('Health service could not be found.');
    }
    $stmt = $conn->prepare("
        UPDATE health_services
        SET service_name = ?, queue_mode = ?, description = NULLIF(?, ''),
            priority_only = ?, display_order = ?
        WHERE service_id = ?
    ");
    $name = trim((string) ($source['service_name'] ?? ''));
    $queueMode = normalizeQueueMode((string) ($source['queue_mode'] ?? 'central'));
    $description = trim((string) ($source['description'] ?? ''));
    $priority = (int) ($source['priority_only'] ?? 0);
    $order = (int) $existing['display_order'];
    $stmt->bind_param('sssiii', $name, $queueMode, $description, $priority, $order, $id);
    $stmt->execute();
}

function setHealthServiceActive(mysqli $conn, int $serviceId, int $isActive): void {
    $stmt = $conn->prepare("UPDATE health_services SET is_active = ? WHERE service_id = ?");
    $stmt->bind_param('ii', $isActive, $serviceId);
    $stmt->execute();
}

function healthServiceHasTicketHistory(mysqli $conn, int $serviceId): bool {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM queue_tickets WHERE service_id = ?");
    $stmt->bind_param('i', $serviceId);
    $stmt->execute();
    return (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0) > 0;
}

function deleteOrDeactivateHealthService(mysqli $conn, int $serviceId): ?array {
    $service = findHealthService($conn, $serviceId);
    if (!$service) {
        return null;
    }

    $hadTicketHistory = healthServiceHasTicketHistory($conn, $serviceId);
    $softDeleted = $hadTicketHistory;
    if ($hadTicketHistory) {
        setHealthServiceActive($conn, $serviceId, 0);
    } else {
        try {
            $stmt = $conn->prepare("DELETE FROM health_services WHERE service_id = ?");
            $stmt->bind_param('i', $serviceId);
            $stmt->execute();
        } catch (Throwable $error) {
            setHealthServiceActive($conn, $serviceId, 0);
            $softDeleted = true;
        }
    }

    return [
        'service' => $service,
        'soft_deleted' => $softDeleted,
        'had_ticket_history' => $hadTicketHistory,
    ];
}
