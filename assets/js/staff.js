/**
 * SmartQMS staff actions, status feedback, timeout checks, and theme behavior.
 */
(function () {
  'use strict';

  const timers = new Set();
  let queueUnsubscribe = null;
  let refreshTimer = null;
  let pageRefreshInFlight = false;
  let staffRefreshPending = false;
  let staffRefreshPromise = null;
  let staffActionInFlight = false;
  let staffActionVersion = 0;
  let staffLastUpdated = '';
  let arrivalScannerController = null;
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
    const statusToast = document.querySelector('[data-staff-status-toast]');
    const statusMessage = statusToast?.querySelector('[data-staff-status-message]');
    const statusIcon = statusToast?.querySelector('[data-staff-status-icon]');
    const clearStatus = statusToast?.querySelector('[data-staff-status-clear]');
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

    const showStatus = (message, type = 'info') => {
      if (!statusToast || !statusMessage || !window.bootstrap) return;
      statusMessage.textContent = message;
      statusToast.classList.remove('is-success', 'is-danger', 'is-warning', 'is-info');
      statusToast.classList.add(`is-${type}`);
      statusToast.setAttribute('role', type === 'danger' ? 'alert' : 'status');
      statusToast.setAttribute('aria-live', type === 'danger' ? 'assertive' : 'polite');
      if (statusIcon) {
        statusIcon.setAttribute('data-lucide', type === 'success'
          ? 'circle-check'
          : (type === 'danger' ? 'circle-alert' : (type === 'warning' ? 'triangle-alert' : 'info')));
      }
      window.lucide?.createIcons?.();
      window.bootstrap.Toast.getOrCreateInstance(statusToast, { delay: 5000 }).show();
    };
    const hideStatus = () => {
      if (!statusToast || !statusMessage || !window.bootstrap) return;
      window.bootstrap.Toast.getOrCreateInstance(statusToast, { delay: 5000 }).hide();
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
        activeButton.dataset.hadAriaLabel = String(activeButton.hasAttribute('aria-label'));
        activeButton.dataset.originalAriaLabel = activeButton.getAttribute('aria-label') || '';
        activeButton.dataset.hadTitle = String(activeButton.hasAttribute('title'));
        activeButton.dataset.originalTitle = activeButton.getAttribute('title') || '';
        const loadingLabel = activeButton.dataset.loadingText || 'Please wait...';
        activeButton.setAttribute('aria-label', loadingLabel);
        activeButton.setAttribute('title', loadingLabel);
        activeButton.setAttribute('aria-busy', 'true');
        activeButton.classList.add('is-loading');
      } else {
        if (activeButton.dataset.hadAriaLabel === 'true') {
          activeButton.setAttribute('aria-label', activeButton.dataset.originalAriaLabel || '');
        } else {
          activeButton.removeAttribute('aria-label');
        }
        if (activeButton.dataset.hadTitle === 'true') {
          activeButton.setAttribute('title', activeButton.dataset.originalTitle || '');
        } else {
          activeButton.removeAttribute('title');
        }
        activeButton.removeAttribute('aria-busy');
        activeButton.classList.remove('is-loading');
        delete activeButton.dataset.hadAriaLabel;
        delete activeButton.dataset.originalAriaLabel;
        delete activeButton.dataset.hadTitle;
        delete activeButton.dataset.originalTitle;
      }
    };

    const flash = flashConsume();
    if (flash?.message) showStatus(flash.message, flash.type || 'success');
    if (clearStatus && clearStatus.dataset.staffClearInitialized !== 'true') {
      clearStatus.dataset.staffClearInitialized = 'true';
      clearStatus.addEventListener('click', () => {
        window.setTimeout(() => { statusMessage.textContent = ''; }, 150);
      });
    }

    const runAction = async button => {
        if (requestInFlight || button.disabled) return;
        const url = button.dataset.actionUrl || '';
        if (!url) return;

        requestInFlight = true;
        staffActionInFlight = true;
        staffActionVersion += 1;
        hideStatus();
        confirmationModal?.hide();
        if (confirmationSubmit) confirmationSubmit.disabled = true;
        setActionsBusy(true, button);
        try {
          const adapter = window.SmartQmsData;
          const actionKey = button.dataset.providerAction || '';
          let payload;
          if (adapter && actionKey && typeof adapter[actionKey] === 'function') {
            const argument = actionKey === 'setCounterStatus'
              ? button.dataset.status
              : (button.dataset.ticketId || button.dataset.counterId || '');
            payload = await adapter[actionKey](argument);
          } else {
            const body = new FormData();
            if (button.dataset.ticketId) body.append('ticket_id', button.dataset.ticketId);
            if (button.dataset.status) body.append('status', button.dataset.status);
            const token = csrfToken();
            const response = await fetch(url, {
              method: 'POST',
              body,
              credentials: 'same-origin',
              headers: token ? { 'X-CSRF-Token': token } : {}
            });
            try {
              payload = await response.json();
            } catch (error) {
              throw new Error('SmartQMS returned an unexpected response. Refresh the page and try again.');
            }
            if (!response.ok || !payload.success) {
              throw new Error(payload.error || 'The action could not be completed.');
            }
          }
          // The action has succeeded. A failed refresh must not invite resubmission.
          try {
            await refreshStaffContent(shell, true);
          } catch (_) {
            shell.staffShowStatus?.('Action completed. Queue updates are unavailable; refresh before taking another action.', 'warning');
            return;
          }
          shell.staffShowStatus?.(button.dataset.actionSuccess || 'Action completed.', 'success', true);
        } catch (error) {
          showStatus(error.message || 'The action could not be completed. Please try again.', 'danger', true);
          setActionsBusy(false, button);
          requestInFlight = false;
          if (confirmationSubmit) confirmationSubmit.disabled = false;
        } finally {
          staffActionInFlight = false;
          requestInFlight = false;
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

    if (confirmationElement?.staffConfirmClick) confirmationSubmit?.removeEventListener('click', confirmationElement.staffConfirmClick);
    if (confirmationElement?.staffConfirmHidden) confirmationElement.removeEventListener('hidden.bs.modal', confirmationElement.staffConfirmHidden);
    const confirmClick = () => {
      if (!pendingConfirmationButton || requestInFlight) return;
      runAction(pendingConfirmationButton);
    };
    const confirmHidden = () => {
      const returnTarget = pendingConfirmationButton;
      pendingConfirmationButton = null;
      if (!requestInFlight && returnTarget instanceof HTMLElement) returnTarget.focus();
    };
    confirmationSubmit?.addEventListener('click', confirmClick);
    confirmationElement?.addEventListener('hidden.bs.modal', confirmHidden);
    if (confirmationElement) {
      confirmationElement.staffConfirmClick = confirmClick;
      confirmationElement.staffConfirmHidden = confirmHidden;
    }

    shell.staffShowStatus = showStatus;
  }

  function staffUpdateStatus(message, stale = false) {
    const status = document.querySelector('[data-staff-refresh-status]');
    if (!status) return;
    if (!staffLastUpdated) staffLastUpdated = status.textContent;
    const text = stale ? `${message} ${staffLastUpdated}` : message;
    if (status.textContent !== text) status.textContent = text;
    status.classList.toggle('is-stale', stale);
  }

  function staffIsInteracting(current, allowedFocus = null) {
    const active = document.activeElement;
    return Boolean(document.querySelector('.modal.show, .offcanvas.show')
      || current?.querySelector('form[data-user-editing], details[open]')
      || (active && current?.contains(active) && active !== current && active !== allowedFocus));
  }

  async function refreshStaffContent(shell, afterAction = false) {
    if (pageRefreshInFlight) {
      staffRefreshPending = true;
      if (!afterAction) return;
      await staffRefreshPromise.catch(() => {});
      return refreshStaffContent(shell, true);
    }
    staffRefreshPromise = performStaffRefresh(shell, afterAction);
    return staffRefreshPromise;
  }

  async function performStaffRefresh(shell, afterAction) {
    if (document.hidden || (!afterAction && staffActionInFlight)) { staffRefreshPending = true; return; }
    const current = document.querySelector('#staff-main');
    const previousFocus = document.activeElement;
    const allowedFocus = afterAction && previousFocus?.hasAttribute?.('data-staff-action') ? previousFocus : null;
    if (staffIsInteracting(current, allowedFocus)) {
      staffRefreshPending = true;
      staffUpdateStatus('New information is ready. Updates resume when you finish interacting.');
      return;
    }
    const actionVersion = staffActionVersion;
    pageRefreshInFlight = true;
    try {
      const response = await fetch(window.location.href, {
        credentials: 'same-origin',
        headers: { 'X-SmartQMS-Partial': 'main' }
      });
      if (!response.ok) throw new Error('Staff workspace could not be refreshed.');
      const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
      const incoming = parsed.querySelector('#staff-main');
      if (!incoming || !current) throw new Error('Staff workspace returned an invalid response.');
      if (!afterAction && (staffActionInFlight || actionVersion !== staffActionVersion)) {
        staffRefreshPending = true;
        return;
      }
      // Recheck after the request: the operator may have started typing meanwhile.
      if (staffIsInteracting(current, allowedFocus)) {
        staffRefreshPending = true;
        staffUpdateStatus('New information is ready. Updates resume when you finish interacting.');
        return;
      }
      const focusBelongedToMain = previousFocus && current.contains(previousFocus);
      const focusAction = previousFocus?.dataset?.actionUrl;
      const focusTicket = previousFocus?.dataset?.ticketId;
      clearStaffTimers();
      current.innerHTML = incoming.innerHTML;
      staffRefreshPending = false;
      delete shell.dataset.actionsInitialized;
      initStaffActions(shell);
      initVoidCountdown(shell);
      initWalkInForm(shell);
      initArrivalCheckIn(shell);
      window.lucide?.createIcons?.();
      staffLastUpdated = `Last updated ${new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(new Date())}.`;
      staffUpdateStatus(staffLastUpdated);
      if (afterAction && focusBelongedToMain) {
        const replacement = Array.from(current.querySelectorAll('[data-staff-action]')).find(button => !button.disabled && button.dataset.actionUrl === focusAction && button.dataset.ticketId === focusTicket);
        (replacement || current).focus({ preventScroll: true });
      }
    } catch (error) {
      staffRefreshPending = true;
      staffUpdateStatus('Updates unavailable. Showing the last received queue; retrying automatically.', true);
      throw error;
    } finally {
      pageRefreshInFlight = false;
    }
  }

  function initWalkInForm(shell) {
    const form = shell.querySelector('[data-staff-walk-in-form]');
    if (!form || form.dataset.walkInInitialized === 'true') return;
    form.dataset.walkInInitialized = 'true';
    form.addEventListener('submit', async event => {
      if (!form.checkValidity()) return;
      event.preventDefault();
      if (form.dataset.submitting === 'true') return;
      form.dataset.submitting = 'true';
      const submit = form.querySelector('[type="submit"]');
      const original = submit?.textContent || '';
      if (submit) {
        submit.disabled = true;
        submit.textContent = submit.dataset.loadingText || 'Adding ticket...';
      }
      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          credentials: 'same-origin',
          headers: { Accept: 'application/json' }
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.error || 'The walk-in ticket could not be created.');
        if (shell.querySelector('[data-staff-arrival]')) {
          renderArrivalTicket(shell, payload.data || {});
          form.reset();
          form.dataset.submitting = 'false';
          if (submit) {
            submit.disabled = false;
            submit.textContent = original;
          }
          shell.staffShowStatus?.(`Walk-in ticket ${payload.data?.ticket_number || ''} added to the Active Waiting Queue.`, 'success');
        } else {
          await refreshStaffContent(shell);
          shell.staffShowStatus?.(`Walk-in ticket ${payload.data?.ticket_number || ''} added to the queue.`, 'success', true);
        }
      } catch (error) {
        shell.staffShowStatus?.(error.message || 'The walk-in ticket could not be created.', 'danger', true);
        form.dataset.submitting = 'false';
        if (submit) {
          submit.disabled = false;
          submit.textContent = original;
        }
      }
    });
  }

  function formatArrivalValue(value, fallback = '—') {
    const normalized = String(value ?? '').trim();
    return normalized || fallback;
  }

  function formatArrivalDate(value) {
    const normalized = String(value ?? '').trim();
    if (!normalized) return '—';
    const parsed = new Date(normalized.replace(' ', 'T'));
    return Number.isNaN(parsed.getTime())
      ? normalized
      : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(parsed);
  }

  function formatArrivalVisitDate(value) {
    const normalized = String(value ?? '').trim();
    if (!normalized) return '—';
    const parsed = new Date(`${normalized}T00:00:00`);
    return Number.isNaN(parsed.getTime())
      ? normalized
      : new Intl.DateTimeFormat(undefined, { dateStyle: 'long' }).format(parsed);
  }

  function renderArrivalTicket(shell, ticket) {
    const modalElement = shell.querySelector('[data-arrival-ticket-modal]');
    if (!modalElement || !window.bootstrap) return;
    modalElement.querySelectorAll('[data-arrival-ticket]').forEach(element => {
      const field = element.dataset.arrivalTicket;
      element.textContent = formatArrivalValue(ticket[field]);
    });
    window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
  }

  function stopArrivalCamera() {
    if (!arrivalScannerController) return Promise.resolve();
    return arrivalScannerController.stop().catch(() => {});
  }

  function initArrivalCheckIn(shell) {
    const root = shell.querySelector('[data-staff-arrival]');
    if (!root || root.dataset.arrivalInitialized === 'true') return;
    root.dataset.arrivalInitialized = 'true';

    const lookupUrl = root.dataset.arrivalLookupUrl || '';
    const confirmUrl = root.dataset.arrivalConfirmUrl || '';
    const referenceForm = root.querySelector('[data-arrival-reference-form]');
    const result = shell.querySelector('[data-arrival-lookup-result]');
    const clearButton = result?.querySelector('[data-arrival-clear]');
    const openConfirm = result?.querySelector('[data-arrival-open-confirm]');
    const readiness = result?.querySelector('[data-arrival-readiness]');
    const confirmElement = shell.querySelector('[data-arrival-confirm-modal]');
    const confirmModal = confirmElement && window.bootstrap
      ? window.bootstrap.Modal.getOrCreateInstance(confirmElement)
      : null;
    const confirmSubmit = confirmElement?.querySelector('[data-arrival-confirm-submit]');
    const confirmName = confirmElement?.querySelector('[data-arrival-confirm-name]');
    const cameraFrame = root.querySelector('[data-arrival-camera-frame]');
    const cameraVideo = root.querySelector('[data-arrival-camera]');
    const cameraStart = root.querySelector('[data-arrival-camera-start]');
    const cameraStop = root.querySelector('[data-arrival-camera-stop]');
    const cameraStatus = root.querySelector('[data-arrival-camera-status]');
    const cameraSelectWrap = root.querySelector('[data-arrival-camera-select-wrap]');
    const cameraSelect = root.querySelector('[data-arrival-camera-select]');
    let selectedTicket = null;
    let selectedMethod = 'reference';
    let lookupInFlight = false;
    let confirmInFlight = false;
    let cameraSwitchInFlight = false;

    const setCameraStatus = message => {
      if (cameraStatus) cameraStatus.textContent = message;
    };
    const renderCameraOptions = (cameras, selectedId) => {
      if (!cameraSelect || !cameraSelectWrap) return;
      cameraSelect.replaceChildren();
      cameras.forEach((camera, index) => {
        const option = document.createElement('option');
        option.value = camera.id;
        option.textContent = camera.name || `Camera ${index + 1}`;
        option.selected = camera.id === selectedId;
        cameraSelect.append(option);
      });
      cameraSelectWrap.hidden = cameras.length < 2;
    };
    const resetLookup = () => {
      selectedTicket = null;
      if (result) result.hidden = true;
      if (openConfirm) openConfirm.disabled = true;
      if (readiness) readiness.hidden = true;
    };
    const arrivalReadiness = ticket => {
      if (ticket.already_checked_in) {
        return { message: `Ticket ${formatArrivalValue(ticket.ticket_number)} is already in the Active Waiting Queue.`, tone: 'info' };
      }
      if (ticket.is_expired) {
        return { message: 'This Scheduled appointment has expired and cannot be checked in.', tone: 'warning' };
      }
      if (!ticket.is_for_today) {
        return {
          message: `This reservation is for ${formatArrivalVisitDate(ticket.visit_date)}. Check it in on the reserved date.`,
          tone: 'warning'
        };
      }
      return { message: 'Appointment ready. Confirm the client to add them to the Active Waiting Queue.', tone: 'success' };
    };
    const renderLookup = (ticket, method) => {
      selectedTicket = ticket;
      selectedMethod = method;
      if (!result) return;
      result.querySelectorAll('[data-arrival-detail]').forEach(element => {
        const field = element.dataset.arrivalDetail;
        let value = ticket[field];
        if (field === 'status') {
          value = ticket.already_checked_in ? 'Already Waiting' : (ticket.is_expired ? 'Expired' : 'Scheduled');
        }
        if (field === 'visit_date') value = formatArrivalVisitDate(value);
        if (field === 'scheduled_expires_at') value = formatArrivalDate(value);
        element.textContent = formatArrivalValue(value);
      });
      const state = arrivalReadiness(ticket);
      if (readiness) {
        readiness.className = `alert alert-${state.tone === 'success' ? 'success' : state.tone} mb-3`;
        readiness.textContent = state.message;
        readiness.hidden = false;
      }
      result.hidden = false;
      if (openConfirm) {
        openConfirm.disabled = !ticket.can_check_in;
        openConfirm.textContent = ticket.already_checked_in
          ? 'Already Checked In'
          : (ticket.is_expired ? 'Appointment Expired' : (!ticket.is_for_today ? 'Available on Visit Date' : 'Check In Client'));
      }
      result.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'nearest' });
    };

    const runLookup = async (lookupType, lookupValue, method) => {
      if (lookupInFlight || !lookupUrl) return;
      lookupInFlight = true;
      resetLookup();
      try {
        const body = new FormData();
        body.append('lookup_type', lookupType);
        body.append('lookup_value', lookupValue);
        const token = csrfToken();
        const response = await fetch(lookupUrl, {
          method: 'POST',
          body,
          credentials: 'same-origin',
          headers: token ? { 'X-CSRF-Token': token, Accept: 'application/json' } : { Accept: 'application/json' }
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.error || 'The appointment could not be found.');
        renderLookup(payload.data || {}, method);
        const state = arrivalReadiness(payload.data || {});
        shell.staffShowStatus?.(
          state.message,
          state.tone
        );
      } catch (error) {
        shell.staffShowStatus?.(error.message || 'The appointment could not be found.', 'danger', true);
      } finally {
        lookupInFlight = false;
      }
    };

    referenceForm?.addEventListener('submit', event => {
      if (!referenceForm.checkValidity()) return;
      event.preventDefault();
      const value = referenceForm.querySelector('[name="lookup_value"]')?.value || '';
      runLookup('reference', value, 'reference');
    });
    clearButton?.addEventListener('click', resetLookup);
    openConfirm?.addEventListener('click', () => {
      if (!selectedTicket?.can_check_in || !confirmModal) return;
      if (confirmName) {
        confirmName.textContent = `${formatArrivalValue(selectedTicket.client_name, 'Client')} — ${formatArrivalValue(selectedTicket.service_name)}`;
      }
      confirmSubmit.disabled = false;
      confirmModal.show();
    });
    confirmSubmit?.addEventListener('click', async () => {
      if (confirmInFlight || !selectedTicket?.ticket_id || !confirmUrl) return;
      confirmInFlight = true;
      const original = confirmSubmit.textContent;
      confirmSubmit.disabled = true;
      confirmSubmit.textContent = confirmSubmit.dataset.loadingText || 'Checking in...';
      try {
        const body = new FormData();
        body.append('ticket_id', selectedTicket.ticket_id);
        body.append('check_in_method', selectedMethod);
        const token = csrfToken();
        const response = await fetch(confirmUrl, {
          method: 'POST',
          body,
          credentials: 'same-origin',
          headers: token ? { 'X-CSRF-Token': token, Accept: 'application/json' } : { Accept: 'application/json' }
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.error || 'The client could not be checked in.');
        confirmModal.hide();
        resetLookup();
        referenceForm?.reset();
        renderArrivalTicket(shell, payload.data || {});
        shell.staffShowStatus?.(
          payload.data?.status === 'already_checked_in'
            ? `Ticket ${payload.data?.ticket_number || ''} was already checked in.`
            : `Ticket ${payload.data?.ticket_number || ''} joined the FIFO queue.`,
          'success'
        );
      } catch (error) {
        shell.staffShowStatus?.(error.message || 'The client could not be checked in.', 'danger', true);
      } finally {
        confirmInFlight = false;
        confirmSubmit.disabled = false;
        confirmSubmit.textContent = original;
      }
    });

    const stopCameraUi = () => {
      void stopArrivalCamera();
      if (cameraFrame) cameraFrame.hidden = true;
      if (cameraStart) cameraStart.hidden = false;
      if (cameraStop) cameraStop.hidden = true;
    };
    cameraStop?.addEventListener('click', () => {
      stopCameraUi();
      setCameraStatus('Camera stopped. Manual Input remains available.');
    });

    const scannerOptions = {
      video: cameraVideo,
      expectedOrigin: window.location.origin,
      onCameras: renderCameraOptions,
      onFallback: message => setCameraStatus(message),
      onInvalid: message => {
        setCameraStatus(message);
        shell.staffShowStatus?.(message, 'warning');
      },
      onScan: async parsed => {
        stopCameraUi();
        setCameraStatus('QR code read successfully. Review the appointment details below.');
        await runLookup(parsed.lookup_type, parsed.lookup_value, 'qr');
      },
    };

    const showManualScannerFallback = message => {
      stopCameraUi();
      if (cameraSelectWrap) cameraSelectWrap.hidden = true;
      setCameraStatus(message);
      shell.staffShowStatus?.(message, 'warning');
    };

    const startScanner = async (cameraId = '') => {
      if (!window.SmartQmsArrivalScanner?.create || !cameraVideo) {
        showManualScannerFallback('QR camera scanning is unavailable. Use Manual Input with the appointment reference.');
        return;
      }
      if (!arrivalScannerController) {
        arrivalScannerController = window.SmartQmsArrivalScanner.create(scannerOptions);
      }
      cameraStart.disabled = true;
      try {
        const active = await arrivalScannerController.start(cameraId);
        cameraFrame.hidden = false;
        cameraStart.hidden = true;
        cameraStop.hidden = false;
        const engineLabel = active.engine === 'instascan' ? 'Instascan' : 'browser scanner';
        setCameraStatus(`${engineLabel} active. Hold the SmartQMS appointment QR inside the guide.`);
      } catch (error) {
        showManualScannerFallback('Camera access is unavailable. Use Manual Input with the appointment reference.');
      } finally {
        cameraStart.disabled = false;
      }
    };

    cameraStart?.addEventListener('click', async () => {
      await startScanner(cameraSelect?.value || '');
    });

    cameraSelect?.addEventListener('change', async () => {
      if (!arrivalScannerController || cameraSwitchInFlight || cameraFrame?.hidden) return;
      cameraSwitchInFlight = true;
      cameraSelect.disabled = true;
      try {
        const active = await arrivalScannerController.switchCamera(cameraSelect.value);
        setCameraStatus(`${active.engine === 'instascan' ? 'Instascan' : 'Browser scanner'} switched to ${cameraSelect.selectedOptions[0]?.textContent || 'the selected camera'}.`);
      } catch (error) {
        showManualScannerFallback('The selected camera could not start. Use Manual Input or try another camera.');
      } finally {
        cameraSwitchInFlight = false;
        cameraSelect.disabled = false;
      }
    });

    document.addEventListener('visibilitychange', () => {
      if (!document.hidden || cameraFrame?.hidden) return;
      stopCameraUi();
      setCameraStatus('Camera paused because this page is no longer visible. Select Start Camera to resume.');
    });

    shell.querySelector('[data-arrival-print]')?.addEventListener('click', () => {
      document.body.classList.add('is-arrival-print');
      window.print();
      window.setTimeout(() => document.body.classList.remove('is-arrival-print'), 50);
    });
    root.staffStopArrivalCamera = stopCameraUi;
  }

  function scheduleStaffRefresh(shell) {
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(() => {
      refreshStaffContent(shell).catch(error => {
        shell.staffShowStatus?.(error.message || 'Live queue update failed.', 'danger');
      });
    }, 180);
  }

  function initStaffRealtime(shell) {
    if (queueUnsubscribe || document.body.dataset.staffLiveRefresh !== 'true') return;
    const subscribe = window.SmartQmsData?.subscribeToQueue;
    if (typeof subscribe !== 'function') return;
    queueUnsubscribe = subscribe(
      document.body.dataset.queueBranchId || '',
      (payload, error, metadata = {}) => {
        if (error || !payload?.success) {
          staffRefreshPending = true;
          staffUpdateStatus('Updates unavailable. Showing the last received queue; retrying automatically.', true);
          return;
        }
        if (metadata.source === 'initial' && !staffRefreshPending) return;
        scheduleStaffRefresh(shell);
      },
      { interval: 10000 }
    );
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
          await refreshStaffContent(shell);
          shell.staffShowStatus?.(
            'The called ticket was voided after the configured response time expired.',
            'warning',
            true
          );
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
    window.clearTimeout(refreshTimer);
  }

  function resumeStaffTimers(event) {
    if (!event.persisted) return;
    document.querySelector('[data-void-countdown]')?.smartQmsVoidResume?.();
  }

  function initStaffPage() {
    const shell = document.querySelector('[data-staff-shell]');
    if (!shell || shell.dataset.staffInitialized === 'true') return;
    shell.dataset.staffInitialized = 'true';
    shell.addEventListener('input', event => {
      const form = event.target.closest?.('form');
      if (form) form.dataset.userEditing = 'true';
    });
    shell.addEventListener('reset', event => {
      if (event.target.dataset) delete event.target.dataset.userEditing;
    });
    const resumePending = () => {
      if (staffRefreshPending) scheduleStaffRefresh(shell);
    };
    shell.addEventListener('focusout', resumePending);
    shell.addEventListener('toggle', resumePending, true);
    document.addEventListener('hidden.bs.modal', resumePending);
    document.addEventListener('hidden.bs.offcanvas', resumePending);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) resumePending(); });
    const workspaceClock = document.querySelector('[data-staff-workspace-clock]');
    if (workspaceClock) {
      const updateWorkspaceClock = () => {
        const now = new Date();
        workspaceClock.dateTime = now.toISOString();
        workspaceClock.textContent = new Intl.DateTimeFormat(undefined, {
          weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'
        }).format(now);
      };
      updateWorkspaceClock();
      timers.add(window.setInterval(updateWorkspaceClock, 30000));
    }
    initStaffActions(shell);
    initVoidCountdown(shell);
    initWalkInForm(shell);
    initArrivalCheckIn(shell);
    initStaffRealtime(shell);
  }

  document.addEventListener('DOMContentLoaded', initStaffPage);
  window.addEventListener('pagehide', () => {
    clearStaffTimers();
    stopArrivalCamera();
    queueUnsubscribe?.();
    queueUnsubscribe = null;
  });
  window.addEventListener('pageshow', resumeStaffTimers);
})();
