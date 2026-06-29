<?php
/**
 * SmartQMS -- System Settings Manager
 * Admin reads and writes all values in system_settings table.
 * Handles GET (read all) and POST (update a setting).
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $settings = [];
    $result = $conn->query("SELECT setting_key, setting_val, label, section FROM system_settings ORDER BY section, setting_id");
    while ($row = $result->fetch_assoc()) {
        $section = $row['section'] ?: 'other';
        $settings[$section][$row['setting_key']] = [
            'value' => $row['setting_val'],
            'label' => $row['label'],
        ];
    }
    jsonResponse(true, ['data' => $settings]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $key = trim($_POST['setting_key'] ?? '');
    $value = trim($_POST['setting_val'] ?? '');
    if ($key === '') {
        jsonResponse(false, ['error' => 'Missing setting key.'], 422);
    }
    $stmt = $conn->prepare("UPDATE system_settings SET setting_val=?, updated_by=? WHERE setting_key=?");
    $stmt->bind_param('sis', $value, $_SESSION['user_id'], $key);
    $stmt->execute();
    logActivity($conn, 'setting_updated', $key);
    jsonResponse(true, ['data' => ['setting_key' => $key, 'setting_val' => $value]]);
}

jsonResponse(false, ['error' => 'Unsupported method.'], 405);
?>
