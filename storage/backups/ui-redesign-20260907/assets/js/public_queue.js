(() => {
  'use strict';

  const root = document.querySelector('[data-public-ticket-tracker]');
  if (!root || root.dataset.initialized === 'true') return;
  root.dataset.initialized = 'true';

  const statusUrl = root.dataset.statusUrl;
  const feedbackUrl = root.dataset.feedbackUrl;
  const token = root.dataset.token;
  const panel = root.querySelector('[data-status-panel]');
  const metadata = root.querySelector('[data-ticket-metadata]');
  const gate = root.querySelector('[data-feedback-gate]');
  const complete = root.querySelector('[data-feedback-complete]');
  const soundButton = root.querySelector('[data-enable-sound]');
  let timer = 0;
  let inFlight = false;
  let soundEnabled = false;
  let lastStatus = panel?.dataset.status || '';

  const setText = (selector, value) => {
    const element = root.querySelector(selector);
    if (element) element.textContent = String(value ?? '');
  };

  const statusCopy = {
    scheduled: ['Scheduled', 'Present this QR code or reference to staff when you arrive today.'],
    waiting: ['Waiting in Line', 'Your arrival is confirmed. Please keep this page open.'],
    calling: ['Now Calling', 'Please proceed to your service counter now.'],
    'in-progress': ['Being Served', 'You are currently being attended to.'],
    completed: ['Service Complete', 'Please submit feedback to finish this ticket.'],
    void: ['Ticket Closed', 'This ticket is no longer active. Contact the service counter if you need assistance.']
  };

  const chime = () => {
    if (!soundEnabled) return;
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (!AudioContext) return;
    const context = new AudioContext();
    [660, 880].forEach((frequency, index) => {
      const oscillator = context.createOscillator();
      const gain = context.createGain();
      oscillator.frequency.value = frequency;
      gain.gain.setValueAtTime(0.001, context.currentTime + index * 0.18);
      gain.gain.exponentialRampToValueAtTime(0.24, context.currentTime + index * 0.18 + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.001, context.currentTime + index * 0.18 + 0.16);
      oscillator.connect(gain).connect(context.destination);
      oscillator.start(context.currentTime + index * 0.18);
      oscillator.stop(context.currentTime + index * 0.18 + 0.18);
    });
    window.setTimeout(() => context.close(), 700);
  };

  const render = (data) => {
    const status = data.status || 'waiting';
    const copy = statusCopy[status] || statusCopy.waiting;
    panel.dataset.status = status;
    document.body.classList.toggle('is-ticket-calling', status === 'calling');
    setText('[data-status-label]', copy[0]);
    setText('[data-status-message]', copy[1]);
    setText('[data-ticket-number]', data.ticket_number || (status === 'scheduled' ? 'Appointment Confirmed' : 'Queue Ticket'));
    setText('[data-queue-number]', data.ticket_number || 'Assigned at check-in');
    setText('[data-status-detail]', copy[0]);
    setText('[data-service-name]', data.service_name);
    setText('[data-people-ahead]', status === 'scheduled' ? 'Available after check-in' : data.people_ahead);
    const waitDisplay = root.querySelector('[data-wait-display]');
    if (waitDisplay) {
      waitDisplay.textContent = status === 'scheduled'
        ? 'Calculated at check-in'
        : `${Number(data.predicted_wait_minutes || 0).toFixed(1)} min`;
    }
    setText('[data-counter-label]', data.counter_label || 'To be assigned');
    if (status === 'calling' && data.counter_label) {
      setText('[data-status-message]', `Proceed to ${data.counter_label}`);
    }
    const lastUpdated = root.querySelector('[data-last-updated]');
    if (lastUpdated) {
      const now = new Date();
      lastUpdated.dateTime = now.toISOString();
      lastUpdated.textContent = `Last updated ${new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(now)}`;
    }

    const feedbackRequired = status === 'completed' && !data.has_feedback;
    if (metadata) metadata.hidden = status === 'completed';
    if (gate) gate.hidden = !feedbackRequired;
    if (complete) complete.hidden = !(status === 'completed' && data.has_feedback);
    if (status === 'calling' && lastStatus !== 'calling') chime();
    lastStatus = status;
  };

  const schedule = () => {
    window.clearTimeout(timer);
    if (!document.hidden) timer = window.setTimeout(poll, 5000);
  };

  const poll = async () => {
    if (inFlight || document.hidden) return schedule();
    inFlight = true;
    try {
      const response = await fetch(`${statusUrl}?token=${encodeURIComponent(token)}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        cache: 'no-store'
      });
      const payload = await response.json();
      if (response.ok && payload.success) render(payload.data);
    } catch (_) {
      // Keep the last authoritative projection visible and retry on schedule.
    } finally {
      inFlight = false;
      schedule();
    }
  };

  soundButton?.addEventListener('click', () => {
    soundEnabled = !soundEnabled;
    soundButton.setAttribute('aria-pressed', String(soundEnabled));
    soundButton.textContent = soundEnabled ? 'Alerts enabled' : 'Enable alerts';
    if (soundEnabled && lastStatus === 'calling') chime();
  });

  root.querySelector('[data-public-feedback-form]')?.addEventListener('submit', async (event) => {
    const form = event.currentTarget;
    if (!form.checkValidity()) return;
    event.preventDefault();
    const button = form.querySelector('[type="submit"]');
    const error = form.querySelector('[data-feedback-error]');
    if (button?.disabled) return;
    if (button) button.disabled = true;
    if (error) error.hidden = true;
    try {
      const response = await fetch(feedbackUrl, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json' },
        credentials: 'same-origin'
      });
      const payload = await response.json();
      if (!response.ok || !payload.success) throw new Error(payload.error || 'Feedback could not be submitted.');
      form.reset();
      await poll();
    } catch (failure) {
      if (error) {
        error.textContent = failure.message;
        error.hidden = false;
      }
    } finally {
      if (button) button.disabled = false;
    }
  });

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) window.clearTimeout(timer);
    else poll();
  });

  render({
    status: panel?.dataset.status || 'waiting',
    ticket_number: root.querySelector('[data-ticket-number]')?.textContent || '',
    service_name: root.querySelector('[data-service-name]')?.textContent || '',
    people_ahead: root.querySelector('[data-people-ahead]')?.textContent || '0',
    predicted_wait_minutes: root.querySelector('[data-wait-minutes]')?.textContent || '0',
    counter_label: root.querySelector('[data-counter-label]')?.textContent || '',
    has_feedback: !complete?.hidden
  });
  poll();
})();
