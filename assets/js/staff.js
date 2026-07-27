/**
 * SmartQMS staff actions, status feedback, timeout checks, and theme behavior.
 */
(function () {
  'use strict';

  const timers = new Set();
  const shared = () => window.SmartQms || {};
  const csrfToken = () => shared().csrfTokenFromPage ? shared().csrfTokenFromPage() : '';

  function flashStore(message, type = 'success') {
    try {
      window.sessionStorage.setItem('smartqms-staff-flash', JSON.stringify({ message, type }));
    } catch (error) {
      // Storage can be unavailable in privacy-restricted sessions.
    }
  }

  function flashConsume() {
    try {
      const stored = window.sessionStorage.getItem('smartqms-staff-flash');
      window.sessionStorage.removeItem('smartqms-staff-flash');
      return stored ? JSON.parse(stored) : null;
    } catch (error) {
      return null;
    }
  }

  function initStaffActions(shell) {
    if (shell.dataset.actionsInitialized === 'true') return;
    shell.dataset.actionsInitialized = 'true';
    const statusRegion = shell.querySelector('[data-staff-status]');
    const statusMessage = shell.querySelector('[data-staff-status-message]');
    const clearStatus = shell.querySelector('[data-staff-status-clear]');
    const actionButtons = Array.from(shell.querySelectorAll('[data-staff-action]'));
    const confirmationElement = document.querySelector('[data-staff-confirm-modal]');
    const confirmationModal = confirmationElement && window.bootstrap
      ? window.bootstrap.Modal.getOrCreateInstance(confirmationElement)
      : null;
    const confirmationTitle = confirmationElement?.querySelector('[data-staff-confirm-modal-title]');
    const confirmationMessage = confirmationElement?.querySelector('[data-staff-confirm-modal-message]');
    const confirmationSubmit = confirmationElement?.querySelector('[data-staff-confirm-modal-submit]');
    const confirmationSubmitLabel = confirmationElement?.querySelector('[data-staff-confirm-modal-submit-label]');
    let pendingConfirmationButton = null;
    let requestInFlight = false;

    const showStatus = (message, type = 'info', focus = false) => {
      if (!statusRegion || !statusMessage) return;
      statusMessage.textContent = message;
      statusRegion.hidden = false;
      statusRegion.classList.remove('is-success', 'is-danger', 'is-warning', 'is-info');
      statusRegion.classList.add(`is-${type}`);
      statusRegion.setAttribute('role', type === 'danger' ? 'alert' : 'status');
      if (focus) statusRegion.focus();
    };
    const hideStatus = () => {
      if (!statusRegion || !statusMessage) return;
      statusRegion.hidden = true;
      statusMessage.textContent = '';
    };
    const setActionsBusy = (busy, activeButton = null) => {
      actionButtons.forEach(button => {
        if (busy) {
          button.dataset.wasDisabled = String(button.disabled);
          button.disabled = true;
        } else {
          button.disabled = button.dataset.wasDisabled === 'true';
          delete button.dataset.wasDisabled;
        }
      });

      if (!activeButton) return;
      if (busy) {
        activeButton.dataset.originalText = activeButton.innerHTML;
        activeButton.textContent = activeButton.dataset.loadingText || 'Please wait...';
        activeButton.setAttribute('aria-busy', 'true');
      } else {
        if (activeButton.dataset.originalText) activeButton.innerHTML = activeButton.dataset.originalText;
        activeButton.removeAttribute('aria-busy');
        delete activeButton.dataset.originalText;
      }
    };

    const flash = flashConsume();
    if (flash?.message) showStatus(flash.message, flash.type || 'success');
    clearStatus?.addEventListener('click', hideStatus);

    const runAction = async button => {
        if (requestInFlight || button.disabled) return;
        const url = button.dataset.actionUrl || '';
        if (!url) return;

        requestInFlight = true;
        hideStatus();
        confirmationModal?.hide();
        if (confirmationSubmit) confirmationSubmit.disabled = true;
        setActionsBusy(true, button);
        const body = new FormData();
        if (button.dataset.ticketId) body.append('ticket_id', button.dataset.ticketId);
        if (button.dataset.status) body.append('status', button.dataset.status);
        const token = csrfToken();

        try {
          const response = await fetch(url, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: token ? { 'X-CSRF-Token': token } : {}
          });
          let payload;
          try {
            payload = await response.json();
          } catch (error) {
            throw new Error('SmartQMS returned an unexpected response. Refresh the page and try again.');
          }
          if (!response.ok || !payload.success) {
            throw new Error(payload.error || 'The action could not be completed.');
          }
          flashStore(button.dataset.actionSuccess || 'Action completed.', 'success');
          window.location.reload();
        } catch (error) {
          showStatus(error.message || 'The action could not be completed. Please try again.', 'danger', true);
          setActionsBusy(false, button);
          requestInFlight = false;
          if (confirmationSubmit) confirmationSubmit.disabled = false;
        }
    };

    actionButtons.forEach(button => {
      button.addEventListener('click', () => {
        if (button.hasAttribute('data-staff-confirm') && confirmationModal) {
          pendingConfirmationButton = button;
          if (confirmationTitle) confirmationTitle.textContent = button.dataset.staffConfirmTitle || 'Confirm ticket action';
          if (confirmationMessage) confirmationMessage.textContent = button.dataset.staffConfirmMessage || 'Continue with this ticket action?';
          if (confirmationSubmitLabel) confirmationSubmitLabel.textContent = button.dataset.staffConfirmLabel || 'Confirm';
          if (confirmationSubmit) {
            confirmationSubmit.disabled = false;
            confirmationSubmit.classList.toggle('is-danger', button.dataset.staffConfirmTone === 'danger');
            confirmationSubmit.classList.toggle('is-warning', button.dataset.staffConfirmTone === 'warning');
          }
          confirmationModal.show();
          return;
        }
        runAction(button);
      });
    });

    confirmationSubmit?.addEventListener('click', () => {
      if (!pendingConfirmationButton || requestInFlight) return;
      runAction(pendingConfirmationButton);
    });
    confirmationElement?.addEventListener('hidden.bs.modal', () => {
      const returnTarget = pendingConfirmationButton;
      pendingConfirmationButton = null;
      if (!requestInFlight && returnTarget instanceof HTMLElement) returnTarget.focus();
    });

    shell.staffShowStatus = showStatus;
  }

  function initVoidCountdown(shell) {
    const countdown = shell.querySelector('[data-void-countdown]');
    if (!countdown || countdown.dataset.voidInitialized === 'true') return;
    countdown.dataset.voidInitialized = 'true';
    const voidUrl = countdown.dataset.voidUrl || '';
    let remaining = Math.max(0, Number(countdown.dataset.remainingSeconds || 0));
    const expiresAt = Date.now() + (remaining * 1000);
    let requestInFlight = false;
    let zeroCheckStarted = false;
    if (!voidUrl) return;

    const showStatus = (message, type = 'warning') => {
      if (typeof shell.staffShowStatus === 'function') shell.staffShowStatus(message, type);
    };
    const render = () => {
      remaining = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
      const minutes = Math.floor(remaining / 60);
      const seconds = remaining % 60;
      countdown.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
      countdown.classList.toggle('is-expired', remaining <= 0);
      countdown.closest('[data-void-timer]')?.classList.toggle('is-expired', remaining <= 0);
    };
    const checkForVoid = async (fromExpiry = false) => {
      if (requestInFlight) return;
      requestInFlight = true;
      const token = csrfToken();
      try {
        const response = await fetch(voidUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: token ? { 'X-CSRF-Token': token } : {}
        });
        let payload;
        try {
          payload = await response.json();
        } catch (error) {
          throw new Error('Timeout check returned an unexpected response.');
        }
        if (!response.ok || !payload.success) throw new Error(payload.error || 'Timeout check failed.');
        const voided = Number(payload.voided ?? payload.data?.voided ?? 0);
        if (voided > 0) {
          flashStore('The called ticket was voided after the configured response time expired.', 'warning');
          window.location.reload();
          return;
        }
        if (fromExpiry) showStatus('Response time has expired. SmartQMS is confirming the ticket status.', 'warning');
      } catch (error) {
        if (fromExpiry) showStatus('The timeout check could not complete. SmartQMS will retry automatically.', 'danger');
      } finally {
        requestInFlight = false;
      }
    };

    const resume = () => {
      render();
      if (remaining <= 0 && !zeroCheckStarted) {
        zeroCheckStarted = true;
        checkForVoid(true);
      }
      const countdownTimer = window.setInterval(() => {
        render();
        if (remaining <= 0 && !zeroCheckStarted) {
          zeroCheckStarted = true;
          checkForVoid(true);
        }
      }, 1000);
      const checkerTimer = window.setInterval(() => checkForVoid(remaining <= 0), 30000);
      timers.add(countdownTimer);
      timers.add(checkerTimer);
    };
    countdown.smartQmsVoidResume = resume;
    resume();
  }

  function clearStaffTimers() {
    timers.forEach(timer => window.clearInterval(timer));
    timers.clear();
  }

  function resumeStaffTimers(event) {
    if (!event.persisted) return;
    document.querySelector('[data-void-countdown]')?.smartQmsVoidResume?.();
  }

  function initStaffPage() {
    const shell = document.querySelector('[data-staff-shell]');
    if (!shell || shell.dataset.staffInitialized === 'true') return;
    shell.dataset.staffInitialized = 'true';
    initStaffActions(shell);
    initVoidCountdown(shell);
  }

  document.addEventListener('DOMContentLoaded', initStaffPage);
  window.addEventListener('pagehide', clearStaffTimers);
  window.addEventListener('pageshow', resumeStaffTimers);
})();
