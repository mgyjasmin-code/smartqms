<?php
/**
 * Provider-neutral Admin management adapter.
 *
 * Existing pages retain their forms and server-side validation. This layer
 * selects the characterized MySQL implementation or the server-only Supabase
 * REST/Auth implementation without exposing privileged credentials.
 */

function smartqmsAdminRemoteRows(string $table, string $query): array {
    $response = smartqmsSupabaseRequest('GET', '/rest/v1/' . $table . '?' . $query);
    if (!$response['ok'] || !is_array($response['data'])) {
        throw new RuntimeException($response['error'] ?: 'Supabase administration data is unavailable.');
    }
    return $response['data'];
}

function smartqmsAdminRemoteWrite(string $method, string $path, ?array $body = null, bool $returnRow = false): mixed {
    $headers = ['Prefer: ' . ($returnRow ? 'return=representation' : 'return=minimal')];
    $response = smartqmsSupabaseRequest($method, $path, $body, $headers);
    if (!$response['ok']) {
        throw new RuntimeException($response['error'] ?: 'The Supabase administration action failed.');
    }
    if (!$returnRow) return true;
    $rows = $response['data'];
    return is_array($rows) && array_is_list($rows) ? ($rows[0] ?? null) : $rows;
}

function smartqmsAdminListBranches(mysqli $conn, bool $includeInactive = true): array {
    if (smartqmsDataProviderMode() !== 'supabase') return smartqmsListBranches($conn);
    $filter = $includeInactive ? '' : '&active=eq.true';
    return smartqmsAdminRemoteRows(
        'branches',
        'select=id,legacy_id,name,address,active,created_at,updated_at' . $filter . '&order=name.asc'
    );
}

function smartqmsAdminSaveBranch(
    mysqli $conn,
    int $actorLegacyId,
    ?string $branchId,
    string $name,
    string $address,
    bool $active
): array {
    if (smartqmsDataProviderMode() !== 'supabase') {
        throw new DomainException('Multi-branch administration requires the Supabase provider.');
    }
    $response = smartqmsSupabaseRequest('POST', '/rest/v1/rpc/admin_save_branch', [
        'p_actor_legacy_id' => $actorLegacyId,
        'p_branch_id' => $branchId !== null && $branchId !== '' ? $branchId : null,
        'p_name' => trim($name),
        'p_address' => trim($address),
        'p_active' => $active,
    ]);
    return smartqmsNormalizeRemoteStaffResult($response);
}

function smartqmsAdminRolesForLegacy(int $legacyUserId): array {
    if (smartqmsDataProviderMode() !== 'supabase') return ['admin'];
    $profileId = smartqmsSupabaseProfileIdForLegacy($legacyUserId);
    if ($profileId === '') return [];
    $rows = smartqmsAdminRemoteRows(
        'user_roles',
        'select=role&user_id=eq.' . rawurlencode($profileId) . '&order=role.asc'
    );
    return array_values(array_unique(array_map(
        static fn(array $row): string => (string) ($row['role'] ?? ''),
        $rows
    )));
}

function smartqmsAdminListRoleAssignments(): array {
    if (smartqmsDataProviderMode() !== 'supabase') return [];
    return smartqmsAdminRemoteRows(
        'user_roles',
        'select=id,user_id,role,created_at,profile:profiles!user_roles_user_id_fkey(full_name,legacy_id)'
            . '&order=created_at.desc'
    );
}

function smartqmsAdminSetRole(
    int $actorLegacyId,
    string $targetUserId,
    string $role,
    bool $grant
): array {
    if (smartqmsDataProviderMode() !== 'supabase') {
        throw new DomainException('Role administration requires the Supabase provider.');
    }
    return smartqmsNormalizeRemoteStaffResult(smartqmsSupabaseRequest(
        'POST', '/rest/v1/rpc/super_admin_set_role', [
            'p_actor_legacy_id' => $actorLegacyId,
            'p_target_user_id' => $targetUserId,
            'p_role' => $role,
            'p_grant' => $grant,
        ]
    ));
}

