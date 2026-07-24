<?php
/**
 * SmartQMS -- System Settings Manager
 * Admin reads and writes all values in system_settings table.
 * Handles GET (read all) and POST (update a setting).
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/settings_store.php';
requireLogin(ROLE_ADMIN);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $settings = groupSystemSettingsForJson(adminSystemSettings($conn));
    jsonResponse(true, ['data' => $settings]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf('', '', [], 'Security check failed. Please refresh the page and try again.', true);

    $key = trim($_POST['setting_key'] ?? '');
    $value = trim($_POST['setting_val'] ?? '');
    if ($key === '') {
        jsonResponse(false, [
            'error' => 'Please correct the highlighted field.',
            'field_errors' => [
                'setting_key' => 'Setting key is required.',
            ],
        ], 422);
    }
    updateAdminSetting($conn, $key, $value, (int) $_SESSION['user_id']);
    logActivity($conn, 'setting_updated', $key);
    jsonResponse(true, ['data' => ['setting_key' => $key, 'setting_val' => $value]]);
}

jsonResponse(false, ['error' => 'Unsupported method.'], 405);
?>
