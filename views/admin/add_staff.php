<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);

$fieldErrors = [];
$oldInput = [
    'first_name' => '',
    'last_name' => '',
    'phone_number' => '',
    'email' => '',
    'department' => '',
    'shift' => '',
];
$formError = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = normalizePhone($_POST['phone_number'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $department = trim($_POST['department'] ?? '');
    $shift = trim($_POST['shift'] ?? '');
    $oldInput = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone_number' => $phone,
        'email' => $email,
        'department' => $department,
        'shift' => $shift,
    ];

    if (!isValidCsrfToken()) {
        $formError = 'Security check failed. Please refresh the page and try again.';
    }

    if ($formError === '' && $firstName === '') {
        $fieldErrors['first_name'] = 'First name is required.';
    }
    if ($formError === '' && $lastName === '') {
        $fieldErrors['last_name'] = 'Last name is required.';
    }
    if ($formError === '' && $phone === '') {
        $fieldErrors['phone_number'] = 'Phone number is required.';
    } elseif ($formError === '' && !isValidPhMobile($phone)) {
        $fieldErrors['phone_number'] = 'Enter a valid Philippine mobile number (e.g. 09171234567).';
    }
    if ($formError === '' && $email === '') {
        $fieldErrors['email'] = 'Email is required.';
    } elseif ($formError === '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fieldErrors['email'] = 'Enter a valid email address.';
    }
    if ($formError === '' && $password === '') {
        $fieldErrors['password'] = 'Password is required.';
    } elseif ($formError === '' && strlen($password) < 8) {
        $fieldErrors['password'] = 'Password must be at least 8 characters.';
    }

    if ($formError !== '') {
        $fieldErrors = [];
    } elseif ($fieldErrors) {
        $formError = '';
    } else {
        $exists = $conn->prepare("SELECT user_id, phone_number, email FROM users WHERE phone_number = ? OR email = ? LIMIT 1");
        $exists->bind_param('ss', $phone, $email);
        $exists->execute();
        $existing = $exists->get_result()->fetch_assoc();
        if ($existing) {
            if (($existing['phone_number'] ?? '') === $phone) {
                $fieldErrors['phone_number'] = 'That phone number is already registered.';
            }
            if (($existing['email'] ?? '') === $email) {
                $fieldErrors['email'] = 'That email is already registered.';
            }
        } else {
            $conn->begin_transaction();
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $role = ROLE_STAFF;
                $verified = 1;
                $userStmt = $conn->prepare("INSERT INTO users (first_name, last_name, phone_number, email, password_hash, role, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $userStmt->bind_param('ssssssi', $firstName, $lastName, $phone, $email, $hash, $role, $verified);
                $userStmt->execute();
                $userId = $conn->insert_id;
                $staffStmt = $conn->prepare("INSERT INTO staff (user_id, department, shift, added_by) VALUES (?, NULLIF(?, ''), NULLIF(?, ''), ?)");
                $staffStmt->bind_param('issi', $userId, $department, $shift, $_SESSION['user_id']);
                $staffStmt->execute();
                logActivity($conn, 'staff_created', 'Created staff account for ' . $phone);
                $conn->commit();
                $success = 'Staff account created.';
                $oldInput = [
                    'first_name' => '',
                    'last_name' => '',
                    'phone_number' => '',
                    'email' => '',
                    'department' => '',
                    'shift' => '',
                ];
            } catch (Throwable $e) {
                $conn->rollback();
                $formError = 'Could not create staff account.';
            }
        }
    }
}
$feedback = [
    'field_errors' => $fieldErrors,
    'old' => $oldInput,
    'form_error' => $formError,
];
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
      <?php if ($feedback['form_error']): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($feedback['form_error']) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
      <form method="POST" class="js-validated-form" novalidate>
        <?= csrfInput() ?>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="first_name">First Name</label>
            <input id="first_name" class="form-control<?= fieldInvalidClass($feedback, 'first_name') ?>" name="first_name" value="<?= htmlspecialchars(oldFormValue($feedback, 'first_name'), ENT_QUOTES) ?>" required data-validate="required"<?= fieldAriaInvalid($feedback, 'first_name') ?>>
            <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'first_name')) ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="last_name">Last Name</label>
            <input id="last_name" class="form-control<?= fieldInvalidClass($feedback, 'last_name') ?>" name="last_name" value="<?= htmlspecialchars(oldFormValue($feedback, 'last_name'), ENT_QUOTES) ?>" required data-validate="required"<?= fieldAriaInvalid($feedback, 'last_name') ?>>
            <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'last_name')) ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="phone_number">Phone</label>
            <input id="phone_number" class="form-control<?= fieldInvalidClass($feedback, 'phone_number') ?>" name="phone_number" value="<?= htmlspecialchars(oldFormValue($feedback, 'phone_number'), ENT_QUOTES) ?>" placeholder="09171234567" required data-validate="phone"<?= fieldAriaInvalid($feedback, 'phone_number') ?>>
            <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'phone_number')) ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="email">Email</label>
            <input id="email" class="form-control<?= fieldInvalidClass($feedback, 'email') ?>" type="email" name="email" value="<?= htmlspecialchars(oldFormValue($feedback, 'email'), ENT_QUOTES) ?>" required data-validate="email"<?= fieldAriaInvalid($feedback, 'email') ?>>
            <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'email')) ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="password">Password</label>
            <input id="password" class="form-control<?= fieldInvalidClass($feedback, 'password') ?>" type="password" name="password" minlength="8" required data-validate="password"<?= fieldAriaInvalid($feedback, 'password') ?>>
            <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'password')) ?></div>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="department">Department</label>
            <input id="department" class="form-control" name="department" value="<?= htmlspecialchars(oldFormValue($feedback, 'department'), ENT_QUOTES) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label" for="shift">Shift</label>
            <input id="shift" class="form-control" name="shift" value="<?= htmlspecialchars(oldFormValue($feedback, 'shift'), ENT_QUOTES) ?>">
          </div>
        </div>
        <button class="btn btn-primary mt-4" type="submit">Create Staff</button>
      </form>
    </div>
  </main>
  <script src="<?= assetUrl('assets/js/main.js') ?>"></script>
</body>
</html>
