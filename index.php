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

$error = $_GET['error'] ?? '';
$msg   = $_GET['msg']   ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign In -- SmartQMS</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
  <main class="auth-shell auth-shell-login">
    <section class="auth-card">
      <header class="auth-hero auth-hero-compact">
        <div class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" role="img">
            <path d="M12 3.2 18.2 5.8v5.1c0 3.9-2.5 7.5-6.2 8.9-3.7-1.4-6.2-5-6.2-8.9V5.8L12 3.2Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
            <path d="m9 12 2 2 4-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <h1>Smart QMS</h1>
        <p>Barangay Health Center Queue System</p>
      </header>

      <div class="auth-body">
        <?php if ($error): ?>
          <div class="auth-alert auth-alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($msg === 'logged_out'): ?>
          <div class="auth-alert auth-alert-success">You have been logged out successfully.</div>
        <?php endif; ?>
        <?php if ($msg === 'registered'): ?>
          <div class="auth-alert auth-alert-success">Phone verified. You can now sign in.</div>
        <?php endif; ?>

        <form action="modules/auth/login.php" method="POST" class="auth-form" novalidate>
          <div class="form-row-single">
            <label class="auth-label" for="phone_number">Phone Number</label>
            <input id="phone_number" type="tel" name="phone_number" class="auth-input"
                   placeholder="0917 123 4567" required autocomplete="tel">
          </div>
          <div class="form-row-single">
            <label class="auth-label" for="password">Security PIN / Password</label>
            <input id="password" type="password" name="password" class="auth-input"
                   placeholder="Enter your PIN or password" required autocomplete="current-password">
          </div>
          <button type="submit" class="auth-submit">
            Sign In
            <span aria-hidden="true">→</span>
          </button>
        </form>

        <div class="auth-divider"></div>
        <p class="auth-switch">
          New patient?
          <a href="views/client/register.php">Register here</a>
        </p>
        <p class="auth-note">
          Staff and Administrator accounts are managed by the health center.
        </p>
      </div>
    </section>
  </main>
</body>
</html>
