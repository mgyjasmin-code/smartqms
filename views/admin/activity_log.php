<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);

$logs = $conn->query("
    SELECT al.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name
    FROM activity_logs al
    JOIN users u ON u.user_id=al.user_id
    ORDER BY al.logged_at DESC
    LIMIT 100
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Activity Log -- SmartQMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
  <main class="container py-4">
    <a href="dashboard.php" class="btn btn-link px-0">Back to dashboard</a>
    <div class="card p-4">
      <h1 class="h4">Activity Log</h1>
      <table class="table">
        <thead><tr><th>User</th><th>Role</th><th>Action</th><th>Details</th><th>Time</th></tr></thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td><?= htmlspecialchars($log['user_name']) ?></td>
              <td><?= htmlspecialchars($log['role'] ?? '') ?></td>
              <td><?= htmlspecialchars($log['action']) ?></td>
              <td><?= htmlspecialchars($log['details'] ?? '') ?></td>
              <td><?= htmlspecialchars($log['logged_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>
