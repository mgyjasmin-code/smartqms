/**
 * SmartQMS client queue, notification, feedback, and ticket behavior.
 */
(function () {
  'use strict';

  const timers = new Set();
  const controllers = new Set();
  const subscriptions = new Set();
  const shared = () => window.SmartQms || {};
  const escapeHtml = value => shared().escapeHtml
    ? shared().escapeHtml(value)
    : String(value ?? '').replace(/[&<>"']/g, character => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[character]));
  const csrfToken = () => shared().csrfTokenFromPage ? shared().csrfTokenFromPage() : '';

  function initServicePrediction(root) {
    if (!root || root.dataset.predictionInitialized === 'true') return;
    root.dataset.predictionInitialized = 'true';
    const predictionUrl = root.dataset.predictionUrl || '';
    const serviceRadios = Array.from(root.querySelectorAll('[data-service-radio]'));
    const typeRadios = Array.from(root.querySelectorAll('input[name="client_type"]'));
    const preview = root.querySelector('[data-prediction-preview]');
    const waitElement = root.querySelector('[data-prediction-wait]');
    const queueElement = root.querySelector('[data-prediction-queue]');
    const windowsElement = root.querySelector('[data-prediction-windows]');
    const sourceElement = root.querySelector('[data-prediction-source]');
    let requestController = null;
    let lastValidPreview = null;

    if (!predictionUrl || !serviceRadios.length || !waitElement || !queueElement || !windowsElement || !sourceElement) return;

    const selectedClientType = () => root.querySelector('input[name="client_type"]:checked')?.value || 'regular';
    const selectedService = () => serviceRadios.find(radio => radio.checked && !radio.disabled);
    const clearGroupError = fieldName => {
      const error = root.querySelector(`[data-field-error-for="${fieldName}"]`);
      if (error) error.textContent = '';
    };
    const updateCardStates = () => {
      serviceRadios.forEach(radio => {
        const card = radio.closest('[data-service-card]');
        card?.classList.toggle('is-selected', radio.checked);
      });
    };
    const setPreview = (wait, queue, windows, source, state = 'idle') => {
      waitElement.textContent = wait;
      queueElement.textContent = queue;
      windowsElement.textContent = windows;
      sourceElement.textContent = source;
      preview?.classList.toggle('is-loading', state === 'loading');
      if (preview) preview.dataset.state = state;
    };

    const loadPrediction = async () => {
      updateCardStates();
      const service = selectedService();
      if (!service) {
        requestController?.abort();
        setPreview('Select a service', '--', '--', 'Ready', 'idle');
        return;
      }

      requestController?.abort();
      requestController = new AbortController();
      const controller = requestController;
      controllers.add(controller);
      setPreview('Calculating...', lastValidPreview?.queue ?? '--', lastValidPreview?.windows ?? '--', 'Updating estimate', 'loading');
      const params = new URLSearchParams({
        service_id: service.value,
        client_type: selectedClientType()
      });

      try {
        const response = await fetch(`${predictionUrl}?${params.toString()}`, {
          credentials: 'same-origin',
          signal: controller.signal
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.message || 'Prediction unavailable');
        const minutes = Number(payload.predicted_wait_minutes || 0)
          .toLocaleString(undefined, { maximumFractionDigits: 1 });
        lastValidPreview = {
          wait: `~${minutes} minutes`,
          queue: String(payload.queue_length ?? 0),
          windows: String(payload.active_windows ?? 1),
          source: payload.source === 'ml' ? 'ML estimate' : 'Queue activity estimate',
          state: payload.source === 'ml' ? 'ml' : 'fallback'
        };
        setPreview(
          lastValidPreview.wait,
          lastValidPreview.queue,
          lastValidPreview.windows,
          lastValidPreview.source,
          lastValidPreview.state
        );
      } catch (error) {
        if (error.name === 'AbortError') return;
        if (lastValidPreview) {
          setPreview(lastValidPreview.wait, lastValidPreview.queue, lastValidPreview.windows, 'Last estimate shown — retrying later', 'error');
        } else {
          setPreview('Estimate unavailable', '--', '--', 'You can still join the queue', 'error');
        }
      } finally {
        controllers.delete(controller);
      }
    };

    serviceRadios.forEach(radio => radio.addEventListener('change', () => {
      clearGroupError('service_id');
      loadPrediction();
    }));
    typeRadios.forEach(radio => radio.addEventListener('change', () => {
      clearGroupError('client_type');
      loadPrediction();
    }));
    loadPrediction();
  }

  function initQueueBooking(form) {
    if (!form || form.dataset.bookingInitialized === 'true') return;
    form.dataset.bookingInitialized = 'true';
    const panels = Array.from(form.querySelectorAll('[data-booking-step]'));
    const shell = form.closest('[data-queue-booking-shell]');
    const indicators = Array.from(shell?.querySelectorAll('[data-booking-step-indicator]') || []);
    const maximumStep = panels.length;
    let currentStep = Math.min(maximumStep, Math.max(1, Number(form.dataset.initialStep || 1)));

    if (!maximumStep) return;

    const fieldError = name => form.querySelector(`[data-field-error-for="${name}"]`);
    const selectedService = () => form.querySelector('input[name="service_id"]:checked');
    const selectedBranch = () => form.querySelector('input[name="branch_id"]:checked');
    const selectedType = () => form.querySelector('input[name="client_type"]:checked');
    const reviewTarget = name => form.querySelector(`[data-booking-review="${name}"]`);

    const updateReview = () => {
      const firstName = String(form.elements.first_name?.value || '').trim();
      const lastName = String(form.elements.last_name?.value || '').trim();
      const service = selectedService();
      const branch = selectedBranch();
      const type = selectedType();
      const values = {
        service: service?.dataset.reviewLabel || 'Not selected',
        branch: branch?.dataset.reviewLabel || 'Not selected',
        customer: `${firstName} ${lastName}`.trim() || '—',
        phone: String(form.elements.phone_number?.value || '').trim() || '—',
        classification: type ? type.value.charAt(0).toUpperCase() + type.value.slice(1) : '—',
      };
      Object.entries(values).forEach(([name, value]) => {
        const target = reviewTarget(name);
        if (target) target.textContent = value;
      });
    };

    const showStep = (step, focus = true) => {
      currentStep = Math.min(maximumStep, Math.max(1, step));
      panels.forEach(panel => {
        const active = Number(panel.dataset.bookingStep) === currentStep;
        panel.hidden = !active;
        panel.setAttribute('aria-hidden', String(!active));
      });
      indicators.forEach(indicator => {
        const index = Number(indicator.dataset.bookingStepIndicator);
        indicator.classList.toggle('is-current', index === currentStep);
        indicator.classList.toggle('is-complete', index < currentStep);
        indicator.setAttribute('aria-current', index === currentStep ? 'step' : 'false');
      });
      if (currentStep === maximumStep) updateReview();
      if (focus) {
        panels[currentStep - 1]?.querySelector('h3, legend, input:not([type="hidden"])')?.focus?.({ preventScroll: true });
        shell?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
      }
    };

    const validateStep = step => {
      const panel = panels[step - 1];
      const controls = Array.from(panel?.querySelectorAll('input, select, textarea') || [])
        .filter(control => !control.disabled && control.type !== 'hidden');
      const invalid = controls.find(control => typeof control.checkValidity === 'function' && !control.checkValidity());
      if (!invalid) return true;
      invalid.reportValidity?.();
      return false;
    };

    const prioritySelectionAllowed = () => {
      const service = selectedService();
      const type = selectedType()?.value || 'regular';
      const error = fieldError('client_type');
      if (service?.dataset.priorityOnly === '1' && !['senior', 'pwd'].includes(type)) {
        if (error) error.textContent = 'This service is reserved for Senior or PWD clients.';
        form.querySelector('input[name="client_type"][value="senior"]')?.focus();
        return false;
      }
      return true;
    };

    form.addEventListener('click', event => {
      const next = event.target.closest('[data-booking-next]');
      const back = event.target.closest('[data-booking-back]');
      if (next) {
        if (!validateStep(currentStep)) return;
        if (currentStep === 3 && !prioritySelectionAllowed()) return;
        showStep(currentStep + 1);
      } else if (back) {
        showStep(currentStep - 1);
      }
    });

    form.addEventListener('change', event => {
      const input = event.target;
      if (input.matches('[data-service-radio]')) {
        form.querySelectorAll('[data-service-card]').forEach(card => {
          card.classList.toggle('is-selected', card.contains(input));
        });
      }
      if (input.matches('[data-branch-radio]')) {
        form.querySelectorAll('[data-branch-card]').forEach(card => {
          card.classList.toggle('is-selected', card.contains(input));
        });
      }
      if (input.name === 'client_type') {
        const error = fieldError('client_type');
        if (error) error.textContent = '';
      }
      updateReview();
    });
    form.addEventListener('input', updateReview);

    document.querySelectorAll('[data-booking-start]').forEach(trigger => {
      trigger.addEventListener('click', () => {
        window.setTimeout(() => panels[currentStep - 1]?.querySelector('input:not([type="hidden"])')?.focus(), 0);
      });
    });

    showStep(currentStep, false);
    updateReview();
    const firstServerInvalid = form.querySelector('.is-invalid');
    if (firstServerInvalid) window.setTimeout(() => firstServerInvalid.focus(), 0);
  }

  function initQueueStatus(root) {
    if (!root || root.dataset.queueStatusInitialized === 'true') return;
    root.dataset.queueStatusInitialized = 'true';
    const statusUrl = root.dataset.statusUrl || '';
    const refreshInterval = Math.max(5000, Number(root.dataset.refreshInterval || 10000));
    const branchId = root.dataset.branchId || '';
    const windowsElement = root.querySelector('[data-window-status]');
    const nextElement = root.querySelector('[data-next-tickets]');
    const updatedElement = root.querySelector('[data-status-updated]');
    const viewerTicket = root.querySelector('[data-viewer-ticket]');
    let requestInFlight = false;

    if (!statusUrl || !windowsElement || !nextElement || !updatedElement) return;
    const windowStateClass = status => ['open', 'busy', 'closed'].includes(status) ? status : 'closed';

    const renderWindows = windows => {
      if (!windows.length) {
        windowsElement.innerHTML = '<div class="queue-empty-state queue-empty-state-rich"><i class="bi bi-window" aria-hidden="true"></i><p>No service windows are configured yet.</p></div>';
        return;
      }
      windowsElement.innerHTML = windows.map(windowInfo => {
        const status = windowInfo.status || 'closed';
        const clientType = windowInfo.client_type || '';
        const classification = ['senior', 'pwd'].includes(clientType)
          ? `<span class="badge-priority">${escapeHtml(clientType)}</span>`
          : (clientType ? `<span class="status-mini-badge">${escapeHtml(clientType)}</span>` : '');
        return `<article class="window-status-card">
          <div class="window-status-card-head">
            <span>${escapeHtml(windowInfo.window_name || 'Window')}</span>
            <strong class="window-state window-state-${windowStateClass(status)}">${escapeHtml(status.toUpperCase())}</strong>
          </div>
          <div class="window-ticket">${escapeHtml(windowInfo.ticket_number || '--')}</div>
          <p>${escapeHtml(windowInfo.service_name || 'No service assigned')}</p>
          ${classification}
        </article>`;
      }).join('');
    };

    const renderNextTickets = tickets => {
      if (!tickets.length) {
        nextElement.innerHTML = '<div class="queue-empty-state queue-empty-state-rich"><i class="bi bi-people" aria-hidden="true"></i><p>No waiting tickets right now.</p></div>';
        return;
      }
      nextElement.innerHTML = tickets.map((ticket, index) => {
        return `<article class="next-ticket-row">
          <span aria-hidden="true">${index + 1}</span>
          <div><strong>${escapeHtml(ticket.ticket_number)}</strong>
          <small>${escapeHtml(ticket.service_name)} &middot; ${escapeHtml(ticket.client_type)}</small></div>
        </article>`;
      }).join('');
    };

    const renderViewerTicket = ticket => {
      if (!viewerTicket) return;
      const number = viewerTicket.querySelector('[data-viewer-ticket-number]');
      const service = viewerTicket.querySelector('[data-viewer-ticket-service]');
      const statusElement = viewerTicket.querySelector('[data-viewer-ticket-status]');
      const ahead = viewerTicket.querySelector('[data-viewer-ticket-ahead]');
      const waitElement = viewerTicket.querySelector('[data-viewer-ticket-wait]');
      if (!ticket) {
        viewerTicket.hidden = true;
        viewerTicket.dataset.state = 'inactive';
        return;
      }

      viewerTicket.hidden = false;
      viewerTicket.dataset.state = 'active';
      const status = ['waiting', 'serving', 'completed', 'voided', 'skipped'].includes(ticket.status)
        ? ticket.status
        : 'waiting';
      const wait = ticket.predicted_wait_min === null || ticket.predicted_wait_min === undefined
        ? 'Estimate pending'
        : `~${Number(ticket.predicted_wait_min).toLocaleString(undefined, { maximumFractionDigits: 1 })} minutes`;
      if (number) number.textContent = ticket.ticket_number || '--';
      if (service) service.textContent = ticket.service_name || 'Health service';
      if (ahead) ahead.textContent = String(ticket.people_ahead ?? 0);
      if (waitElement) waitElement.textContent = wait;
      if (statusElement) {
        statusElement.textContent = status;
        statusElement.className = `status-badge badge-${status}`;
      }
    };

    const renderFailure = () => {
      windowsElement.innerHTML = '<div class="queue-empty-state queue-empty-state-rich queue-error-state"><i class="bi bi-wifi-off" aria-hidden="true"></i><p>Queue status could not be loaded. Check your connection and try again.</p><button class="btn btn-outline-primary" type="button" data-queue-retry>Retry update</button></div>';
      nextElement.innerHTML = '<div class="queue-empty-state queue-empty-state-rich"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i><p>Waiting tickets will appear after a successful update.</p></div>';
    };

    const renderSnapshot = (payload, error, metadata = {}) => {
      root.setAttribute('aria-busy', 'true');
      if (error || !payload?.success) {
        renderFailure();
        updatedElement.textContent = 'Update failed';
      } else {
        renderWindows(payload.data?.windows || []);
        renderNextTickets(payload.data?.next || []);
        renderViewerTicket(payload.data?.viewer_ticket || null);
        const mode = metadata.source === 'realtime' ? 'Live' : 'Updated';
        updatedElement.textContent = `${mode} ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
      }
      requestInFlight = false;
      root.removeAttribute('aria-busy');
    };

    const loadQueueStatus = async () => {
      if (requestInFlight) return;
      requestInFlight = true;
      updatedElement.textContent = 'Updating...';
      try {
        const response = await fetch(statusUrl, { credentials: 'same-origin' });
        renderSnapshot(await response.json(), response.ok ? null : new Error('Status unavailable'), { source: 'manual' });
      } catch (error) {
        renderSnapshot(null, error, { source: 'manual' });
      }
    };

    root.addEventListener('click', event => {
      if (event.target.closest('[data-queue-retry]')) loadQueueStatus();
    });
    const resume = () => {
      if (window.SmartQmsData?.subscribeToQueue) {
        const unsubscribe = window.SmartQmsData.subscribeToQueue(branchId, renderSnapshot, { interval: refreshInterval });
        subscriptions.add(unsubscribe);
      } else {
        loadQueueStatus();
        const timer = window.setInterval(loadQueueStatus, refreshInterval);
        timers.add(timer);
      }
    };
    root.smartQmsQueueResume = resume;
    resume();
  }

  function showBrowserNotification(message) {
    if (!('Notification' in window)) return;
    const show = () => new window.Notification('SmartQMS -- Your Turn is Near', { body: message });
    if (window.Notification.permission === 'granted') {
      show();
    }
  }

  function initNotificationPolling(root) {
    const url = root?.dataset.clientNotificationUrl || '';
    const readUrl = root?.dataset.clientNotificationReadUrl || '';
    const center = root?.querySelector('[data-client-notification-center]');
    const toggle = center?.querySelector('[data-client-notification-toggle]');
    const list = center?.querySelector('[data-client-notification-list]');
    const badge = center?.querySelector('[data-client-notification-badge]');
    const retry = center?.querySelector('[data-client-notification-retry]');
    if (!root || !url || root.dataset.notificationsInitialized === 'true') return;
    root.dataset.notificationsInitialized = 'true';
    let requestInFlight = false;
    let readRequestInFlight = false;
    let notifications = [];

    const notifiedStorageKey = 'smartqms-client-notified-ids';
    const notifiedIds = (() => {
      try {
        const stored = JSON.parse(window.sessionStorage.getItem(notifiedStorageKey) || '[]');
        return new Set(Array.isArray(stored) ? stored.map(String) : []);
      } catch (error) {
        return new Set();
      }
    })();
    const saveNotifiedIds = () => {
      try {
        window.sessionStorage.setItem(notifiedStorageKey, JSON.stringify(Array.from(notifiedIds).slice(-100)));
      } catch (error) {
        // Notification history remains available when storage is restricted.
      }
    };
    const setBadge = count => {
      if (!badge || !toggle) return;
      const normalized = Math.max(0, Number(count || 0));
      badge.textContent = normalized > 99 ? '99+' : String(normalized);
      badge.hidden = normalized === 0;
      toggle.setAttribute('aria-label', normalized
        ? `Open notifications, ${normalized} unread`
        : 'Open notifications');
    };
    const notificationIcon = type => {
      if (type === 'turn_alert') return 'bell-ring';
      if (type === 'turn_void') return 'circle-x';
      if (type === 'feedback_prompt') return 'message-square-heart';
      return 'info';
    };
    const formatTimestamp = value => {
      const date = new Date(String(value || '').replace(' ', 'T'));
      if (Number.isNaN(date.getTime())) return 'Recently';
      return date.toLocaleString([], {
        month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'
      });
    };
    const render = () => {
      if (!list) return;
      if (!notifications.length) {
        list.innerHTML = '<p class="app-notification-state">No notifications yet. Queue updates will appear here.</p>';
      } else {
        list.innerHTML = notifications.map(notification => {
          const unread = Number(notification.is_read) === 0;
          return `<article class="app-notification-item${unread ? ' is-unread' : ''}" data-notification-id="${escapeHtml(notification.notif_id)}">
            <span class="app-notification-icon" aria-hidden="true"><i data-lucide="${notificationIcon(notification.type)}"></i></span>
            <div class="app-notification-copy">
              <p>${escapeHtml(notification.message || 'SmartQMS queue update')}</p>
              <small>${escapeHtml(formatTimestamp(notification.sent_at))}${unread ? ' · Unread' : ''}</small>
            </div>
          </article>`;
        }).join('');
      }
      if (window.lucide?.createIcons) window.lucide.createIcons();
    };
    const renderFailure = () => {
      if (list) list.innerHTML = '<p class="app-notification-state">Notifications could not be loaded. Check your connection and try again.</p>';
      if (retry) retry.hidden = false;
    };

    const acknowledgeVisible = async () => {
      if (!readUrl || readRequestInFlight) return;
      const unreadIds = notifications
        .filter(notification => Number(notification.is_read) === 0)
        .map(notification => String(notification.notif_id));
      if (!unreadIds.length) return;
      readRequestInFlight = true;
      const body = new FormData();
      unreadIds.forEach(id => body.append('notification_ids[]', id));
      const token = csrfToken();
      try {
        const response = await fetch(readUrl, {
          method: 'POST',
          body,
          credentials: 'same-origin',
          headers: token ? { 'X-CSRF-Token': token } : {}
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error('Acknowledgement failed');
        const acknowledged = new Set(unreadIds);
        notifications = notifications.map(notification => acknowledged.has(String(notification.notif_id))
          ? { ...notification, is_read: 1 }
          : notification);
        setBadge(payload.unread_count || 0);
        render();
      } catch (error) {
        // Keep the unread presentation so acknowledgement can be retried.
      } finally {
        readRequestInFlight = false;
      }
    };

    const poll = async () => {
      if (requestInFlight || document.hidden) return;
      requestInFlight = true;
      const token = csrfToken();
      try {
        const response = await fetch(url, {
          method: 'POST',
          credentials: 'same-origin',
          headers: token ? { 'X-CSRF-Token': token } : {}
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) throw new Error('Notifications unavailable');
        notifications = Array.isArray(payload.data) ? payload.data : [];
        if (retry) retry.hidden = true;
        setBadge(payload.unread_count || 0);
        render();
        notifications
          .filter(notification => Number(notification.is_read) === 0 && !notifiedIds.has(String(notification.notif_id)))
          .forEach(notification => {
            notifiedIds.add(String(notification.notif_id));
            showBrowserNotification(notification.message || '');
          });
        saveNotifiedIds();
      } catch (error) {
        renderFailure();
      } finally {
        requestInFlight = false;
      }
    };

    toggle?.addEventListener('click', () => {
      if ('Notification' in window && window.Notification.permission === 'default') {
        window.Notification.requestPermission().catch(() => {});
      }
    });
    center?.addEventListener('shown.bs.dropdown', acknowledgeVisible);
    retry?.addEventListener('click', event => {
      event.stopPropagation();
      poll();
    });
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) poll();
    });

    const resume = () => {
      poll();
      const timer = window.setInterval(poll, 10000);
      timers.add(timer);
    };
    root.smartQmsNotificationResume = resume;
    resume();
  }

  function initFeedbackForm(form) {
    if (!form || form.dataset.feedbackInitialized === 'true') return;
    form.dataset.feedbackInitialized = 'true';
    const url = form.dataset.feedbackUrl || '';
    const successUrl = form.dataset.feedbackSuccessUrl || '';
    const result = document.querySelector('[data-feedback-result]');
    let requestInFlight = false;
    if (!url || !result) return;

    const clearErrors = () => {
      form.querySelectorAll('.is-invalid').forEach(field => {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
      });
      form.querySelectorAll('.field-error').forEach(error => {
        error.textContent = '';
      });
    };
    const showResult = (message, type) => {
      result.replaceChildren();
      const alert = document.createElement('div');
      alert.className = `alert alert-${type}`;
      alert.setAttribute('role', type === 'danger' ? 'alert' : 'status');
      alert.textContent = message;
      result.appendChild(alert);
    };

    form.querySelectorAll('select, textarea').forEach(field => {
      field.addEventListener('input', () => {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
        const error = form.querySelector(`[data-field-error-for="${field.name}"]`);
        if (error) error.textContent = '';
      });
    });

    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
        form.reportValidity?.();
        return;
      }
      if (requestInFlight) return;
      requestInFlight = true;
      clearErrors();
      result.replaceChildren();
      const submit = form.querySelector('[type="submit"]');
      if (submit) {
        submit.disabled = true;
        submit.setAttribute('aria-busy', 'true');
      }
      const token = csrfToken();

      try {
        const response = await fetch(url, {
          method: 'POST',
          body: new FormData(form),
          credentials: 'same-origin',
          headers: token ? { 'X-CSRF-Token': token } : {}
        });
        let payload;
        try {
          payload = await response.json();
        } catch (error) {
          throw new Error('SmartQMS returned an unexpected response. Please try again.');
        }
        if (response.ok && payload.success) {
          if (successUrl) {
            window.location.assign(successUrl);
            return;
          }
          showResult('Thank you for your feedback.', 'success');
          form.reset();
          return;
        }

        Object.entries(payload.field_errors || {}).forEach(([fieldName, message]) => {
          const field = form.elements[fieldName];
          const error = form.querySelector(`[data-field-error-for="${fieldName}"]`);
          if (field) {
            field.classList.add('is-invalid');
            field.setAttribute('aria-invalid', 'true');
          }
          if (error) error.textContent = String(message);
        });
        const firstInvalid = form.querySelector('.is-invalid');
        if (firstInvalid) {
          firstInvalid.focus();
        } else {
          showResult(payload.error || 'Could not submit feedback.', 'danger');
        }
      } catch (error) {
        showResult(error.message || 'Could not submit feedback. Please try again.', 'danger');
      } finally {
        requestInFlight = false;
        if (submit) {
          submit.disabled = false;
          submit.removeAttribute('aria-busy');
        }
      }
    });

    const modalElement = form.closest('[data-client-feedback-modal]');
    if (modalElement?.dataset.autoOpen === 'true' && window.bootstrap?.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
  }

  function initTicketPrinting(root) {
    root.querySelectorAll('[data-ticket-print]').forEach(button => {
      if (button.dataset.printInitialized === 'true') return;
      button.dataset.printInitialized = 'true';
      button.addEventListener('click', () => window.print());
    });
  }

  function clearClientRuntime() {
    timers.forEach(timer => window.clearInterval(timer));
    timers.clear();
    controllers.forEach(controller => controller.abort());
    controllers.clear();
    subscriptions.forEach(unsubscribe => unsubscribe());
    subscriptions.clear();
  }

  function resumeClientRuntime(event) {
    if (!event.persisted) return;
    document.querySelectorAll('[data-queue-status-root]').forEach(root => root.smartQmsQueueResume?.());
    document.querySelector('[data-client-root]')?.smartQmsNotificationResume?.();
  }

  function initClientPage() {
    const root = document.querySelector('[data-client-root]');
    if (!root || root.dataset.clientInitialized === 'true') return;
    root.dataset.clientInitialized = 'true';
    document.querySelectorAll('[data-prediction-root]').forEach(initServicePrediction);
    document.querySelectorAll('[data-queue-booking]').forEach(initQueueBooking);
    document.querySelectorAll('[data-queue-status-root]').forEach(initQueueStatus);
    initNotificationPolling(root);
    initFeedbackForm(document.querySelector('[data-feedback-form]'));
    initTicketPrinting(root);
  }

  document.addEventListener('DOMContentLoaded', initClientPage);
  window.addEventListener('pagehide', clearClientRuntime);
  window.addEventListener('pageshow', resumeClientRuntime);
})();
