<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_STAFF);
require_once __DIR__ . '/includes/context.php';

$pageTitle = 'My Window';
$pageHeading = 'My Window';
$pageSubtitle = 'Control your service window and call the next ticket in priority order.';
$activePage = 'window';
include __DIR__ . '/includes/header.php';
?>

<?php if (!$window): ?>
  <section class="staff-empty-state">
    <span><i class="bi bi-window-x" aria-hidden="true"></i></span>
    <h2>No service window assigned</h2>
    <p>Your administrator needs to assign an active service window before queue controls become available.</p>
  </section>
<?php else: ?>
  <section class="staff-window-card">
    <div class="staff-window-identity">
      <span class="staff-window-icon"><i class="bi bi-window-stack" aria-hidden="true"></i></span>
      <div>
        <p class="staff-section-kicker">Assigned Counter</p>
        <h2><?= htmlspecialchars($window['window_name']) ?></h2>
        <p><?= htmlspecialchars($window['service_name'] ?? 'No health service assigned') ?></p>
      </div>
      <span class="window-state window-state-<?= htmlspecialchars($window['status']) ?>"><?= htmlspecialchars($window['status']) ?></span>
    </div>

    <div class="staff-window-controls">
      <div class="staff-status-segment staff-status-segment-inline" role="group" aria-label="Window status">
        <?php foreach (['open' => 'Open', 'busy' => 'Busy', 'closed' => 'Closed'] as $statusValue => $statusLabel): ?>
          <button class="staff-status-option<?= $window['status'] === $statusValue ? ' is-active' : '' ?>" type="button" data-staff-action
                  data-status="<?= htmlspecialchars($statusValue) ?>"
                  data-action-url="<?= APP_URL ?>/modules/service_window/window_status.php"
                  data-action-success="<?= htmlspecialchars($window['window_name'], ENT_QUOTES) ?> is now <?= htmlspecialchars($statusValue, ENT_QUOTES) ?>."
                  data-loading-text="Updating..."
                  aria-pressed="<?= $window['status'] === $statusValue ? 'true' : 'false' ?>">
            <i class="bi bi-circle-fill" aria-hidden="true"></i>
            <span><?= htmlspecialchars($statusLabel) ?></span>
          </button>
        <?php endforeach; ?>
      </div>

      <button class="btn btn-primary staff-call-next" id="call-next" type="button" data-staff-action
              data-action-url="<?= APP_URL ?>/modules/service_window/call_next.php"
              data-action-success="The next ticket was called successfully."
              data-loading-text="Calling next..."
              <?= $current || $window['status'] === 'closed' || empty($window['service_id']) || !$waiting ? 'disabled' : '' ?>>
        <i class="bi bi-megaphone" aria-hidden="true"></i> Call Next
      </button>
    </div>

    <?php if ($current): ?>
      <div class="staff-window-notice">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        <p><strong><?= htmlspecialchars($current['ticket_number']) ?></strong> is currently being served. Complete or skip it from the Dashboard before calling another ticket.</p>
        <a href="dashboard.php">Open Dashboard</a>
      </div>
    <?php elseif ($window['status'] === 'closed'): ?>
      <div class="staff-window-notice is-warning">
        <i class="bi bi-door-closed" aria-hidden="true"></i>
        <p>Open your window before calling the next ticket.</p>
      </div>
    <?php elseif (!$waiting): ?>
      <div class="staff-window-notice">
        <i class="bi bi-check2-circle" aria-hidden="true"></i>
        <p>There are no waiting tickets for this service right now.</p>
      </div>
    <?php endif; ?>
  </section>

  <section class="staff-queue-card">
    <div class="staff-section-heading">
      <div>
        <p class="staff-section-kicker">Priority Order</p>
        <h2>Waiting Queue</h2>
      </div>
      <span class="staff-queue-count"><?= number_format(count($waiting)) ?> waiting</span>
    </div>

    <?php if ($waiting): ?>
      <ol class="staff-queue-list">
        <?php foreach ($waiting as $index => $ticket): ?>
          <?php $priorityTicket = (int) $ticket['priority_level'] === 1; ?>
          <li class="staff-queue-row<?= $priorityTicket ? ' is-priority' : '' ?>">
            <span class="staff-queue-position" aria-label="Queue position <?= $index + 1 ?>"><?= $index + 1 ?></span>
            <div class="staff-queue-ticket">
              <strong><?= htmlspecialchars($ticket['ticket_number']) ?></strong>
              <small><?= htmlspecialchars($ticket['service_name']) ?></small>
            </div>
            <div class="staff-queue-classification">
              <?php if ($priorityTicket): ?>
                <span class="badge-priority"><?= htmlspecialchars($ticket['client_type']) ?></span>
              <?php else: ?>
                <span>Regular</span>
              <?php endif; ?>
            </div>
            <time datetime="<?= htmlspecialchars(date(DATE_ATOM, strtotime((string) $ticket['issued_at']))) ?>">
              <?= htmlspecialchars(date('g:i A', strtotime((string) $ticket['issued_at']))) ?>
            </time>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php else: ?>
      <div class="staff-empty-inline staff-queue-empty">
        <span><i class="bi bi-people" aria-hidden="true"></i></span>
        <div><h3>Queue is clear</h3><p>New waiting tickets for this service will appear here after the page refreshes.</p></div>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
