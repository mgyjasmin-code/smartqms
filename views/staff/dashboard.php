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
  <?= csrfMetaTag() ?>
  <title>Staff Dashboard -- SmartQMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
</head>
<body class="staff-page">
  <main class="container py-4 staff-shell">
    <div class="staff-topbar">
      <div>
        <h1 class="h3 mb-1">Staff Window</h1>
        <p class="text-muted mb-0"><?= htmlspecialchars($_SESSION['name'] ?? 'Staff') ?></p>
      </div>
      <button class="btn btn-outline-secondary" type="button" data-confirm-logout>
        <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
        Logout
      </button>
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

  <div class="logout-modal" data-logout-modal hidden>
    <div class="logout-modal-backdrop" data-logout-cancel></div>
    <section class="logout-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="logout-modal-title" aria-describedby="logout-modal-copy" tabindex="-1">
      <button class="logout-modal-close" type="button" data-logout-cancel aria-label="Close logout confirmation">
        <i class="bi bi-x-lg" aria-hidden="true"></i>
      </button>
      <div class="logout-modal-icon" aria-hidden="true">
        <i class="bi bi-box-arrow-right"></i>
      </div>
      <h2 id="logout-modal-title">Log out of staff dashboard?</h2>
      <p id="logout-modal-copy">You will return to the sign-in screen and need to sign in again before managing your service window.</p>
      <div class="logout-modal-actions">
        <button class="logout-modal-button logout-modal-button-secondary" type="button" data-logout-cancel>Cancel</button>
        <form action="<?= APP_URL ?>/modules/auth/logout.php" method="POST" class="m-0">
          <?= csrfInput() ?>
          <button class="logout-modal-button logout-modal-button-primary" type="submit" data-logout-confirm>Log out</button>
        </form>
      </div>
    </section>
  </div>

  <script src="<?= assetUrl('assets/js/main.js') ?>"></script>
  <script>
    async function postAction(url, body = null) {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      const response = await fetch(url, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: csrfToken ? { 'X-CSRF-Token': csrfToken } : {}
      });
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
