<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$principal = smartqmsApiPrincipal(['super_admin']);
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'GET') {
    try {
        jsonResponse(true, ['data' => smartqmsAdminListRoleAssignments()]);
    } catch (Throwable $error) {
        jsonResponse(false, ['error' => 'Role assignments are unavailable.'], 503);
    }
}
if (!in_array($method, ['POST', 'DELETE'], true)) jsonResponse(false, ['error' => 'Method not allowed.'], 405);
if (smartqmsDataProviderMode() !== 'supabase') requireValidCsrf('', '', [], 'Security check failed.', true);
$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) $payload = $_POST;
$target = trim((string) ($payload['user_id'] ?? ''));
$role = strtolower(trim((string) ($payload['role'] ?? '')));
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f-]{27}$/i', $target)
    || !in_array($role, ['customer', 'staff', 'admin', 'super_admin'], true)) {
    jsonResponse(false, ['error' => 'A valid user_id and supported role are required.'], 422);
}
try {
    $result = smartqmsAdminSetRole(
        (int) ($principal['legacy_id'] ?? 0), $target, $role, $method === 'POST'
    );
    jsonResponse(true, ['data' => $result]);
} catch (Throwable $error) {
    jsonResponse(false, ['error' => 'Role assignment could not be changed.'], 403);
}
