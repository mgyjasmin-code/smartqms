(() => {
  'use strict';

  const root = document.querySelector('[data-public-display]');
  if (!root || root.dataset.initialized === 'true') return;
  root.dataset.initialized = 'true';

  const endpoint = root.dataset.statusUrl || '';
  const featured = root.querySelector('[data-display-featured]');
  const featuredLabel = root.querySelector('[data-display-featured-label]');
  const featuredNumber = root.querySelector('[data-display-featured-number]');
  const featuredCounter = root.querySelector('[data-display-featured-counter]');
  const featuredService = root.querySelector('[data-display-featured-service]');
  const activeList = root.querySelector('[data-display-active-list]');
  const activeCount = root.querySelector('[data-display-active-count]');
  const activeOverflow = root.querySelector('[data-display-active-overflow]');
  const waitingList = root.querySelector('[data-display-waiting]');
  const waitingCount = root.querySelector('[data-display-waiting-count]');
  const waitingOverflow = root.querySelector('[data-display-waiting-overflow]');
  const clockTime = root.querySelector('[data-display-time]');
  const clockDate = root.querySelector('[data-display-date]');

  let servingRows = [];
  let waitingRows = [];
  let inFlight = false;
  let hasRenderedPayload = false;
  let lastFeaturedEvent = '';
  let emphasisTimer = 0;
  let resizeFrame = 0;

  const text = (tagName, className, value) => {
    const element = document.createElement(tagName);
    if (className) element.className = className;
    element.textContent = value;
    return element;
  };

  const eventId = ticket => ticket ? `${ticket.ticket_id}:${ticket.called_at || ''}` : '';

  const setConnectionState = state => {
    root.dataset.connectionState = state;
  };

  const readPixelToken = (element, property, fallback) => {
    if (!element || typeof window.getComputedStyle !== 'function') return fallback;
    const value = Number.parseFloat(window.getComputedStyle(element).getPropertyValue(property));
    return Number.isFinite(value) && value > 0 ? value : fallback;
  };

  const visibleCapacity = (element, rowProperty, fallbackRowHeight) => {
    if (!element) return 1;
    const rowHeight = readPixelToken(element, rowProperty, fallbackRowHeight);
    const gap = readPixelToken(element, '--display-row-gap', 10);
    return Math.max(1, Math.floor((element.clientHeight + gap) / (rowHeight + gap)));
  };

  const emphasizeNewCall = () => {
    window.clearTimeout(emphasisTimer);
    root.classList.remove('has-new-call');
    void root.offsetWidth;
    root.classList.add('has-new-call');
    emphasisTimer = window.setTimeout(() => root.classList.remove('has-new-call'), 2400);
  };

  const activeRow = row => {
    const article = document.createElement('article');
    article.className = 'public-display-active-row';
    article.setAttribute('aria-label', `Queue number ${row.ticket_number}, ${row.counter_label}, ${row.service_name}`);
    article.append(
      text('strong', '', row.ticket_number),
      text('span', '', row.counter_label),
      text('small', '', row.service_name)
    );
    return article;
  };

  const waitingRow = (row, index) => {
    const article = document.createElement('article');
    article.className = `public-display-waiting-row${index === 0 ? ' is-next' : ''}`;
    article.setAttribute('aria-label', `Position ${index + 1}, queue number ${row.ticket_number}, ${row.service_name}`);
    const position = text('span', 'public-display-position', String(index + 1));
    const details = document.createElement('div');
    details.className = 'public-display-waiting-details';
    details.append(text('strong', '', row.ticket_number), text('small', '', row.service_name));
    article.append(position, details);
    if (index === 0) article.append(text('span', 'public-display-next-label', 'Next'));
    return article;
  };

  const renderActive = () => {
    if (!featured || !activeList || !activeCount || !activeOverflow) return;
    const current = servingRows[0] || null;
    activeCount.textContent = `${servingRows.length} active`;

    if (!current) {
      featured.dataset.state = 'empty';
      featuredLabel.textContent = 'Waiting for the next call';
      featuredNumber.textContent = '—';
      featuredCounter.textContent = '—';
      featuredService.textContent = 'The next queue number will appear here.';
      activeList.replaceChildren(text('p', 'public-display-empty', 'No other windows are currently serving.'));
      activeOverflow.hidden = true;
      activeOverflow.textContent = '';
      lastFeaturedEvent = '';
      return;
    }

    featured.dataset.state = 'active';
    featuredLabel.textContent = 'Now serving';
    featuredNumber.textContent = current.ticket_number;
    featuredCounter.textContent = current.counter_label;
    featuredService.textContent = current.service_name;
    const currentEvent = eventId(current);
    if (hasRenderedPayload && currentEvent && currentEvent !== lastFeaturedEvent) emphasizeNewCall();
    lastFeaturedEvent = currentEvent;

    const otherRows = servingRows.slice(1);
    if (!otherRows.length) {
      activeList.replaceChildren(text('p', 'public-display-empty', 'No other windows are currently serving.'));
      activeOverflow.hidden = true;
      activeOverflow.textContent = '';
      return;
    }

    const capacity = visibleCapacity(activeList, '--display-active-row-height', 64);
    const visibleRows = otherRows.slice(0, capacity);
    activeList.replaceChildren(...visibleRows.map(activeRow));
    const hiddenCount = otherRows.length - visibleRows.length;
    activeOverflow.hidden = hiddenCount <= 0;
    activeOverflow.textContent = hiddenCount > 0 ? `+${hiddenCount} other active windows` : '';
  };

  const renderWaiting = () => {
    if (!waitingList || !waitingCount || !waitingOverflow) return;
    waitingCount.textContent = `${waitingRows.length} waiting`;
    if (!waitingRows.length) {
      waitingList.replaceChildren(text('p', 'public-display-empty', 'No clients are waiting right now.'));
      waitingOverflow.hidden = true;
      waitingOverflow.textContent = '';
      return;
    }

    const capacity = visibleCapacity(waitingList, '--display-waiting-row-height', 72);
    const visibleRows = waitingRows.slice(0, capacity);
    waitingList.replaceChildren(...visibleRows.map(waitingRow));
    const hiddenCount = waitingRows.length - visibleRows.length;
    waitingOverflow.hidden = hiddenCount <= 0;
    waitingOverflow.textContent = hiddenCount > 0 ? `+${hiddenCount} more waiting` : '';
  };

  const renderBoard = () => {
    renderActive();
    renderWaiting();
    hasRenderedPayload = true;
    window.lucide?.createIcons?.();
  };

  const scheduleRender = () => {
    window.cancelAnimationFrame?.(resizeFrame);
    resizeFrame = window.requestAnimationFrame(renderBoard);
  };

  const poll = async () => {
    if (inFlight || document.hidden || !endpoint) return;
    inFlight = true;
    try {
      const response = await fetch(endpoint, {
        headers: { Accept: 'application/json' },
        cache: 'no-store',
        credentials: 'same-origin'
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) throw new Error('Queue display request failed.');
      servingRows = Array.isArray(payload.serving) ? payload.serving : (Array.isArray(payload.data) ? payload.data : []);
      waitingRows = Array.isArray(payload.waiting) ? payload.waiting : [];
      renderBoard();
      setConnectionState('online');
    } catch (_) {
      setConnectionState('offline');
    } finally {
      inFlight = false;
    }
  };

  const timeFormatter = new Intl.DateTimeFormat('en-PH', {
    hour: 'numeric', minute: '2-digit', second: '2-digit', timeZone: 'Asia/Manila'
  });
  const dateFormatter = new Intl.DateTimeFormat('en-PH', {
    weekday: 'long', month: 'long', day: 'numeric', year: 'numeric', timeZone: 'Asia/Manila'
  });
  const updateClock = () => {
    const now = new Date();
    if (clockTime) {
      clockTime.dateTime = now.toISOString();
      clockTime.textContent = timeFormatter.format(now);
    }
    if (clockDate) clockDate.textContent = dateFormatter.format(now);
  };

  const pollTimer = window.setInterval(poll, 3000);
  const clockTimer = window.setInterval(updateClock, 1000);
  const resizeObserver = typeof window.ResizeObserver === 'function'
    ? new window.ResizeObserver(scheduleRender)
    : null;
  resizeObserver?.observe(activeList);
  resizeObserver?.observe(waitingList);
  window.addEventListener('resize', scheduleRender);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
  window.addEventListener('pagehide', () => {
    window.clearInterval(pollTimer);
    window.clearInterval(clockTimer);
    window.clearTimeout(emphasisTimer);
    window.cancelAnimationFrame?.(resizeFrame);
    resizeObserver?.disconnect();
  });

  updateClock();
  poll();
  window.lucide?.createIcons?.();
})();
