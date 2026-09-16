<?php
require_once __DIR__ . '/../../config/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jsonResponse(false, ['error' => 'Method not allowed.'], 405);
}
if (smartqmsDataProviderMode() !== 'supabase') {
    jsonResponse(false, ['error' => 'Supabase Auth is not enabled.'], 409);
}
$principal = smartqmsSupabasePrincipal(smartqmsBearerToken());
if (!$principal) jsonResponse(false, ['error' => 'A valid Supabase access token is required.'], 401);
jsonResponse(true, ['data' => $principal, 'provider' => 'supabase']);
