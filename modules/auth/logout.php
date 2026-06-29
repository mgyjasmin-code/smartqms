<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

logActivity($conn, 'logout', 'User signed out');
session_unset();
session_destroy();
header('Location: ' . APP_URL . '/index.php?msg=logged_out');
exit();
?>
