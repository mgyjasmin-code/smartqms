<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_CLIENT);

$activeTicket = getActiveTicket($conn, (int) $_SESSION['user_id']);
$services = $conn->query("SELECT * FROM health_services WHERE is_active=1 ORDER BY display_order, service_name")->fetch_all(MYSQLI_ASSOC);
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Client Dashboard -- SmartQMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
  <main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h1 class="h3 mb-1">Welcome, <?= htmlspecialchars($_SESSION['name'] ?? 'Client') ?></h1>
        <p class="text-muted mb-0">Join the queue or check your active ticket.</p>
      </div>
      <a class="btn btn-outline-secondary" href="<?= APP_URL ?>/modules/auth/logout.php">Logout</a>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($activeTicket): ?>
      <?php $activeTicket['people_ahead'] = peopleAhead($conn, $activeTicket); ?>
      <section class="card p-4">
        <div class="d-flex flex-wrap justify-content-between gap-3">
          <div>
            <div class="text-muted">Your Queue Number</div>
            <div class="queue-number"><?= htmlspecialchars($activeTicket['ticket_number']) ?></div>
            <div><?= htmlspecialchars($activeTicket['service_name']) ?></div>
          </div>
          <div>
            <span class="badge badge-<?= htmlspecialchars($activeTicket['status']) ?>"><?= htmlspecialchars(strtoupper($activeTicket['status'])) ?></span>
            <p class="mt-3 mb-1">People ahead: <strong><?= (int) $activeTicket['people_ahead'] ?></strong></p>
            <p class="mb-0">Estimated wait: <strong><?= htmlspecialchars((string) ($activeTicket['predicted_wait_min'] ?? 'Calculating')) ?> min</strong></p>
          </div>
        </div>
        <a class="btn btn-primary mt-4" href="ticket.php">View Ticket</a>
      </section>
    <?php else: ?>
      <section class="card p-4" style="max-width:760px;">
        <h2 class="h4 mb-3">Get Queue Number</h2>
        <form action="<?= postActionUrl('modules/queue/join_queue.php') ?>" method="POST">
          <label class="form-label">Health Service</label>
          <select class="form-select mb-3" name="service_id" required>
            <option value="">Choose service</option>
            <?php foreach ($services as $service): ?>
              <option value="<?= $service['service_id'] ?>"><?= htmlspecialchars($service['service_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <label class="form-label">Client Type</label>
          <select class="form-select mb-3" name="client_type">
            <option value="regular">Regular</option>
            <option value="senior">Senior Citizen</option>
            <option value="pwd">PWD</option>
          </select>
          <button class="btn btn-primary" type="submit">Join Queue</button>
        </form>
      </section>
    <?php endif; ?>
  </main>
  <script src="../../assets/js/main.js"></script>
</body>
</html>