function smartqmsAdminListServices(mysqli $conn, bool $activeFirst = false): array {
    if (smartqmsDataProviderMode() !== 'supabase') return listHealthServices($conn, $activeFirst);
    $order = $activeFirst ? 'active.desc,display_order.asc,name.asc' : 'display_order.asc,name.asc';
    $rows = smartqmsAdminRemoteRows(
        'services',
        'select=id,legacy_id,code,name,description,ml_value,queue_mode,priority_only,active,display_order'
            . '&order=' . $order
    );
    return array_map('smartqmsNormalizeProviderService', $rows);
}

function smartqmsAdminFindService(mysqli $conn, int $serviceId): ?array {
    if (smartqmsDataProviderMode() !== 'supabase') return findHealthService($conn, $serviceId);
    $rows = smartqmsAdminRemoteRows(
        'services',
        'select=id,legacy_id,code,name,description,ml_value,queue_mode,priority_only,active,display_order'
            . '&legacy_id=eq.' . $serviceId . '&limit=1'
    );
    return isset($rows[0]) ? smartqmsNormalizeProviderService($rows[0]) : null;
}

function smartqmsAdminNextServiceValues(mysqli $conn): array {
    if (smartqmsDataProviderMode() !== 'supabase') {
        return [
            'code' => nextHealthServiceCode($conn),
            'encoded' => nextHealthServiceEncoded($conn),
            'display_order' => nextHealthServiceDisplayOrder($conn),
        ];
    }
    $services = smartqmsAdminListServices($conn);
    $maxCode = 0;
    $maxEncoded = 0;
    $maxOrder = 0;
    foreach ($services as $service) {
        if (preg_match('/^SVC-(\d+)$/', (string) $service['service_code'], $matches)) {
            $maxCode = max($maxCode, (int) $matches[1]);
        }
        $maxEncoded = max($maxEncoded, (int) $service['service_encoded']);
        $maxOrder = max($maxOrder, (int) $service['display_order']);
    }
    if ($maxEncoded >= 127) throw new RuntimeException('No ML category identifiers remain in the supported range.');
    return [
        'code' => 'SVC-' . str_pad((string) ($maxCode + 1), 3, '0', STR_PAD_LEFT),
        'encoded' => $maxEncoded + 1,
        'display_order' => min(127, $maxOrder + 1),
    ];
}

function smartqmsAdminServiceCodeLocked(mysqli $conn, int $serviceId): bool {
    if (smartqmsDataProviderMode() !== 'supabase') return healthServiceCodeIsLocked($conn, $serviceId);
    $service = smartqmsAdminFindService($conn, $serviceId);
    if (!$service || empty($service['id'])) return false;
    return (bool) smartqmsAdminRemoteRows('tickets', 'select=id&service_id=eq.' . rawurlencode($service['id']) . '&limit=1');
}

function smartqmsAdminDuplicateServiceCode(mysqli $conn, string $code, int $excludingId = 0): bool {
    if (smartqmsDataProviderMode() !== 'supabase') return duplicateHealthServiceCode($conn, $code, $excludingId);
    $rows = smartqmsAdminRemoteRows('services', 'select=legacy_id&code=eq.' . rawurlencode($code) . '&limit=2');
    foreach ($rows as $row) {
        if ((int) ($row['legacy_id'] ?? 0) !== $excludingId) return true;
    }
    return false;
}

function smartqmsAdminCreateService(mysqli $conn, array $input, int $createdBy): int {
    if (smartqmsDataProviderMode() !== 'supabase') {
        return createHealthService(
            $conn,
            '',
            $input['service_name'],
            0,
            $input['description'],
            (int) $input['priority_only'],
            (int) $input['display_order'],
            $createdBy,
            (int) $input['is_active'],
            $input['queue_mode'],
            (int) $input['fallback_duration_mins'],
            (int) $input['is_hidden']
        );
    }
    $services = smartqmsAdminListServices($conn);
    $next = smartqmsAdminNextServiceValues($conn);
    $legacyId = 1;
    foreach ($services as $service) $legacyId = max($legacyId, (int) ($service['legacy_id'] ?? 0) + 1);
    smartqmsAdminRemoteWrite('POST', '/rest/v1/services', [
        'legacy_id' => $legacyId,
        'code' => $next['code'],
        'name' => $input['service_name'],
        'description' => $input['description'] !== '' ? $input['description'] : null,
        'ml_value' => $next['encoded'],
        'queue_mode' => normalizeQueueMode($input['queue_mode']),
        'priority_only' => (bool) $input['priority_only'],
        'active' => (bool) $input['is_active'],
        'display_order' => $next['display_order'],
        'created_by' => smartqmsSupabaseProfileIdForLegacy($createdBy) ?: null,
    ]);
    return $legacyId;
}

