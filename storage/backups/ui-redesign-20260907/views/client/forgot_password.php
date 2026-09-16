<?php
require_once __DIR__ . '/../../config/config.php';

if (isLoggedIn()) {
    redirectAfterLogin((string) ($_SESSION['role'] ?? ''));
}

$requestFeedback = consumeFormFeedback('forgot_password_request');
$otpFeedback = consumeFormFeedback('forgot_password_otp');
$resetFeedback = consumeFormFeedback('reset_password');
$msg = $_GET['msg'] ?? '';
$isVerified = !empty($_SESSION['reset_capability']);
$hasResetUser = !empty($_SESSION['reset_user_id']) || $isVerified;
$activeFeedback = !$hasResetUser ? $requestFeedback : (!$isVerified ? $otpFeedback : $resetFeedback);
$lastSent = (int) ($_SESSION['otp_last_sent_at'] ?? 0);
$resendCooldown = $lastSent ? max(0, OTP_RESEND_COOLDOWN_SECONDS - (time() - $lastSent)) : 0;
$resendLabel = $resendCooldown > 0 ? 'Resend OTP in ' . gmdate('i:s', $resendCooldown) : 'Resend OTP';
$shouldDispatchEmail = in_array($msg, ['reset_otp_sent', 'otp_resent'], true);
$recoveryStage = !$hasResetUser ? 1 : (!$isVerified ? 2 : 3);
$recoveryTitles = [1 => 'Find your account', 2 => 'Verify the security code', 3 => 'Create a new password'];
$recoveryCopy = [1 => 'Enter the registered email address for your Staff or Administrator account.', 2 => 'Enter the six-digit code sent to the registered email address.', 3 => 'Choose a strong password you have not used for this account before.'];
$publicActivePage = 'login';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= csrfMetaTag() ?>
  <title>Recover Access — SmartQMS</title>
  <?php require __DIR__ . '/../shared/includes/theme_boot.php'; ?>
  <link rel="stylesheet" href="<?= assetUrl('vendor/twbs/bootstrap/dist/css/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
