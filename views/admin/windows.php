<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);

$fieldErrors = [];
$oldInput = [
    'window_name' => '',
    'service_id' => '0',
    'staff_id' => '0',
    'status' => 'closed',
];
$formError = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $windowId = (int) ($_POST['window_id'] ?? 0);
    $windowName = trim($_POST['window_name'] ?? '');
    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $staffId = (int) ($_POST['staff_id'] ?? 0);
    $status = $_POST['status'] ?? 'closed';
    $oldInput = [
        'window_name' => $windowName,
        'service_id' => (string) $serviceId,
        'staff_id' => (string) $staffId,
        'status' => $status,
    ];

    if (!isValidCsrfToken()) {
        $formError = 'Security check failed. Please refresh the page and try again.';
    }

    if ($formError === '' && $windowName === '') {
        $fieldErrors['window_name'] = 'Window name is required.';
    }
    if ($formError === '' && !in_array($status, ['open', 'busy', 'closed'], true)) {
        $fieldErrors['status'] = 'Choose a valid window status.';
    }

    if ($formError !== '') {
        $fieldErrors = [];
    } elseif ($fieldErrors) {
        $formError = '';
    } elseif ($windowId > 0) {
        $stmt = $conn->prepare("UPDATE service_windows SET window_name=?, service_id=NULLIF(?,0), staff_id=NULLIF(?,0), status=? WHERE window_id=?");
        $stmt->bind_param('siisi', $windowName, $serviceId, $staffId, $status, $windowId);
        $stmt->execute();
        logActivity($conn, 'window_updated', 'Updated window ' . $windowName);
        $success = 'Window updated.';
        $oldInput = [
            'window_name' => '',
            'service_id' => '0',
            'staff_id' => '0',
            'status' => 'closed',
        ];
    } else {
        $stmt = $conn->prepare("INSERT INTO service_windows (window_name, service_id, staff_id, status) VALUES (?, NULLIF(?,0), NULLIF(?,0), ?)");
        $stmt->bind_param('siis', $windowName, $serviceId, $staffId, $status);
        $stmt->execute();
        logActivity($conn, 'window_created', 'Created window ' . $windowName);
        $success = 'Window created.';
        $oldInput = [
            'window_name' => '',
            'service_id' => '0',
            'staff_id' => '0',
            'status' => 'closed',
        ];
    }
}
$feedback = [
    'field_errors' => $fieldErrors,
    'old' => $oldInput,
    'form_error' => $formError,
];

$services = $conn->query("SELECT service_id, service_name FROM health_services WHERE is_active=1 ORDER BY display_order, service_name")->fetch_all(MYSQLI_ASSOC);
$staffRows = $conn->query("SELECT s.staff_id, CONCAT(u.first_name, ' ', u.last_name) AS staff_name FROM staff s JOIN users u ON u.user_id=s.user_id WHERE u.is_active=1 ORDER BY staff_name")->fetch_all(MYSQLI_ASSOC);
$windows = $conn->query("
    SELECT sw.*, hs.service_name, CONCAT(u.first_name, ' ', u.last_name) AS staff_name
    FROM service_windows sw
    LEFT JOIN health_services hs ON hs.service_id=sw.service_id
    LEFT JOIN staff s ON s.staff_id=sw.staff_id
    LEFT JOIN users u ON u.user_id=s.user_id
    WHERE sw.is_active=1
    ORDER BY sw.window_id
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Service Windows -- SmartQMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
  <main class="container py-4">
    <a href="dashboard.php" class="btn btn-link px-0">Back to dashboard</a>
    <div class="row g-4">
      <section class="col-lg-5">
        <div class="card p-4">
          <h1 class="h4 mb-3">Add Window</h1>
          <?php if ($feedback['form_error']): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($feedback['form_error']) ?></div><?php endif; ?>
          <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
          <form method="POST" class="js-validated-form" novalidate>
            <?= csrfInput() ?>
            <label class="form-label" for="window_name">Window Name</label>
            <input id="window_name" class="form-control<?= fieldInvalidClass($feedback, 'window_name') ?>" name="window_name"
                   value="<?= htmlspecialchars(oldFormValue($feedback, 'window_name'), ENT_QUOTES) ?>"
                   placeholder="Window 1" required data-validate="required"<?= fieldAriaInvalid($feedback, 'window_name') ?>>
            <div class="field-error mb-3" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'window_name')) ?></div>
            <label class="form-label" for="service_id">Service</label>
            <select id="service_id" class="form-select mb-3" name="service_id">
              <option value="0"<?= oldFormValue($feedback, 'service_id', '0') === '0' ? ' selected' : '' ?>>Unassigned</option>
              <?php foreach ($services as $service): ?>
                <?php $selectedService = oldFormValue($feedback, 'service_id', '0') === (string) $service['service_id']; ?>
                <option value="<?= $service['service_id'] ?>"<?= $selectedService ? ' selected' : '' ?>><?= htmlspecialchars($service['service_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <label class="form-label" for="staff_id">Staff</label>
            <select id="staff_id" class="form-select mb-3" name="staff_id">
              <option value="0"<?= oldFormValue($feedback, 'staff_id', '0') === '0' ? ' selected' : '' ?>>Unassigned</option>
              <?php foreach ($staffRows as $staff): ?>
                <?php $selectedStaff = oldFormValue($feedback, 'staff_id', '0') === (string) $staff['staff_id']; ?>
                <option value="<?= $staff['staff_id'] ?>"<?= $selectedStaff ? ' selected' : '' ?>><?= htmlspecialchars($staff['staff_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <label class="form-label" for="status">Status</label>
            <?php $selectedStatus = oldFormValue($feedback, 'status', 'closed'); ?>
            <select id="status" class="form-select<?= fieldInvalidClass($feedback, 'status') ?>" name="status" required data-validate="required"<?= fieldAriaInvalid($feedback, 'status') ?>>
              <option value="closed"<?= $selectedStatus === 'closed' ? ' selected' : '' ?>>Closed</option>
              <option value="open"<?= $selectedStatus === 'open' ? ' selected' : '' ?>>Open</option>
              <option value="busy"<?= $selectedStatus === 'busy' ? ' selected' : '' ?>>Busy</option>
            </select>
            <div class="field-error mb-3" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'status')) ?></div>
            <button class="btn btn-primary" type="submit">Create Window</button>
          </form>
        </div>
      </section>
      <section class="col-lg-7">
        <div class="card p-4">
          <h2 class="h5 mb-3">Configured Windows</h2>
          <div class="table-responsive">
            <table class="table">
              <thead><tr><th>Window</th><th>Service</th><th>Staff</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($windows as $window): ?>
                  <tr>
                    <td><?= htmlspecialchars($window['window_name']) ?></td>
                    <td><?= htmlspecialchars($window['service_name'] ?? 'Unassigned') ?></td>
                    <td><?= htmlspecialchars($window['staff_name'] ?? 'Unassigned') ?></td>
                    <td><span class="badge text-bg-secondary"><?= htmlspecialchars($window['status']) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </main>
  <script src="<?= assetUrl('assets/js/main.js') ?>"></script>
</body>
</html>
