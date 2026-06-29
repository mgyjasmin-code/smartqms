<?php
/**
 * Report 1 -- Queue Summary Report
 * Total tickets per day, per service type, per window.
 * Supports date range filter and CSV export.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);
header('Content-Type: application/json');
// TODO: Accept date_from, date_to from GET params
// TODO: Query queue_tickets JOIN health_services, service_windows
// TODO: Group by date, service_name, window_name
// TODO: Return JSON for Chart.js bar chart + table
?>
