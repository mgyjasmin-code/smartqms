<?php
/**
 * Compatibility redirect for the retired display-board implementation.
 * Remove after the documented migration window.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
recordSecurityEvent($conn, 'legacy_route_used', 'redirected', 'route', 'display.php');
header('Deprecation: true');
header('Sunset: Sat, 05 Dec 2026 00:00:00 GMT');
header('Location: ' . APP_URL . '/public-display/', true, 308);
exit;
