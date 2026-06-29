<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$error = $_GET['error'] ?? '';
$msg = $_GET['msg'] ?? '';
$pendingUserId = (int) ($_SESSION['pending_user_id'] ?? 0);
$latestOtp = '';

if ($pendingUserId) {
    $stmt = $conn->prepare("SELECT otp_code FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $pendingUserId);
    $stmt->execute();
    $latestOtp = $stmt->get_result()->fetch_assoc()['otp_code'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verify OTP -- SmartQMS</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh; background:#F4F4F4;">
  <main class="card p-4" style="width:100%; max-width:420px;">
    <h2 class="mb-2" style="font-family:Poppins; font-weight:600;">Verify Phone</h2>
    <p class="text-muted" style="font-size:13px;">Enter the 6-digit code sent to your phone.</p>

    <?php if ($error): ?>
      <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($msg === 'otp_sent'): ?>
      <div class="alert alert-success py-2">OTP sent. SMS may be simulated during local setup.</div>
    <?php endif; ?>
    <?php if ($latestOtp): ?>
      <div class="alert alert-info py-2" style="font-size:13px;">Demo OTP: <strong><?= htmlspecialchars($latestOtp) ?></strong></div>
    <?php endif; ?>

    <form action="<?= postActionUrl('modules/auth/verify_otp.php') ?>" method="POST">
      <label class="form-label">OTP Code</label>
      <input class="form-control text-center" name="otp_code" maxlength="6" pattern="\d{6}" required style="font-size:28px; letter-spacing:8px;">
      <button class="btn btn-primary w-100 mt-3" type="submit">Verify</button>
    </form>

    <p class="text-center mt-3 mb-0" style="font-size:13px;">
      <a href="<?= APP_URL ?>/index.php">Back to sign in</a>
    </p>
  </main>
</body>
</html>
