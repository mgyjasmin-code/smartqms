<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../modules/auth/auth_utils.php';

requireLogin();
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!isValidCsrfToken()) {
        $error = 'Your session expired. Refresh and try again.';
    } else {
        $current = (string) ($_POST['current_password'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        $user = authUserById($conn, (int) $_SESSION['user_id']);
        if (!$user || !password_verify($current, (string) $user['password_hash'])) {
            $error = 'The current password is incorrect.';
        } elseif (strlen($password) < 12 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
            $error = 'Use at least 12 characters with upper-case, lower-case, and a number.';
        } elseif ($password !== $confirm) {
            $error = 'The new password entries do not match.';
        } elseif (password_verify($password, (string) $user['password_hash'])) {
            $error = 'Choose a password you have not just used.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $userId = (int) $user['user_id'];
            $stmt = $conn->prepare('UPDATE users SET password_hash = ?, must_change_password = 0, session_version = session_version + 1 WHERE user_id = ?');
            $stmt->bind_param('si', $hash, $userId);
            $stmt->execute();
            $_SESSION['session_version'] = (int) $user['session_version'] + 1;
            logActivity($conn, 'password_changed', 'Password changed through authenticated rotation flow.');
            redirectAfterLogin((string) $_SESSION['role'], ['msg' => 'password_changed']);
        }
    }
}
$publicActivePage = 'login';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Change Password — SmartQMS</title><?php require __DIR__ . '/../views/shared/includes/theme_boot.php'; ?><link rel="stylesheet" href="<?= assetUrl('vendor/twbs/bootstrap/dist/css/bootstrap.min.css') ?>"><link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>"></head>
<body class="public-journey-page auth-page d-flex flex-column min-vh-100"><a class="skip-link" href="#main-content">Skip to password form</a><?php require __DIR__ . '/../views/shared/includes/public_header.php'; ?><main id="main-content" class="public-auth-main flex-grow-1" tabindex="-1"><div class="container public-auth-container"><section class="card public-auth-composition mx-auto"><div class="public-auth-form-panel p-4 p-md-5"><p class="public-kicker">Account security</p><h1 class="h2">Create a private password</h1><p class="text-body-secondary">A password change is required before entering the workspace.</p><?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?><form method="post" class="auth-form js-auth-form"><?= csrfInput() ?><div class="mb-3"><label class="form-label" for="current-password">Current password</label><input class="form-control form-control-lg" id="current-password" name="current_password" type="password" required autocomplete="current-password"></div><div class="mb-3"><label class="form-label" for="new-password">New password</label><input class="form-control form-control-lg" id="new-password" name="password" type="password" minlength="12" required autocomplete="new-password"></div><div class="mb-4"><label class="form-label" for="confirm-password">Confirm new password</label><input class="form-control form-control-lg" id="confirm-password" name="confirm_password" type="password" minlength="12" required autocomplete="new-password"></div><button class="btn btn-primary btn-lg w-100" type="submit">Change password and continue</button></form></div></section></div></main><script src="<?= assetUrl('assets/js/theme.js') ?>"></script><script src="<?= assetUrl('assets/js/main.js') ?>"></script></body></html>
