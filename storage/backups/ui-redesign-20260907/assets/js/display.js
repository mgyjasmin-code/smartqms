/**
 * SmartQMS public display-board clock and queue rendering.
 */
(function () {
  'use strict';

  const timers = new Set();

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[character]));
  }

  function initDisplayBoard() {
    const root = document.querySelector('[data-display-root]');
    if (!root || root.dataset.displayInitialized === 'true') return;
    root.dataset.displayInitialized = 'true';
    const statusUrl = root.dataset.statusUrl || '/smartqms/modules/queue/status.php';
    const clock = root.querySelector('#live-clock');
    const grid = root.querySelector('#window-grid');
    const ticker = root.querySelector('#next-ticker');
    const updated = root.querySelector('#last-updated');
    let requestInFlight = false;

    const updateClock = () => {
      const options = {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
      };
      if (clock) clock.textContent = new Date().toLocaleString('en-PH', options);
    };

    const refreshBoard = async () => {
      if (requestInFlight) return;
      requestInFlight = true;
      try {
        const response = await fetch(statusUrl, { credentials: 'same-origin' });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error('Display board refresh failed');
        const payload = data.data || { windows: [], next: [] };
        if (grid) {
          grid.innerHTML = (payload.windows || []).map(windowInfo => {
            const ticket = windowInfo.ticket_number || '--';
            const service = windowInfo.service_name || 'Unassigned';
            const status = ['open', 'busy', 'closed'].includes(windowInfo.status) ? windowInfo.status : 'closed';
            return `<article class="window-card">
              <div class="window-name">${escapeHtml(windowInfo.window_name || 'Window')}</div>
              <div class="serving-label">NOW SERVING</div>
              <div class="ticket-number">${escapeHtml(ticket)}</div>
              <div class="service-name">${escapeHtml(service)}</div>
              <div class="status-${status}">${escapeHtml(status.toUpperCase())}</div>
            </article>`;
          }).join('') || '<article class="window-card"><div class="ticket-number">--</div><div class="service-name">No active windows</div></article>';
        }
        if (ticker) {
          ticker.innerHTML = (payload.next || [])
            .map(ticket => `<span>${escapeHtml(ticket.ticket_number)}</span>`)
            .join('') || 'No waiting tickets';
        }
        if (updated) updated.textContent = `Last updated: ${new Date().toLocaleTimeString('en-PH')}`;
      } catch (error) {
        console.error('Display board refresh failed');
      } finally {
        requestInFlight = false;
      }
    };

    const resume = () => {
      updateClock();
      refreshBoard();
      const clockTimer = window.setInterval(updateClock, 1000);
      const refreshTimer = window.setInterval(refreshBoard, 10000);
      timers.add(clockTimer);
      timers.add(refreshTimer);
    };
    root.smartQmsDisplayResume = resume;
    resume();
  }

  function clearDisplayTimers() {
    timers.forEach(timer => window.clearInterval(timer));
    timers.clear();
  }

  function resumeDisplayTimers(event) {
    if (!event.persisted) return;
    document.querySelector('[data-display-root]')?.smartQmsDisplayResume?.();
  }

  document.addEventListener('DOMContentLoaded', initDisplayBoard);
  window.addEventListener('pagehide', clearDisplayTimers);
  window.addEventListener('pageshow', resumeDisplayTimers);
})();
