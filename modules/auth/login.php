<?php
/**
 * SmartQMS -- Unified Login Handler
 * Handles POST from index.php login form.
 * Detects role from database -> redirects to correct dashboard.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';

if (!isset($_POST['phone_number'], $_POST['password'])) {
    header('Location: ' . APP_URL . '/index.php?error=invalid');
    exit();
}

$phone    = trim($_POST['phone_number']);
$password = $_POST['password'];

$phone = normalizePhone($phone);

$stmt = $conn->prepare("SELECT * FROM users WHERE phone_number = ? LIMIT 1");
$stmt->bind_param('s', $phone);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || !password_verify($password, $user['password_hash'])) {
    redirectTo('index.php', ['error' => 'Invalid phone number or password.']);
}

if ((int) $user['is_active'] !== 1) {
    redirectTo('index.php', ['error' => 'This account has been deactivated.']);
}

if ((int) $user['is_verified'] !== 1) {
    $_SESSION['pending_user_id'] = (int) $user['user_id'];
    redirectTo('views/client/verify_otp.php', ['error' => 'Please verify your phone number before signing in.']);
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['user_id'];
$_SESSION['role'] = $user['role'];
$_SESSION['name'] = trim($user['first_name'] . ' ' . $user['last_name']);
$_SESSION['phone'] = $user['phone_number'];

if ($user['role'] === ROLE_STAFF) {
    $staffStmt = $conn->prepare("SELECT staff_id FROM staff WHERE user_id = ? LIMIT 1");
    $staffStmt->bind_param('i', $_SESSION['user_id']);
    $staffStmt->execute();
    $staff = $staffStmt->get_result()->fetch_assoc();
    if ($staff) {
        $_SESSION['staff_id'] = (int) $staff['staff_id'];
    }
}

$update = $conn->prepare("UPDATE users SET last_login_at = NOW() WHERE user_id = ?");
$update->bind_param('i', $_SESSION['user_id']);
$update->execute();
logActivity($conn, 'login', 'User signed in');

if ($_SESSION['role'] === ROLE_ADMIN) {
    redirectTo('views/admin/dashboard.php');
}
if ($_SESSION['role'] === ROLE_STAFF) {
    redirectTo('views/staff/dashboard.php');
}
redirectTo('views/client/index.php');
?>
