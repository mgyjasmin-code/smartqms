<?php
/** Unified Staff/Admin authentication page. */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modules/auth/auth_redirects.php';

if (isLoggedIn()) {
    $role = (string) ($_SESSION['role'] ?? '');
    if (in_array($role, [ROLE_ADMIN, ROLE_STAFF], true)) {
        redirectAfterLogin($role);
    }
    session_unset();
    session_destroy();
    redirectTo('');
}

$feedback = consumeFormFeedback('login');
$loginFormError = trim((string) $feedback['form_error']);
if ($loginFormError !== '' && empty($feedback['field_errors']['password'])) {
    $feedback['field_errors']['password'] = $loginFormError;
    $feedback['form_error'] = '';
}
$publicActivePage = 'login';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Staff/Admin Login — SmartQMS</title>
  <?php require __DIR__ . '/../views/shared/includes/theme_boot.php'; ?>
  <link rel="stylesheet" href="<?= assetUrl('vendor/twbs/bootstrap/dist/css/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
</head>
<body class="public-journey-page auth-page d-flex flex-column min-vh-100">
  <a class="skip-link" href="#main-content">Skip to login form</a>
  <?php require __DIR__ . '/../views/shared/includes/public_header.php'; ?>

  <main id="main-content" class="public-auth-main flex-grow-1" tabindex="-1">
    <div class="container public-auth-container">
      <section class="card public-auth-composition overflow-hidden" aria-labelledby="login-title">
        <div class="row g-0">
          <div class="col-lg-5 public-auth-context d-none d-lg-flex">
            <div class="p-5 d-flex flex-column h-100">
              <span class="public-auth-emblem"><img src="<?= assetUrl('assets/images/brand/smartqms-mark.svg') ?>" alt="" width="52" height="52"></span>
              <p class="public-kicker mt-4">Authorized workspace</p>
              <h1 class="h2">Health-center operations, kept clear and accountable.</h1>
              <p>Staff and administrators sign in here to check clients in, serve the fair FIFO queue, and manage configured services.</p>
              <ul class="list-unstyled public-auth-trust mt-auto mb-0">
                <li><i data-lucide="shield-check" aria-hidden="true"></i><span><strong>Role-based access</strong><small>Your account opens only the workspace assigned to it.</small></span></li>
                <li><i data-lucide="lock-keyhole" aria-hidden="true"></i><span><strong>Protected sign-in</strong><small>CSRF protection and login throttling remain active.</small></span></li>
                <li><i data-lucide="users" aria-hidden="true"></i><span><strong>Client privacy</strong><small>Public queue views never expose names or mobile numbers.</small></span></li>
              </ul>
            </div>
          </div>
          <div class="col-lg-7">
            <div class="public-auth-form-panel p-4 p-md-5">
              <div class="public-auth-mobile-brand d-lg-none"><img src="<?= assetUrl('assets/images/brand/smartqms-mark.svg') ?>" alt="" width="44" height="44"><span>SmartQMS</span></div>
              <p class="public-kicker">Staff and administration</p>
              <h1 id="login-title" class="h2">Sign in to your workspace</h1>
              <p class="text-body-secondary mb-4">Use your email address or username. Client booking does not require an account.</p>
              <?php if ($feedback['form_error']): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($feedback['form_error']) ?></div><?php endif; ?>
              <form action="<?= APP_URL ?>/modules/auth/login.php" method="post" class="auth-form auth-form-stable-errors js-auth-form">
                <?= csrfInput() ?>
                <div class="mb-3">
                  <label class="form-label" for="login_id">Email address or username</label>
                  <input id="login_id" type="text" name="login_id" class="form-control form-control-lg<?= fieldInvalidClass($feedback, 'login_id') ?>" value="<?= htmlspecialchars(oldFormValue($feedback, 'login_id'), ENT_QUOTES) ?>" placeholder="Enter your email or username" required autocomplete="username"<?= fieldAriaInvalid($feedback, 'login_id') ?>>
                  <div class="invalid-feedback field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'login_id')) ?></div>
                </div>
                <div class="mb-2">
                  <div class="d-flex justify-content-between align-items-center gap-3"><label class="form-label" for="password">Password</label><a class="small" href="<?= APP_URL ?>/forgot-password/">Forgot password?</a></div>
                  <div class="input-group input-group-lg auth-password-field">
                    <input id="password" type="password" name="password" class="form-control<?= fieldInvalidClass($feedback, 'password') ?>" placeholder="Enter your password" required autocomplete="current-password"<?= fieldAriaInvalid($feedback, 'password') ?>>
                    <button class="btn btn-outline-secondary password-toggle" type="button" data-password-toggle data-password-toggle-label="password" aria-label="Show password" aria-controls="password" aria-pressed="false" title="Show password"><i data-lucide="eye" data-password-show-icon aria-hidden="true"></i><i data-lucide="eye-off" data-password-hide-icon aria-hidden="true" hidden></i></button>
                    <div class="invalid-feedback field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'password')) ?></div>
                  </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100 mt-4" data-loading-text="Signing in…">Sign In</button>
              </form>
              <div class="public-auth-return mt-4 pt-4"><i data-lucide="circle-help" aria-hidden="true"></i><span>Looking for client services? <a href="<?= APP_URL ?>/queue/join/">Book a visit without signing in.</a></span></div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </main>

  <?php require __DIR__ . '/../views/shared/includes/public_footer.php'; ?>
  <script src="<?= assetUrl('vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= assetUrl('assets/vendor/lucide/lucide.min.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/theme.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/language.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/main.js') ?>"></script>
</body>
</html>
