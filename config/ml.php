<?php
/** Server-only Python prediction-service configuration. */

function smartqmsMlEnvironmentValue(string $key, string $default = ''): string {
    $value = getenv($key);
    if ($value === false && array_key_exists($key, $_ENV)) {
        $value = $_ENV[$key];
    }
    if ($value === false && array_key_exists($key, $_SERVER)) {
        $value = $_SERVER[$key];
    }
    return is_scalar($value) ? trim((string) $value) : $default;
}

function smartqmsValidMlUrl(string $url): bool {
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    return $scheme === 'https'
        || ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '::1'], true));
}

function smartqmsMlConfig(): array {
    static $config = null;
    if (is_array($config)) {
        return $config;
    }

    $local = [];
    $localPath = __DIR__ . '/ml.local.php';
    if ((!defined('APP_ENV') || APP_ENV !== 'production') && is_file($localPath)) {
        $loaded = require $localPath;
        $local = is_array($loaded) ? $loaded : [];
    }

    $legacyUrl = smartqmsMlEnvironmentValue('PYTHON_ML_URL', (string) ($local['url'] ?? 'http://127.0.0.1:5000'));
    $baseUrl = rtrim(smartqmsMlEnvironmentValue('SMARTQMS_ML_URL', $legacyUrl), '/');
    $timeout = (int) smartqmsMlEnvironmentValue(
        'PYTHON_ML_TIMEOUT_SECONDS',
        (string) ($local['timeout_seconds'] ?? 2)
    );
    $valid = smartqmsValidMlUrl($baseUrl);

    $config = [
        'configured' => $valid,
        'base_url' => $valid ? $baseUrl : '',
        'predict_url' => $valid ? $baseUrl . '/predict' : '',
        'health_url' => $valid ? $baseUrl . '/health' : '',
        'token' => smartqmsMlEnvironmentValue('PYTHON_ML_TOKEN', (string) ($local['token'] ?? '')),
        'timeout_seconds' => max(1, min(5, $timeout)),
    ];
    return $config;
}