</head>
<body class="public-journey-page auth-page d-flex flex-column min-vh-100">
  <a class="skip-link" href="#main-content">Skip to recovery form</a>
  <?php require __DIR__ . '/../shared/includes/public_header.php'; ?>

  <main id="main-content" class="public-auth-main flex-grow-1" tabindex="-1">
    <div class="container public-auth-container">
      <section class="card public-auth-composition public-recovery-composition overflow-hidden" aria-labelledby="recovery-title">
        <div class="row g-0">
          <div class="col-lg-5 public-auth-context d-none d-lg-flex">
            <div class="p-5 d-flex flex-column h-100">
              <span class="public-auth-emblem"><img src="<?= assetUrl('assets/images/brand/smartqms-mark.svg') ?>" alt="" width="52" height="52"></span>
              <p class="public-kicker mt-4">Account recovery</p>
              <h1 class="h2">Return to your workspace securely.</h1>
              <p>The three-stage process verifies the account before allowing a password change.</p>
              <ol class="public-recovery-steps list-unstyled mt-auto mb-0" aria-label="Recovery progress">
                <?php foreach (['Registered email', 'Security code', 'New password'] as $stage => $label): $number = $stage + 1; ?>
                  <li class="<?= $number < $recoveryStage ? 'is-complete' : ($number === $recoveryStage ? 'is-current' : '') ?>"<?= $number === $recoveryStage ? ' aria-current="step"' : '' ?>><span><?= $number < $recoveryStage ? '<i data-lucide="check" aria-hidden="true"></i>' : $number ?></span><strong><?= htmlspecialchars($label) ?></strong></li>
                <?php endforeach; ?>
              </ol>
            </div>
          </div>
          <div class="col-lg-7">
            <div class="public-auth-form-panel p-4 p-md-5">
              <div class="public-auth-mobile-brand d-lg-none"><img src="<?= assetUrl('assets/images/brand/smartqms-mark.svg') ?>" alt="" width="44" height="44"><span>SmartQMS</span></div>
              <p class="public-kicker">Step <?= $recoveryStage ?> of 3</p>
              <div class="progress public-recovery-progress d-lg-none mb-4" role="progressbar" aria-label="Recovery progress" aria-valuemin="1" aria-valuemax="3" aria-valuenow="<?= $recoveryStage ?>"><div class="progress-bar" style="width: <?= ($recoveryStage / 3) * 100 ?>%"></div></div>
              <h1 id="recovery-title" class="h2"><?= htmlspecialchars($recoveryTitles[$recoveryStage]) ?></h1>
              <p class="text-body-secondary mb-4"><?= htmlspecialchars($recoveryCopy[$recoveryStage]) ?></p>

              <?php if ($activeFeedback['form_error']): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($activeFeedback['form_error']) ?></div><?php endif; ?>
              <?php if ($shouldDispatchEmail): ?><span hidden data-email-dispatch="<?= postActionUrl('modules/notifications/dispatch_email_jobs.php') ?>"></span><?php endif; ?>

              <?php if (!$hasResetUser): ?>
                <form action="<?= postActionUrl('modules/auth/forgot_password.php') ?>" method="post" class="auth-form auth-form-stable-errors js-auth-form">
                  <?= csrfInput() ?><input type="hidden" name="action" value="request_otp">
                  <div class="mb-3"><label class="form-label" for="email">Registered email address</label><input id="email" type="email" name="email" class="form-control form-control-lg<?= fieldInvalidClass($requestFeedback, 'email') ?>" value="<?= htmlspecialchars(oldFormValue($requestFeedback, 'email'), ENT_QUOTES) ?>" placeholder="staff@example.com" required autocomplete="email"<?= fieldAriaInvalid($requestFeedback, 'email') ?>><div class="invalid-feedback field-error" aria-live="polite"><?= htmlspecialchars(fieldError($requestFeedback, 'email')) ?></div></div>
                  <button type="submit" class="btn btn-primary btn-lg w-100" data-loading-text="Sending code…">Send Security Code</button>
                </form>
              <?php elseif (!$isVerified): ?>
                <form action="<?= postActionUrl('modules/auth/forgot_password.php') ?>" method="post" class="auth-form auth-form-stable-errors js-auth-form">
                  <?= csrfInput() ?><input type="hidden" name="action" value="verify_otp">
                  <div class="mb-3"><label class="form-label" for="otp_code">Six-digit security code</label><input id="otp_code" type="text" inputmode="numeric" name="otp_code" class="form-control form-control-lg public-otp-input<?= fieldInvalidClass($otpFeedback, 'otp_code') ?>" maxlength="6" pattern="[0-9]{6}" placeholder="123456" required autocomplete="one-time-code"<?= fieldAriaInvalid($otpFeedback, 'otp_code') ?>><div class="invalid-feedback field-error" aria-live="polite"><?= htmlspecialchars(fieldError($otpFeedback, 'otp_code')) ?></div></div>
                  <button type="submit" class="btn btn-primary btn-lg w-100" data-loading-text="Verifying…">Verify Code</button>
                </form>
                <form action="<?= postActionUrl('modules/auth/resend_otp.php') ?>" method="post" class="auth-resend text-center mt-3"><?= csrfInput() ?><button class="btn btn-link" type="submit" data-otp-resend data-ready-text="Resend security code" data-wait-text="Resend code in" data-remaining="<?= (int) $resendCooldown ?>" <?= $resendCooldown > 0 ? 'disabled' : '' ?>><?= htmlspecialchars($resendLabel) ?></button></form>
              <?php else: ?>
                <form action="<?= postActionUrl('modules/auth/forgot_password.php') ?>" method="post" class="auth-form auth-form-stable-errors js-auth-form">
                  <?= csrfInput() ?><input type="hidden" name="action" value="reset_password">
                  <?php foreach ([['password', 'New password', 'new password'], ['confirm_password', 'Confirm new password', 'confirmation password']] as [$fieldName, $fieldLabel, $toggleLabel]): ?>
                    <div class="mb-3"><label class="form-label" for="<?= $fieldName ?>"><?= htmlspecialchars($fieldLabel) ?></label><div class="input-group input-group-lg auth-password-field"><input id="<?= $fieldName ?>" type="password" name="<?= $fieldName ?>" class="form-control<?= fieldInvalidClass($resetFeedback, $fieldName) ?>" placeholder="12+ characters" required minlength="12" autocomplete="new-password"<?= $fieldName === 'confirm_password' ? ' data-confirm-password-for="password"' : '' ?><?= fieldAriaInvalid($resetFeedback, $fieldName) ?>><button class="btn btn-outline-secondary password-toggle" type="button" data-password-toggle data-password-toggle-label="<?= $toggleLabel ?>" aria-label="Show <?= $toggleLabel ?>" aria-controls="<?= $fieldName ?>" aria-pressed="false"><i data-lucide="eye" data-password-show-icon aria-hidden="true"></i><i data-lucide="eye-off" data-password-hide-icon aria-hidden="true" hidden></i></button><div class="invalid-feedback field-error" aria-live="polite"><?= htmlspecialchars(fieldError($resetFeedback, $fieldName)) ?></div></div></div>
                  <?php endforeach; ?>
                  <button type="submit" class="btn btn-primary btn-lg w-100" data-loading-text="Saving…">Change Password</button>
                </form>
              <?php endif; ?>

              <div class="public-auth-return mt-4 pt-4"><i data-lucide="arrow-left" aria-hidden="true"></i><a href="<?= APP_URL ?>/login/">Back to Staff/Admin Login</a></div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </main>

  <?php require __DIR__ . '/../shared/includes/public_footer.php'; ?>
  <script src="<?= assetUrl('vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= assetUrl('assets/vendor/lucide/lucide.min.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/theme.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/language.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/main.js') ?>"></script>
</body>
</html>
