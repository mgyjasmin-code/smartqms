<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_STAFF);
require_once __DIR__ . '/includes/context.php';

$pageTitle = 'Staff Dashboard';
$pageHeading = 'Dashboard';
$pageSubtitle = 'Keep the current client and your service window status visible at a glance.';
$activePage = 'dashboard';
$showStaffPageHeader = false;
include __DIR__ . '/includes/header.php';
?>

<?php if (!$window): ?>
  <section class="staff-empty-state">
    <span><i class="bi bi-window-x" aria-hidden="true"></i></span>
    <h2>No service window assigned</h2>
    <p>Your administrator needs to assign an active service window before you can manage tickets.</p>
  </section>
<?php else: ?>
  <?php
    $serviceName = $window['service_name'] ?? 'No health service assigned';
    $waitingPreview = array_slice($waiting, 0, 5);
    $callNextDisabled = $current || $window['status'] === 'closed' || empty($window['service_id']) || !$waiting;
    $currentClientName = $current['client_name'] ?? 'Current client';
    $currentWaitMinutes = 0;
    if ($current && !empty($current['issued_at'])) {
        $issuedAt = strtotime((string) $current['issued_at']);
        $currentWaitMinutes = $issuedAt ? max(0, (int) floor((time() - $issuedAt) / 60)) : 0;
    }
  ?>

  <section class="staff-window-hero" aria-label="Assigned service window">
    <div class="staff-window-summary">
      <span class="staff-window-hero-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
      <div>
        <h1><?= htmlspecialchars($window['window_name']) ?>: <?= htmlspecialchars($serviceName) ?></h1>
        <p>Health Center Staff: <?= htmlspecialchars($_SESSION['name'] ?? 'Staff') ?></p>
      </div>
    </div>

    <div class="staff-status-segment" role="group" aria-label="Window status">
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
  </section>

  <section class="staff-serving-section" aria-labelledby="currently-serving-title">
    <p class="staff-section-kicker">Currently Serving</p>
    <article class="staff-serving-card<?= $current ? ' has-ticket' : ' is-empty' ?>">
    <?php if ($current): ?>
      <div class="staff-serving-main">
        <span class="staff-live-badge">Live Session</span>
        <h2 id="currently-serving-title" class="staff-serving-ticket"><?= htmlspecialchars($current['ticket_number']) ?></h2>
        <div class="staff-serving-client">
          <span class="staff-client-avatar" aria-hidden="true"><?= htmlspecialchars(staffClientInitials($currentClientName)) ?></span>
          <div>
            <strong><?= htmlspecialchars($currentClientName) ?></strong>
            <span><?= htmlspecialchars($serviceName) ?></span>
          </div>
        </div>
      </div>

      <div class="staff-serving-meta">
        <?php if ($currentIsPriority): ?>
          <span class="badge-priority">Priority &mdash; <?= htmlspecialchars($currentClientTypeLabel) ?></span>
        <?php else: ?>
          <span class="staff-type-pill">Regular</span>
        <?php endif; ?>
        <span class="staff-wait-copy"><i class="bi bi-clock" aria-hidden="true"></i> Waiting: <?= number_format($currentWaitMinutes) ?> mins</span>
        <span class="staff-wait-copy"><i class="bi bi-telephone-outbound" aria-hidden="true"></i> Called <?= htmlspecialchars(date('g:i A', strtotime((string) $current['called_at']))) ?></span>
        <aside class="staff-void-timer staff-void-timer-compact" data-void-timer>
          <small>Response time left</small>
          <strong data-void-countdown data-remaining-seconds="<?= (int) ($voidRemainingSeconds ?? 0) ?>"
                  data-void-url="<?= APP_URL ?>/modules/queue/void_checker.php">00:00</strong>
        </aside>
      </div>

      <div class="staff-action-stack" aria-label="Current ticket actions">
        <button class="btn btn-primary staff-call-next" id="call-next" type="button" data-staff-action
                data-action-url="<?= APP_URL ?>/modules/service_window/call_next.php"
                data-action-success="The next ticket was called successfully."
                data-loading-text="Calling next..."
                disabled>
          <i class="bi bi-play-circle" aria-hidden="true"></i> Call Next
        </button>
        <button class="btn btn-primary staff-complete-action" type="button" data-staff-action
                data-action-url="<?= APP_URL ?>/modules/service_window/complete_ticket.php"
                data-ticket-id="<?= (int) $current['ticket_id'] ?>"
                data-action-success="Ticket <?= htmlspecialchars($current['ticket_number'], ENT_QUOTES) ?> was marked complete."
                data-loading-text="Completing...">
          <i class="bi bi-check2-circle" aria-hidden="true"></i> Mark Complete
        </button>
        <button class="btn btn-outline-danger staff-skip-action" type="button" data-staff-action
                data-action-url="<?= APP_URL ?>/modules/service_window/skip_ticket.php"
                data-ticket-id="<?= (int) $current['ticket_id'] ?>"
                data-action-success="Ticket <?= htmlspecialchars($current['ticket_number'], ENT_QUOTES) ?> was skipped."
                data-staff-confirm
                data-staff-confirm-title="Skip ticket <?= htmlspecialchars($current['ticket_number'], ENT_QUOTES) ?>?"
                data-staff-confirm-message="This records the client as a no-show and returns your service window to open."
                data-staff-confirm-label="Skip ticket"
                data-staff-confirm-tone="warning"
                data-loading-text="Skipping...">
          <i class="bi bi-person-dash" aria-hidden="true"></i> Skip
        </button>
        <button class="btn btn-danger staff-void-action" type="button" data-staff-action
                data-action-url="<?= APP_URL ?>/modules/service_window/void_ticket.php"
                data-ticket-id="<?= (int) $current['ticket_id'] ?>"
                data-action-success="Ticket <?= htmlspecialchars($current['ticket_number'], ENT_QUOTES) ?> was voided."
                data-staff-confirm
                data-staff-confirm-title="Void ticket <?= htmlspecialchars($current['ticket_number'], ENT_QUOTES) ?>?"
                data-staff-confirm-message="The ticket will be voided immediately, the client will be notified, and your service window will return to open."
                data-staff-confirm-label="Void ticket"
                data-staff-confirm-tone="danger"
                data-loading-text="Voiding...">
          <i class="bi bi-ban" aria-hidden="true"></i> Void
        </button>
      </div>
    <?php else: ?>
      <div class="staff-serving-main">
        <span class="staff-live-badge is-muted">Ready</span>
        <h2 id="currently-serving-title" class="staff-empty-serving-title">No active ticket</h2>
        <p class="staff-empty-serving-copy">Call the next waiting client when your window is open and ready.</p>
      </div>
      <div class="staff-serving-meta">
        <span class="staff-type-pill"><?= number_format(count($waiting)) ?> waiting</span>
        <span class="staff-wait-copy"><i class="bi bi-info-circle" aria-hidden="true"></i> Priority rows are called first.</span>
      </div>
      <div class="staff-action-stack">
        <button class="btn btn-primary staff-call-next" id="call-next" type="button" data-staff-action
                data-action-url="<?= APP_URL ?>/modules/service_window/call_next.php"
                data-action-success="The next ticket was called successfully."
                data-loading-text="Calling next..."
                <?= $callNextDisabled ? 'disabled' : '' ?>>
          <i class="bi bi-play-circle" aria-hidden="true"></i> Call Next
        </button>
        <a class="btn btn-outline-primary" href="window.php"><i class="bi bi-list-ul" aria-hidden="true"></i> View Full Queue</a>
      </div>
    <?php endif; ?>
    </article>
  </section>

  <section class="staff-upcoming-section" aria-labelledby="upcoming-queue-title">
    <div class="staff-section-heading staff-section-heading-flat">
      <div>
        <h2 id="upcoming-queue-title">Upcoming Queue</h2>
        <span class="staff-queue-count"><?= number_format(count($waiting)) ?> Tickets Waiting</span>
      </div>
      <a class="staff-view-link" href="window.php">View Full List <i class="bi bi-chevron-right" aria-hidden="true"></i></a>
    </div>

    <?php if ($waitingPreview): ?>
      <div class="staff-table-wrap">
        <table class="staff-upcoming-table">
          <thead>
            <tr>
              <th>Ticket ID</th>
              <th>Patient Name</th>
              <th>Type</th>
              <th>Service Type</th>
              <th>Registration</th>
              <th>Quick Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($waitingPreview as $index => $ticket): ?>
              <?php
                $priorityTicket = (int) $ticket['priority_level'] === 1;
                $clientTypeLabel = staffClientTypeLabel($ticket['client_type'] ?? 'regular');
              ?>
              <tr class="<?= $priorityTicket ? 'is-priority' : '' ?>">
                <td data-label="Ticket ID"><strong><?= htmlspecialchars($ticket['ticket_number']) ?></strong></td>
                <td data-label="Patient Name">
                  <?php if ($priorityTicket): ?><i class="bi bi-person-check staff-row-priority-icon" aria-hidden="true"></i><?php endif; ?>
                  <?= htmlspecialchars($ticket['client_name'] ?? 'Client') ?>
                </td>
                <td data-label="Type">
                  <?php if ($priorityTicket): ?>
                    <span class="badge-priority"><?= htmlspecialchars($clientTypeLabel) ?></span>
                  <?php else: ?>
                    <span class="staff-type-pill">Regular</span>
                  <?php endif; ?>
                </td>
                <td data-label="Service Type"><?= htmlspecialchars($ticket['service_name']) ?></td>
                <td data-label="Registration">
                  <time datetime="<?= htmlspecialchars(date(DATE_ATOM, strtotime((string) $ticket['issued_at']))) ?>">
                    <?= htmlspecialchars(date('g:i A', strtotime((string) $ticket['issued_at']))) ?>
                  </time>
                </td>
                <td data-label="Quick Action"><span class="staff-row-status"><?= $index === 0 ? 'Next in line' : 'Waiting' ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="staff-table-note"><i class="bi bi-info-circle" aria-hidden="true"></i> Showing next 5 available tickets. Prioritize rows highlighted in soft green.</p>
    <?php else: ?>
      <div class="staff-empty-inline staff-queue-empty">
        <span><i class="bi bi-people" aria-hidden="true"></i></span>
        <div><h3>No waiting tickets</h3><p>New tickets for <?= htmlspecialchars($serviceName) ?> will appear here after clients join the queue.</p></div>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