function smartqmsAdminUpdateService(mysqli $conn, array $input): void {
    if (smartqmsDataProviderMode() !== 'supabase') {
        updateHealthServiceFromHtml($conn, $input);
        return;
    }
    $existing = smartqmsAdminFindService($conn, (int) $input['service_id']);
    if (!$existing) throw new RuntimeException('Health service could not be found.');
    smartqmsAdminRemoteWrite(
        'PATCH',
        '/rest/v1/services?legacy_id=eq.' . (int) $input['service_id'],
        [
            'code' => $existing['service_code'],
            'name' => $input['service_name'],
            'description' => $input['description'] !== '' ? $input['description'] : null,
            'queue_mode' => normalizeQueueMode($input['queue_mode']),
            'priority_only' => (bool) $input['priority_only'],
            'active' => (bool) $input['is_active'],
            'updated_at' => gmdate('c'),
        ]
    );
}

function smartqmsAdminMoveService(mysqli $conn, int $serviceId, string $direction, int $actorId): bool {
    if (smartqmsDataProviderMode() !== 'supabase') return moveHealthService($conn, $serviceId, $direction);
    $result = smartqmsNormalizeRemoteStaffResult(smartqmsSupabaseRequest(
        'POST', '/rest/v1/rpc/admin_move_service', [
            'p_actor_legacy_id' => $actorId,
            'p_service_legacy_id' => $serviceId,
            'p_direction' => $direction,
        ]
    ));
    return !empty($result['moved']);
}

function smartqmsAdminSetServiceActive(mysqli $conn, int $serviceId, int $isActive): void {
    if (smartqmsDataProviderMode() !== 'supabase') {
        setHealthServiceActive($conn, $serviceId, $isActive);
        return;
    }
    smartqmsAdminRemoteWrite('PATCH', '/rest/v1/services?legacy_id=eq.' . $serviceId, [
        'active' => $isActive === 1,
        'updated_at' => gmdate('c'),
    ]);
}

function smartqmsAdminDeleteService(mysqli $conn, int $serviceId): ?array {
    if (smartqmsDataProviderMode() !== 'supabase') return deleteOrDeactivateHealthService($conn, $serviceId);
    $service = smartqmsAdminFindService($conn, $serviceId);
    if (!$service) return null;
    $hasHistory = smartqmsAdminServiceCodeLocked($conn, $serviceId);
    if ($hasHistory) {
        smartqmsAdminSetServiceActive($conn, $serviceId, 0);
    } else {
        smartqmsAdminRemoteWrite('DELETE', '/rest/v1/services?legacy_id=eq.' . $serviceId);
    }
    return ['service' => $service, 'soft_deleted' => $hasHistory, 'had_ticket_history' => $hasHistory];
}

function smartqmsAdminListWindowServices(mysqli $conn): array {
    if (smartqmsDataProviderMode() !== 'supabase') return listWindowServiceAssignments($conn);
    return array_values(array_filter(smartqmsAdminListServices($conn), static fn(array $service): bool =>
        $service['active'] && $service['queue_mode'] === 'specialized'
    ));
}

