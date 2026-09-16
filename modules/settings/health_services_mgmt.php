<?php
/**
 * SmartQMS -- Health Services Management
 * Admin adds, edits, deactivates, and reorders health services.
 * These are the options shown in the client queue dropdown.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/service_catalog.php';
requireLogin(ROLE_ADMIN);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = listHealthServices($conn);
    jsonResponse(true, ['data' => $rows]);
}

requirePostRequest(true);
requireValidCsrf('', '', [], 'Security check failed. Please refresh the page and try again.', true);

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $name = trim((string) ($_POST['service_name'] ?? ''));
    $encoded = 0;
    $fieldErrors = validateServiceJsonAdd($_POST);
    if ($fieldErrors) {
        jsonResponse(false, [
            'error' => 'Please correct the highlighted fields.',
            'field_errors' => $fieldErrors,
        ], 422);
    }
    $priority = (int) ($_POST['priority_only'] ?? 0);
    $order = (int) ($_POST['display_order'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $queueMode = normalizeQueueMode((string) ($_POST['queue_mode'] ?? 'central'));
    $serviceId = createHealthService(
        $conn,
        '',
        $name,
        $encoded,
        $description,
        $priority,
        $order,
        (int) $_SESSION['user_id'],
        1,
        $queueMode
    );
    logActivity($conn, 'service_created', $name);
    recordSecurityEvent($conn, 'health_service_created', 'success', 'health_service', (string) $serviceId);
    jsonResponse(true, ['data' => ['service_id' => $serviceId]]);
}

if ($action === 'edit') {
    $name = trim($_POST['service_name'] ?? '');
    $fieldErrors = validateServiceJsonEdit($_POST);
    if ($fieldErrors) {
        jsonResponse(false, [
            'error' => 'Please correct the highlighted fields.',
            'field_errors' => $fieldErrors,
        ], 422);
    }
    updateHealthServiceFromJson($conn, $_POST);
    logActivity($conn, 'service_updated', $name);
    recordSecurityEvent($conn, 'health_service_updated', 'success', 'health_service', (string) ((int) $_POST['service_id']));
    jsonResponse(true);
}

if ($action === 'toggle') {
    $id = (int) ($_POST['service_id'] ?? 0);
    if (!isPositiveIdentifier($id)) {
        jsonResponse(false, ['error' => 'Service id is required.'], 422);
    }
    $active = (int) ($_POST['is_active'] ?? 0);
    setHealthServiceActive($conn, $id, $active);
    logActivity($conn, 'service_availability_changed', 'service_id=' . $id . ', enabled=' . $active);
    recordSecurityEvent($conn, 'health_service_status_changed', 'success', 'health_service', (string) $id, [
        'reason' => $active === 1 ? 'activated' : 'deactivated',
    ]);
    jsonResponse(true);
}

if ($action === 'reorder') {
    $id = (int) ($_POST['service_id'] ?? 0);
    $direction = (string) ($_POST['direction'] ?? '');
    if (!isPositiveIdentifier($id) || !in_array($direction, ['up', 'down'], true)) {
        jsonResponse(false, ['error' => 'Choose a valid service ordering action.'], 422);
    }
    jsonResponse(true, ['moved' => moveHealthService($conn, $id, $direction)]);
}

if ($action === 'delete') {
    $id = (int) ($_POST['service_id'] ?? 0);
    if (!isPositiveIdentifier($id)) {
        jsonResponse(false, ['error' => 'Service id is required.'], 422);
    }

    $result = deleteOrDeactivateHealthService($conn, $id);
    if (!$result) {
        jsonResponse(false, ['error' => 'Service could not be found.'], 404);
    }

    logActivity($conn, 'service_deleted', (string) $result['service']['service_name']);
    recordSecurityEvent($conn, 'health_service_removed', 'success', 'health_service', (string) $id, [
        'reason' => $result['soft_deleted'] ? 'deactivated' : 'deleted',
    ]);
    jsonResponse(true, ['soft_deleted' => $result['soft_deleted']]);
}

jsonResponse(false, ['error' => 'Unsupported action.'], 400);
?>
