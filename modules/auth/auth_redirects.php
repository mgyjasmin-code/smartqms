<?php
/**
 * Role-aware authentication redirects.
 */

function roleDestinationPath(string $role): string {
    if ($role === ROLE_ADMIN) {
        return 'views/admin/dashboard.php';
    }
    if ($role === ROLE_STAFF) {
        return 'staff/select-counter/';
    }
    return '';
}

function redirectAfterLogin(string $role, array $params = []): void {
    redirectTo(roleDestinationPath($role), $params);
}

function otpFlowFormContext(string $flow): array {
    if ($flow === 'reset') {
        return [
            'target' => 'forgot-password/',
            'form_key' => 'forgot_password_otp',
        ];
    }

    return [
        'target' => 'views/client/verify_otp.php',
        'form_key' => 'verify_otp',
    ];
}
