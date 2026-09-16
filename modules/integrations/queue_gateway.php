<?php
/**
 * Provider-neutral staff queue reads and mutations.
 *
 * Local mode delegates to the characterized MySQL transaction layer. Shadow
 * mode keeps MySQL authoritative and mirrors immutable queue events. Supabase
 * mode invokes transaction-safe PostgreSQL functions through the server-only
 * service-role client; browser code never receives that credential.
 */

function smartqmsSupabaseSingle(string $table, string $query): ?array {
    $response = smartqmsSupabaseRequest(
        'GET',
        '/rest/v1/' . rawurlencode($table) . '?' . $query,
        null,
        ['Accept: application/vnd.pgrst.object+json']
    );
    return $response['ok'] && is_array($response['data']) ? $response['data'] : null;
}

function smartqmsSupabaseProfileIdForLegacy(int $legacyUserId): string {
    $row = smartqmsSupabaseSingle(
        'profiles',
        'select=id&legacy_id=eq.' . $legacyUserId . '&limit=1'
    );
    return trim((string) ($row['id'] ?? ''));
}

function smartqmsSupabaseTicketForLegacy(int $legacyTicketId): ?array {
    return smartqmsSupabaseSingle(
        'tickets',
        'select=id,branch_id,service_id,counter_id,ticket_number,legacy_id,status&legacy_id=eq.'
            . $legacyTicketId . '&limit=1'
    );
}

function smartqmsMirrorQueueEvent(
    string $eventType,
    int $legacyUserId,
    ?int $legacyTicketId,
    array $metadata = []
): bool {
    if (smartqmsDataProviderMode() !== 'shadow') {
        return true;
    }

    $ticket = $legacyTicketId ? smartqmsSupabaseTicketForLegacy($legacyTicketId) : null;
    $actorId = smartqmsSupabaseProfileIdForLegacy($legacyUserId);
    $response = smartqmsSupabaseRequest('POST', '/rest/v1/queue_events', [[
        'ticket_id' => $ticket['id'] ?? null,
        'branch_id' => $ticket['branch_id'] ?? null,
        'event_type' => $eventType,
        'actor_user_id' => $actorId !== '' ? $actorId : null,
        'metadata' => array_merge([
            'provider' => 'smartqms-shadow',
            'legacy_ticket_id' => $legacyTicketId,
        ], $metadata),
    ]], ['Prefer: return=minimal']);

    return (bool) $response['ok'];
}

function smartqmsNormalizeRemoteStaffResult(array $response): array {
    if (!$response['ok']) {
        throw new RuntimeException($response['error'] ?: 'The Supabase queue operation failed.');
    }
    $data = $response['data'];
    if (is_array($data) && array_is_list($data)) {
        $data = $data[0] ?? [];
    }
    if (!is_array($data)) {
        throw new RuntimeException('The Supabase queue operation returned an invalid response.');
    }
    return $data;
}

function smartqmsCallNextForStaff(mysqli $conn, int $staffId, int $legacyUserId): array {
    if (smartqmsDataProviderMode() === 'supabase') {
        return smartqmsNormalizeRemoteStaffResult(smartqmsSupabaseRequest(
            'POST',
            '/rest/v1/rpc/staff_call_next',
            ['p_actor_legacy_id' => $legacyUserId]
        ));
    }

    $result = callNextTicketForStaff($conn, $staffId);
    if (($result['status'] ?? '') === 'success') {
        smartqmsMirrorQueueEvent(
            'called',
            $legacyUserId,
            (int) ($result['ticket']['ticket_id'] ?? 0),
            ['legacy_window_id' => (int) ($result['window_id'] ?? 0)]
        );
    }
    return $result;
}

function smartqmsCompleteForStaff(mysqli $conn, int $staffId, int $legacyUserId, int $ticketId): array {
    if (smartqmsDataProviderMode() === 'supabase') {
        return smartqmsNormalizeRemoteStaffResult(smartqmsSupabaseRequest(
            'POST',
            '/rest/v1/rpc/staff_finish_ticket',
            [
                'p_actor_legacy_id' => $legacyUserId,
                'p_ticket_legacy_id' => $ticketId,
                'p_action' => 'complete',
            ]
        ));
    }

    $result = completeTicketForStaff($conn, $staffId, $ticketId);
    if (($result['status'] ?? '') === 'success') {
        smartqmsMirrorQueueEvent('completed', $legacyUserId, $ticketId, [
            'actual_wait_minutes' => (float) ($result['actual_wait_min'] ?? 0),
            'actual_service_seconds' => (int) ($result['actual_service_dur'] ?? 0),
        ]);
    }
    return $result;
}

