<?php
/** Authenticated, secret-minimized deployment health snapshot. */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(false, ['error' => 'Method not allowed.'], 405);
}
smartqmsApiPrincipal(['admin', 'super_admin']);

$checks = [
    'database' => ['status' => 'unavailable'],
    'migration' => ['status' => 'missing'],
    'storage' => ['status' => 'unavailable'],
    'notification_worker' => ['status' => 'unknown'],
    'prediction_service' => ['status' => 'disabled'],
];

try {
    $conn->query('SELECT 1');
    $checks['database']['status'] = 'ready';

    $migrationId = '20260905_001_production_security';
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM schema_migrations WHERE migration_id = ?');
    $stmt->bind_param('s', $migrationId);
    $stmt->execute();
    $checks['migration']['status'] = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0) === 1
        ? 'ready'
        : 'missing';

    $jobs = $conn->query("
        SELECT
          SUM(status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)) AS stale,
          SUM(status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)) AS recent_failed
        FROM email_jobs
    ")->fetch_assoc();
    $stale = (int) ($jobs['stale'] ?? 0);
    $recentFailed = (int) ($jobs['recent_failed'] ?? 0);
    $checks['notification_worker'] = [
        'status' => ($stale > 0 || $recentFailed > 0) ? 'attention' : 'ready',
        'stale_jobs' => $stale,
        'recent_failures' => $recentFailed,
    ];
} catch (Throwable $error) {
    // The public response stays generic; the request ID links it to server logs.
    error_log('Deployment health database check failed [' . SMARTQMS_CORRELATION_ID . ']: ' . $error->getMessage());
}

$checks['storage']['status'] = is_dir(SESSION_DIR) && is_writable(SESSION_DIR)
    && is_dir(LOG_DIR) && is_writable(LOG_DIR)
    ? 'ready'
    : 'unavailable';

$ml = smartqmsMlConfig();
if (!empty($ml['configured'])) {
    $checks['prediction_service']['status'] = !empty($ml['token']) ? 'configured' : 'misconfigured';
}

$ready = $checks['database']['status'] === 'ready'
    && $checks['migration']['status'] === 'ready'
    && $checks['storage']['status'] === 'ready'
    && $checks['notification_worker']['status'] !== 'attention'
    && $checks['prediction_service']['status'] !== 'misconfigured';

recordSecurityEvent($conn, 'deployment_health_viewed', $ready ? 'success' : 'attention');
jsonResponse(true, [
    'data' => [
        'status' => $ready ? 'ready' : 'attention',
        'checks' => $checks,
        'request_id' => SMARTQMS_CORRELATION_ID,
    ],
]);
