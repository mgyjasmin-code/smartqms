<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modules/auth/auth_utils.php';

if (isLoggedIn()) {
    redirectAfterLogin((string) $_SESSION['role']);
}

// Compatibility route for previously issued administrator OTP links.
clearOtpSession();
redirectTo('login/');
