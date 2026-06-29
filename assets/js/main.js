/**
 * SmartQMS -- Main JavaScript
 * Handles: queue polling, notifications, void countdown, form validation
 */

// ── Queue status polling (every 10 seconds) ──────────────────
function startQueuePolling(statusUrl, callbackFn) {
  callbackFn(); // run immediately
  return setInterval(callbackFn, 10000);
}

// ── Load predicted wait time from ML API proxy ───────────────
function loadPredictedWaitTime(ticketId, displayElementId) {
  fetch(`../../modules/queue/get_prediction.php?ticket_id=${ticketId}`)
    .then(res => res.json())
    .then(data => {
      const el = document.getElementById(displayElementId);
      if (el && data.predicted_wait_minutes !== undefined) {
        el.textContent = `~${data.predicted_wait_minutes} min`;
      }
    })
    .catch(() => {
      const el = document.getElementById(displayElementId);
      if (el) el.textContent = 'Calculating...';
    });
}

// ── Void countdown timer ─────────────────────────────────────
// Started when staff clicks Call Next. Counts down from voidTimeout.
function startVoidCountdown(timeoutMinutes, displayElementId, onVoid) {
  let remaining = timeoutMinutes * 60; // seconds
  const el      = document.getElementById(displayElementId);

  const interval = setInterval(() => {
    remaining--;
    const m = Math.floor(remaining / 60);
    const s = remaining % 60;
    if (el) el.textContent = `${m}:${String(s).padStart(2,'0')}`;

    if (remaining <= 0) {
      clearInterval(interval);
      if (onVoid) onVoid();
    }
  }, 1000);

  return interval;
}

// ── Auto-void checker (polls server every 30 seconds) ────────
function startVoidChecker() {
  return setInterval(() => {
    fetch('../../modules/queue/void_checker.php')
      .then(res => res.json())
      .then(data => {
        if (data.voided > 0) {
          // Reload queue display to reflect changes
          location.reload();
        }
      });
  }, 30000);
}

// ── Notification poller (client only, every 10 seconds) ──────
function startNotificationPoller(notifUrl) {
  return setInterval(() => {
    fetch(notifUrl)
      .then(res => res.json())
      .then(data => {
        const notifications = Array.isArray(data) ? data : (data.data || []);
        if (notifications.length > 0) {
          notifications.forEach(n => showBrowserNotification(n.message));
        }
      });
  }, 10000);
}

// ── Browser push notification ────────────────────────────────
function showBrowserNotification(message) {
  if ('Notification' in window && Notification.permission === 'granted') {
    new Notification('SmartQMS -- Your Turn is Near', { body: message });
  } else if (Notification.permission !== 'denied') {
    Notification.requestPermission().then(perm => {
      if (perm === 'granted') {
        new Notification('SmartQMS -- Your Turn is Near', { body: message });
      }
    });
  }
}

// ── Inline form validation ────────────────────────────────────
function validateField(input, rules) {
  const value = input.value.trim();
  let error   = '';

  if (rules.required && !value)      error = 'This field is required.';
  if (rules.minLength && value.length < rules.minLength)
    error = `Minimum ${rules.minLength} characters.`;
  if (rules.phone && !/^09\d{9}$/.test(value))
    error = 'Enter a valid Philippine mobile number (e.g. 09171234567).';

  const feedback = input.nextElementSibling;
  if (error) {
    input.classList.add('is-invalid');
    input.classList.remove('is-valid');
    if (feedback) feedback.textContent = error;
  } else {
    input.classList.remove('is-invalid');
    input.classList.add('is-valid');
    if (feedback) feedback.textContent = '';
  }
  return !error;
}
