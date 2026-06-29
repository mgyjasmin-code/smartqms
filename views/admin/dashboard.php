<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);

$counts = [];
foreach ([
    'users' => "SELECT COUNT(*) AS c FROM users",
    'waiting' => "SELECT COUNT(*) AS c FROM queue_tickets WHERE status='waiting'",
    'serving' => "SELECT COUNT(*) AS c FROM queue_tickets WHERE status='serving'",
    'completed_today' => "SELECT COUNT(*) AS c FROM queue_tickets WHERE status='completed' AND DATE(completed_at)=CURDATE()",
] as $key => $sql) {
    $counts[$key] = (int) ($conn->query($sql)->fetch_assoc()['c'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard -- SmartQMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
  <main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h1 class="h3 mb-1">Admin Dashboard</h1>
        <p class="text-muted mb-0">Manage setup and monitor today's queue.</p>
      </div>
      <a class="btn btn-outline-secondary" href="<?= APP_URL ?>/modules/auth/logout.php">Logout</a>
    </div>
    <nav class="d-flex flex-wrap gap-2 mb-4">
      <a class="btn btn-primary" href="add_staff.php">Add Staff</a>
      <a class="btn btn-primary" href="windows.php">Service Windows</a>
      <a class="btn btn-primary" href="settings.php">Settings</a>
      <a class="btn btn-outline-primary" href="users.php">Users</a>
      <a class="btn btn-outline-primary" href="reports.php">Reports</a>
    </nav>
    <div class="row g-3">
      <div class="col-md-3"><div class="card p-3"><div class="text-muted">Users</div><div class="h2"><?= $counts['users'] ?></div></div></div>
      <div class="col-md-3"><div class="card p-3"><div class="text-muted">Waiting</div><div class="h2"><?= $counts['waiting'] ?></div></div></div>
      <div class="col-md-3"><div class="card p-3"><div class="text-muted">Serving</div><div class="h2"><?= $counts['serving'] ?></div></div></div>
      <div class="col-md-3"><div class="card p-3"><div class="text-muted">Completed Today</div><div class="h2"><?= $counts['completed_today'] ?></div></div></div>
    </div>
  </main>
</body>
</html>
