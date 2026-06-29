<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_STAFF);

$staffId = getCurrentStaffId($conn);
$window = $staffId ? getStaffWindow($conn, $staffId) : null;
$current = null;
$waiting = [];

if ($window) {
    $windowId = (int) $window['window_id'];
    $serviceId = (int) $window['service_id'];
    $stmt = $conn->prepare("SELECT * FROM queue_tickets WHERE window_id=? AND status='serving' ORDER BY called_at DESC LIMIT 1");
    $stmt->bind_param('i', $windowId);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    if ($serviceId > 0) {
        $wait = $conn->prepare("SELECT qt.*, hs.service_name FROM queue_tickets qt JOIN health_services hs ON hs.service_id=qt.service_id WHERE qt.status='waiting' AND qt.service_id=? ORDER BY qt.priority_level DESC, qt.issued_at ASC LIMIT 20");
        $wait->bind_param('i', $serviceId);
        $wait->execute();
        $waiting = $wait->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Staff Dashboard -- SmartQMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
  <main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h1 class="h3 mb-1">Staff Window</h1>
        <p class="text-muted mb-0"><?= htmlspecialchars($_SESSION['name'] ?? 'Staff') ?></p>
      </div>
      <a class="btn btn-outline-secondary" href="<?= APP_URL ?>/modules/auth/logout.php">Logout</a>
    </div>

    <?php if (!$window): ?>
      <div class="alert alert-warning">No active service window is assigned to your staff account yet.</div>
    <?php else: ?>
      <section class="card p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between gap-3">
          <div>
            <h2 class="h4 mb-1"><?= htmlspecialchars($window['window_name']) ?></h2>
            <p class="text-muted mb-0"><?= htmlspecialchars($window['service_name'] ?? 'No service assigned') ?></p>
          </div>
          <div>
            <span class="badge text-bg-secondary"><?= htmlspecialchars(strtoupper($window['status'])) ?></span>
          </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3">
          <button class="btn btn-success js-status" data-status="open">Open</button>
          <button class="btn btn-warning js-status" data-status="closed">Close</button>
          <button class="btn btn-primary" id="call-next">Call Next</button>
        </div>
      </section>

      <section class="card p-4 mb-4">
        <h2 class="h5">Now Serving</h2>
        <?php if ($current): ?>
          <div class="queue-number"><?= htmlspecialchars($current['ticket_number']) ?></div>
          <p class="mb-3"><?= htmlspecialchars($current['client_type']) ?> client</p>
          <button class="btn btn-success js-complete" data-ticket-id="<?= (int) $current['ticket_id'] ?>">Complete</button>
          <button class="btn btn-danger js-skip" data-ticket-id="<?= (int) $current['ticket_id'] ?>">Skip</button>
        <?php else: ?>
          <p class="text-muted mb-0">No client is currently being served.</p>
        <?php endif; ?>
      </section>

      <section class="card p-4">
        <h2 class="h5">Waiting Queue</h2>
        <div class="table-responsive">
          <table class="table">
            <thead><tr><th>Ticket</th><th>Type</th><th>Issued</th></tr></thead>
            <tbody>
              <?php foreach ($waiting as $ticket): ?>
                <tr class="<?= (int) $ticket['priority_level'] ? 'priority-row' : '' ?>">
                  <td><?= htmlspecialchars($ticket['ticket_number']) ?></td>
                  <td><?= htmlspecialchars($ticket['client_type']) ?></td>
                  <td><?= htmlspecialchars($ticket['issued_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>
  </main>
  <script>
    async function postAction(url, body = null) {
      const response = await fetch(url, { method: 'POST', body });
      const data = await response.json();
      if (!data.success) alert(data.error || 'Action failed.');
      location.reload();
    }
    document.querySelectorAll('.js-status').forEach(btn => btn.addEventListener('click', () => {
      const form = new FormData();
      form.append('status', btn.dataset.status);
      postAction('<?= APP_URL ?>/modules/service_window/window_status.php', form);
    }));
    document.getElementById('call-next')?.addEventListener('click', () => postAction('<?= APP_URL ?>/modules/service_window/call_next.php'));
    document.querySelectorAll('.js-complete').forEach(btn => btn.addEventListener('click', () => {
      const form = new FormData();
      form.append('ticket_id', btn.dataset.ticketId);
      postAction('<?= APP_URL ?>/modules/service_window/complete_ticket.php', form);
    }));
    document.querySelectorAll('.js-skip').forEach(btn => btn.addEventListener('click', () => {
      const form = new FormData();
      form.append('ticket_id', btn.dataset.ticketId);
      postAction('<?= APP_URL ?>/modules/service_window/skip_ticket.php', form);
    }));
  </script>
</body>
</html>
