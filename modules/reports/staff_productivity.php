<?php
// Report 7 -- Staff Productivity Report
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);
header('Content-Type: application/json');
// TODO: Use view v_staff_productivity_today or query by date range
// TODO: Return per staff: tickets_served, avg_wait_min, avg_service_min
?>
