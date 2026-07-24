<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_CLIENT);

$activeTicket = getActiveTicket($conn, (int) $_SESSION['user_id']);
$activePeopleAhead = $activeTicket ? peopleAhead($conn, $activeTicket) : 0;
$activeWait = $activeTicket && $activeTicket['predicted_wait_min'] !== null
    ? '~' . rtrim(rtrim(number_format((float) $activeTicket['predicted_wait_min'], 1), '0'), '.') . ' minutes'
    : 'Calculating';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= csrfMetaTag() ?>
  <title>Queue Status -- SmartQMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600&family=Inter:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
</head>
<body class="client-page" data-client-root
      data-client-notification-url="<?= htmlspecialchars(postActionUrl('modules/notifications/get_notifications.php'), ENT_QUOTES) ?>">
  <a class="skip-link" href="#main-content">Skip to live queue status</a>
  <main id="main-content" class="client-shell" tabindex="-1"
        data-queue-status-root data-status-url="<?= APP_URL ?>/modules/queue/status.php" data-refresh-interval="10000">
    <div class="client-subnav">
      <a href="index.php" class="btn btn-link px-0">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        Back to dashboard
      </a>
      <a href="ticket.php" class="btn btn-outline-primary">
        <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
        My Ticket
      </a>
    </div>

    <section class="client-panel queue-status-hero">
      <div>
        <p class="panel-kicker">Live Queue</p>
        <h1>Queue Status</h1>
        <p>See active service windows and the next waiting tickets.</p>
      </div>
      <div class="status-refresh" aria-live="polite">
        <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
        <span data-status-updated>Updating...</span>
      </div>
    </section>

    <?php if ($activeTicket): ?>
      <section class="client-panel queue-current-strip" data-viewer-ticket>
        <div>
          <p class="panel-kicker">Your Ticket</p>
          <h2 data-viewer-ticket-number><?= htmlspecialchars($activeTicket['ticket_number']) ?></h2>
          <span data-viewer-ticket-service><?= htmlspecialchars($activeTicket['service_name']) ?></span>
        </div>
        <div class="queue-current-metrics">
          <div>
            <span>Status</span>
            <strong><span class="status-badge badge-<?= htmlspecialchars($activeTicket['status']) ?>" data-viewer-ticket-status><?= htmlspecialchars($activeTicket['status']) ?></span></strong>
          </div>
          <div>
            <span>People Ahead</span>
            <strong data-viewer-ticket-ahead><?= (int) $activePeopleAhead ?></strong>
          </div>
          <div>
            <span>Estimated Wait</span>
            <strong data-viewer-ticket-wait><?= htmlspecialchars($activeWait) ?></strong>
          </div>
        </div>
        <a class="btn btn-outline-primary" href="ticket.php">
          <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
          View Ticket
        </a>
      </section>
    <?php endif; ?>

    <section class="queue-status-layout">
      <div class="queue-status-section">
        <div class="panel-heading">
          <div>
            <p class="panel-kicker">Now Serving</p>
            <h2>Service Windows</h2>
          </div>
        </div>
        <div class="window-status-grid" data-window-status>
          <div class="client-skeleton"></div>
          <div class="client-skeleton"></div>
          <div class="client-skeleton"></div>
        </div>
      </div>

      <aside class="queue-status-section">
        <div class="panel-heading">
          <div>
            <p class="panel-kicker">Waiting Queue</p>
            <h2>Next Tickets</h2>
          </div>
        </div>
        <div class="next-ticket-list" data-next-tickets>
          <div class="client-skeleton"></div>
          <div class="client-skeleton"></div>
          <div class="client-skeleton"></div>
        </div>
      </aside>
    </section>
  </main>
  <script src="<?= assetUrl('assets/js/main.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/client.js') ?>"></script>
</body>
</html>
