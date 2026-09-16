<?php
/**
 * Fail-safe Supabase configuration.
 *
 * Runtime defaults to the existing local database. Remote modes become
 * effective only when both the project URL and server-only service-role key
 * are available. Browser configuration is deliberately projected through a
 * separate allowlist.
 */

function smartqmsEnvironmentValue(string $key, string $default = ''): string {
    $value = getenv($key);
    if ($value === false && array_key_exists($key, $_ENV)) {
        $value = $_ENV[$key];
    }
    if ($value === false && array_key_exists($key, $_SERVER)) {
        $value = $_SERVER[$key];
    }

    return is_scalar($value) ? trim((string) $value) : $default;
}

function smartqmsValidSupabaseUrl(string $url): bool {
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));

    return $scheme === 'https'
        || ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '::1'], true));
}

function smartqmsSupabaseConfig(): array {
    static $config = null;
    if (is_array($config)) {
        return $config;
    }

    $local = [];
    $localPath = __DIR__ . '/supabase.local.php';
    if ((!defined('APP_ENV') || APP_ENV !== 'production') && is_file($localPath)) {
        $loaded = require $localPath;
        $local = is_array($loaded) ? $loaded : [];
    }

    $requestedMode = strtolower(smartqmsEnvironmentValue(
        'SMARTQMS_DATA_PROVIDER',
        (string) ($local['provider'] ?? 'local')
    ));
    if (!in_array($requestedMode, ['local', 'shadow', 'supabase'], true)) {
        $requestedMode = 'local';
    }
    if (defined('APP_ENV') && APP_ENV === 'production' && $requestedMode !== 'local') {
        throw new RuntimeException('The first production release requires SMARTQMS_DATA_PROVIDER=local.');
    }

    $url = rtrim(smartqmsEnvironmentValue('SUPABASE_URL', (string) ($local['url'] ?? '')), '/');
    $publishableKey = smartqmsEnvironmentValue(
        'SUPABASE_PUBLISHABLE_KEY',
        smartqmsEnvironmentValue('SUPABASE_ANON_KEY', (string) ($local['publishable_key'] ?? ''))
    );
    $serviceRoleKey = smartqmsEnvironmentValue(
        'SUPABASE_SERVICE_ROLE_KEY',
        (string) ($local['service_role_key'] ?? '')
    );
    $timeout = (int) smartqmsEnvironmentValue(
        'SUPABASE_REQUEST_TIMEOUT_SECONDS',
        (string) ($local['request_timeout_seconds'] ?? 5)
    );
    $timeout = max(1, min(30, $timeout));

    $validUrl = smartqmsValidSupabaseUrl($url);
    $browserConfigured = $validUrl && $publishableKey !== '';
    $serverConfigured = $validUrl && $serviceRoleKey !== '';
    $requestedModeConfigured = $requestedMode === 'shadow'
        ? $serverConfigured
        : ($requestedMode === 'supabase' ? $serverConfigured && $browserConfigured : true);
    $effectiveMode = $requestedModeConfigured ? $requestedMode : 'local';

    $config = [
        'requested_mode' => $requestedMode,
        'mode' => $effectiveMode,
        'url' => $validUrl ? $url : '',
        'publishable_key' => $publishableKey,
        'service_role_key' => $serviceRoleKey,
        'jwt_audience' => smartqmsEnvironmentValue(
            'SUPABASE_JWT_AUDIENCE',
            (string) ($local['jwt_audience'] ?? 'authenticated')
        ),
        'browser_configured' => $browserConfigured,
        'server_configured' => $serverConfigured,
        'request_timeout_seconds' => $timeout,
    ];

    return $config;
}

function smartqmsBrowserRuntimeConfig(): array {
    $config = smartqmsSupabaseConfig();
    $remoteBrowserEnabled = $config['mode'] === 'supabase' && $config['browser_configured'];
    $accessToken = '';
    if ($remoteBrowserEnabled) {
        $accessToken = function_exists('smartqmsSupabaseSessionAccessToken')
            ? smartqmsSupabaseSessionAccessToken()
            : (is_string($_SESSION['supabase_access_token'] ?? null)
                ? $_SESSION['supabase_access_token']
                : '');
    }

    return [
        'provider' => $config['mode'],
        'baseUrl' => defined('APP_URL') ? APP_URL : '',
        'supabase' => [
            'enabled' => $remoteBrowserEnabled,
            'url' => $remoteBrowserEnabled ? $config['url'] : '',
            'publishableKey' => $remoteBrowserEnabled ? $config['publishable_key'] : '',
            'accessToken' => $accessToken,
        ],
        'endpoints' => [
            'services' => (defined('APP_URL') ? APP_URL : '') . '/api/services.php',
            'branches' => (defined('APP_URL') ? APP_URL : '') . '/api/branches.php',
            'createTicket' => (defined('APP_URL') ? APP_URL : '') . '/api/tickets.php',
            'ticket' => (defined('APP_URL') ? APP_URL : '') . '/api/tickets.php',
            'liveQueue' => (defined('APP_URL') ? APP_URL : '') . '/modules/queue/status.php',
            'callNext' => (defined('APP_URL') ? APP_URL : '') . '/modules/service_window/call_next.php',
            'completeTicket' => (defined('APP_URL') ? APP_URL : '') . '/modules/service_window/complete_ticket.php',
            'skipTicket' => (defined('APP_URL') ? APP_URL : '') . '/modules/service_window/skip_ticket.php',
            'voidTicket' => (defined('APP_URL') ? APP_URL : '') . '/modules/service_window/void_ticket.php',
            'counterStatus' => (defined('APP_URL') ? APP_URL : '') . '/modules/service_window/window_status.php',
        ],
    ];
}