function smartqmsAdminListWindows(mysqli $conn): array {
    if (smartqmsDataProviderMode() !== 'supabase') return listAdminWindows($conn);
    $rows = smartqmsAdminRemoteRows(
        'counters',
        'select=id,legacy_id,name,location_description,counter_type,service_id,staff_user_id,status,active'
            . '&order=legacy_id.asc,name.asc'
    );
    $services = smartqmsAdminListServices($conn);
    $serviceById = [];
    foreach ($services as $service) $serviceById[$service['id']] = $service;
    $windows = [];
    foreach ($rows as $row) {
        $service = $serviceById[(string) ($row['service_id'] ?? '')] ?? null;
        $staffName = 'Unoccupied';
        if (!empty($row['staff_user_id'])) {
            $profile = smartqmsSupabaseSingle('profiles', 'select=full_name&id=eq.' . rawurlencode($row['staff_user_id']) . '&limit=1');
            $staffName = trim((string) ($profile['full_name'] ?? '')) ?: 'Assigned staff';
        }
        $windows[] = [
            'window_id' => (int) ($row['legacy_id'] ?? 0),
            'counter_number' => (int) ($row['legacy_id'] ?? 0),
            '_provider_id' => (string) ($row['id'] ?? ''),
            'window_name' => (string) ($row['name'] ?? ''),
            'location_description' => (string) ($row['location_description'] ?? ''),
            'window_type' => (string) ($row['counter_type'] ?? 'shared'),
            'service_id' => (int) ($service['legacy_id'] ?? 0),
            'service_name' => (string) ($service['service_name'] ?? ''),
            'staff_name' => $staffName,
            'status' => (string) ($row['status'] ?? 'closed'),
            'is_active' => !empty($row['active']) ? 1 : (($row['status'] ?? '') === 'paused' ? 2 : 0),
            'management_status' => !empty($row['active'])
                ? 'active'
                : (($row['status'] ?? '') === 'paused' ? 'maintenance' : 'inactive'),
            'service_ids' => $service ? (string) ($service['legacy_id'] ?? '') : '',
            'queue_handled' => ($row['counter_type'] ?? 'shared') === 'shared'
                ? 'All services'
                : (string) ($service['service_name'] ?? 'Unassigned'),
        ];
    }
    return $windows;
}

function smartqmsAdminSaveWindow(mysqli $conn, array $input): bool {
    if (smartqmsDataProviderMode() !== 'supabase') return saveAdminWindow($conn, $input);
    $id = (int) $input['window_id'];
    $existing = $id > 0 ? findAdminWindow(smartqmsAdminListWindows($conn), $id) : null;
    if ($existing && (($existing['staff_name'] ?? 'Unoccupied') !== 'Unoccupied' || !in_array($existing['status'], ['closed', 'paused'], true))) {
        throw new DomainException('Close the runtime window before changing its configuration.');
    }
    $serviceProviderId = null;
    if ($input['window_type'] === 'specialized') {
        $service = smartqmsAdminFindService($conn, (int) $input['service_id']);
        if (!$service) throw new DomainException('Choose an active assigned service.');
        $serviceProviderId = $service['id'];
    }
    $body = [
        'name' => $input['window_name'],
        'location_description' => $input['location_description'] !== '' ? $input['location_description'] : null,
        'counter_type' => $input['window_type'],
        'service_id' => $serviceProviderId,
        'staff_user_id' => null,
        'status' => $input['management_status'] === 'maintenance' ? 'paused' : 'closed',
        'active' => $input['management_status'] === 'active',
        'updated_at' => gmdate('c'),
    ];
    if ($id > 0) {
        if (!$existing) throw new RuntimeException('Service window could not be found.');
        smartqmsAdminRemoteWrite('PATCH', '/rest/v1/counters?legacy_id=eq.' . $id, $body);
        return true;
    }
    $branches = smartqmsListBranches($conn);
    if (!$branches || empty($branches[0]['id'])) throw new RuntimeException('Create an active branch before adding a counter.');
    $rows = smartqmsAdminListWindows($conn);
    $legacyId = 1;
    foreach ($rows as $row) $legacyId = max($legacyId, (int) $row['window_id'] + 1);
    $body['legacy_id'] = $legacyId;
    $body['branch_id'] = $branches[0]['id'];
    smartqmsAdminRemoteWrite('POST', '/rest/v1/counters', $body);
    return false;
}

