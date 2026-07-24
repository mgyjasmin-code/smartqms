<?php
/**
 * SmartQMS -- Entry Point & Unified Login Page
 */
require_once 'config/config.php';

if (isLoggedIn()) {
    $role = $_SESSION['role'];
    if ($role === ROLE_ADMIN)  { header('Location: views/admin/dashboard.php');  exit(); }
    if ($role === ROLE_STAFF)  { header('Location: views/staff/dashboard.php');  exit(); }
    if ($role === ROLE_CLIENT) { header('Location: views/client/index.php');     exit(); }
}

$feedback = consumeFormFeedback('login');
$loginFormError = trim((string) $feedback['form_error']);
if ($loginFormError !== '') {
    if (empty($feedback['field_errors']['password'])) {
        $feedback['field_errors']['password'] = $loginFormError;
    } elseif (empty($feedback['field_errors']['login_id'])) {
        $feedback['field_errors']['login_id'] = $loginFormError;
    }
    $feedback['form_error'] = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign In -- SmartQMS</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
</head>
<body class="auth-page">
  <a class="skip-link" href="#main-content">Skip to sign in form</a>
  <main id="main-content" class="auth-shell auth-shell-login d-flex flex-column justify-content-center align-items-center" tabindex="-1">
    <section class="auth-card">
      <header class="auth-hero auth-hero-compact">
        <h1>Smart QMS</h1>
        <p>Barangay Health Center Queue System</p>
      </header>

      <div class="auth-body">
        <?php if ($feedback['form_error']): ?>
          <div class="auth-alert auth-alert-error" role="alert"><?= htmlspecialchars($feedback['form_error']) ?></div>
        <?php endif; ?>

        <form action="modules/auth/login.php" method="POST" class="auth-form auth-form-stable-errors auth-form-polished js-auth-form" novalidate>
          <?= csrfInput() ?>
          <div class="form-row-single">
            <label class="auth-label" for="login_id">Email Address</label>
            <input id="login_id" type="email" name="login_id" class="auth-input<?= fieldInvalidClass($feedback, 'login_id') ?>"
                   value="<?= htmlspecialchars(oldFormValue($feedback, 'login_id'), ENT_QUOTES) ?>"
                   placeholder="Enter your email" required autocomplete="email" data-validate="email"<?= fieldAriaInvalid($feedback, 'login_id') ?>>
            <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'login_id')) ?></div>
          </div>
          <div class="form-row-single">
            <label class="auth-label" for="password">Password</label>
            <div class="auth-password-field">
              <input id="password" type="password" name="password" class="auth-input<?= fieldInvalidClass($feedback, 'password') ?>"
                     placeholder="Enter your password" required autocomplete="current-password" data-validate="required"<?= fieldAriaInvalid($feedback, 'password') ?>>
              <button class="password-toggle" type="button" data-password-toggle
                      data-password-toggle-label="password" aria-label="Show password"
                      aria-controls="password" aria-pressed="false" title="Show password">
                <i class="bi bi-eye" aria-hidden="true"></i>
              </button>
            </div>
            <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'password')) ?></div>
          </div>
          <div class="auth-form-meta">
            <a href="views/client/forgot_password.php">Forgot password?</a>
          </div>
          <button type="submit" class="auth-submit" data-loading-text="Signing in...">
            Sign In
          </button>
        </form>

        <div class="auth-divider"></div>
        <p class="auth-switch">
          Don't have an account?
          <a href="views/client/register.php">Register here</a>
        </p>
      </div>
    </section>
  </main>
  <script src="<?= assetUrl('assets/js/main.js') ?>"></script>
</body>
</html>
