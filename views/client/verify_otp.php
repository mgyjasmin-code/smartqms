<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$feedback = consumeFormFeedback('verify_otp');
$msg = $_GET['msg'] ?? '';
$flow = $_SESSION['otp_flow'] ?? 'register';
$title = $flow === 'login' ? 'Verify Login' : 'Verify Email';
$copy = $flow === 'login'
    ? 'Enter the 6-digit code sent to your registered email to finish signing in.'
    : 'Enter the 6-digit code sent to your email to finish registration.';
$lastSent = (int) ($_SESSION['otp_last_sent_at'] ?? 0);
$resendCooldown = $lastSent ? max(0, OTP_RESEND_COOLDOWN_SECONDS - (time() - $lastSent)) : 0;
$resendLabel = $resendCooldown > 0 ? 'Resend OTP in ' . gmdate('i:s', $resendCooldown) : 'Resend OTP';
$shouldDispatchEmail = in_array($msg, ['otp_sent', 'login_otp_sent', 'otp_resent'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= csrfMetaTag() ?>
  <title>Verify OTP -- SmartQMS</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
</head>
<body class="auth-page">
  <main class="auth-shell auth-shell-login d-flex flex-column justify-content-center align-items-center">
    <section class="auth-card">
      <header class="auth-hero auth-hero-compact">
        <h1><?= htmlspecialchars($title) ?></h1>
        <p><?= htmlspecialchars($copy) ?></p>
      </header>

      <div class="auth-body">
        <?php if ($feedback['form_error']): ?>
          <div class="auth-alert auth-alert-error" role="alert"><?= htmlspecialchars($feedback['form_error']) ?></div>
        <?php endif; ?>
        <?php if ($shouldDispatchEmail): ?>
          <span hidden data-email-dispatch="<?= postActionUrl('modules/notifications/dispatch_email_jobs.php') ?>"></span>
        <?php endif; ?>

        <form action="<?= postActionUrl('modules/auth/verify_otp.php') ?>" method="POST" class="auth-form auth-form-stable-errors js-auth-form" novalidate>
          <?= csrfInput() ?>
          <div class="form-row-single">
            <label class="auth-label required" for="otp_code">OTP Code</label>
            <input id="otp_code" class="auth-input auth-otp-input<?= fieldInvalidClass($feedback, 'otp_code') ?>" name="otp_code" inputmode="numeric"
                   maxlength="6" pattern="\d{6}" placeholder="123456" required autocomplete="one-time-code" data-validate="otp"<?= fieldAriaInvalid($feedback, 'otp_code') ?>>
            <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'otp_code')) ?></div>
          </div>
          <button class="auth-submit" type="submit" data-loading-text="Verifying...">Verify OTP</button>
        </form>

        <form action="<?= postActionUrl('modules/auth/resend_otp.php') ?>" method="POST" class="auth-resend">
          <?= csrfInput() ?>
          <button class="auth-link-button" type="submit" data-otp-resend data-ready-text="Resend OTP" data-wait-text="Resend OTP in" data-remaining="<?= (int) $resendCooldown ?>" <?= $resendCooldown > 0 ? 'disabled' : '' ?>>
            <?= htmlspecialchars($resendLabel) ?>
          </button>
        </form>

        <div class="auth-divider"></div>
        <p class="auth-switch"><a href="<?= APP_URL ?>/index.php">Back to sign in</a></p>
      </div>
    </section>
  </main>
  <script src="<?= assetUrl('assets/js/main.js') ?>"></script>
</body>
</html>
