<?php
/**
 * Service-window form validation, assignments, reads, and writes.
 */

require_once __DIR__ . '/../settings/staff_capabilities_mgmt.php';

function windowAdminDefaults(): array {
    return [
        'window_id' => '',
        'counter_number' => '',
        'window_name' => '',
        'location_description' => '',
        'window_type' => 'shared',
        'service_id' => '0',
        'service_ids' => [],
        'is_active' => '1',
        'management_status' => 'active',
    ];
}

function normalizeWindowManagementStatus(string $status): string {
    return in_array($status, ['active', 'inactive', 'maintenance'], true) ? $status : 'active';
}

function windowAdminInput(array $source): array {
    $serviceIds = normalizeCapabilityIds(is_array($source['service_ids'] ?? null) ? $source['service_ids'] : []);
    $primaryServiceId = $serviceIds[0] ?? (int) ($source['service_id'] ?? 0);
    $counterNumber = (int) ($source['counter_number'] ?? 0);
    $legacyActive = (int) ($source['is_active'] ?? 1);
    $managementStatus = isset($source['management_status'])
        ? normalizeWindowManagementStatus(trim((string) $source['management_status']))
        : ($legacyActive === 2 ? 'maintenance' : ($legacyActive === 1 ? 'active' : 'inactive'));
    $windowName = trim((string) ($source['window_name'] ?? ''));
    if ($windowName === '' && $counterNumber > 0) {
        $windowName = 'Window ' . $counterNumber;
    }
    return [
        'window_id' => (string) (int) ($source['window_id'] ?? 0),
        'counter_number' => (string) $counterNumber,
        'window_name' => $windowName,
        'location_description' => trim((string) ($source['location_description'] ?? '')),
        'window_type' => normalizeWindowType((string) ($source['window_type'] ?? 'shared')),
        'service_id' => (string) $primaryServiceId,
        'service_ids' => $serviceIds,
        'is_active' => match ($managementStatus) {
            'inactive' => '0',
            'maintenance' => '2',
            default => '1',
        },
        'management_status' => $managementStatus,
    ];
}

function validateWindowAdminInput(array $input): array {
    $errors = [];
    if (!hasRequiredText($input['window_name'])) {
        $errors['window_name'] = 'Window name is required.';
    }
    if ((int) $input['counter_number'] < 1) {
        $errors['counter_number'] = 'Counter number must be at least 1.';
    }
    if (!in_array($input['window_type'], ['shared', 'specialized'], true)) {
        $errors['window_type'] = 'Choose Shared or Specialized.';
    }
    if (!in_array($input['management_status'], ['active', 'inactive', 'maintenance'], true)) {
        $errors['management_status'] = 'Choose Active, Inactive, or Under maintenance.';
    }
    if (!$input['service_ids']) {
        $errors['service_ids'] = 'Choose at least one service for this counter.';
    }
    return $errors;
}

function validateWindowAdminRouting(mysqli $conn, array $input): array {
    $errors = validateWindowAdminInput($input);
    if (!$errors && $input['service_ids']) {
        if (smartqmsDataProviderMode() === 'supabase') {
            foreach ($input['service_ids'] as $serviceId) {
                $service = smartqmsAdminFindService($conn, (int) $serviceId);
                if (!$service || !$service['active']) {
                    $errors['service_ids'] = 'Choose only active health services.';
                    break;
                }
            }
            return $errors;
        }
        $idList = implode(',', array_map('intval', $input['service_ids']));
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM health_services WHERE service_id IN ({$idList}) AND is_active = 1");
        $stmt->execute();
        if ((int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0) !== count($input['service_ids'])) {
            $errors['service_ids'] = 'Choose only active health services.';
        }
    }
    return $errors;
}

