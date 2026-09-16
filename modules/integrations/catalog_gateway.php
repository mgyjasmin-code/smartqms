<?php
/**
 * Read gateway for the first provider-neutral catalog capabilities.
 */

function smartqmsListServices(mysqli $conn): array {
    if (smartqmsDataProviderMode() === 'supabase') {
        $response = smartqmsSupabaseRequest(
            'GET',
            '/rest/v1/services?select=id,legacy_id,code,name,description,ml_value,queue_mode,priority_only,active,display_order&active=eq.true&order=display_order.asc,name.asc'
        );
        if (!$response['ok'] || !is_array($response['data'])) {
            throw new RuntimeException($response['error'] ?: 'Service catalog is unavailable.');
        }

        return array_map('smartqmsNormalizeProviderService', $response['data']);
    }

    $rows = $conn->query("
        SELECT service_id, service_code, service_name, service_encoded,
               queue_mode, description, priority_only, is_active, display_order
        FROM health_services
        WHERE is_active = 1
        ORDER BY display_order, service_name
    ")->fetch_all(MYSQLI_ASSOC);

    return array_map('smartqmsNormalizeLocalService', $rows);
}

function smartqmsNormalizeLocalService(array $row): array {
    $id = (int) ($row['service_id'] ?? 0);
    return array_merge(smartqmsProviderIdentifier('local-service-' . $id, $id), [
        'service_id' => $id,
        'code' => (string) ($row['service_code'] ?? ''),
        'service_code' => (string) ($row['service_code'] ?? ''),
        'name' => (string) ($row['service_name'] ?? ''),
        'service_name' => (string) ($row['service_name'] ?? ''),
        'description' => (string) ($row['description'] ?? ''),
        'ml_value' => (int) ($row['service_encoded'] ?? 0),
        'service_encoded' => (int) ($row['service_encoded'] ?? 0),
        'queue_mode' => (string) ($row['queue_mode'] ?? 'central'),
        'priority_only' => (bool) ($row['priority_only'] ?? false),
        'active' => (bool) ($row['is_active'] ?? false),
        'is_active' => (int) ($row['is_active'] ?? 0),
        'display_order' => (int) ($row['display_order'] ?? 0),
    ]);
}

function smartqmsNormalizeProviderService(array $row): array {
    $legacyId = is_numeric($row['legacy_id'] ?? null) ? (int) $row['legacy_id'] : null;
    return array_merge(smartqmsProviderIdentifier($row['id'] ?? '', $legacyId), [
        'service_id' => $legacyId,
        'code' => (string) ($row['code'] ?? ''),
        'service_code' => (string) ($row['code'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'service_name' => (string) ($row['name'] ?? ''),
        'description' => (string) ($row['description'] ?? ''),
        'ml_value' => (int) ($row['ml_value'] ?? 0),
        'service_encoded' => (int) ($row['ml_value'] ?? 0),
        'queue_mode' => (string) ($row['queue_mode'] ?? 'central'),
        'priority_only' => (bool) ($row['priority_only'] ?? false),
        'active' => (bool) ($row['active'] ?? false),
        'is_active' => !empty($row['active']) ? 1 : 0,
        'display_order' => (int) ($row['display_order'] ?? 0),
    ]);
}

function smartqmsListBranches(mysqli $conn): array {
    if (smartqmsDataProviderMode() === 'supabase') {
        $response = smartqmsSupabaseRequest(
            'GET',
            '/rest/v1/branches?select=id,legacy_id,name,address,active&active=eq.true&order=name.asc'
        );
        if (!$response['ok'] || !is_array($response['data'])) {
            throw new RuntimeException($response['error'] ?: 'Branch catalog is unavailable.');
        }

        return array_map(static function (array $row): array {
            return array_merge(
                smartqmsProviderIdentifier($row['id'] ?? '', $row['legacy_id'] ?? null),
                [
                    'name' => (string) ($row['name'] ?? ''),
                    'address' => (string) ($row['address'] ?? ''),
                    'active' => (bool) ($row['active'] ?? false),
                ]
            );
        }, $response['data']);
    }

    $name = trim(getSetting($conn, 'bhc_name', 'Barangay Health Center')) ?: 'Barangay Health Center';
    $address = trim(getSetting($conn, 'bhc_address', ''));

    return [[
        'id' => 'local-branch-1',
        'legacy_id' => 1,
        'name' => $name,
        'address' => $address,
        'active' => true,
    ]];
}
