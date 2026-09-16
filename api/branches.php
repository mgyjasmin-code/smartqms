<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(false, ['error' => 'Method not allowed.'], 405);
}

try {
    jsonResponse(true, [
        'data' => smartqmsListBranches($conn),
        'provider' => smartqmsDataProviderMode(),
    ]);
} catch (Throwable $error) {
    error_log('SmartQMS branch catalog provider failure: ' . $error->getMessage());
    jsonResponse(false, ['error' => 'Branch catalog is temporarily unavailable.'], 503);
}
