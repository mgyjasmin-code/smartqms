<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = normalizePhone($_POST['phone_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $department = trim($_POST['department'] ?? '');
    $shift = trim($_POST['shift'] ?? '');

    if ($firstName === '' || $lastName === '' || !isValidPhMobile($phone) || strlen($password) < 8) {
        $error = 'Complete all required fields. Password must be at least 8 characters.';
    } else {
        $exists = $conn->prepare("SELECT user_id FROM users WHERE phone_number = ?");
        $exists->bind_param('s', $phone);
        $exists->execute();
        if ($exists->get_result()->fetch_assoc()) {
            $error = 'That phone number is already registered.';
        } else {
            $conn->begin_transaction();
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $role = ROLE_STAFF;
                $verified = 1;
                $userStmt = $conn->prepare("INSERT INTO users (first_name, last_name, phone_number, email, password_hash, role, is_verified) VALUES (?, ?, ?, NULLIF(?, ''), ?, ?, ?)");
                $userStmt->bind_param('ssssssi', $firstName, $lastName, $phone, $email, $hash, $role, $verified);
                $userStmt->execute();
                $userId = $conn->insert_id;
                $staffStmt = $conn->prepare("INSERT INTO staff (user_id, department, shift, added_by) VALUES (?, NULLIF(?, ''), NULLIF(?, ''), ?)");
                $staffStmt->bind_param('issi', $userId, $department, $shift, $_SESSION['user_id']);
                $staffStmt->execute();
                logActivity($conn, 'staff_created', 'Created staff account for ' . $phone);
                $conn->commit();
                $success = 'Staff account created.';
            } catch (Throwable $e) {
                $conn->rollback();
                $error = 'Could not create staff account.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add Staff -- SmartQMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
  <main class="container py-4">
    <a href="dashboard.php" class="btn btn-link px-0">Back to dashboard</a>
    <div class="card p-4" style="max-width:760px;">
      <h1 class="h4 mb-3">Add Staff</h1>
      <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
      <form method="POST">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">First Name</label><input class="form-control" name="first_name" required></div>
          <div class="col-md-6"><label class="form-label">Last Name</label><input class="form-control" name="last_name" required></div>
          <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone_number" placeholder="09171234567" required></div>
          <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email"></div>
          <div class="col-md-6"><label class="form-label">Password</label><input class="form-control" type="password" name="password" minlength="8" required></div>
          <div class="col-md-3"><label class="form-label">Department</label><input class="form-control" name="department"></div>
          <div class="col-md-3"><label class="form-label">Shift</label><input class="form-control" name="shift"></div>
        </div>
        <button class="btn btn-primary mt-4" type="submit">Create Staff</button>
      </form>
    </div>
  </main>
</body>
</html>
