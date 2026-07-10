<?php
/**
 * SmartQMS -- Health Services Management
 * Admin adds, edits, deactivates, and reorders health services.
 * These are the options shown in the client queue dropdown.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $conn->query("SELECT * FROM health_services ORDER BY display_order, service_name")->fetch_all(MYSQLI_ASSOC);
    jsonResponse(true, ['data' => $rows]);
}

requirePostRequest(true);
requireValidCsrf('', '', [], 'Security check failed. Please refresh the page and try again.', true);

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $code = trim($_POST['service_code'] ?? '');
    $name = trim($_POST['service_name'] ?? '');
    $encoded = (int) ($_POST['service_encoded'] ?? 0);
    if ($code === '' || $name === '' || $encoded <= 0) {
        $fieldErrors = [];
        if ($code === '') {
            $fieldErrors['service_code'] = 'Service code is required.';
        }
        if ($name === '') {
            $fieldErrors['service_name'] = 'Service name is required.';
        }
        if ($encoded <= 0) {
            $fieldErrors['service_encoded'] = 'Encoded value is required.';
        }
        jsonResponse(false, [
            'error' => 'Please correct the highlighted fields.',
            'field_errors' => $fieldErrors,
        ], 422);
    }
    $priority = (int) ($_POST['priority_only'] ?? 0);
    $order = (int) ($_POST['display_order'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $stmt = $conn->prepare("INSERT INTO health_services (service_code, service_name, service_encoded, description, priority_only, display_order, created_by) VALUES (?, ?, ?, NULLIF(?, ''), ?, ?, ?)");
    $stmt->bind_param('ssisiii', $code, $name, $encoded, $description, $priority, $order, $_SESSION['user_id']);
    $stmt->execute();
    logActivity($conn, 'service_added', $name);
    jsonResponse(true, ['data' => ['service_id' => $conn->insert_id]]);
}

if ($action === 'edit') {
    $id = (int) ($_POST['service_id'] ?? 0);
    $name = trim($_POST['service_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority = (int) ($_POST['priority_only'] ?? 0);
    $order = (int) ($_POST['display_order'] ?? 0);
    if ($id <= 0 || $name === '') {
        $fieldErrors = [];
        if ($id <= 0) {
            $fieldErrors['service_id'] = 'Service id is required.';
        }
        if ($name === '') {
            $fieldErrors['service_name'] = 'Service name is required.';
        }
        jsonResponse(false, [
            'error' => 'Please correct the highlighted fields.',
            'field_errors' => $fieldErrors,
        ], 422);
    }
    $stmt = $conn->prepare("UPDATE health_services SET service_name=?, description=NULLIF(?, ''), priority_only=?, display_order=? WHERE service_id=?");
    $stmt->bind_param('ssiii', $name, $description, $priority, $order, $id);
    $stmt->execute();
    logActivity($conn, 'service_updated', $name);
    jsonResponse(true);
}

if ($action === 'toggle') {
    $id = (int) ($_POST['service_id'] ?? 0);
    $active = (int) ($_POST['is_active'] ?? 0);
    $stmt = $conn->prepare("UPDATE health_services SET is_active=? WHERE service_id=?");
    $stmt->bind_param('ii', $active, $id);
    $stmt->execute();
    logActivity($conn, 'service_toggled', 'service_id=' . $id);
    jsonResponse(true);
}

jsonResponse(false, ['error' => 'Unsupported action.'], 400);
?>
