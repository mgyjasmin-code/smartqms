<?php
// Live queue status page for client (AJAX-updated)
require_once '../../config/config.php';
requireLogin(ROLE_CLIENT);
// TODO: Render queue status, JS polls status.php every 10 seconds
?>