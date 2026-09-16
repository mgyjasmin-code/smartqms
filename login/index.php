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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Staff/Admin Login — SmartQMS</title>
  <link rel="stylesheet" href="<?= assetUrl('vendor/twbs/bootstrap/dist/css/bootstrap.min.css') ?>">
</head>
<body class="bg-light">
  <a class="visually-hidden-focusable" href="#main-content">Skip to login form</a>

  <main id="main-content" class="container min-vh-100 d-flex align-items-center justify-content-center py-5" tabindex="-1">
    <div class="row justify-content-center w-100">
      <div class="col-12 col-sm-10 col-md-8 col-lg-7 col-xl-6 col-xxl-5">
        <section class="card shadow-sm" aria-labelledby="login-title">
          <div class="card-body p-4 p-sm-5">
            <h1 id="login-title" class="card-title h2 text-center mb-4">Login</h1>

            <?php if ($feedback['form_error']): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($feedback['form_error']) ?></div><?php endif; ?>

            <form action="<?= APP_URL ?>/modules/auth/login.php" method="post" class="js-auth-form">
              <?= csrfInput() ?>
              <div class="form-floating mb-3">
                <input id="login_id" type="text" name="login_id" class="form-control<?= fieldInvalidClass($feedback, 'login_id') ?>" value="<?= htmlspecialchars(oldFormValue($feedback, 'login_id'), ENT_QUOTES) ?>" placeholder="Enter your email or username" required autocomplete="username"<?= fieldAriaInvalid($feedback, 'login_id') ?>>
                <label for="login_id">Email address or username</label>
                <div class="invalid-feedback" data-field-error-for="login_id" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'login_id')) ?></div>
              </div>
              <div class="mb-3">
                <div class="form-floating position-relative">
                  <input id="password" type="password" name="password" class="form-control pe-5<?= fieldInvalidClass($feedback, 'password') ?>" placeholder="Enter your password" required autocomplete="current-password"<?= fieldAriaInvalid($feedback, 'password') ?>>
                  <label for="password">Password</label>
                  <button class="btn border-0 position-absolute top-50 end-0 translate-middle-y me-2 text-secondary z-1" type="button" data-password-toggle data-password-toggle-label="password" aria-label="Show password" aria-controls="password" aria-pressed="false" title="Show password">
                    <svg data-password-show-icon aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M13.359 11.238 15 12.879l-.707.707-13-13L2 .879l2.28 2.28A8.8 8.8 0 0 1 8 2c3.636 0 6.22 2.642 7.385 4.191a1.47 1.47 0 0 1 0 1.618 12 12 0 0 1-2.026 2.429ZM5.063 3.942l1.013 1.013A3 3 0 0 1 11 7.269l1.636 1.636A10.8 10.8 0 0 0 14.586 7 10.7 10.7 0 0 0 8 3c-1.08 0-2.057.251-2.937.942ZM8.5 9.975 6.025 7.5q-.025.245-.025.5a2 2 0 0 0 2.5 1.975Z"/><path d="M3.35 5.064A10.8 10.8 0 0 0 1.414 7 10.7 10.7 0 0 0 8 11c.421 0 .828-.038 1.219-.108l.853.853A9.7 9.7 0 0 1 8 12c-3.636 0-6.22-2.642-7.385-4.191a1.47 1.47 0 0 1 0-1.618 12 12 0 0 1 2.02-2.423z"/></svg>
                    <svg data-password-hide-icon hidden aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8a13 13 0 0 1-1.66 2.043C11.88 11.332 10.12 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/><path d="M8 5.5A2.5 2.5 0 1 0 8 10.5 2.5 2.5 0 0 0 8 5.5"/></svg>
                  </button>
                </div>
                <div class="invalid-feedback<?= fieldError($feedback, 'password') !== '' ? ' d-block' : '' ?>" data-field-error-for="password" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'password')) ?></div>
              </div>

              <div class="text-end mb-3">
                <a class="text-decoration-none" href="<?= APP_URL ?>/forgot-password/">Forgot password?</a>
              </div>

              <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg" data-loading-text="Logging in…">Login</button>
              </div>
            </form>
          </div>
        </section>
      </div>
    </div>
  </main>

  <script src="<?= assetUrl('vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/main.js') ?>"></script>
</body>
</html>
