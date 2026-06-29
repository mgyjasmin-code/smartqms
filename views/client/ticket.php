<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_CLIENT);

$ticket = getActiveTicket($conn, (int) $_SESSION['user_id']);
if (!$ticket) {
    $stmt = $conn->prepare("
        SELECT qt.*, hs.service_name, sw.window_name, wl.predicted_wait_min
        FROM queue_tickets qt
        JOIN health_services hs ON hs.service_id=qt.service_id
        LEFT JOIN service_windows sw ON sw.window_id=qt.window_id
        LEFT JOIN wait_time_logs wl ON wl.ticket_id=qt.ticket_id
        WHERE qt.user_id=?
        ORDER BY qt.issued_at DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
}

$peopleAhead = $ticket && in_array($ticket['status'], ['waiting', 'serving'], true) ? peopleAhead($conn, $ticket) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Your Ticket -- SmartQMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
  <main class="container py-4">
    <a href="index.php" class="btn btn-link px-0">Back to dashboard</a>
    <?php if (!$ticket): ?>
      <div class="card p-4"><h1 class="h4">No tickets yet</h1><p class="text-muted mb-0">Join the queue from your dashboard.</p></div>
    <?php else: ?>
      <section class="card p-4" style="max-width:720px;">
        <div class="d-flex justify-content-between align-items-start gap-3">
          <div>
            <div class="text-muted">Queue Number</div>
            <div class="queue-number"><?= htmlspecialchars($ticket['ticket_number']) ?></div>
            <h1 class="h5"><?= htmlspecialchars($ticket['service_name']) ?></h1>
            <p class="text-muted mb-0">Reference: <?= htmlspecialchars($ticket['reference_number']) ?></p>
          </div>
          <span class="badge badge-<?= htmlspecialchars($ticket['status']) ?>"><?= htmlspecialchars(strtoupper($ticket['status'])) ?></span>
        </div>
        <hr>
        <div class="row g-3">
          <div class="col-md-4"><div class="text-muted">People Ahead</div><strong><?= $peopleAhead ?></strong></div>
          <div class="col-md-4"><div class="text-muted">Estimated Wait</div><strong><?= htmlspecialchars((string) ($ticket['predicted_wait_min'] ?? 'Calculating')) ?> min</strong></div>
          <div class="col-md-4"><div class="text-muted">Window</div><strong><?= htmlspecialchars($ticket['window_name'] ?? 'Not called yet') ?></strong></div>
        </div>
        <?php if ($ticket['status'] === 'completed'): ?>
          <a class="btn btn-success mt-4" href="feedback.php?ticket_id=<?= (int) $ticket['ticket_id'] ?>">Submit Feedback</a>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>