function smartqmsAdminAuthUsers(): array {
    $response = smartqmsSupabaseRequest('GET', '/auth/v1/admin/users?page=1&per_page=1000');
    if (!$response['ok'] || !is_array($response['data'])) {
        throw new RuntimeException($response['error'] ?: 'Supabase staff accounts are unavailable.');
    }
    return is_array($response['data']['users'] ?? null) ? $response['data']['users'] : [];
}

function smartqmsAdminListStaff(mysqli $conn): array {
    if (smartqmsDataProviderMode() !== 'supabase') return listStaffAccounts($conn);
    $profiles = smartqmsAdminRemoteRows('profiles', 'select=id,legacy_id,first_name,last_name,phone,active,must_change_password');
    $roles = smartqmsAdminRemoteRows('user_roles', 'select=user_id,role&role=eq.staff');
    $staffIds = array_fill_keys(array_column($roles, 'user_id'), true);
    $authById = [];
    foreach (smartqmsAdminAuthUsers() as $authUser) $authById[(string) ($authUser['id'] ?? '')] = $authUser;
    $windows = smartqmsAdminListWindows($conn);
    $counterRows = smartqmsAdminRemoteRows('counters', 'select=staff_user_id,name,status,counter_type,service_id&staff_user_id=not.is.null');
    $counterByStaff = [];
    foreach ($counterRows as $counter) $counterByStaff[$counter['staff_user_id']] = $counter;
    $services = smartqmsAdminListServices($conn);
    $serviceById = [];
    foreach ($services as $service) $serviceById[$service['id']] = $service;
    $capabilityRows = smartqmsAdminRemoteRows(
        'staff_service_capabilities',
        'select=staff_user_id,service_id&active=eq.true'
    );
    $capabilitiesByStaff = [];
    foreach ($capabilityRows as $capability) {
        $capabilitiesByStaff[$capability['staff_user_id']][] = $capability['service_id'];
    }
    $result = [];
    foreach ($profiles as $profile) {
        $providerId = (string) ($profile['id'] ?? '');
        if (!isset($staffIds[$providerId])) continue;
        $counter = $counterByStaff[$providerId] ?? [];
        $runtimeService = $serviceById[(string) ($counter['service_id'] ?? '')] ?? null;
        $capabilityNames = [];
        $capabilityIds = [];
        foreach ($capabilitiesByStaff[$providerId] ?? [] as $serviceProviderId) {
            $capabilityService = $serviceById[$serviceProviderId] ?? null;
            if (!$capabilityService) continue;
            $capabilityNames[] = $capabilityService['service_name'];
            $capabilityIds[] = (int) $capabilityService['service_id'];
        }
        $result[] = [
            'staff_id' => (int) ($profile['legacy_id'] ?? 0),
            'user_id' => (int) ($profile['legacy_id'] ?? 0),
            '_provider_id' => $providerId,
            'first_name' => (string) ($profile['first_name'] ?? ''),
            'last_name' => (string) ($profile['last_name'] ?? ''),
            'email' => (string) ($authById[$providerId]['email'] ?? ''),
            'username' => (string) ($authById[$providerId]['user_metadata']['username']
                ?? $authById[$providerId]['email'] ?? ''),
            'job_title' => (string) ($authById[$providerId]['user_metadata']['job_title'] ?? ''),
            'phone_number' => (string) ($profile['phone'] ?? ''),
            'is_active' => !empty($profile['active']) ? 1 : 0,
            'must_change_password' => !empty($profile['must_change_password']) ? 1 : 0,
            'window_name' => $counter['name'] ?? null,
            'window_status' => $counter['status'] ?? null,
            'runtime_queue' => ($counter['counter_type'] ?? '') === 'shared'
                ? 'All services'
                : ($runtimeService['service_name'] ?? null),
            'specialized_capabilities' => implode(', ', $capabilityNames),
            'capability_ids' => $capabilityIds,
        ];
    }
    return $result;
}

