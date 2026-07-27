<?php
/**
 * Service-window form validation, assignments, reads, and writes.
 */

function windowAdminDefaults(): array {
    return [
        'window_id' => '',
        'window_name' => '',
        'service_id' => '0',
        'staff_id' => '0',
        'status' => 'closed',
    ];
}

function windowAdminInput(array $source): array {
    return [
        'window_id' => (string) (int) ($source['window_id'] ?? 0),
        'window_name' => trim((string) ($source['window_name'] ?? '')),
        'service_id' => (string) (int) ($source['service_id'] ?? 0),
        'staff_id' => (string) (int) ($source['staff_id'] ?? 0),
        'status' => (string) ($source['status'] ?? 'closed'),
    ];
}

function validateWindowAdminInput(array $input): array {
    $errors = [];
    if (!hasRequiredText($input['window_name'])) {
        $errors['window_name'] = 'Window name is required.';
    }
    if (!isAllowedWindowStatus($input['status'])) {
        $errors['status'] = 'Choose a valid window status.';
    }
    return $errors;
}

function saveAdminWindow(mysqli $conn, array $input): bool {
    $id = (int) $input['window_id'];
    $name = $input['window_name'];
    $serviceId = (int) $input['service_id'];
    $staffId = (int) $input['staff_id'];
    $status = $input['status'];
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE service_windows SET window_name=?, service_id=NULLIF(?,0), staff_id=NULLIF(?,0), status=? WHERE window_id=?");
        $stmt->bind_param('siisi', $name, $serviceId, $staffId, $status, $id);
        $stmt->execute();
        logActivity($conn, 'window_updated', 'Updated window ' . $name);
        return true;
    }
    $stmt = $conn->prepare("INSERT INTO service_windows (window_name, service_id, staff_id, status) VALUES (?, NULLIF(?,0), NULLIF(?,0), ?)");
    $stmt->bind_param('siis', $name, $serviceId, $staffId, $status);
    $stmt->execute();
    logActivity($conn, 'window_created', 'Created window ' . $name);
    return false;
}

function listWindowServiceAssignments(mysqli $conn): array {
    return $conn->query("SELECT service_id, service_name FROM health_services WHERE is_active=1 ORDER BY display_order, service_name")->fetch_all(MYSQLI_ASSOC);
}

function listWindowStaffAssignments(mysqli $conn): array {
    return $conn->query("
        SELECT s.staff_id, CONCAT(u.first_name, ' ', u.last_name) AS staff_name
        FROM staff s JOIN users u ON u.user_id=s.user_id
        WHERE u.is_active=1 ORDER BY staff_name
    ")->fetch_all(MYSQLI_ASSOC);
}

function listAdminWindows(mysqli $conn): array {
    return $conn->query("
        SELECT sw.*, hs.service_name, CONCAT(u.first_name, ' ', u.last_name) AS staff_name
        FROM service_windows sw
        LEFT JOIN health_services hs ON hs.service_id=sw.service_id
        LEFT JOIN staff s ON s.staff_id=sw.staff_id
        LEFT JOIN users u ON u.user_id=s.user_id
        WHERE sw.is_active=1
        ORDER BY sw.window_id
    ")->fetch_all(MYSQLI_ASSOC);
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
    return [
        'window_id' => (string) $window['window_id'],
        'window_name' => (string) $window['window_name'],
        'service_id' => (string) ($window['service_id'] ?? '0'),
        'staff_id' => (string) ($window['staff_id'] ?? '0'),
        'status' => (string) $window['status'],
    ];
}
