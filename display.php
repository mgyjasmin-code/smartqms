<?php
/**
 * SmartQMS -- Public Queue Display Board Entry Point
 * No login required. Protected by token only.
 *
 * Usage:
 *   http://localhost/smartqms/display.php?token=YOUR_TOKEN
 *
 * Admin sets the token in System Settings -> Display Board section.
 * Staff opens this URL on the TV browser and leaves it running.
 */
require_once 'config/config.php';
require_once 'config/database.php';

$token = $_GET['token'] ?? '';

// Fetch token from system_settings
$stmt = $conn->prepare("SELECT setting_val FROM system_settings WHERE setting_key = 'display_board_token'");
$stmt->execute();
$savedToken = $stmt->get_result()->fetch_assoc()['setting_val'] ?? '';

if (empty($token) || empty($savedToken) || !hash_equals($savedToken, $token)) {
    http_response_code(403);
    die('<h2 style="font-family:sans-serif;text-align:center;margin-top:100px;color:#D85A30;">
         403 -- Invalid or missing display board token.<br>
         <small style="font-size:14px;color:#888;">Contact your administrator for the correct URL.</small>
         </h2>');
}

// Valid token -- show display board
require_once 'views/display/board.php';
?>
