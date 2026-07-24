<?php
require_once '../../config/config.php';

if (isLoggedIn()) {
    redirectTo('views/client/index.php');
}

$feedback = consumeFormFeedback('register');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register -- SmartQMS</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
</head>
<body class="auth-page">
  <a class="skip-link" href="#main-content">Skip to registration form</a>
  <main id="main-content" class="auth-shell auth-shell-login d-flex flex-column justify-content-center align-items-center" tabindex="-1">
    <section class="auth-card register-card register-card-compact">
      <header class="auth-hero">
        <h1>Smart QMS</h1>
        <p>Create your account. We will send a verification code to your email.</p>
      </header>

      <div class="auth-body">
        <?php if ($feedback['form_error']): ?>
          <div class="auth-alert auth-alert-error" role="alert"><?= htmlspecialchars($feedback['form_error']) ?></div>
        <?php endif; ?>

        <form action="<?= postActionUrl('modules/auth/register.php') ?>" method="POST" class="auth-form auth-form-stable-errors auth-form-polished js-auth-form" novalidate>
          <?= csrfInput() ?>
          <div class="auth-grid auth-grid-2">
            <div>
              <label class="auth-label required" for="first_name">First Name</label>
              <input id="first_name" class="auth-input<?= fieldInvalidClass($feedback, 'first_name') ?>" name="first_name"
                     value="<?= htmlspecialchars(oldFormValue($feedback, 'first_name'), ENT_QUOTES) ?>"
                     placeholder="Ana" required autocomplete="given-name" data-validate="required"<?= fieldAriaInvalid($feedback, 'first_name') ?>>
              <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'first_name')) ?></div>
            </div>
            <div>
              <label class="auth-label required" for="last_name">Last Name</label>
              <input id="last_name" class="auth-input<?= fieldInvalidClass($feedback, 'last_name') ?>" name="last_name"
                     value="<?= htmlspecialchars(oldFormValue($feedback, 'last_name'), ENT_QUOTES) ?>"
                     placeholder="Santos" required autocomplete="family-name" data-validate="required"<?= fieldAriaInvalid($feedback, 'last_name') ?>>
              <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'last_name')) ?></div>
            </div>
          </div>

          <div class="form-row-single">
            <label class="auth-label required" for="email">Email</label>
            <input id="email" class="auth-input<?= fieldInvalidClass($feedback, 'email') ?>" type="email" name="email"
                   value="<?= htmlspecialchars(oldFormValue($feedback, 'email'), ENT_QUOTES) ?>"
                   placeholder="ana.santos@example.ph" required autocomplete="email" data-validate="email"<?= fieldAriaInvalid($feedback, 'email') ?>>
            <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'email')) ?></div>
          </div>

          <div class="form-row-single">
            <label class="auth-label required" for="password">Password</label>
            <div class="auth-password-field">
              <input id="password" class="auth-input<?= fieldInvalidClass($feedback, 'password') ?>" type="password" name="password"
                     placeholder="At least 8 characters" required autocomplete="new-password" data-validate="password"
                     aria-describedby="password-helper"<?= fieldAriaInvalid($feedback, 'password') ?>>
              <button class="password-toggle" type="button" data-password-toggle
                      data-password-toggle-label="password" aria-label="Show password"
                      aria-controls="password" aria-pressed="false" title="Show password">
                <i class="bi bi-eye" aria-hidden="true"></i>
              </button>
            </div>
            <p id="password-helper" class="auth-helper">Use at least 8 characters. Your password is never included in email messages.</p>
            <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'password')) ?></div>
          </div>

          <button class="auth-submit mb-3 mt-2" type="submit" data-loading-text="Sending OTP...">
            Register and Verify Email
          </button>
        </form>
        <p class="auth-switch">
          Already have an account?
          <a href="<?= APP_URL ?>/index.php">Login here</a>
        </p>
        <p class="auth-admin-note">
          <i class="bi bi-person-check" aria-hidden="true"></i>
          This form creates client accounts only.
        </p>
      </div>
    </section>
  </main>
  <script src="<?= assetUrl('assets/js/main.js') ?>"></script>
</body>
</html>