function saveAdminWindow(mysqli $conn, array $input): bool {
    $id = (int) $input['window_id'];
    $name = $input['window_name'];
    $counterNumber = (int) $input['counter_number'];
    $location = $input['location_description'];
    $windowType = normalizeWindowType($input['window_type']);
    $serviceId = $windowType === 'specialized' ? (int) ($input['service_ids'][0] ?? 0) : null;
    $active = (int) $input['is_active'];
    if ($id > 0) {
        $existing = $conn->prepare('SELECT * FROM service_windows WHERE window_id = ? LIMIT 1 FOR UPDATE');
        $existing->bind_param('i', $id);
        $existing->execute();
        $window = $existing->get_result()->fetch_assoc();
        if (!$window) {
            throw new RuntimeException('Service window could not be found.');
        }
        $counterNumber = (int) ($window['counter_number'] ?? $window['window_id']);
        if ($window['staff_id'] !== null || ($window['status'] ?? 'closed') !== 'closed') {
            throw new DomainException('Close the runtime window before changing its configuration.');
        }
        $counterNumberSql = smartqmsTableHasColumn($conn, 'service_windows', 'counter_number')
            ? 'counter_number = ?, '
            : '';
        $stmt = $conn->prepare("
            UPDATE service_windows
            SET {$counterNumberSql}window_name = ?, location_description = NULLIF(?, ''),
                window_type = ?, service_id = ?, is_active = ?,
                staff_id = NULL, status = 'closed'
            WHERE window_id = ? AND staff_id IS NULL AND status = 'closed'
        ");
        if ($counterNumberSql !== '') {
            $stmt->bind_param('isssiii', $counterNumber, $name, $location, $windowType, $serviceId, $active, $id);
        } else {
            $stmt->bind_param('sssiii', $name, $location, $windowType, $serviceId, $active, $id);
        }
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            $state = $conn->prepare('SELECT staff_id, status FROM service_windows WHERE window_id = ? LIMIT 1');
            $state->bind_param('i', $id);
            $state->execute();
            $current = $state->get_result()->fetch_assoc();
            if (!$current || $current['staff_id'] !== null || $current['status'] !== 'closed') {
                throw new DomainException('The runtime window changed before its configuration could be saved.');
            }
        }
        syncCounterServices($conn, $id, $input['service_ids']);
        logActivity($conn, 'window_updated', 'Updated window ' . $name);
        recordSecurityEvent($conn, 'service_window_updated', 'success', 'service_window', (string) $id);
        return true;
    }
    $counterNumber = nextCounterNumber($conn);
    if (smartqmsTableHasColumn($conn, 'service_windows', 'counter_number')) {
        $stmt = $conn->prepare("
            INSERT INTO service_windows
              (counter_number, window_name, location_description, window_type, service_id,
               staff_id, status, is_active)
            VALUES (?, ?, NULLIF(?, ''), ?, ?, NULL, 'closed', ?)
        ");
        $stmt->bind_param('isssii', $counterNumber, $name, $location, $windowType, $serviceId, $active);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO service_windows
              (window_name, location_description, window_type, service_id,
               staff_id, status, is_active)
            VALUES (?, NULLIF(?, ''), ?, ?, NULL, 'closed', ?)
        ");
        $stmt->bind_param('sssii', $name, $location, $windowType, $serviceId, $active);
    }
    $stmt->execute();
    $createdWindowId = (int) $conn->insert_id;
    syncCounterServices($conn, $createdWindowId, $input['service_ids']);
    logActivity($conn, 'window_created', 'Created window ' . $name);
    recordSecurityEvent($conn, 'service_window_created', 'success', 'service_window', (string) $createdWindowId);
    return false;
}

function listWindowServiceAssignments(mysqli $conn): array {
    return $conn->query("SELECT service_id, service_code, service_name, is_active FROM health_services WHERE is_active = 1 ORDER BY display_order, service_name")
        ->fetch_all(MYSQLI_ASSOC);
}

function nextCounterNumber(mysqli $conn): int {
    if (!smartqmsTableHasColumn($conn, 'service_windows', 'counter_number')) {
        return (int) ($conn->query('SELECT COALESCE(MAX(window_id), 0) + 1 AS next_value FROM service_windows')->fetch_assoc()['next_value'] ?? 1);
    }
    return (int) ($conn->query('SELECT COALESCE(MAX(counter_number), 0) + 1 AS next_value FROM service_windows')->fetch_assoc()['next_value'] ?? 1);
}

function syncCounterServices(mysqli $conn, int $counterId, array $serviceIds): void {
    if (!smartqmsTableExists($conn, 'counter_services')) return;
    $serviceIds = normalizeCapabilityIds($serviceIds);
    $delete = $conn->prepare('DELETE FROM counter_services WHERE counter_id = ?');
    $delete->bind_param('i', $counterId);
    $delete->execute();
    if (!$serviceIds) return;
    $insert = $conn->prepare('INSERT INTO counter_services (counter_id, service_id) VALUES (?, ?)');
    foreach ($serviceIds as $serviceId) {
        $insert->bind_param('ii', $counterId, $serviceId);
        $insert->execute();
    }
}

function listWindowStaffAssignments(mysqli $conn): array {
    return $conn->query("
        SELECT s.staff_id, CONCAT(u.first_name, ' ', u.last_name) AS staff_name
        FROM staff s JOIN users u ON u.user_id=s.user_id
        WHERE u.is_active=1 ORDER BY staff_name
    ")->fetch_all(MYSQLI_ASSOC);
}

function listAdminWindows(mysqli $conn): array {
    $mappingColumns = smartqmsTableExists($conn, 'counter_services')
        ? ", (SELECT GROUP_CONCAT(cs.service_id ORDER BY cs.service_id) FROM counter_services cs WHERE cs.counter_id = sw.window_id) AS service_ids,
             (SELECT GROUP_CONCAT(hsm.service_name ORDER BY hsm.display_order SEPARATOR ', ') FROM counter_services cs JOIN health_services hsm ON hsm.service_id = cs.service_id WHERE cs.counter_id = sw.window_id) AS mapped_service_names"
        : ", CAST(sw.service_id AS CHAR) AS service_ids, hs.service_name AS mapped_service_names";
    $rows = $conn->query("
        SELECT sw.*, hs.service_name, CONCAT(u.first_name, ' ', u.last_name) AS staff_name,
               CASE
                 WHEN sw.window_type = 'shared' THEN 'All services'
                 ELSE hs.service_name
               END AS queue_handled
               {$mappingColumns}
        FROM service_windows sw
        LEFT JOIN health_services hs ON hs.service_id=sw.service_id
        LEFT JOIN staff s ON s.staff_id=sw.staff_id
        LEFT JOIN users u ON u.user_id=s.user_id
        ORDER BY sw.window_id
    ")->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        $row['counter_number'] = (int) ($row['counter_number'] ?? $row['window_id']);
        $row['queue_handled'] = trim((string) ($row['mapped_service_names'] ?? '')) ?: $row['queue_handled'];
        $storedActive = (int) ($row['is_active'] ?? 1);
        $row['management_status'] = $storedActive === 2
            ? 'maintenance'
            : ($storedActive === 1 ? 'active' : 'inactive');
    }
    unset($row);
    return $rows;
}

function findAdminWindow(array $windows, int $windowId): ?array {
    foreach ($windows as $window) {
        if ((int) $window['window_id'] === $windowId) {
            return $window;
        }
    }
    return null;
}

function windowRowToForm(array $window): array {
    $storedActive = (int) ($window['is_active'] ?? 1);
    $managementStatus = (string) ($window['management_status'] ?? (
        $storedActive === 2 ? 'maintenance' : ($storedActive === 1 ? 'active' : 'inactive')
    ));
    return [
        'window_id' => (string) $window['window_id'],
        'counter_number' => (string) ($window['counter_number'] ?? $window['window_id']),
        'window_name' => (string) $window['window_name'],
        'location_description' => (string) ($window['location_description'] ?? ''),
        'window_type' => normalizeWindowType((string) ($window['window_type'] ?? 'shared')),
        'service_id' => (string) ($window['service_id'] ?? '0'),
        'service_ids' => normalizeCapabilityIds(array_filter(explode(',', (string) ($window['service_ids'] ?? $window['service_id'] ?? '')))),
        'is_active' => match (normalizeWindowManagementStatus($managementStatus)) {
            'inactive' => '0',
            'maintenance' => '2',
            default => '1',
        },
        'management_status' => normalizeWindowManagementStatus($managementStatus),
    ];
}
