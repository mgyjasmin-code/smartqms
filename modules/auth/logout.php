<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/auth_utils.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    redirectTo('login/');
}

requireValidCsrf('login/', 'login');
$providerToken = (string) ($_SESSION['supabase_access_token'] ?? '');
if (smartqmsDataProviderMode() === 'supabase' && $providerToken !== '') {
    smartqmsSupabaseAuthRequest('POST', '/auth/v1/logout', null, $providerToken);
} else {
    logActivity($conn, 'logout', 'User signed out');
}
destroyAuthenticatedSession();
header('Location: ' . APP_URL . '/login/');
exit();
?>
