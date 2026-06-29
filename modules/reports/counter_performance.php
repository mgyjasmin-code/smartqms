<?php
// Report 4 -- Service Counter Performance Report
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);
header('Content-Type: application/json');
// TODO: Query wait_time_logs + service_windows
// TODO: Return per window: tickets served, avg wait time, avg service duration
?>
