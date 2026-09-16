<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(false, ['error' => 'Method not allowed.'], 405);
}

try {
    jsonResponse(true, [
        'data' => smartqmsListServices($conn),
        'provider' => smartqmsDataProviderMode(),
    ]);
} catch (Throwable $error) {
    error_log('SmartQMS service catalog provider failure: ' . $error->getMessage());
    jsonResponse(false, ['error' => 'Service catalog is temporarily unavailable.'], 503);
}
