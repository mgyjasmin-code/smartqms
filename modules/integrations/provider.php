<?php
/**
 * Provider-neutral role, status, and identifier translation.
 */

function smartqmsDataProviderMode(): string {
    return (string) (smartqmsSupabaseConfig()['mode'] ?? 'local');
}

function smartqmsRoleToProvider(string $role): string {
    return match (strtolower(trim($role))) {
        'client', 'customer' => 'customer',
        'staff' => 'staff',
        'admin' => 'admin',
        'super_admin' => 'super_admin',
        default => 'customer',
    };
}

function smartqmsRoleFromProvider(string $role): string {
    return match (strtolower(trim($role))) {
        'customer', 'client' => ROLE_CLIENT,
        'staff' => ROLE_STAFF,
        'admin', 'super_admin' => ROLE_ADMIN,
        default => ROLE_CLIENT,
    };
}

function smartqmsTicketStatusToProvider(string $status): string {
    return strtolower(trim($status)) === 'completed' ? 'done' : strtolower(trim($status));
}

function smartqmsTicketStatusFromProvider(string $status): string {
    return strtolower(trim($status)) === 'done' ? 'completed' : strtolower(trim($status));
}

function smartqmsProviderIdentifier(mixed $id, mixed $legacyId = null): array {
    return [
        'id' => trim((string) $id),
        'legacy_id' => is_numeric($legacyId) ? (int) $legacyId : null,
    ];
}
