<?php
/**
 * SmartQMS -- Generic CSV Export
 * Called by all report pages with report type + date range.
 * Triggers file download in browser.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);

$report = $_GET['report'] ?? 'queue_summary';
$from   = $_GET['from']   ?? date('Y-m-01');
$to     = $_GET['to']     ?? date('Y-m-d');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="smartqms_' . $report . '_' . $from . '_' . $to . '.csv"');

// TODO: Based on $report value, run the correct query
// TODO: Output headers row followed by data rows
// TODO: Use fputcsv() for clean CSV output
$output = fopen('php://output', 'w');
fputcsv($output, ['SmartQMS Report:', $report, 'From:', $from, 'To:', $to]);
// TODO: Add data rows
fclose($output);
?>
