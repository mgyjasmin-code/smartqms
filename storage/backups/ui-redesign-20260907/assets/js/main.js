/**
 * SmartQMS shared browser behavior.
 * Loaded by authentication, client, staff, and administrator pages.
 */
(function () {
  'use strict';

  const activeTimers = new Set();

  function csrfTokenFromPage() {
    return document.querySelector('meta[name="csrf-token"]')?.content
      || document.querySelector('input[name="csrf_token"]')?.value
      || '';
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[character]));
  }

  function fieldFeedbackElement(input) {
    const form = input.closest('form');
    if (form && input.name) {
      const namedFeedback = form.querySelector(`[data-field-error-for="${input.name}"]`);
      if (namedFeedback) return namedFeedback;
    }

    if (input.nextElementSibling?.classList.contains('field-error')) {
      return input.nextElementSibling;
    }

    const field = input.closest('.form-row-single')
      || input.closest('.auth-grid > div')
      || input.closest('.col-md-6')
      || input.closest('.col-md-3')
      || input.parentElement;
    return field ? field.querySelector('.field-error') : null;
  }

  function clearServerFieldError(input) {
    if (!input.classList.contains('is-invalid') && input.getAttribute('aria-invalid') !== 'true') return;

    input.classList.remove('is-invalid', 'is-valid');
    input.removeAttribute('aria-invalid');
    const feedback = fieldFeedbackElement(input);
    if (feedback) feedback.textContent = '';
  }

  function initPasswordConfirmations(form) {
    form.querySelectorAll('[data-confirm-password-for]').forEach(confirmation => {
      if (confirmation.dataset.nativeConfirmationInitialized === 'true') return;
      confirmation.dataset.nativeConfirmationInitialized = 'true';

      const sourceReference = confirmation.dataset.confirmPasswordFor || '';
      const source = form.elements?.namedItem?.(sourceReference)
        || document.getElementById(sourceReference);
      if (!source || typeof confirmation.setCustomValidity !== 'function') return;

      const syncValidity = () => {
        const mismatch = confirmation.value !== '' && confirmation.value !== source.value;
        confirmation.setCustomValidity(mismatch ? 'Password entries do not match.' : '');
      };

      source.addEventListener('input', syncValidity);
      confirmation.addEventListener('input', syncValidity);
      syncValidity();
    });
  }

  function setSubmitBusy(form, busy) {
    const submit = form.querySelector('[type="submit"]');
    if (!submit) return;

    if (busy) {
      form.dataset.submitting = 'true';
      submit.dataset.originalText = submit.innerHTML;
      submit.innerHTML = submit.dataset.loadingText || 'Please wait...';
      submit.disabled = true;
      submit.setAttribute('aria-busy', 'true');
      submit.classList.add('is-loading');
      return;
    }

    form.dataset.submitting = 'false';
    if (submit.dataset.originalText) submit.innerHTML = submit.dataset.originalText;
    submit.disabled = false;
    submit.removeAttribute('aria-busy');
    submit.classList.remove('is-loading');
  }

  function initValidatedForms() {
    document.querySelectorAll('.js-auth-form, .js-validated-form').forEach(form => {
      if (form.dataset.validationInitialized === 'true') return;
      form.dataset.validationInitialized = 'true';
      initPasswordConfirmations(form);

      form.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach(input => {
        const clearServerError = () => clearServerFieldError(input);
        input.addEventListener('input', clearServerError);
        input.addEventListener('change', clearServerError);
      });

      form.addEventListener('submit', event => {
        if (form.dataset.submitting === 'true') {
          event.preventDefault();
          return;
        }

        if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
          event.preventDefault();
          form.reportValidity?.();
          return;
        }

        if (form.hasAttribute('data-admin-confirm-form') && form.dataset.adminConfirmed !== 'true') {
          const confirmationEvent = new CustomEvent('smartqms:request-confirmation', {
            bubbles: true,
            cancelable: true,
            detail: {
              handled: false,
              submitter: event.submitter || null,
            },
          });
          form.dispatchEvent(confirmationEvent);

          if (confirmationEvent.detail.handled) {
            event.preventDefault();
            return;
          }
        }

        delete form.dataset.adminConfirmed;
        setSubmitBusy(form, true);
      });
    });
  }

  function formatCountdown(seconds) {
    const safeSeconds = Math.max(0, seconds);
    return `${String(Math.floor(safeSeconds / 60)).padStart(2, '0')}:${String(safeSeconds % 60).padStart(2, '0')}`;
  }

  function initOtpResendCountdown() {
    document.querySelectorAll('[data-otp-resend]').forEach(button => {
      if (button.dataset.otpInitialized === 'true') return;
      button.dataset.otpInitialized = 'true';
      let remaining = parseInt(button.dataset.remaining || '0', 10);
      const readyAt = Date.now() + (remaining * 1000);
      const readyText = button.dataset.readyText || 'Resend OTP';
      const waitText = button.dataset.waitText || 'Resend OTP in';

      const render = () => {
        remaining = Math.max(0, Math.ceil((readyAt - Date.now()) / 1000));
        button.disabled = remaining > 0;
        button.textContent = remaining > 0 ? `${waitText} ${formatCountdown(remaining)}` : readyText;
      };

      const resume = () => {
        render();
        if (remaining <= 0) return;
        const timer = window.setInterval(() => {
          render();
          if (remaining <= 0) {
            window.clearInterval(timer);
            activeTimers.delete(timer);
          }
        }, 1000);
        activeTimers.add(timer);
      };
      button.smartQmsOtpResume = resume;
      resume();
    });
  }

  function initPasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach(button => {
      if (button.dataset.passwordInitialized === 'true') return;
      button.dataset.passwordInitialized = 'true';
      const targetId = button.getAttribute('aria-controls');
      const input = targetId
        ? document.getElementById(targetId)
        : button.closest('.auth-password-field')?.querySelector('input');
      const icon = button.querySelector('i');
      const showIcon = button.querySelector('[data-password-show-icon]');
      const hideIcon = button.querySelector('[data-password-hide-icon]');
      const label = button.dataset.passwordToggleLabel || 'password';
      const usesPairedIcons = Boolean(showIcon && hideIcon);
      if (!input) return;

      button.addEventListener('click', () => {
        const shouldShow = input.type === 'password';
        input.type = shouldShow ? 'text' : 'password';
        if (usesPairedIcons) {
          showIcon.hidden = shouldShow;
          hideIcon.hidden = !shouldShow;
        } else if (icon) {
          icon.classList.toggle('bi-eye', !shouldShow);
          icon.classList.toggle('bi-eye-slash', shouldShow);
        }
        const action = shouldShow ? 'Hide' : 'Show';
        button.setAttribute('aria-label', `${action} ${label}`);
        button.setAttribute('aria-pressed', String(shouldShow));
        button.title = `${action} ${label}`;
      });
    });
  }

  function initEmailDispatch() {
    document.querySelectorAll('[data-email-dispatch]').forEach(status => {
      if (status.dataset.dispatchInitialized === 'true') return;
      status.dataset.dispatchInitialized = 'true';
      const url = status.dataset.emailDispatch;
      if (!url) return;
      const csrfToken = csrfTokenFromPage();

      fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: csrfToken ? { 'X-CSRF-Token': csrfToken } : {}
      })
        .then(response => response.json())
        .then(data => {
          if (!data.success || data.failed > 0) {
            status.textContent = data.message || 'Could not send OTP email right now. Please try resending the code.';
            status.classList.add('auth-note-error');
          } else if (data.sent > 0) {
            status.textContent = 'OTP email sent. Please check your inbox.';
          } else {
            status.hidden = true;
          }
        })
        .catch(() => {
          status.textContent = 'OTP email is still pending. You can resend after the countdown.';
          status.classList.add('auth-note-error');
        });
    });
  }

  function initPrintButtons() {
    document.querySelectorAll('[data-print-page]').forEach(button => {
      if (button.dataset.printInitialized === 'true') return;
      button.dataset.printInitialized = 'true';
      button.addEventListener('click', () => window.print());
    });
  }

  function resetSubmittingForms() {
    document.querySelectorAll('form[data-submitting="true"]').forEach(form => setSubmitBusy(form, false));
  }

  function clearTimers() {
    activeTimers.forEach(timer => window.clearInterval(timer));
    activeTimers.clear();
  }

  function resumeSharedTimers(event) {
    if (!event.persisted) return;
    document.querySelectorAll('[data-otp-resend]').forEach(button => button.smartQmsOtpResume?.());
  }

  function initSharedPage() {
    const root = document.documentElement;
    if (root.dataset.smartqmsSharedInitialized === 'true') return;
    root.dataset.smartqmsSharedInitialized = 'true';
    window.lucide?.createIcons?.();
    initValidatedForms();
    initOtpResendCountdown();
    initEmailDispatch();
    initPasswordToggles();
    initPrintButtons();
  }

  window.SmartQms = Object.assign(window.SmartQms || {}, {
    csrfTokenFromPage,
    escapeHtml,
    setSubmitBusy,
  });

  document.addEventListener('DOMContentLoaded', initSharedPage);
  window.addEventListener('pageshow', resetSubmittingForms);
  window.addEventListener('pageshow', resumeSharedTimers);
  window.addEventListener('pagehide', clearTimers);
})();