function smartqmsSkipForStaff(mysqli $conn, int $staffId, int $legacyUserId, int $ticketId): array {
    if (smartqmsDataProviderMode() === 'supabase') {
        return smartqmsNormalizeRemoteStaffResult(smartqmsSupabaseRequest(
            'POST',
            '/rest/v1/rpc/staff_finish_ticket',
            [
                'p_actor_legacy_id' => $legacyUserId,
                'p_ticket_legacy_id' => $ticketId,
                'p_action' => 'skip',
            ]
        ));
    }

    $result = skipTicketForStaff($conn, $staffId, $ticketId);
    if (($result['status'] ?? '') === 'success') {
        smartqmsMirrorQueueEvent('skipped', $legacyUserId, $ticketId, [
            'reason' => 'Client did not appear',
        ]);
    }
    return $result;
}

function smartqmsVoidForStaff(mysqli $conn, int $staffId, int $legacyUserId, int $ticketId): array {
    if (smartqmsDataProviderMode() === 'supabase') {
        return smartqmsNormalizeRemoteStaffResult(smartqmsSupabaseRequest(
            'POST',
            '/rest/v1/rpc/staff_finish_ticket',
            [
                'p_actor_legacy_id' => $legacyUserId,
                'p_ticket_legacy_id' => $ticketId,
                'p_action' => 'void',
            ]
        ));
    }

    $result = voidTicketForStaff($conn, $staffId, $ticketId);
    if (($result['status'] ?? '') === 'success') {
        smartqmsMirrorQueueEvent('voided', $legacyUserId, $ticketId, [
            'reason' => 'Voided manually by staff',
        ]);
    }
    return $result;
}

function smartqmsSetCounterStatusForStaff(
    mysqli $conn,
    int $staffId,
    int $legacyUserId,
    string $status
): array {
    if (smartqmsDataProviderMode() === 'supabase') {
        return smartqmsNormalizeRemoteStaffResult(smartqmsSupabaseRequest(
            'POST',
            '/rest/v1/rpc/staff_set_counter_status',
            ['p_actor_legacy_id' => $legacyUserId, 'p_status' => $status]
        ));
    }

    $result = setServiceWindowStatusForStaff($conn, $staffId, $status);
    if (($result['status'] ?? '') === 'success') {
        smartqmsMirrorQueueEvent('counter_' . $status, $legacyUserId, null, [
            'legacy_window_id' => (int) ($result['window_id'] ?? 0),
        ]);
    }
    return $result;
}

