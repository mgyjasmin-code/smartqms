<?php
// Report 6 -- No-Show Report (voided and skipped tickets)
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);
header('Content-Type: application/json');
// TODO: Query queue_tickets WHERE status IN ('voided','skipped')
// TODO: Group by date, hour, service_type, voided_reason
?>
