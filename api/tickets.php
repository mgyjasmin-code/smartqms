<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$principal = smartqmsApiPrincipal(['customer']);
if (smartqmsDataProviderMode() !== 'supabase') {
    if ($method !== 'GET') {
        jsonResponse(false, ['error' => 'Use the existing queue form while the local provider is active.'], 409);
    }
    $ticket = getActiveTicket($conn, (int) $principal['legacy_id']);
    if (!$ticket) jsonResponse(false, ['error' => 'No active ticket found.'], 404);
    $ticket['people_ahead'] = peopleAhead($conn, $ticket);
    jsonResponse(true, ['data' => $ticket, 'provider' => 'local']);
}

if ($method === 'GET') {
    $response = smartqmsSupabaseRequest(
        'GET',
        '/rest/v1/tickets?select=id,ticket_number,reference_number,status,customer_name,client_type,priority_level,created_at,called_at,served_at,predicted_wait_minutes,prediction_confidence,model_version,service:services(name),branch:branches(name,address),counter:counters(name)'
            . '&customer_id=eq.' . rawurlencode((string) $principal['id'])
            . '&status=in.(waiting,serving)&order=created_at.desc&limit=1'
    );
    $ticket = $response['ok'] && is_array($response['data']) ? ($response['data'][0] ?? null) : null;
    if (!$ticket) jsonResponse(false, ['error' => 'No active ticket found.'], 404);
    jsonResponse(true, ['data' => $ticket, 'provider' => 'supabase']);
}
if ($method !== 'POST') jsonResponse(false, ['error' => 'Method not allowed.'], 405);

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) jsonResponse(false, ['error' => 'A JSON request body is required.'], 400);
$serviceId = trim((string) ($payload['service_id'] ?? ''));
$branchId = trim((string) ($payload['branch_id'] ?? ''));
$clientType = normalizeQueueClientType((string) ($payload['client_type'] ?? 'regular'));
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f-]{27}$/i', $serviceId)
    || !preg_match('/^[0-9a-f]{8}-[0-9a-f-]{27}$/i', $branchId)) {
    jsonResponse(false, ['error' => 'Valid service_id and branch_id values are required.'], 422);
}

$service = smartqmsSupabaseSingle(
    'services',
    'select=id,ml_value,priority_only,active&id=eq.' . rawurlencode($serviceId) . '&active=eq.true&limit=1'
);
$branch = smartqmsSupabaseSingle(
    'branches',
    'select=id,active&id=eq.' . rawurlencode($branchId) . '&active=eq.true&limit=1'
);
if (!$service || !$branch) jsonResponse(false, ['error' => 'Choose an active service and branch.'], 422);
if (!empty($service['priority_only']) && $clientType === 'regular') {
    jsonResponse(false, ['error' => 'This service is not available for the selected client type.'], 422);
}

$waiting = smartqmsAdminRemoteRows(
    'tickets',
    'select=id,priority_level&branch_id=eq.' . rawurlencode($branchId)
        . '&service_id=eq.' . rawurlencode($serviceId) . '&status=eq.waiting&limit=500'
);
$counters = smartqmsAdminRemoteRows(
    'counters',
    'select=id,average_service_minutes,service_id,status,active&branch_id=eq.' . rawurlencode($branchId)
        . '&active=eq.true&status=in.(open,busy)&limit=100'
);
$eligibleCounters = array_values(array_filter($counters, static fn(array $counter): bool =>
    empty($counter['service_id']) || (string) $counter['service_id'] === $serviceId
));
$averageService = $eligibleCounters
    ? array_sum(array_map(static fn(array $counter): float => (float) ($counter['average_service_minutes'] ?? 5), $eligibleCounters)) / count($eligibleCounters)
    : 5.0;
$priorityLevel = queuePriorityLevelForClientType($clientType);
$fallback = fallbackWaitEstimate(count($waiting), count($eligibleCounters), $averageService, $priorityLevel);
$features = [
    'queue_length' => count($waiting),
    'hour_of_day' => (int) date('G'),
    'day_of_week' => (int) date('w'),
    'service_type_encoded' => (int) ($service['ml_value'] ?? 1),
    'client_type_encoded' => clientTypeEncoded($clientType),
    'active_windows' => max(1, count($eligibleCounters)),
    'avg_service_time' => max(0.1, $averageService),
];
$prediction = requestMlPrediction($features, 2);
$estimated = $prediction['estimated_wait_minutes'] ?? $fallback;
$response = smartqmsSupabaseRequest('POST', '/rest/v1/rpc/server_create_queue_ticket', [
    'p_customer_id' => $principal['id'],
    'p_service_id' => $serviceId,
    'p_branch_id' => $branchId,
    'p_customer_name' => $principal['name'] ?: 'SmartQMS Client',
    'p_customer_phone' => $principal['phone'] ?: null,
    'p_client_type' => $clientType,
    'p_reference_prefix' => REF_PREFIX,
    'p_predicted_wait_minutes' => $estimated,
    'p_prediction_confidence' => $prediction['confidence'] ?? null,
    'p_model_version' => $prediction['model_version'] ?? 'fallback-v1',
]);
if (!$response['ok']) {
    $status = $response['status'] === 409 ? 409 : 422;
    jsonResponse(false, ['error' => $response['error'] ?: 'Ticket could not be created.'], $status);
}
jsonResponse(true, [
    'data' => $response['data'],
    'prediction' => [
        'estimated_wait_minutes' => (float) $estimated,
        'confidence' => $prediction['confidence'] ?? null,
        'model_version' => $prediction['model_version'] ?? 'fallback-v1',
        'source' => $prediction ? 'ml' : 'fallback',
    ],
    'provider' => 'supabase',
], 201);
