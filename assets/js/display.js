/**
 * SmartQMS -- Display Board JavaScript
 * Auto-refreshes queue data every 10 seconds.
 * Updates the live clock every second.
 */

const STATUS_URL = window.SMARTQMS_STATUS_URL || '/smartqms/modules/queue/status.php';

// Live clock
function updateClock() {
  const now = new Date();
  const options = {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
    hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
  };
  const el = document.getElementById('live-clock');
  if (el) el.textContent = now.toLocaleString('en-PH', options);
}
setInterval(updateClock, 1000);
updateClock();

// Queue data refresh
function refreshBoard() {
  fetch(STATUS_URL)
    .then(res => res.json())
    .then(data => {
      const payload = data.data || { windows: [], next: [] };
      const grid = document.getElementById('window-grid');
      if (grid) {
        grid.innerHTML = (payload.windows || []).map(windowInfo => {
          const ticket = windowInfo.ticket_number || '--';
          const service = windowInfo.service_name || 'Unassigned';
          const status = windowInfo.status || 'closed';
          return `
            <article class="window-card">
              <div class="window-name">${escapeHtml(windowInfo.window_name || 'Window')}</div>
              <div class="serving-label">NOW SERVING</div>
              <div class="ticket-number">${escapeHtml(ticket)}</div>
              <div class="service-name">${escapeHtml(service)}</div>
              <div class="status-${escapeHtml(status)}">${escapeHtml(status.toUpperCase())}</div>
            </article>
          `;
        }).join('') || '<article class="window-card"><div class="ticket-number">--</div><div class="service-name">No active windows</div></article>';
      }
      const ticker = document.getElementById('next-ticker');
      if (ticker) {
        ticker.innerHTML = (payload.next || []).map(ticket => `<span>${escapeHtml(ticket.ticket_number)}</span>`).join('') || 'No waiting tickets';
      }
      const el = document.getElementById('last-updated');
      if (el) el.textContent = 'Last updated: ' + new Date().toLocaleTimeString('en-PH');
    })
    .catch(() => console.error('Display board refresh failed'));
}

setInterval(refreshBoard, 10000);
refreshBoard();

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}