function smartqmsAdminSyncRemoteStaffCapabilities(
    mysqli $conn,
    string $providerId,
    array $legacyServiceIds,
    int $adminLegacyId
): void {
    smartqmsAdminRemoteWrite(
        'DELETE',
        '/rest/v1/staff_service_capabilities?staff_user_id=eq.' . rawurlencode($providerId)
    );
    if (!$legacyServiceIds) return;
    $assignedBy = smartqmsSupabaseProfileIdForLegacy($adminLegacyId) ?: null;
    $rows = [];
    foreach (array_unique(array_map('intval', $legacyServiceIds)) as $legacyServiceId) {
        $service = smartqmsAdminFindService($conn, $legacyServiceId);
        if (!$service || $service['queue_mode'] !== 'specialized' || !$service['active']) continue;
        $rows[] = [
            'staff_user_id' => $providerId,
            'service_id' => $service['id'],
            'active' => true,
            'assigned_by' => $assignedBy,
        ];
    }
    if ($rows) smartqmsAdminRemoteWrite('POST', '/rest/v1/staff_service_capabilities', $rows);
}

function smartqmsAdminFindStaff(mysqli $conn, int $staffId): ?array {
    if (smartqmsDataProviderMode() !== 'supabase') return findStaffAccount($conn, $staffId);
    foreach (smartqmsAdminListStaff($conn) as $staff) {
        if ((int) $staff['staff_id'] === $staffId) return $staff;
    }
    return null;
}

function smartqmsAdminStaffEmailExists(mysqli $conn, string $email, int $excludeUserId = 0): bool {
    if (smartqmsDataProviderMode() !== 'supabase') return staffEmailExists($conn, $email, $excludeUserId);
    foreach (smartqmsAdminAuthUsers() as $user) {
        $profile = smartqmsSupabaseSingle('profiles', 'select=legacy_id&id=eq.' . rawurlencode((string) ($user['id'] ?? '')) . '&limit=1');
        if (strcasecmp((string) ($user['email'] ?? ''), $email) === 0 && (int) ($profile['legacy_id'] ?? 0) !== $excludeUserId) return true;
    }
    return false;
}

function smartqmsAdminStaffPhoneExists(mysqli $conn, string $phone, int $excludeUserId = 0): bool {
    if (smartqmsDataProviderMode() !== 'supabase') return staffPhoneExists($conn, $phone, $excludeUserId);
    $rows = smartqmsAdminRemoteRows('profiles', 'select=legacy_id&phone=eq.' . rawurlencode($phone) . '&limit=2');
    foreach ($rows as $row) if ((int) ($row['legacy_id'] ?? 0) !== $excludeUserId) return true;
    return false;
}

function smartqmsAdminStaffUsernameExists(mysqli $conn, string $username, int $excludeUserId = 0): bool {
    if (smartqmsDataProviderMode() !== 'supabase') return staffUsernameExists($conn, $username, $excludeUserId);
    foreach (smartqmsAdminListStaff($conn) as $staff) {
        if (strcasecmp((string) ($staff['username'] ?? ''), $username) === 0
            && (int) ($staff['user_id'] ?? 0) !== $excludeUserId) {
            return true;
        }
    }
    return false;
}

