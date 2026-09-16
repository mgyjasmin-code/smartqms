<?php
/**
 * Small server-only Supabase REST/RPC client.
 *
 * The transport is injectable for tests. Responses never include configured
 * credentials or raw cURL diagnostics that could contain authorization data.
 */

function smartqmsSupabaseRequest(
    string $method,
    string $path,
    ?array $body = null,
    array $headers = [],
    ?callable $transport = null
): array {
    $config = smartqmsSupabaseConfig();
    if (empty($config['server_configured'])) {
        return ['ok' => false, 'status' => 503, 'data' => null, 'error' => 'Supabase is not configured.'];
    }

    $path = '/' . ltrim($path, '/');
    if (str_contains($path, "\r") || str_contains($path, "\n")) {
        return ['ok' => false, 'status' => 400, 'data' => null, 'error' => 'Invalid Supabase request path.'];
    }

    $request = [
        'method' => strtoupper($method),
        'url' => $config['url'] . $path,
        'headers' => array_merge([
            'Accept: application/json',
            'Content-Type: application/json',
            'apikey: ' . $config['service_role_key'],
            'Authorization: Bearer ' . $config['service_role_key'],
        ], $headers),
        'body' => $body,
        'timeout' => (int) $config['request_timeout_seconds'],
    ];

    if ($transport !== null) {
        $response = $transport($request);
        return smartqmsNormalizeSupabaseResponse(is_array($response) ? $response : []);
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 503, 'data' => null, 'error' => 'Supabase transport is unavailable.'];
    }

    $handle = curl_init($request['url']);
    curl_setopt_array($handle, [
        CURLOPT_CUSTOMREQUEST => $request['method'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $request['headers'],
        CURLOPT_CONNECTTIMEOUT => $request['timeout'],
        CURLOPT_TIMEOUT => $request['timeout'],
    ]);
    if ($body !== null) {
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
    }

    $raw = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $failed = $raw === false;
    curl_close($handle);

    if ($failed) {
        return ['ok' => false, 'status' => 503, 'data' => null, 'error' => 'Supabase request failed.'];
    }

    $decoded = json_decode((string) $raw, true);
    return smartqmsNormalizeSupabaseResponse([
        'status' => $status,
        'data' => json_last_error() === JSON_ERROR_NONE ? $decoded : null,
    ]);
}

function smartqmsNormalizeSupabaseResponse(array $response): array {
    $status = (int) ($response['status'] ?? 0);
    $data = $response['data'] ?? null;
    $ok = $status >= 200 && $status < 300;
    $error = '';
    if (!$ok) {
        $error = is_array($data)
            ? (string) ($data['message'] ?? $data['error_description'] ?? $data['error'] ?? 'Supabase request failed.')
            : 'Supabase request failed.';
    }

    return [
        'ok' => $ok,
        'status' => $status > 0 ? $status : 503,
        'data' => $data,
        'error' => $error,
    ];
}

/**
 * Call a Supabase Auth endpoint as the browser principal, never as the
 * service-role principal. Used by protected compatibility APIs.
 */
function smartqmsSupabaseAuthRequest(
    string $method,
    string $path,
    ?array $body = null,
    string $accessToken = '',
    ?callable $transport = null
): array {
    $config = smartqmsSupabaseConfig();
    if (empty($config['browser_configured'])) {
        return ['ok' => false, 'status' => 503, 'data' => null, 'error' => 'Supabase Auth is not configured.'];
    }
    $path = '/' . ltrim($path, '/');
    if (str_contains($path, "\r") || str_contains($path, "\n")) {
        return ['ok' => false, 'status' => 400, 'data' => null, 'error' => 'Invalid Supabase Auth path.'];
    }
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'apikey: ' . $config['publishable_key'],
    ];
    if ($accessToken !== '') $headers[] = 'Authorization: Bearer ' . $accessToken;
    $request = [
        'method' => strtoupper($method),
        'url' => $config['url'] . $path,
        'headers' => $headers,
        'body' => $body,
        'timeout' => (int) $config['request_timeout_seconds'],
    ];
    if ($transport !== null) {
        $response = $transport($request);
        return smartqmsNormalizeSupabaseResponse(is_array($response) ? $response : []);
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 503, 'data' => null, 'error' => 'Supabase Auth transport is unavailable.'];
    }
    $handle = curl_init($request['url']);
    curl_setopt_array($handle, [
        CURLOPT_CUSTOMREQUEST => $request['method'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => $request['timeout'],
        CURLOPT_TIMEOUT => $request['timeout'],
    ]);
    if ($body !== null) curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
    $raw = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $failed = $raw === false;
    curl_close($handle);
    if ($failed) {
        return ['ok' => false, 'status' => 503, 'data' => null, 'error' => 'Supabase Auth request failed.'];
    }
    $decoded = json_decode((string) $raw, true);
    return smartqmsNormalizeSupabaseResponse([
        'status' => $status,
        'data' => json_last_error() === JSON_ERROR_NONE ? $decoded : null,
    ]);
}
