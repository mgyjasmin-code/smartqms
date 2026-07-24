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

  function ensureFeedbackId(input, feedback) {
    if (!input || !feedback) return;
    if (!feedback.id) feedback.id = `${input.id || input.name}-error`;
    const describedBy = new Set((input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
    describedBy.add(feedback.id);
    input.setAttribute('aria-describedby', Array.from(describedBy).join(' '));
  }

  function clearFieldValidation(input, feedback) {
    input.classList.remove('is-invalid', 'is-valid');
    input.removeAttribute('aria-invalid');
    if (feedback) feedback.textContent = '';
  }

  function validateField(input, rules, options = {}) {
    const value = input.value.trim();
    const feedback = fieldFeedbackElement(input);
    const showRequired = options.showRequired !== false;
    let error = '';

    if (rules.required && !value && !showRequired) {
      clearFieldValidation(input, feedback);
      return true;
    }

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

    ensureFeedbackId(input, feedback);
    input.classList.toggle('is-invalid', Boolean(error));
    input.classList.toggle('is-valid', !error);
    if (error) {
      input.setAttribute('aria-invalid', 'true');
    } else {
      input.removeAttribute('aria-invalid');
    }
    if (feedback) feedback.textContent = error;
    return !error;
  }

  function validationRulesFor(input) {
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
      const fields = Array.from(form.querySelectorAll('[data-validate]'));

      fields.forEach(input => {
        const onFieldEdit = () => {
          if (form.classList.contains('was-validated') || input.classList.contains('is-invalid')) {
            validateField(input, validationRulesFor(input));
          }
        };

        input.addEventListener('blur', () => {
          const showRequired = form.classList.contains('was-validated') || input.classList.contains('is-invalid');
          validateField(input, validationRulesFor(input), { showRequired });
        });
        input.addEventListener('input', onFieldEdit);
        input.addEventListener('change', onFieldEdit);
      });

      form.addEventListener('submit', event => {
        if (form.dataset.submitting === 'true') {
          event.preventDefault();
          return;
        }

        form.classList.add('was-validated');
        const valid = fields.map(input => validateField(input, validationRulesFor(input))).every(Boolean);
        if (!valid) {
          event.preventDefault();
          form.querySelector('.is-invalid')?.focus();
          return;
        }
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

  function initLogoutConfirmation() {
    const modal = document.querySelector('[data-logout-modal]');
    const triggers = document.querySelectorAll('[data-confirm-logout]');
    if (!modal || !triggers.length || modal.dataset.logoutInitialized === 'true') return;
    modal.dataset.logoutInitialized = 'true';

    const dialog = modal.querySelector('.logout-modal-dialog');
    const closeButtons = modal.querySelectorAll('[data-logout-cancel]');
    let lastFocused = null;

    const closeModal = () => {
      modal.hidden = true;
      document.body.classList.remove('modal-open');
      if (lastFocused) lastFocused.focus();
    };
    const onKeydown = event => {
      if (!modal.hidden && event.key === 'Escape') closeModal();
    };

    triggers.forEach(trigger => {
      trigger.addEventListener('click', event => {
        event.preventDefault();
        lastFocused = document.activeElement;
        modal.hidden = false;
        document.body.classList.add('modal-open');
        (modal.querySelector('.logout-modal-close') || modal.querySelector('.logout-modal-button') || dialog)?.focus();
      });
    });
    closeButtons.forEach(button => button.addEventListener('click', closeModal));
    document.addEventListener('keydown', onKeydown);
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
    initValidatedForms();
    initOtpResendCountdown();
    initEmailDispatch();
    initLogoutConfirmation();
    initPasswordToggles();
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
