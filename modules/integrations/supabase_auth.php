<?php
/** Supabase access-token verification for compatibility APIs. */

function smartqmsBearerToken(?array $server = null): string {
    $server ??= $_SERVER;
    $header = trim((string) ($server['HTTP_AUTHORIZATION'] ?? $server['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    return preg_match('/^Bearer\s+([^\s]+)$/i', $header, $matches) ? trim($matches[1]) : '';
}

function smartqmsSupabasePrincipal(string $accessToken): ?array {
    if ($accessToken === '') return null;
    $response = smartqmsSupabaseAuthRequest('GET', '/auth/v1/user', null, $accessToken);
    if (!$response['ok'] || !is_array($response['data']) || empty($response['data']['id'])) return null;
    $user = $response['data'];
    $userId = (string) $user['id'];
    $profileResponse = smartqmsSupabaseRequest(
        'GET',
        '/rest/v1/profiles?select=id,legacy_id,first_name,last_name,full_name,phone,active,verified&id=eq.'
            . rawurlencode($userId) . '&limit=1'
    );
    $rolesResponse = smartqmsSupabaseRequest(
        'GET',
        '/rest/v1/user_roles?select=role&user_id=eq.' . rawurlencode($userId)
    );
    if (!$profileResponse['ok'] || !$rolesResponse['ok']) return null;
    $profile = is_array($profileResponse['data']) ? ($profileResponse['data'][0] ?? []) : [];
    if ($profile && !is_numeric($profile['legacy_id'] ?? null)) {
        $finalized = smartqmsSupabaseRequest(
            'POST', '/rest/v1/rpc/server_finalize_customer_profile', ['p_user_id' => $userId]
        );
        if ($finalized['ok'] && is_array($finalized['data'])) {
            $profile = array_is_list($finalized['data']) ? ($finalized['data'][0] ?? $profile) : $finalized['data'];
        }
    }
    if ($profile && array_key_exists('active', $profile) && !$profile['active']) return null;
    $roles = [];
    foreach (is_array($rolesResponse['data']) ? $rolesResponse['data'] : [] as $row) {
        $role = trim((string) ($row['role'] ?? ''));
        if ($role !== '') $roles[] = $role;
    }
    return [
        'id' => $userId,
        'email' => (string) ($user['email'] ?? ''),
        'legacy_id' => is_numeric($profile['legacy_id'] ?? null) ? (int) $profile['legacy_id'] : null,
        'name' => trim((string) ($profile['full_name'] ?? '')) ?: trim(
            (string) ($profile['first_name'] ?? '') . ' ' . (string) ($profile['last_name'] ?? '')
        ),
        'phone' => (string) ($profile['phone'] ?? ''),
        'roles' => array_values(array_unique($roles)),
    ];
}

function smartqmsCompleteSupabaseLogin(array $principal, array $authSession): void {
    $roleOrder = ['super_admin', 'admin', 'staff', 'customer'];
    $providerRole = 'customer';
    foreach ($roleOrder as $candidate) {
        if (in_array($candidate, $principal['roles'] ?? [], true)) {
            $providerRole = $candidate;
            break;
        }
    }
    if (!is_numeric($principal['legacy_id'] ?? null)) {
        throw new RuntimeException('The Supabase profile is not linked to a SmartQMS identifier.');
    }
    session_regenerate_id(true);
    $now = time();
    $_SESSION['user_id'] = (int) $principal['legacy_id'];
    $_SESSION['role'] = smartqmsRoleFromProvider($providerRole);
    $_SESSION['provider_role'] = $providerRole;
    $_SESSION['provider_user_id'] = (string) $principal['id'];
    $_SESSION['name'] = (string) ($principal['name'] ?? 'SmartQMS User');
    $_SESSION['phone'] = (string) ($principal['phone'] ?? '');
    $_SESSION['email'] = (string) ($principal['email'] ?? '');
    $_SESSION['auth_created_at'] = $now;
    $_SESSION['auth_last_activity_at'] = $now;
    $_SESSION['auth_rotated_at'] = $now;
    $_SESSION['supabase_access_token'] = (string) ($authSession['access_token'] ?? '');
    $_SESSION['supabase_refresh_token'] = (string) ($authSession['refresh_token'] ?? '');
    $expiresAt = (int) ($authSession['expires_at'] ?? 0);
    $_SESSION['supabase_expires_at'] = $expiresAt > time()
        ? $expiresAt
        : time() + max(60, (int) ($authSession['expires_in'] ?? 3600));
}

function smartqmsSupabaseSessionAccessToken(): string {
    $accessToken = is_string($_SESSION['supabase_access_token'] ?? null)
        ? $_SESSION['supabase_access_token']
        : '';
    $expiresAt = (int) ($_SESSION['supabase_expires_at'] ?? 0);
    if ($accessToken === '' || $expiresAt > time() + 60) return $accessToken;

    $refreshToken = is_string($_SESSION['supabase_refresh_token'] ?? null)
        ? $_SESSION['supabase_refresh_token']
        : '';
    if ($refreshToken === '') return '';

    $response = smartqmsSupabaseAuthRequest(
        'POST',
        '/auth/v1/token?grant_type=refresh_token',
        ['refresh_token' => $refreshToken]
    );
    $session = is_array($response['data'] ?? null) ? $response['data'] : [];
    if (!$response['ok'] || empty($session['access_token'])) {
        unset(
            $_SESSION['supabase_access_token'],
            $_SESSION['supabase_refresh_token'],
            $_SESSION['supabase_expires_at']
        );
        return '';
    }

    $_SESSION['supabase_access_token'] = (string) $session['access_token'];
    $_SESSION['supabase_refresh_token'] = (string) ($session['refresh_token'] ?? $refreshToken);
    $newExpiresAt = (int) ($session['expires_at'] ?? 0);
    $_SESSION['supabase_expires_at'] = $newExpiresAt > time()
        ? $newExpiresAt
        : time() + max(60, (int) ($session['expires_in'] ?? 3600));
    return (string) $_SESSION['supabase_access_token'];
}

function smartqmsApiPrincipal(array $allowedProviderRoles): array {
    if (smartqmsDataProviderMode() !== 'supabase') {
        if (!isLoggedIn() || authenticatedSessionExpired() || authenticatedPrincipalRevoked()) {
            destroyLocalSessionState();
            jsonResponse(false, ['error' => 'Authentication required.'], 401);
        }
        if (authenticatedPrincipalMustRotatePassword()) {
            jsonResponse(false, ['error' => 'Password change required.'], 403);
        }
        $now = time();
        $_SESSION['auth_last_activity_at'] = $now;
        $rotatedAt = (int) ($_SESSION['auth_rotated_at'] ?? 0);
        if ($rotatedAt <= 0 || $now - $rotatedAt >= SESSION_ROTATE_SECONDS) {
            session_regenerate_id(true);
            $_SESSION['auth_rotated_at'] = $now;
        }
        $providerRole = smartqmsRoleToProvider((string) ($_SESSION['role'] ?? ''));
        if (!in_array($providerRole, $allowedProviderRoles, true)
            && !(in_array('admin', $allowedProviderRoles, true) && $providerRole === 'super_admin')) {
            jsonResponse(false, ['error' => 'Insufficient permissions.'], 403);
        }
        return [
            'id' => null,
            'legacy_id' => (int) ($_SESSION['user_id'] ?? 0),
            'name' => (string) ($_SESSION['name'] ?? ''),
            'roles' => [$providerRole],
        ];
    }

    $principal = smartqmsSupabasePrincipal(smartqmsBearerToken());
    if (!$principal) jsonResponse(false, ['error' => 'A valid Supabase access token is required.'], 401);
    if (!array_intersect($allowedProviderRoles, $principal['roles'])) {
        jsonResponse(false, ['error' => 'Insufficient permissions.'], 403);
    }
    return $principal;
}
