<?php
/**
 * SmartQMS -- OTP Verification
 * Verifies the 6-digit SMS OTP sent during registration.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';

$userId = (int) ($_SESSION['pending_user_id'] ?? 0);
$otp = trim($_POST['otp_code'] ?? '');

if (!$userId || !preg_match('/^\d{6}$/', $otp)) {
    redirectTo('views/client/verify_otp.php', ['error' => 'Enter the 6-digit OTP code.']);
}

$stmt = $conn->prepare("SELECT user_id, otp_code, otp_expires_at FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || $user['otp_code'] !== $otp) {
    redirectTo('views/client/verify_otp.php', ['error' => 'Wrong OTP code.']);
}

if (strtotime($user['otp_expires_at']) < time()) {
    redirectTo('views/client/verify_otp.php', ['error' => 'OTP expired. Please register again or ask the staff to help reset it.']);
}

$update = $conn->prepare("UPDATE users SET is_verified = 1, otp_code = NULL, otp_expires_at = NULL WHERE user_id = ?");
$update->bind_param('i', $userId);
$update->execute();
logActivity($conn, 'phone_verified', 'Client verified OTP', null, $userId, ROLE_CLIENT);

unset($_SESSION['pending_user_id']);
redirectTo('index.php', ['msg' => 'registered']);
?>
