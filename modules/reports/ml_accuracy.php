<?php
// Report 8 -- Machine Learning Accuracy Report
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);
header('Content-Type: application/json');
// TODO: Use view v_ml_latest_comparison
// TODO: Return all algorithms with their metrics
// TODO: Flag is_best=1 row for frontend to highlight as Random Forest winner
?>
