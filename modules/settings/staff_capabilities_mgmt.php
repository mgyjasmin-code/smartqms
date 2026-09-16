<?php
/**
 * Specialized staff-capability administration.
 */

function normalizeCapabilityIds(array $values): array {
    $ids = [];
    foreach ($values as $value) {
        $id = (int) $value;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    ksort($ids, SORT_NUMERIC);
    return array_values($ids);
}

function listSpecializedServices(mysqli $conn, bool $activeOnly = true): array {
    $sql = "SELECT service_id, service_code, service_name, is_active
            FROM health_services
            WHERE queue_mode = 'specialized'";
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' ORDER BY display_order, service_name';
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

function staffCapabilityIds(mysqli $conn, int $staffId): array {
    $stmt = $conn->prepare("
        SELECT service_id
        FROM staff_service_capabilities
        WHERE staff_id = ? AND is_active = 1
        ORDER BY service_id
    ");
    $stmt->bind_param('i', $staffId);
    $stmt->execute();
    return array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'service_id'));
}

function validSpecializedCapabilityIds(mysqli $conn, array $serviceIds): array {
    $serviceIds = normalizeCapabilityIds($serviceIds);
    if (!$serviceIds) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
    $types = str_repeat('i', count($serviceIds));
    $stmt = $conn->prepare("
        SELECT service_id
        FROM health_services
        WHERE service_id IN ({$placeholders})
          AND queue_mode = 'specialized'
          AND is_active = 1
    ");
    $stmt->bind_param($types, ...$serviceIds);
    $stmt->execute();
    return normalizeCapabilityIds(array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'service_id'));
}

function syncStaffCapabilities(
    mysqli $conn,
    int $staffId,
    array $requestedServiceIds,
    int $assignedBy
): void {
    $selected = validSpecializedCapabilityIds($conn, $requestedServiceIds);
    if ($selected !== normalizeCapabilityIds($requestedServiceIds)) {
        throw new InvalidArgumentException('One or more specialized capabilities are invalid.');
    }

    $existing = staffCapabilityIds($conn, $staffId);
    $toDisable = array_values(array_diff($existing, $selected));
    $toEnable = array_values(array_diff($selected, $existing));

    $upsert = $conn->prepare("
        INSERT INTO staff_service_capabilities
          (staff_id, service_id, is_active, assigned_by)
        VALUES (?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE
          is_active = 1,
          assigned_by = VALUES(assigned_by),
          assigned_at = CURRENT_TIMESTAMP
    ");
    foreach ($toEnable as $serviceId) {
        $upsert->bind_param('iii', $staffId, $serviceId, $assignedBy);
        $upsert->execute();
        logActivity($conn, 'staff_capability_added', "staff_id={$staffId}; service_id={$serviceId}");
    }

    $disable = $conn->prepare("
        UPDATE staff_service_capabilities
        SET is_active = 0, assigned_by = ?, assigned_at = CURRENT_TIMESTAMP
        WHERE staff_id = ? AND service_id = ? AND is_active = 1
    ");
    foreach ($toDisable as $serviceId) {
        $disable->bind_param('iii', $assignedBy, $staffId, $serviceId);
        $disable->execute();
        logActivity($conn, 'staff_capability_removed', "staff_id={$staffId}; service_id={$serviceId}");
    }
}

function staffCapabilitiesSummary(mysqli $conn, int $staffId): array {
    $stmt = $conn->prepare("
        SELECT hs.service_id, hs.service_name
        FROM staff_service_capabilities capability
        JOIN health_services hs ON hs.service_id = capability.service_id
        WHERE capability.staff_id = ? AND capability.is_active = 1
        ORDER BY hs.display_order, hs.service_name
    ");
    $stmt->bind_param('i', $staffId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
