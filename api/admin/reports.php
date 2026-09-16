<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../modules/reports/report_utils.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') jsonResponse(false, ['error' => 'Method not allowed.'], 405);
smartqmsApiPrincipal(['admin', 'super_admin']);
try {
    $range = reportDateRange($_GET);
    $key = normalizeReportKey((string) ($_GET['report'] ?? 'queue_summary'));
    jsonResponse(true, ['data' => buildReport($conn, $key, $range)]);
} catch (InvalidArgumentException $error) {
    jsonResponse(false, ['error' => $error->getMessage()], 422);
} catch (Throwable $error) {
    jsonResponse(false, ['error' => 'Could not build report.'], 500);
}
