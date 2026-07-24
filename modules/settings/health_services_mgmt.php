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
    $code = trim((string) ($_POST['service_code'] ?? ''));
    $name = trim((string) ($_POST['service_name'] ?? ''));
    $encoded = (int) ($_POST['service_encoded'] ?? 0);
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
    $serviceId = createHealthService($conn, $code, $name, $encoded, $description, $priority, $order, (int) $_SESSION['user_id']);
    logActivity($conn, 'service_added', $name);
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
    jsonResponse(true);
}

if ($action === 'toggle') {
    $id = (int) ($_POST['service_id'] ?? 0);
    if (!isPositiveIdentifier($id)) {
        jsonResponse(false, ['error' => 'Service id is required.'], 422);
    }
    $active = (int) ($_POST['is_active'] ?? 0);
    setHealthServiceActive($conn, $id, $active);
    logActivity($conn, 'service_toggled', 'service_id=' . $id);
    jsonResponse(true);
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
    jsonResponse(true, ['soft_deleted' => $result['soft_deleted']]);
}

jsonResponse(false, ['error' => 'Unsupported action.'], 400);
?>
