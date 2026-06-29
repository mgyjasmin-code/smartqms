<?php
// ML Prediction Logs -- predicted vs actual wait time per ticket
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);
// TODO: Table from wait_time_logs, color-code accuracy difference
?>