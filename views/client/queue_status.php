<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_CLIENT);

$activeTicket = getActiveTicket($conn, (int) $_SESSION['user_id']);
$activePeopleAhead = $activeTicket ? peopleAhead($conn, $activeTicket) : 0;
$activeWait = $activeTicket && $activeTicket['predicted_wait_min'] !== null
    ? '~' . rtrim(rtrim(number_format((float) $activeTicket['predicted_wait_min'], 1), '0'), '.') . ' minutes'
    : 'Calculating';
$pageTitle = 'Queue Status';
$pageHeading = 'Queue Status';
$pageSubtitle = 'See active service windows and the next waiting tickets.';
$activePage = 'queue';
$showClientPageHeader = false;
include __DIR__ . '/includes/header.php';
?>
  <div data-queue-status-root data-status-url="<?= APP_URL ?>/modules/queue/status.php" data-refresh-interval="10000">
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
  </div>
<?php include __DIR__ . '/includes/footer.php'; ?>
