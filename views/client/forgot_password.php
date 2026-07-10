<?php
require_once '../../config/config.php';

if (isLoggedIn()) {
    redirectTo('views/client/index.php');
}

$requestFeedback = consumeFormFeedback('forgot_password_request');
$otpFeedback = consumeFormFeedback('forgot_password_otp');
$resetFeedback = consumeFormFeedback('reset_password');
$msg = $_GET['msg'] ?? '';
$hasResetUser = !empty($_SESSION['reset_user_id']);
$isVerified = !empty($_SESSION['reset_verified_user_id']);
$activeFeedback = !$hasResetUser ? $requestFeedback : (!$isVerified ? $otpFeedback : $resetFeedback);
$lastSent = (int) ($_SESSION['otp_last_sent_at'] ?? 0);
$resendCooldown = $lastSent ? max(0, OTP_RESEND_COOLDOWN_SECONDS - (time() - $lastSent)) : 0;
$resendLabel = $resendCooldown > 0 ? 'Resend OTP in ' . gmdate('i:s', $resendCooldown) : 'Resend OTP';
$shouldDispatchEmail = in_array($msg, ['reset_otp_sent', 'otp_resent'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= csrfMetaTag() ?>
  <title>Forgot Password -- SmartQMS</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
</head>
<body class="auth-page">
  <main class="auth-shell auth-shell-login d-flex flex-column justify-content-center align-items-center">
    <section class="auth-card">
      <header class="auth-hero auth-hero-compact">
        <h1>Reset Access</h1>
        <p>Use your registered email to verify and create a new password.</p>
      </header>

      <div class="auth-body">
        <?php if ($activeFeedback['form_error']): ?>
          <div class="auth-alert auth-alert-error" role="alert"><?= htmlspecialchars($activeFeedback['form_error']) ?></div>
        <?php endif; ?>
        <?php if ($shouldDispatchEmail): ?>
          <span hidden data-email-dispatch="<?= postActionUrl('modules/notifications/dispatch_email_jobs.php') ?>"></span>
        <?php endif; ?>

        <?php if (!$hasResetUser): ?>
          <form action="<?= postActionUrl('modules/auth/forgot_password.php') ?>" method="POST" class="auth-form js-auth-form" novalidate>
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="request_otp">
            <div class="form-row-single">
              <label class="auth-label required" for="email">Registered Email Address</label>
              <input id="email" type="email" name="email" class="auth-input<?= fieldInvalidClass($requestFeedback, 'email') ?>"
                     value="<?= htmlspecialchars(oldFormValue($requestFeedback, 'email'), ENT_QUOTES) ?>"
                     placeholder="client@example.com" required autocomplete="email" data-validate="email"<?= fieldAriaInvalid($requestFeedback, 'email') ?>>
              <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($requestFeedback, 'email')) ?></div>
            </div>
            <button type="submit" class="auth-submit" data-loading-text="Sending OTP...">Send OTP</button>
          </form>
        <?php elseif (!$isVerified): ?>
          <form action="<?= postActionUrl('modules/auth/forgot_password.php') ?>" method="POST" class="auth-form auth-form-stable-errors js-auth-form" novalidate>
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="verify_otp">
            <div class="form-row-single">
              <label class="auth-label required" for="otp_code">OTP Code</label>
              <input id="otp_code" type="text" inputmode="numeric" name="otp_code" class="auth-input auth-otp-input<?= fieldInvalidClass($otpFeedback, 'otp_code') ?>"
                     maxlength="6" pattern="\d{6}" placeholder="123456" required autocomplete="one-time-code" data-validate="otp"<?= fieldAriaInvalid($otpFeedback, 'otp_code') ?>>
              <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($otpFeedback, 'otp_code')) ?></div>
            </div>
            <button type="submit" class="auth-submit" data-loading-text="Verifying...">Verify OTP</button>
          </form>
          <form action="<?= postActionUrl('modules/auth/resend_otp.php') ?>" method="POST" class="auth-resend">
            <?= csrfInput() ?>
            <button class="auth-link-button" type="submit" data-otp-resend data-ready-text="Resend OTP" data-wait-text="Resend OTP in" data-remaining="<?= (int) $resendCooldown ?>" <?= $resendCooldown > 0 ? 'disabled' : '' ?>>
              <?= htmlspecialchars($resendLabel) ?>
            </button>
          </form>
        <?php else: ?>
          <form action="<?= postActionUrl('modules/auth/forgot_password.php') ?>" method="POST" class="auth-form js-auth-form" novalidate>
            <?= csrfInput() ?>
            <input type="hidden" name="action" value="reset_password">
            <div class="form-row-single">
              <label class="auth-label required" for="password">New Password</label>
              <div class="auth-password-field">
                <input id="password" type="password" name="password" class="auth-input<?= fieldInvalidClass($resetFeedback, 'password') ?>"
                       placeholder="At least 8 characters" required autocomplete="new-password" data-validate="password"<?= fieldAriaInvalid($resetFeedback, 'password') ?>>
                <button class="password-toggle" type="button" data-password-toggle
                        data-password-toggle-label="new password" aria-label="Show new password"
                        aria-controls="password" aria-pressed="false" title="Show new password">
                  <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
              </div>
              <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($resetFeedback, 'password')) ?></div>
            </div>
            <div class="form-row-single">
              <label class="auth-label required" for="confirm_password">Re-type Password</label>
              <div class="auth-password-field">
                <input id="confirm_password" type="password" name="confirm_password" class="auth-input<?= fieldInvalidClass($resetFeedback, 'confirm_password') ?>"
                       placeholder="Re-enter your password" required autocomplete="new-password" data-validate="password-confirm"<?= fieldAriaInvalid($resetFeedback, 'confirm_password') ?>>
                <button class="password-toggle" type="button" data-password-toggle
                        data-password-toggle-label="re-typed password" aria-label="Show re-typed password"
                        aria-controls="confirm_password" aria-pressed="false" title="Show re-typed password">
                  <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
              </div>
              <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($resetFeedback, 'confirm_password')) ?></div>
            </div>
            <button type="submit" class="auth-submit" data-loading-text="Saving...">Change Password</button>
          </form>
        <?php endif; ?>

        <div class="auth-divider"></div>
        <p class="auth-switch"><a href="<?= APP_URL ?>/index.php">Back to sign in</a></p>
      </div>
    </section>
  </main>
  <script src="<?= assetUrl('assets/js/main.js') ?>"></script>
</body>
</html>
