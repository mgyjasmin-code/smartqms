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
    const csrfToken = csrfTokenFromPage();
    fetch('../../modules/queue/void_checker.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: csrfToken ? { 'X-CSRF-Token': csrfToken } : {}
    })
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
    const csrfToken = csrfTokenFromPage();
    fetch(notifUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: csrfToken ? { 'X-CSRF-Token': csrfToken } : {}
    })
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
function fieldFeedbackElement(input) {
  const form = input.closest('form');
  if (form && input.name) {
    const namedFeedback = form.querySelector(`[data-field-error-for="${input.name}"]`);
    if (namedFeedback) return namedFeedback;
  }

  if (input.nextElementSibling && input.nextElementSibling.classList.contains('field-error')) {
    return input.nextElementSibling;
  }

  const field = input.closest('.form-row-single') || input.closest('.auth-grid > div') || input.closest('.col-md-6') || input.closest('.col-md-3') || input.parentElement;
  return field ? field.querySelector('.field-error') : null;
}

function ensureFeedbackId(input, feedback) {
  if (!input || !feedback) return;
  if (!feedback.id) {
    feedback.id = `${input.id || input.name}-error`;
  }
  input.setAttribute('aria-describedby', feedback.id);
}

function validateField(input, rules) {
  const value = input.value.trim();
  let error   = '';

  if (rules.required && !value) {
    error = 'This field is required.';
  } else if (rules.minLength && value.length < rules.minLength) {
    error = `Minimum ${rules.minLength} characters.`;
  } else if (rules.phone && !/^09\d{9}$/.test(value)) {
    error = 'Enter a valid Philippine mobile number (e.g. 09171234567).';
  } else if (rules.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
    error = 'Enter a valid email address.';
  } else if (rules.password && value.length < 8) {
    error = 'Password must be at least 8 characters.';
  } else if (rules.pin && !/^\d{6}$/.test(value)) {
    error = 'Enter a 6-digit PIN.';
  } else if (rules.otp && !/^\d{6}$/.test(value)) {
    error = 'Enter the 6-digit OTP code.';
  } else if (rules.confirmFor) {
    const source = document.getElementById(rules.confirmFor);
    if (source && value !== source.value.trim()) error = 'Password entries do not match.';
  }

  const feedback = fieldFeedbackElement(input);
  ensureFeedbackId(input, feedback);
  if (error) {
    input.classList.add('is-invalid');
    input.classList.remove('is-valid');
    input.setAttribute('aria-invalid', 'true');
    if (feedback) feedback.textContent = error;
  } else {
    input.classList.remove('is-invalid');
    input.classList.add('is-valid');
    input.removeAttribute('aria-invalid');
    if (feedback) feedback.textContent = '';
  }
  return !error;
}

function authRulesFor(input) {
  const type = input.dataset.validate || '';
  return {
    required: input.required || type === 'required',
    phone: type === 'phone',
    email: type === 'email' || input.type === 'email',
    password: type === 'password' || type === 'password-confirm',
    pin: type === 'pin' || type === 'pin-confirm',
    otp: type === 'otp',
    confirmFor: (type === 'pin-confirm' || type === 'password-confirm') ? 'password' : '',
  };
}

function formatCountdown(seconds) {
  const safeSeconds = Math.max(0, seconds);
  const minutes = Math.floor(safeSeconds / 60);
  const rest = safeSeconds % 60;
  return `${String(minutes).padStart(2, '0')}:${String(rest).padStart(2, '0')}`;
}

function initOtpResendCountdown() {
  document.querySelectorAll('[data-otp-resend]').forEach(button => {
    let remaining = parseInt(button.dataset.remaining || '0', 10);
    const readyText = button.dataset.readyText || 'Resend OTP';
    const waitText = button.dataset.waitText || 'Resend OTP in';

    const render = () => {
      if (remaining > 0) {
        button.disabled = true;
        button.textContent = `${waitText} ${formatCountdown(remaining)}`;
        return;
      }

      button.disabled = false;
      button.textContent = readyText;
    };

    render();
    if (remaining > 0) {
      const timer = setInterval(() => {
        remaining--;
        render();
        if (remaining <= 0) clearInterval(timer);
      }, 1000);
    }
  });
}

