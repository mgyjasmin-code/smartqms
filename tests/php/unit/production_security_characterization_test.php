<?php

function productionSecuritySource(string $path): string {
    $source = file_get_contents(SMARTQMS_ROOT . '/' . $path);
    if ($source === false) {
        throw new RuntimeException('Could not read ' . $path);
    }
    return $source;
}

testCase('production bootstrap contains no fixed administrator or browser SMS prototype', function (): void {
    $schema = productionSecuritySource('database/smartqms_final_v4.sql');
    assertFalseValue(str_contains($schema, "('admin@smartqms.local'"));
    assertFalseValue(str_contains($schema, '$2y$10$0dVVbYM0md/nlp53WdgET'));
    assertFalseValue(is_file(SMARTQMS_ROOT . '/try.php'));
    assertFalseValue(is_file(SMARTQMS_ROOT . '/index.html'));
    $bootstrap = productionSecuritySource('scripts/bootstrap_admin.php');
    assertStringContains("PHP_SAPI !== 'cli'", $bootstrap);
    assertStringContains('SMARTQMS_BOOTSTRAP_ADMIN_PASSWORD', $bootstrap);
    $apache = productionSecuritySource('.htaccess');
    foreach (['config|database|docs|tests|scripts|storage|ml|vendor', 'md|txt|json|lock', '^\\.'] as $contract) {
        assertStringContains($contract, $apache);
    }
});

testCase('production configuration fails closed and protects authenticated sessions', function (): void {
    $config = productionSecuritySource('config/config.php');
    foreach (['SMARTQMS_APP_ENV', 'SMARTQMS_APP_URL', "!== 'https'", 'Strict-Transport-Security', 'SESSION_IDLE_TIMEOUT_SECONDS', 'SESSION_ABSOLUTE_TIMEOUT_SECONDS', 'Content-Security-Policy', 'Referrer-Policy: no-referrer'] as $contract) {
        assertStringContains($contract, $config);
    }
    assertStringContains('SMARTQMS_CSP_NONCE', $config);
    assertFalseValue(str_contains($config, "script-src 'self' 'unsafe-inline'"));
    $security = productionSecuritySource('modules/shared/security.php');
    foreach (['authenticatedSessionExpired', 'session_regenerate_id(true)', 'authenticatedPrincipalRevoked', 'session_version'] as $contract) {
        assertStringContains($contract, $security);
    }
    $apiAuth = productionSecuritySource('modules/integrations/supabase_auth.php');
    foreach (['authenticatedSessionExpired()', 'authenticatedPrincipalRevoked()', 'authenticatedPrincipalMustRotatePassword()'] as $contract) {
        assertStringContains($contract, $apiAuth);
    }
    assertStringContains('SMARTQMS_DATA_PROVIDER=local', productionSecuritySource('config/supabase.php'));
});

testCase('password reset responses and authority no longer disclose or trust a user id', function (): void {
    $handler = productionSecuritySource('modules/auth/forgot_password.php');
    assertStringContains('reset_capability', $handler);
    assertStringContains('consumePasswordResetCapability', $handler);
    assertFalseValue(str_contains($handler, "\$_SESSION['reset_verified_user_id']"));
    assertStringContains("\$_SESSION['reset_user_id'] = -1", $handler);
    assertSameValue(1, substr_count($handler, "['msg' => 'reset_otp_sent']"));
});

testCase('reservation mutation endpoint accepts only private token sessions', function (): void {
    $endpoint = productionSecuritySource('manage-reservation/index.php');
    assertStringContains('publicManagedReservationByToken', $endpoint);
    assertStringContains('reservation_manage_ticket_id', $endpoint);
    assertFalseValue(str_contains($endpoint, 'mobile_last_four'));
    assertFalseValue(str_contains($endpoint, 'RIGHT(qt.phone_number'));
});

testCase('reference compatibility status is coarse throttled and announced for sunset', function (): void {
    $endpoint = productionSecuritySource('modules/queue/public_reference_status.php');
    foreach (['authThrottleStatus', 'recordAuthAttempt', 'Deprecation: true', 'Sunset:'] as $contract) {
        assertStringContains($contract, $endpoint);
    }
    $service = productionSecuritySource('modules/queue/public_intake.php');
    $start = strpos($service, 'function publicReferenceStatusProjection');
    $end = strpos($service, 'function publicQueueTicketProjection', $start);
    $projection = substr($service, $start, $end - $start);
    foreach (['ticket_number', 'counter_label', 'checked_in_at', 'called_at', 'completed_at'] as $privateField) {
        assertFalseValue(str_contains($projection, "'{$privateField}'"));
    }
});

testCase('ML prediction services reject missing bearer configuration and minimize health output', function (): void {
    foreach (['ml/app.py', 'ml/main.py'] as $path) {
        $source = productionSecuritySource($path);
        assertStringContains('PYTHON_ML_TOKEN', $source);
        assertFalseValue(str_contains($source, '"metadata":'));
        assertFalseValue(str_contains($source, '"features":'));
    }
    assertStringContains('if not expected:', productionSecuritySource('ml/app.py'));
    assertStringContains('return False', productionSecuritySource('ml/app.py'));
});

testCase('deployment health is administrator-only and secret-minimized', function (): void {
    $health = productionSecuritySource('api/admin/health.php');
    assertStringContains("smartqmsApiPrincipal(['admin', 'super_admin'])", $health);
    foreach (['database', 'migration', 'storage', 'notification_worker', 'prediction_service', 'SMARTQMS_CORRELATION_ID'] as $contract) {
        assertStringContains($contract, $health);
    }
    foreach (['DB_PASS', 'service_role_key', 'EMAIL_SMTP_PASSWORD', "['token'] =>"] as $secret) {
        assertFalseValue(str_contains($health, $secret));
    }
});

testCase('production requests verify schema without applying DDL', function (): void {
    $compat = productionSecuritySource('modules/shared/schema_compat.php');
    assertStringContains("APP_ENV === 'production'", $compat);
    assertStringContains("smartqmsTableExists(\$conn, 'auth_attempts')", $compat);
    assertStringContains("smartqmsTableExists(\$conn, 'email_jobs')", $compat);
});
