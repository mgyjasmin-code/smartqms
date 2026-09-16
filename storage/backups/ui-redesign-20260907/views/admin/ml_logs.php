<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);

$rows = $conn->query("
    SELECT qt.ticket_number,
           hs.service_name,
           wl.predicted_wait_min,
           wl.actual_wait_min,
           ROUND(ABS(wl.predicted_wait_min - wl.actual_wait_min), 2) AS error_min,
           wl.algorithm_used,
           wl.logged_at
    FROM wait_time_logs wl
    JOIN queue_tickets qt ON qt.ticket_id = wl.ticket_id
    JOIN health_services hs ON hs.service_id = qt.service_id
    ORDER BY wl.logged_at DESC
    LIMIT 200
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ML Logs -- SmartQMS</title>
  <link href="<?= assetUrl('vendor/twbs/bootstrap/dist/css/bootstrap.min.css') ?>" rel="stylesheet">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
</head>
<body>
  <main class="container py-4">
    <a href="dashboard.php" class="btn btn-link px-0">Back to dashboard</a>
    <div class="card p-4">
      <h1 class="h4 mb-3">ML Prediction Logs</h1>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Ticket</th>
              <th>Service</th>
              <th>Predicted</th>
              <th>Actual</th>
              <th>Error</th>
              <th>Algorithm</th>
              <th>Logged</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="7">No prediction logs found yet.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['ticket_number']) ?></td>
                  <td><?= htmlspecialchars($row['service_name']) ?></td>
                  <td><?= htmlspecialchars((string) $row['predicted_wait_min']) ?> min</td>
                  <td><?= $row['actual_wait_min'] !== null ? htmlspecialchars((string) $row['actual_wait_min']) . ' min' : 'Pending' ?></td>
                  <td><?= $row['error_min'] !== null ? htmlspecialchars((string) $row['error_min']) . ' min' : 'Pending' ?></td>
                  <td><?= htmlspecialchars($row['algorithm_used'] ?? 'Random Forest') ?></td>
                  <td><?= htmlspecialchars($row['logged_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</body>
</html>
