<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $windowId = (int) ($_POST['window_id'] ?? 0);
    $windowName = trim($_POST['window_name'] ?? '');
    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $staffId = (int) ($_POST['staff_id'] ?? 0);
    $status = $_POST['status'] ?? 'closed';

    if ($windowName === '' || !in_array($status, ['open', 'busy', 'closed'], true)) {
        $error = 'Window name and status are required.';
    } elseif ($windowId > 0) {
        $stmt = $conn->prepare("UPDATE service_windows SET window_name=?, service_id=NULLIF(?,0), staff_id=NULLIF(?,0), status=? WHERE window_id=?");
        $stmt->bind_param('siisi', $windowName, $serviceId, $staffId, $status, $windowId);
        $stmt->execute();
        logActivity($conn, 'window_updated', 'Updated window ' . $windowName);
        $success = 'Window updated.';
    } else {
        $stmt = $conn->prepare("INSERT INTO service_windows (window_name, service_id, staff_id, status) VALUES (?, NULLIF(?,0), NULLIF(?,0), ?)");
        $stmt->bind_param('siis', $windowName, $serviceId, $staffId, $status);
        $stmt->execute();
        logActivity($conn, 'window_created', 'Created window ' . $windowName);
        $success = 'Window created.';
    }
}

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
          <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
          <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
          <form method="POST">
            <label class="form-label">Window Name</label>
            <input class="form-control mb-3" name="window_name" placeholder="Window 1" required>
            <label class="form-label">Service</label>
            <select class="form-select mb-3" name="service_id">
              <option value="0">Unassigned</option>
              <?php foreach ($services as $service): ?>
                <option value="<?= $service['service_id'] ?>"><?= htmlspecialchars($service['service_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <label class="form-label">Staff</label>
            <select class="form-select mb-3" name="staff_id">
              <option value="0">Unassigned</option>
              <?php foreach ($staffRows as $staff): ?>
                <option value="<?= $staff['staff_id'] ?>"><?= htmlspecialchars($staff['staff_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <label class="form-label">Status</label>
            <select class="form-select mb-3" name="status">
              <option value="closed">Closed</option>
              <option value="open">Open</option>
              <option value="busy">Busy</option>
            </select>
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
</body>
</html>