function initPasswordToggles() {
  document.querySelectorAll('[data-password-toggle]').forEach(button => {
    const targetId = button.getAttribute('aria-controls');
    const input = targetId ? document.getElementById(targetId) : button.closest('.auth-password-field')?.querySelector('input');
    const icon = button.querySelector('i');
    const label = button.dataset.passwordToggleLabel || 'password';

    if (!input || !icon) return;

    button.addEventListener('click', () => {
      const shouldShow = input.type === 'password';
      input.type = shouldShow ? 'text' : 'password';

      icon.classList.toggle('bi-eye', !shouldShow);
      icon.classList.toggle('bi-eye-slash', shouldShow);

      const action = shouldShow ? 'Hide' : 'Show';
      button.setAttribute('aria-label', `${action} ${label}`);
      button.setAttribute('aria-pressed', String(shouldShow));
      button.title = `${action} ${label}`;
    });
  });
}

function csrfTokenFromPage() {
  return document.querySelector('meta[name="csrf-token"]')?.content
    || document.querySelector('input[name="csrf_token"]')?.value
    || '';
}

function initEmailDispatch() {
  document.querySelectorAll('[data-email-dispatch]').forEach(status => {
    const url = status.dataset.emailDispatch;
    if (!url) return;
    const csrfToken = csrfTokenFromPage();

    fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: csrfToken ? { 'X-CSRF-Token': csrfToken } : {}
    })
      .then(res => res.json())
      .then(data => {
        if (!data.success || data.failed > 0) {
          status.textContent = data.message || 'Could not send OTP email right now. Please try resending the code.';
          status.classList.add('auth-note-error');
          return;
        }

        if (data.sent > 0) {
          status.textContent = 'OTP email sent. Please check your inbox.';
          return;
        }

        status.hidden = true;
      })
      .catch(() => {
        status.textContent = 'OTP email is still pending. You can resend after the countdown.';
        status.classList.add('auth-note-error');
      });
  });
}

function initLogoutConfirmation() {
  const triggers = document.querySelectorAll('[data-confirm-logout]');
  const modal = document.querySelector('[data-logout-modal]');
  if (!triggers.length || !modal) return;

  const dialog = modal.querySelector('.logout-modal-dialog');
  const closeButtons = modal.querySelectorAll('[data-logout-cancel]');
  let lastFocused = null;

  const closeModal = () => {
    modal.hidden = true;
    document.body.classList.remove('modal-open');
    document.removeEventListener('keydown', onKeydown);
    if (lastFocused) lastFocused.focus();
  };

  function onKeydown(event) {
    if (event.key === 'Escape') {
      closeModal();
    }
  }

  triggers.forEach(trigger => {
    trigger.addEventListener('click', event => {
      event.preventDefault();
      lastFocused = document.activeElement;
      modal.hidden = false;
      document.body.classList.add('modal-open');
      document.addEventListener('keydown', onKeydown);
      const initialFocus = modal.querySelector('.logout-modal-close') || modal.querySelector('.logout-modal-button') || dialog;
      if (initialFocus) initialFocus.focus();
    });
  });

  closeButtons.forEach(button => {
    button.addEventListener('click', closeModal);
  });
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.js-auth-form, .js-validated-form').forEach(form => {
    const fields = Array.from(form.querySelectorAll('[data-validate]'));

    fields.forEach(input => {
      const onFieldEdit = () => {
        if (form.classList.contains('was-validated') || input.classList.contains('is-invalid')) {
          validateField(input, authRulesFor(input));
        }
      };

      input.addEventListener('input', onFieldEdit);
      input.addEventListener('change', onFieldEdit);
    });

    form.addEventListener('submit', event => {
      if (form.dataset.submitting === 'true') {
        event.preventDefault();
        return;
      }

      form.classList.add('was-validated');
      const valid = fields.map(input => validateField(input, authRulesFor(input))).every(Boolean);
      if (!valid) {
        event.preventDefault();
        const firstInvalid = form.querySelector('.is-invalid');
        if (firstInvalid) firstInvalid.focus();
        return;
      }

      const submit = form.querySelector('[type="submit"]');
      if (submit) {
        form.dataset.submitting = 'true';
        submit.dataset.originalText = submit.innerHTML;
        submit.innerHTML = submit.dataset.loadingText || 'Please wait...';
        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
        submit.classList.add('is-loading');
      }
    });
  });

  initOtpResendCountdown();
  initEmailDispatch();
  initLogoutConfirmation();
  initPasswordToggles();
});

window.addEventListener('pageshow', () => {
  document.querySelectorAll('form[data-submitting="true"]').forEach(form => {
    form.dataset.submitting = 'false';
    const submit = form.querySelector('[type="submit"].is-loading');
    if (!submit) return;
    if (submit.dataset.originalText) {
      submit.innerHTML = submit.dataset.originalText;
    }
    submit.disabled = false;
    submit.removeAttribute('aria-busy');
    submit.classList.remove('is-loading');
  });
});
