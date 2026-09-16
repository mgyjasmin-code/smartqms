<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_STAFF);
require_once __DIR__ . '/includes/context.php';

$pageTitle = 'Staff Dashboard';
$pageHeading = 'Queue Management';
$pageSubtitle = 'Keep the current client and your service window status visible at a glance.';
$activePage = 'dashboard';
$staffBodyClass = 'staff-dashboard-page';
$staffLiveRefresh = true;
include __DIR__ . '/includes/header.php';
?>

<?php if (!$window): ?>
  <section class="staff-empty-state">
    <span><i data-lucide="monitor-x" aria-hidden="true"></i></span>
    <h2>No service window assigned</h2>
    <p>Claim an available service counter before you begin queue operations.</p>
    <a class="btn btn-primary" href="<?= APP_URL ?>/staff/select-counter/">Select Counter</a>
  </section>
<?php else: ?>
  <?php
    $serviceName = $window['service_name'] ?? 'No health service assigned';
    $waitingPreview = array_slice($waiting, 0, 5);
    $nextTicket = $waitingPreview[0] ?? null;
    $callNextDisabled = $current || $window['status'] === 'closed' || !$windowServices || !$waiting;
    $windowStatus = (string) ($window['status'] ?? 'closed');
    $windowStatusLabels = ['open' => 'Claimed / Active', 'busy' => 'Busy / Serving', 'closed' => 'Closed'];
    $windowStatusLabel = $windowStatusLabels[$windowStatus] ?? ucfirst($windowStatus);
    $currentArrivedAt = $current ? (string) (($current['checked_in_at'] ?? '') ?: ($current['issued_at'] ?? '')) : '';
    $currentServiceName = $current['service_name'] ?? $serviceName;
    $currentEntryType = ucfirst(str_replace('-', ' ', (string) ($current['entry_type'] ?? 'walk-in')));
    $currentStatusLabel = $currentLifecycle === 'calling' ? 'Calling' : 'In Service';
  ?>

  <div class="staff-dashboard">
    <section class="staff-counter-band admin-card" aria-label="Assigned service counter">
      <details class="staff-counter-switcher">
        <summary>
          <span class="staff-counter-icon"><i data-lucide="building-2" aria-hidden="true"></i></span>
          <span class="staff-counter-copy">
            <strong><?= htmlspecialchars($window['window_name']) ?></strong>
            <span>Assigned service counter</span>
          </span>
          <i class="staff-counter-chevron" data-lucide="chevron-down" aria-hidden="true"></i>
        </summary>
        <div class="staff-counter-menu">
          <p>Counter status</p>
          <div class="staff-status-segment" role="group" aria-label="Window status">
            <?php foreach (['open' => 'Open', 'busy' => 'Busy', 'closed' => 'Closed'] as $statusValue => $statusLabel): ?>
              <button class="staff-status-option<?= $windowStatus === $statusValue ? ' is-active' : '' ?>" type="button" data-staff-action
                      data-provider-action="setCounterStatus" data-status="<?= htmlspecialchars($statusValue) ?>"
                      data-action-url="<?= APP_URL ?>/modules/service_window/window_status.php"
                      data-action-success="<?= htmlspecialchars($window['window_name'], ENT_QUOTES) ?> is now <?= htmlspecialchars($statusValue, ENT_QUOTES) ?>."
                      data-loading-text="Updating..." aria-pressed="<?= $windowStatus === $statusValue ? 'true' : 'false' ?>">
                <i data-lucide="circle" aria-hidden="true"></i><span><?= htmlspecialchars($statusLabel) ?></span>
              </button>
            <?php endforeach; ?>
          </div>
          <a class="staff-change-counter" href="<?= APP_URL ?>/staff/select-counter/?change=1">
            <i data-lucide="arrow-left-right" aria-hidden="true"></i><span>Change counter</span>
          </a>
        </div>
      </details>

      <div class="staff-counter-state" data-state="<?= htmlspecialchars($windowStatus) ?>">
        <span aria-hidden="true"></span><strong><?= htmlspecialchars($windowStatusLabel) ?></strong>
      </div>
    </section>

    <p class="staff-refresh-status" data-staff-refresh-status role="status" aria-live="polite">Last updated <?= htmlspecialchars(date('g:i A')) ?>. Queue information updates automatically.</p>


    <section class="staff-ops-panel staff-current-panel admin-card admin-table-card" aria-labelledby="currently-serving-title">
      <header class="staff-ops-heading admin-card-header">
        <div><i data-lucide="volume-2" aria-hidden="true"></i><h2 id="currently-serving-title">Current ticket</h2></div>
      </header>

      <div class="staff-table-wrap table-responsive" role="region" aria-label="Current ticket; scroll horizontally to view all columns" tabindex="0">
          <table class="staff-upcoming-table staff-current-table admin-data-table">
          <thead><tr><th>Queue #</th><th>Service</th><th>Type</th><th>Arrived</th><th>Called</th><th>Status</th></tr></thead>
          <tbody>
            <?php if ($current): ?>
              <tr>
                <td><strong class="staff-current-number"><?= htmlspecialchars($current['ticket_number']) ?></strong></td>
                <td><?= htmlspecialchars($currentServiceName) ?></td>
                <td><span class="staff-booking-badge"><?= htmlspecialchars($currentEntryType) ?></span></td>
                <td><?= $currentArrivedAt !== '' ? htmlspecialchars(date('g:i A', strtotime($currentArrivedAt))) : '—' ?></td>
                <td><?= !empty($current['called_at']) ? htmlspecialchars(date('g:i A', strtotime((string) $current['called_at']))) : '—' ?></td>
                <td>
                  <span class="staff-current-status"><i data-lucide="refresh-cw" aria-hidden="true"></i><?= htmlspecialchars($currentStatusLabel) ?></span>
                  <?php if ($currentLifecycle === 'calling'): ?>
                    <small class="staff-inline-timer" data-void-timer><strong data-void-countdown
                      data-remaining-seconds="<?= (int) ($voidRemainingSeconds ?? 0) ?>"
                      data-void-url="<?= APP_URL ?>/modules/queue/void_checker.php"><?= sprintf('%02d:%02d', intdiv(max(0, (int) ($voidRemainingSeconds ?? 0)), 60), max(0, (int) ($voidRemainingSeconds ?? 0)) % 60) ?></strong> remaining</small>
                  <?php endif; ?>
                </td>

              </tr>
            <?php else: ?>
              <tr><td colspan="6"><div class="staff-table-empty"><i data-lucide="radio" aria-hidden="true"></i><div><strong>No active ticket</strong><span>Call the first eligible waiting ticket when the counter is ready.</span></div></div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ($current): ?><footer class="staff-current-actions">
                  <div class="staff-row-actions" aria-label="Actions for <?= htmlspecialchars($current['ticket_number'], ENT_QUOTES) ?>">
                    <?php if ($currentLifecycle === 'calling'): ?>
                      <button class="staff-table-action" type="button" data-staff-action
                              data-action-url="<?= APP_URL ?>/modules/service_window/recall_ticket.php" data-ticket-id="<?= (int) $current['ticket_id'] ?>"
                              data-action-success="Ticket <?= htmlspecialchars($current['ticket_number'], ENT_QUOTES) ?> was recalled."
                              data-loading-text="Recalling..." aria-label="Recall <?= htmlspecialchars($current['ticket_number'], ENT_QUOTES) ?>" title="Recall ticket">
                        <i data-lucide="refresh-cw" aria-hidden="true"></i><span>Recall</span>
                      </button>
                      <button class="staff-table-action is-primary" type="button" data-staff-action
                              data-action-url="<?= APP_URL ?>/modules/service_window/start_service.php" data-ticket-id="<?= (int) $current['ticket_id'] ?>"
                              data-action-success="Service started for ticket <?= htmlspecialchars($current['ticket_number'], ENT_QUOTES) ?>."
                              data-loading-text="Starting..." aria-label="Start service for <?= htmlspecialchars($current['ticket_number'], ENT_QUOTES) ?>" title="Start service">
                        <i data-lucide="play" aria-hidden="true"></i><span>Start</span>
                      </button>
                      <button class="staff-table-action" type="button" data-staff-action data-provider-action="skipTicket"
                              data-action-url="<?= APP_URL ?>/modules/service_window/skip_ticket.php" data-ticket-id="<?= (int) $current['ticket_id'] ?>"
                              data-action-success="Ticket <?= htmlspecialchars($current['ticket_number'], ENT_QUOTES) ?> was skipped."
                              data-staff-confirm data-staff-confirm-title="Skip ticket <?= htmlspecialchars($current['ticket_number'], ENT_QUOTES) ?>?"
                              data-staff-confirm-message="This records the client as a no-show and returns your service window to open."
                              data-staff-confirm-label="Skip ticket" data-staff-confirm-tone="warning" data-loading-text="Skipping..."
                              aria-label="Skip <?= htmlspecialchars($current['ticket_number'], ENT_QUOTES) ?>" title="Skip ticket">
                        <i data-lucide="skip-forward" aria-hidden="true"></i><span>Skip</span>
                      </button>
                      <button class="staff-table-action is-danger" type="button" data-staff-action data-provider-action="voidTicket"
                              data-action-url="<?= APP_URL ?>/modules/service_window/void_ticket.php" data-ticket-id="<?= (int) $current['ticket_id'] ?>"
                              data-action-success="Ticket <?= htmlspecialchars($current['ticket_number'], ENT_QUOTES) ?> was voided."
                              data-staff-confirm data-staff-confirm-title="Void ticket <?= htmlspecialchars($current['ticket_number'], ENT_QUOTES) ?>?"
                              data-staff-confirm-message="The ticket will be voided immediately, the client will be notified, and your service window will return to open."
                              data-staff-confirm-label="Void ticket" data-staff-confirm-tone="danger" data-loading-text="Voiding..."
                              aria-label="Void <?= htmlspecialchars($current['ticket_number'], ENT_QUOTES) ?>" title="Void ticket">
                        <i data-lucide="ban" aria-hidden="true"></i><span>Void</span>
                      </button>
                    <?php else: ?>
                      <button class="staff-table-action is-primary" type="button" data-staff-action data-provider-action="completeTicket"
                              data-action-url="<?= APP_URL ?>/modules/service_window/complete_ticket.php" data-ticket-id="<?= (int) $current['ticket_id'] ?>"
                              data-action-success="Ticket <?= htmlspecialchars($current['ticket_number'], ENT_QUOTES) ?> was marked complete."
                              data-loading-text="Completing..." aria-label="Complete <?= htmlspecialchars($current['ticket_number'], ENT_QUOTES) ?>" title="Complete service">
                        <i data-lucide="check" aria-hidden="true"></i><span>Complete</span>
                      </button>
                    <?php endif; ?>
                  </div>

      </footer><?php endif; ?>
    </section>

    <section class="staff-kpi-grid admin-kpi-grid" aria-label="Queue summary">
      <?php foreach ([
        ['completed', 'Completed Today', 'circle-check-big', 'neutral'],
        ['active', 'Currently Serving', 'radio', 'live'],
        ['waiting', 'Waiting in Queue', 'hourglass', 'warning'],
        ['voided', 'Voided / Skipped', 'circle-slash-2', 'danger'],
      ] as [$key, $label, $icon, $tone]): ?>
        <article class="staff-kpi-card admin-kpi-card" data-tone="<?= htmlspecialchars($tone) ?>">
          <div class="staff-kpi-copy">
            <span><?= htmlspecialchars($label) ?></span>
            <strong><?= str_pad((string) ((int) $staffKpis[$key]), 2, '0', STR_PAD_LEFT) ?></strong>
          </div>
          <span class="staff-kpi-icon"><i data-lucide="<?= htmlspecialchars($icon) ?>" aria-hidden="true"></i></span>
        </article>
      <?php endforeach; ?>
    </section>

    <section class="staff-ops-panel staff-waiting-panel admin-card admin-table-card" aria-labelledby="upcoming-queue-title">
      <header class="staff-ops-heading admin-card-header">
        <div><i data-lucide="users-round" aria-hidden="true"></i><h2 id="upcoming-queue-title">Active Waiting Queue</h2></div>
        <button class="btn btn-primary staff-call-next" id="call-next" type="button" data-staff-action
                data-provider-action="callNextTicket" data-action-url="<?= APP_URL ?>/modules/service_window/call_next.php"
                data-action-success="The next ticket was called successfully." data-loading-text="Calling next..."
                <?= $callNextDisabled ? 'disabled' : '' ?>>
          <i data-lucide="bell-ring" aria-hidden="true"></i>
          Call Next<?= $nextTicket ? ' (' . htmlspecialchars($nextTicket['ticket_number']) . ')' : '' ?>
        </button>
      </header>

      <?php if ($waitingPreview): ?>
        <div class="staff-table-wrap table-responsive" role="region" aria-label="Strict FIFO queue; scroll horizontally to view all columns" tabindex="0">
          <table class="staff-upcoming-table staff-fifo-table admin-data-table">
            <thead><tr><th>Pos</th><th>Queue #</th><th>Service</th><th>Booking Type</th><th>Arrived Time</th><th class="text-end">Action</th></tr></thead>
            <tbody>
              <?php foreach ($waitingPreview as $index => $ticket): ?>
                <?php $bookingType = ucfirst(str_replace('-', ' ', (string) ($ticket['entry_type'] ?? 'walk-in'))); ?>
                <tr>
                  <td><span class="staff-queue-position"><?= $index + 1 ?></span></td>
                  <td><strong><?= htmlspecialchars($ticket['ticket_number']) ?></strong></td>
                  <td><?= htmlspecialchars($ticket['service_name']) ?></td>
                  <td><span class="staff-booking-badge<?= strtolower((string) ($ticket['entry_type'] ?? 'walk-in')) === 'online' ? ' is-online' : '' ?>"><?= htmlspecialchars($bookingType) ?></span></td>
                  <td><time datetime="<?= htmlspecialchars(date(DATE_ATOM, strtotime((string) ($ticket['checked_in_at'] ?? $ticket['issued_at'])))) ?>"><?= htmlspecialchars(date('g:i A', strtotime((string) ($ticket['checked_in_at'] ?? $ticket['issued_at'])))) ?></time></td>
                  <td class="text-end">
                    <?php if ($index === 0 && !$callNextDisabled): ?>
                      <button class="btn btn-primary btn-sm staff-row-call" type="button" data-staff-action data-provider-action="callNextTicket"
                              data-action-url="<?= APP_URL ?>/modules/service_window/call_next.php"
                              data-action-success="Calling <?= htmlspecialchars($ticket['ticket_number'], ENT_QUOTES) ?> — <?= htmlspecialchars($window['window_name'], ENT_QUOTES) ?>."
                              data-loading-text="Calling...">Call</button>
                    <?php else: ?>
                      <button class="btn btn-outline-secondary btn-sm staff-row-call" type="button" disabled
                              title="<?= $index === 0 ? 'The counter must be open and idle.' : 'Strict FIFO: the previous ticket must be called first.' ?>">Call</button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <footer class="staff-panel-footer">
          <span><i data-lucide="info" aria-hidden="true"></i> Only the first eligible ticket can be called.</span>
        </footer>
      <?php else: ?>
        <div class="staff-table-empty is-large"><i data-lucide="users-round" aria-hidden="true"></i><div><strong>No waiting tickets</strong><span>Checked-in clients assigned to this counter will appear here in FIFO order.</span></div></div>
      <?php endif; ?>
    </section>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
