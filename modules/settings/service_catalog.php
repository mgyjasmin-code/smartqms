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
        'description' => '',
        'priority_only' => '0',
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
        'description' => (string) ($service['description'] ?? ''),
        'priority_only' => (string) (int) $service['priority_only'],
        'is_active' => (string) (int) $service['is_active'],
        'display_order' => (string) (int) $service['display_order'],
    ];
}

function serviceHtmlInput(array $source): array {
    $input = serviceFormDefaults();
    foreach ($input as $key => $default) {
        $input[$key] = $key === 'priority_only'
            ? (isset($source[$key]) ? '1' : '0')
            : trim((string) ($source[$key] ?? $default));
    }
    return $input;
}

function validateServiceHtmlInput(array $input, bool $updating = false): array {
    $errors = [];
    if ($updating && !isPositiveIdentifier((int) $input['service_id'])) {
        $errors['service_id'] = 'Choose a valid service to update.';
    }
    if (!hasRequiredText($input['service_code'])) {
        $errors['service_code'] = 'Service code is required.';
    } elseif (strlen($input['service_code']) > 10) {
        $errors['service_code'] = 'Use 10 characters or fewer.';
    }
    if (!hasRequiredText($input['service_name'])) {
        $errors['service_name'] = 'Service name is required.';
    } elseif (strlen($input['service_name']) > 100) {
        $errors['service_name'] = 'Use 100 characters or fewer.';
    }
    if (!isServiceEncodedValue($input['service_encoded'])) {
        $errors['service_encoded'] = 'Enter a numeric ML value from 1 to 127.';
    }
    if (!isServiceDisplayOrder($input['display_order'])) {
        $errors['display_order'] = 'Enter a display order from 0 to 127.';
    }
    return $errors;
}

function validateServiceJsonAdd(array $source): array {
    $errors = [];
    if (!hasRequiredText((string) ($source['service_code'] ?? ''))) {
        $errors['service_code'] = 'Service code is required.';
    }
    if (!hasRequiredText((string) ($source['service_name'] ?? ''))) {
        $errors['service_name'] = 'Service name is required.';
    }
    if (!isPositiveIdentifier((int) ($source['service_encoded'] ?? 0))) {
        $errors['service_encoded'] = 'Encoded value is required.';
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
    int $isActive = 1
): int {
    $stmt = $conn->prepare("
        INSERT INTO health_services
          (service_code, service_name, service_encoded, description, priority_only, is_active, display_order, created_by)
        VALUES (?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?)
    ");
    $stmt->bind_param('ssisiiii', $code, $name, $encoded, $description, $priorityOnly, $isActive, $displayOrder, $createdBy);
    $stmt->execute();
    return (int) $conn->insert_id;
}

function updateHealthServiceFromHtml(mysqli $conn, array $input): void {
    $stmt = $conn->prepare("
        UPDATE health_services
        SET service_code = ?, service_name = ?, service_encoded = ?,
            description = NULLIF(?, ''), priority_only = ?, is_active = ?, display_order = ?
        WHERE service_id = ?
    ");
    $code = strtoupper($input['service_code']);
    $name = $input['service_name'];
    $encoded = (int) $input['service_encoded'];
    $description = $input['description'];
    $priority = (int) $input['priority_only'];
    $active = (int) $input['is_active'];
    $order = (int) $input['display_order'];
    $id = (int) $input['service_id'];
    $stmt->bind_param('ssisiiii', $code, $name, $encoded, $description, $priority, $active, $order, $id);
    $stmt->execute();
}

function updateHealthServiceFromJson(mysqli $conn, array $source): void {
    $stmt = $conn->prepare("
        UPDATE health_services
        SET service_name = ?, description = NULLIF(?, ''), priority_only = ?, display_order = ?
        WHERE service_id = ?
    ");
    $name = trim((string) ($source['service_name'] ?? ''));
    $description = trim((string) ($source['description'] ?? ''));
    $priority = (int) ($source['priority_only'] ?? 0);
    $order = (int) ($source['display_order'] ?? 0);
    $id = (int) ($source['service_id'] ?? 0);
    $stmt->bind_param('ssiii', $name, $description, $priority, $order, $id);
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
