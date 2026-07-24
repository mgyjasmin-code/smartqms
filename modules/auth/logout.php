<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/auth_utils.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    redirectTo('index.php');
}

requireValidCsrf('index.php', 'login');
logActivity($conn, 'logout', 'User signed out');
destroyAuthenticatedSession();
header('Location: ' . APP_URL . '/index.php');
exit();
?>
