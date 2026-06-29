<?php
require_once '../../config/config.php';

if (isLoggedIn()) {
    redirectTo('views/client/index.php');
}

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register -- SmartQMS</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="auth-page">
  <main class="auth-shell">
    <section class="auth-card register-card">
      <header class="auth-hero">
        <div class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" role="img">
            <path d="M12 3.2 18.2 5.8v5.1c0 3.9-2.5 7.5-6.2 8.9-3.7-1.4-6.2-5-6.2-8.9V5.8L12 3.2Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
            <path d="m9 12 2 2 4-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <h1>Smart QMS</h1>
        <p>Join the priority queue at your Barangay Health Center. Simple, fast, and digital.</p>
      </header>

      <div class="auth-body">
        <?php if ($error): ?>
          <div class="auth-alert auth-alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="<?= postActionUrl('modules/auth/register.php') ?>" method="POST" class="auth-form">
          <div class="auth-grid auth-grid-3">
            <div>
              <label class="auth-label required" for="first_name">First Name</label>
              <input id="first_name" class="auth-input" name="first_name" placeholder="Ana" required autocomplete="given-name">
            </div>
            <div>
              <label class="auth-label required" for="last_name">Last Name</label>
              <input id="last_name" class="auth-input" name="last_name" placeholder="Santos" required autocomplete="family-name">
            </div>
            <div>
              <label class="auth-label" for="middle_name">Middle Name</label>
              <input id="middle_name" class="auth-input" name="middle_name" placeholder="Dela Cruz" autocomplete="additional-name">
            </div>
          </div>

          <div class="auth-grid auth-grid-2">
            <div>
              <label class="auth-label required" for="phone_number">Phone Number</label>
              <input id="phone_number" class="auth-input" type="tel" name="phone_number" placeholder="0917 123 4567" required autocomplete="tel">
            </div>
            <div>
              <label class="auth-label" for="email">Email <span>(Optional)</span></label>
              <input id="email" class="auth-input" type="email" name="email" placeholder="ana.santos@example.ph" autocomplete="email">
            </div>
          </div>

          <div class="form-row-single">
            <label class="auth-label required" for="password">Security PIN / Password</label>
            <input id="password" class="auth-input" type="password" name="password" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="Create a 6-digit PIN" required autocomplete="new-password">
          </div>

          <fieldset class="classification-group">
            <legend>Client Classification</legend>
            <div class="classification-options">
              <label class="classification-card">
                <input type="radio" name="client_type" value="regular" checked>
                <span class="classification-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24"><path d="M12 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-6 7a6 6 0 0 1 12 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                </span>
                <strong>Regular</strong>
                <small>General Queue</small>
              </label>
              <label class="classification-card">
                <input type="radio" name="client_type" value="senior">
                <span class="classification-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24"><path d="M11 5a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm1 6v8m0-5h3l2 5m-5-8-3 3-1 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <strong>Senior</strong>
                <small>Priority Lane</small>
              </label>
              <label class="classification-card">
                <input type="radio" name="client_type" value="pwd">
                <span class="classification-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24"><path d="M10 5a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm0 3v5h5l2 5m-7-8a5 5 0 1 0 5 7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <strong>PWD</strong>
                <small>Priority Lane</small>
              </label>
            </div>
          </fieldset>

          <button class="auth-submit mb-3" type="submit">
            Register and Verify Phone
            <span aria-hidden="true">→</span>
          </button>
        </form>

        <div class="privacy-note">
          <span aria-hidden="true">ⓘ</span>
          Your data is handled securely following Philippine privacy laws.
        </div>

        <p class="auth-switch">
          Already registered?
          <a href="<?= APP_URL ?>/index.php">Sign in</a>
        </p>
      </div>
    </section>
  </main>
</body>
</html>