function smartqmsAdminCreateStaff(mysqli $conn, array $input, int $addedBy): int {
    if (smartqmsDataProviderMode() !== 'supabase') return createStaffAccount($conn, $input, $addedBy);
    $legacyId = 1;
    foreach (smartqmsAdminRemoteRows('profiles', 'select=legacy_id&order=legacy_id.desc&limit=1') as $row) {
        $legacyId = max(1, (int) ($row['legacy_id'] ?? 0) + 1);
    }
    $created = smartqmsAdminRemoteWrite('POST', '/auth/v1/admin/users', [
        'email' => $input['email'],
        'password' => $input['password'],
        'email_confirm' => true,
        'user_metadata' => [
            'username' => $input['username'],
            'job_title' => $input['job_title'],
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'phone' => $input['phone_number'],
        ],
    ], true);
    $providerId = (string) ($created['id'] ?? $created['user']['id'] ?? '');
    if ($providerId === '') throw new RuntimeException('Supabase did not return the created staff account.');
    try {
        smartqmsAdminRemoteWrite('PATCH', '/rest/v1/profiles?id=eq.' . rawurlencode($providerId), [
            'legacy_id' => $legacyId,
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'phone' => $input['phone_number'] !== '' ? $input['phone_number'] : null,
            'active' => (bool) $input['is_active'],
            'verified' => true,
            'must_change_password' => true,
            'updated_at' => gmdate('c'),
        ]);
        smartqmsAdminRemoteWrite('DELETE', '/rest/v1/user_roles?user_id=eq.' . rawurlencode($providerId));
        smartqmsAdminRemoteWrite('POST', '/rest/v1/user_roles', ['user_id' => $providerId, 'role' => 'staff']);
        smartqmsAdminSyncRemoteStaffCapabilities($conn, $providerId, $input['capability_ids'], $addedBy);
    } catch (Throwable $error) {
        smartqmsSupabaseRequest('DELETE', '/auth/v1/admin/users/' . rawurlencode($providerId));
        throw $error;
    }
    return $legacyId;
}

function smartqmsAdminUpdateStaff(mysqli $conn, array $input, int $updatedBy): bool {
    if (smartqmsDataProviderMode() !== 'supabase') return updateStaffAccount($conn, $input, $updatedBy);
    $staff = smartqmsAdminFindStaff($conn, (int) $input['staff_id']);
    if (!$staff) return false;
    $providerId = $staff['_provider_id'];
    $authBody = [
        'email' => $input['email'],
        'user_metadata' => [
            'username' => $input['username'],
            'job_title' => $input['job_title'],
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'phone' => $input['phone_number'],
        ],
    ];
    if ($input['password'] !== '') $authBody['password'] = $input['password'];
    smartqmsAdminRemoteWrite('PUT', '/auth/v1/admin/users/' . rawurlencode($providerId), $authBody);
    smartqmsAdminRemoteWrite('PATCH', '/rest/v1/profiles?id=eq.' . rawurlencode($providerId), [
        'first_name' => $input['first_name'],
        'last_name' => $input['last_name'],
        'phone' => $input['phone_number'] !== '' ? $input['phone_number'] : null,
        'active' => (bool) $input['is_active'],
        'must_change_password' => $input['password'] !== '' ? true : (bool) $staff['must_change_password'],
        'updated_at' => gmdate('c'),
    ]);
    smartqmsAdminSyncRemoteStaffCapabilities($conn, $providerId, $input['capability_ids'], $updatedBy);
    return true;
}

function smartqmsAdminDeactivateStaff(mysqli $conn, int $staffId, int $deletedBy): ?array {
    if (smartqmsDataProviderMode() !== 'supabase') return deleteOrDeactivateStaffAccount($conn, $staffId, $deletedBy);
    $staff = smartqmsAdminFindStaff($conn, $staffId);
    if (!$staff) return null;
    $providerId = $staff['_provider_id'];
    $ownedCounters = smartqmsAdminRemoteRows(
        'counters',
        'select=id&staff_user_id=eq.' . rawurlencode($providerId)
    );
    foreach ($ownedCounters as $counter) {
        if (smartqmsAdminRemoteRows(
            'tickets',
            'select=id&counter_id=eq.' . rawurlencode((string) $counter['id']) . '&status=eq.serving&limit=1'
        )) {
            throw new DomainException('Complete, skip, or void the currently serving ticket before deactivating this staff account.');
        }
    }
    // Clear counter ownership before disabling the profile so a deactivated
    // account cannot retain an operational assignment.
    smartqmsAdminRemoteWrite('PATCH', '/rest/v1/counters?staff_user_id=eq.' . rawurlencode($providerId), [
        'staff_user_id' => null, 'status' => 'closed', 'updated_at' => gmdate('c'),
    ]);
    smartqmsAdminRemoteWrite('PATCH', '/rest/v1/profiles?id=eq.' . rawurlencode($providerId), [
        'active' => false, 'updated_at' => gmdate('c'),
    ]);
    return ['staff' => $staff, 'soft_deleted' => true, 'had_history' => true];
}
