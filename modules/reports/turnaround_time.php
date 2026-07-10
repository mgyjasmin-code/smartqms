<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/report_utils.php';

outputReportJson($conn, 'turnaround_time');
?>
