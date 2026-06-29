<?php
// Report 10 -- Customer Satisfaction Report
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);
header('Content-Type: application/json');
// TODO: Query feedback JOIN queue_tickets, health_services, service_windows
// TODO: Return avg rating per service type, per window, per day
// TODO: Return overall avg rating for the period
?>
