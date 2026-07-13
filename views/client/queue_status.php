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
  <title>Queue Status -- SmartQMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
</head>
<body class="client-page">
  <main class="client-shell">
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
      <section class="client-panel queue-current-strip">
        <div>
          <p class="panel-kicker">Your Ticket</p>
          <h2><?= htmlspecialchars($activeTicket['ticket_number']) ?></h2>
          <span><?= htmlspecialchars($activeTicket['service_name']) ?></span>
        </div>
        <div class="queue-current-metrics">
          <div>
            <span>Status</span>
            <strong><?= htmlspecialchars(ucfirst($activeTicket['status'])) ?></strong>
          </div>
          <div>
            <span>People Ahead</span>
            <strong><?= (int) $activePeopleAhead ?></strong>
          </div>
          <div>
            <span>Estimated Wait</span>
            <strong><?= htmlspecialchars($activeWait) ?></strong>
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

  <script>
    const statusUrl = '<?= APP_URL ?>/modules/queue/status.php';
    const windowsEl = document.querySelector('[data-window-status]');
    const nextEl = document.querySelector('[data-next-tickets]');
    const updatedEl = document.querySelector('[data-status-updated]');

    function escapeHtml(value) {
      return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      }[char]));
    }

    function statusClass(status) {
      return ['open', 'busy', 'closed'].includes(status) ? status : 'closed';
    }

    function renderWindows(windows) {
      if (!windows.length) {
        windowsEl.innerHTML = '<div class="queue-empty-state">No service windows are configured yet.</div>';
        return;
      }

        windowsEl.innerHTML = windows.map(windowInfo => {
          const ticket = windowInfo.ticket_number || '--';
          const service = windowInfo.service_name || 'No service assigned';
          const status = windowInfo.status || 'closed';
          const clientType = windowInfo.client_type ? `<span class="status-mini-badge">${escapeHtml(windowInfo.client_type)}</span>` : '';
          return `
          <article class="window-status-card">
            <div class="window-status-card-head">
              <span>${escapeHtml(windowInfo.window_name || 'Window')}</span>
              <strong class="window-state window-state-${statusClass(status)}">${escapeHtml(status.toUpperCase())}</strong>
            </div>
            <div class="window-ticket">${escapeHtml(ticket)}</div>
            <p>${escapeHtml(service)}</p>
            ${clientType}
          </article>
        `;
      }).join('');
    }

    function renderNextTickets(tickets) {
      if (!tickets.length) {
        nextEl.innerHTML = '<div class="queue-empty-state">No waiting tickets right now.</div>';
        return;
      }

      nextEl.innerHTML = tickets.map((ticket, index) => {
        const priority = ['senior', 'pwd'].includes(ticket.client_type || '');
        return `
        <article class="next-ticket-row${priority ? ' is-priority' : ''}">
          <span>${index + 1}</span>
          <div>
            <strong>${escapeHtml(ticket.ticket_number)}</strong>
            <small>${escapeHtml(ticket.service_name)} &middot; ${escapeHtml(ticket.client_type)}</small>
          </div>
        </article>
      `;
      }).join('');
    }

    async function loadQueueStatus() {
      try {
        const response = await fetch(statusUrl, { credentials: 'same-origin' });
        const payload = await response.json();
        if (!payload.success) throw new Error('Status unavailable');
        renderWindows(payload.data.windows || []);
        renderNextTickets(payload.data.next || []);
        updatedEl.textContent = 'Updated ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      } catch (error) {
        windowsEl.innerHTML = '<div class="queue-empty-state">Could not load queue status.</div>';
        nextEl.innerHTML = '<div class="queue-empty-state">Please refresh the page.</div>';
        updatedEl.textContent = 'Update failed';
      }
    }

    loadQueueStatus();
    setInterval(loadQueueStatus, 10000);
  </script>
</body>
</html>
