<?php
// Report 5 -- Customer Turnaround Time Report
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);
header('Content-Type: application/json');
// TODO: Calculate TIMESTAMPDIFF(MINUTE, issued_at, completed_at) per ticket
// TODO: Group by day: avg turnaround, min, max
?>
