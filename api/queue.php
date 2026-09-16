<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(false, ['error' => 'Method not allowed.'], 405);
}
$principal = smartqmsApiPrincipal(['customer', 'staff', 'admin', 'super_admin']);
$branchId = trim((string) ($_GET['branch_id'] ?? ''));
if (smartqmsDataProviderMode() === 'supabase') {
    $token = smartqmsBearerToken();
    $response = smartqmsSupabaseAuthRequest(
        'POST', '/rest/v1/rpc/get_live_queue_snapshot',
        ['p_branch_id' => $branchId !== '' ? $branchId : null], $token
    );
    if (!$response['ok']) jsonResponse(false, ['error' => 'Live queue is unavailable.'], 503);
    jsonResponse(true, ['data' => $response['data'], 'provider' => 'supabase']);
}
require_once __DIR__ . '/../modules/queue/status_queries.php';
jsonResponse(true, ['data' => [
    'windows' => queueStatusWindows($conn),
    'next' => queueStatusNextTickets($conn),
    'viewer_ticket' => queueStatusViewerTicket($conn),
], 'provider' => 'local']);
