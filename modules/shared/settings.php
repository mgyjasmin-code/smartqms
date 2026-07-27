<?php
/**
 * Shared system-setting access helpers.
 */

function getSetting(mysqli $conn, string $key, string $default = ''): string {
    $stmt = $conn->prepare("SELECT setting_val FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row['setting_val'] ?? $default;
}
