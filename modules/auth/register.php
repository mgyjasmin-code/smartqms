<?php
/**
 * SmartQMS -- Client Self-Registration Handler
 * Handles POST from views/client registration form.
 * Flow: Submit form -> Send OTP -> Verify OTP -> Account active
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/../notifications/sms_sender.php';

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$middleName = trim($_POST['middle_name'] ?? '');
$phone = normalizePhone($_POST['phone_number'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($firstName === '' || $lastName === '' || !isValidPhMobile($phone) || !preg_match('/^\d{6}$/', $password)) {
    redirectTo('views/client/register.php', ['error' => 'Please complete all required fields. PIN must be exactly 6 digits.']);
}

$stmt = $conn->prepare("SELECT user_id FROM users WHERE phone_number = ? LIMIT 1");
$stmt->bind_param('s', $phone);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()) {
    redirectTo('views/client/register.php', ['error' => 'That phone number is already registered.']);
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$otp = (string) random_int(100000, 999999);
$expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
$role = ROLE_CLIENT;
$verified = 0;

$insert = $conn->prepare("
    INSERT INTO users (first_name, last_name, middle_name, phone_number, email, password_hash, role, is_verified, otp_code, otp_expires_at)
    VALUES (?, ?, NULLIF(?, ''), ?, NULLIF(?, ''), ?, ?, ?, ?, ?)
");
$insert->bind_param('sssssssiss', $firstName, $lastName, $middleName, $phone, $email, $hash, $role, $verified, $otp, $expiresAt);
$insert->execute();
$userId = $conn->insert_id;

$_SESSION['pending_user_id'] = $userId;
sendSMS($phone, "Your SmartQMS verification code is {$otp}. It expires in 10 minutes.", 'otp', $userId);
logActivity($conn, 'register', 'Client registered and OTP was generated', null, $userId, ROLE_CLIENT);

redirectTo('views/client/verify_otp.php', ['msg' => 'otp_sent']);
?>
