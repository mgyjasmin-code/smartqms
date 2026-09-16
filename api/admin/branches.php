<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$principal = smartqmsApiPrincipal(['admin', 'super_admin']);
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'GET') {
    try {
        jsonResponse(true, ['data' => smartqmsAdminListBranches($conn, true)]);
    } catch (Throwable $error) {
        jsonResponse(false, ['error' => 'Branches are unavailable.'], 503);
    }
}
if ($method !== 'POST') jsonResponse(false, ['error' => 'Method not allowed.'], 405);
if (smartqmsDataProviderMode() !== 'supabase') requireValidCsrf('', '', [], 'Security check failed.', true);
$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) $payload = $_POST;
$name = trim((string) ($payload['name'] ?? ''));
if ($name === '' || strlen($name) > 120) jsonResponse(false, ['error' => 'Branch name is required and must be 120 characters or fewer.'], 422);
try {
    $branch = smartqmsAdminSaveBranch(
        $conn, (int) ($principal['legacy_id'] ?? 0),
        trim((string) ($payload['branch_id'] ?? '')) ?: null,
        $name, trim((string) ($payload['address'] ?? '')), !empty($payload['active'])
    );
    jsonResponse(true, ['data' => $branch]);
} catch (DomainException $error) {
    jsonResponse(false, ['error' => $error->getMessage()], 409);
} catch (Throwable $error) {
    jsonResponse(false, ['error' => 'Branch could not be saved.'], 422);
}
