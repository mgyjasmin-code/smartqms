<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);

$summary = $conn->query("
    SELECT hs.service_name, qt.status, COUNT(*) AS total
    FROM queue_tickets qt
    JOIN health_services hs ON hs.service_id=qt.service_id
    GROUP BY hs.service_name, qt.status
    ORDER BY hs.service_name, qt.status
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reports -- SmartQMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
  <main class="container py-4">
    <a href="dashboard.php" class="btn btn-link px-0">Back to dashboard</a>
    <div class="card p-4">
      <h1 class="h4">MVP Queue Summary</h1>
      <table class="table">
        <thead><tr><th>Service</th><th>Status</th><th>Total</th></tr></thead>
        <tbody>
          <?php foreach ($summary as $row): ?>
            <tr><td><?= htmlspecialchars($row['service_name']) ?></td><td><?= htmlspecialchars($row['status']) ?></td><td><?= (int) $row['total'] ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>