function smartqmsStaffWorkspaceContext(
    mysqli $conn,
    int $staffId,
    int $legacyUserId,
    int $voidTimeoutMinutes
): array {
    if (smartqmsDataProviderMode() !== 'supabase') {
        $window = $staffId ? getStaffWindow($conn, $staffId) : null;
        $current = null;
        $waiting = [];
        if ($window) {
            $current = getStaffWindowCurrentTicket($conn, (int) $window['window_id']);
            $waiting = getStaffWindowWaitingTicketsForWindow($conn, $window, 20);
        }
        return compact('window', 'current', 'waiting');
    }

    $profileId = smartqmsSupabaseProfileIdForLegacy($legacyUserId);
    if ($profileId === '') {
        return ['window' => null, 'current' => null, 'waiting' => []];
    }
    $counter = smartqmsSupabaseSingle(
        'counters',
        'select=id,legacy_id,name,status,service_id,branch_id,counter_type,active'
            . '&staff_user_id=eq.' . rawurlencode($profileId) . '&active=eq.true&limit=1'
    );
    if (!$counter) {
        return ['window' => null, 'current' => null, 'waiting' => []];
    }
    $service = !empty($counter['service_id'])
        ? smartqmsSupabaseSingle('services', 'select=id,legacy_id,name&id=eq.' . rawurlencode((string) $counter['service_id']) . '&limit=1')
        : null;
    $window = [
        'window_id' => (int) ($counter['legacy_id'] ?? 0),
        '_provider_id' => (string) $counter['id'],
        '_provider_branch_id' => (string) ($counter['branch_id'] ?? ''),
        'window_name' => (string) ($counter['name'] ?? 'Service Window'),
        'status' => (string) ($counter['status'] ?? 'closed'),
        'service_id' => (int) ($service['legacy_id'] ?? 0),
        '_provider_service_id' => (string) ($counter['service_id'] ?? ''),
        'service_name' => (string) ($service['name'] ?? 'No health service assigned'),
    ];

    $ticketSelect = 'select=id,legacy_id,ticket_number,reference_number,customer_name,client_type,priority_level,status,created_at,called_at,service_id,counter_id';
    $currentResponse = smartqmsSupabaseRequest(
        'GET',
        '/rest/v1/tickets?' . $ticketSelect . '&counter_id=eq.' . rawurlencode((string) $counter['id'])
            . '&status=eq.serving&order=called_at.desc&limit=1'
    );
    $currentRow = $currentResponse['ok'] && is_array($currentResponse['data'])
        ? ($currentResponse['data'][0] ?? null)
        : null;
    $current = $currentRow ? [
        'ticket_id' => (int) ($currentRow['legacy_id'] ?? 0),
        '_provider_id' => (string) $currentRow['id'],
        'ticket_number' => (string) $currentRow['ticket_number'],
        'reference_number' => (string) $currentRow['reference_number'],
        'client_name' => (string) $currentRow['customer_name'],
        'client_type' => (string) $currentRow['client_type'],
        'priority_level' => (int) $currentRow['priority_level'],
        'issued_at' => (string) $currentRow['created_at'],
        'called_at' => (string) ($currentRow['called_at'] ?? ''),
        'service_id' => (int) ($service['legacy_id'] ?? 0),
    ] : null;

    $waiting = [];
    if (!empty($counter['service_id'])) {
        $waitingResponse = smartqmsSupabaseRequest(
            'GET',
            '/rest/v1/tickets?' . $ticketSelect . '&service_id=eq.' . rawurlencode((string) $counter['service_id'])
                . '&status=eq.waiting&order=priority_level.desc,created_at.asc&limit=20'
        );
        if ($waitingResponse['ok'] && is_array($waitingResponse['data'])) {
            foreach ($waitingResponse['data'] as $row) {
                $waiting[] = [
                    'ticket_id' => (int) ($row['legacy_id'] ?? 0),
                    '_provider_id' => (string) ($row['id'] ?? ''),
                    'ticket_number' => (string) ($row['ticket_number'] ?? ''),
                    'client_name' => (string) ($row['customer_name'] ?? 'Client'),
                    'client_type' => (string) ($row['client_type'] ?? 'regular'),
                    'priority_level' => (int) ($row['priority_level'] ?? 0),
                    'issued_at' => (string) ($row['created_at'] ?? ''),
                    'service_id' => (int) ($service['legacy_id'] ?? 0),
                    'service_name' => (string) ($service['name'] ?? ''),
                ];
            }
        }
    }

    return compact('window', 'current', 'waiting');
}

function smartqmsStaffActivity(mysqli $conn, int $legacyUserId, int $limit = 50): array {
    $limit = max(1, min(100, $limit));
    if (smartqmsDataProviderMode() !== 'supabase') {
        $stmt = $conn->prepare("SELECT action, details, logged_at FROM activity_logs WHERE user_id=? ORDER BY logged_at DESC LIMIT {$limit}");
        $stmt->bind_param('i', $legacyUserId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    $actorId = smartqmsSupabaseProfileIdForLegacy($legacyUserId);
    if ($actorId === '') return [];
    $response = smartqmsSupabaseRequest(
        'GET',
        '/rest/v1/queue_events?select=event_type,metadata,created_at,ticket:tickets(ticket_number)'
            . '&actor_user_id=eq.' . rawurlencode($actorId)
            . '&order=created_at.desc&limit=' . $limit
    );
    if (!$response['ok'] || !is_array($response['data'])) {
        throw new RuntimeException($response['error'] ?: 'Staff activity is unavailable.');
    }
    return array_map(static function (array $row): array {
        $ticketNumber = (string) ($row['ticket']['ticket_number'] ?? '');
        $metadata = is_array($row['metadata'] ?? null) ? $row['metadata'] : [];
        $details = trim((string) ($metadata['details'] ?? ''));
        if ($details === '' && $ticketNumber !== '') {
            $details = ucfirst(str_replace('_', ' ', (string) $row['event_type'])) . ' ticket ' . $ticketNumber;
        }
        return [
            'action' => (string) ($row['event_type'] ?? 'activity'),
            'details' => $details,
            'logged_at' => (string) ($row['created_at'] ?? ''),
        ];
    }, $response['data']);
}
