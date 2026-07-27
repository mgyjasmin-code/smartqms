(function () {

  function refreshIcons() {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }
  }

  const activeCharts = new Map();

  function initAdminCharts() {
    if (!window.Chart) return;
    const styles = getComputedStyle(document.documentElement);
    const textColor = styles.getPropertyValue('--admin-chart-text').trim() || '#1F2937';
    const mutedColor = styles.getPropertyValue('--admin-chart-muted').trim() || '#596273';
    const gridColor = styles.getPropertyValue('--admin-chart-grid').trim() || '#D8DEE8';
    const primaryColor = styles.getPropertyValue('--admin-primary').trim() || '#10A5F5';
    const successColor = styles.getPropertyValue('--admin-success').trim() || '#1D9E75';
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.querySelectorAll('[data-admin-chart]').forEach((canvas) => {
      const configId = canvas.dataset.adminChart;
      const configElement = configId ? document.getElementById(configId) : null;
      if (!configElement) return;

      let chartData;
      try {
        chartData = JSON.parse(configElement.textContent || '{}');
      } catch (error) {
        canvas.closest('.admin-chart-canvas-wrap')?.classList.add('has-chart-error');
        return;
      }

      const labels = Array.isArray(chartData.labels) ? chartData.labels : [];
      const datasets = Array.isArray(chartData.datasets) ? chartData.datasets : [];
      if (!labels.length || !datasets.length) return;

      activeCharts.get(canvas)?.destroy();
      const palette = [primaryColor, successColor, '#BA7517', '#8B5CF6'];
      const type = chartData.type === 'line' ? 'line' : 'bar';
      const scrollContent = canvas.closest('[data-admin-chart-scroll-content]');
      const longestLabelLength = labels.reduce(
        (length, label) => Math.max(length, String(label || '').length),
        0
      );
      const basePointWidth = type === 'bar' ? 104 : 84;
      const maximumPointWidth = type === 'bar' ? 180 : 150;
      const pointWidth = Math.min(
        maximumPointWidth,
        Math.max(basePointWidth, Math.ceil(longestLabelLength * 6.8) + 32)
      );
      const minimumChartWidth = Math.max(320, (labels.length * pointWidth) + 96);

      scrollContent?.style.setProperty('--admin-chart-min-width', `${minimumChartWidth}px`);

      const normalizedDatasets = datasets.map((dataset, index) => ({
        label: dataset.label || `Series ${index + 1}`,
        data: dataset.data || [],
        borderColor: palette[index % palette.length],
        backgroundColor: type === 'line' ? palette[index % palette.length] : `${palette[index % palette.length]}B8`,
        borderWidth: 2,
        borderRadius: type === 'bar' ? 6 : 0,
        pointRadius: type === 'line' ? 3 : 0,
        pointHoverRadius: type === 'line' ? 5 : 0,
        tension: type === 'line' ? 0.25 : 0,
        fill: false,
      }));

      const chart = new window.Chart(canvas, {
        type,
        data: { labels, datasets: normalizedDatasets },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: reducedMotion ? false : { duration: 250 },
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { display: datasets.length > 1, labels: { color: textColor, usePointStyle: true } },
            tooltip: {
              callbacks: {
                label: (context) => `${context.dataset.label}: ${context.formattedValue}${chartData.unit ? ` ${chartData.unit}` : ''}`,
              },
            },
          },
          scales: {
            x: { ticks: { color: mutedColor, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 }, grid: { display: false } },
            y: { beginAtZero: true, ticks: { color: mutedColor }, grid: { color: gridColor } },
          },
        },
      });
      activeCharts.set(canvas, chart);
    });
  }

  function initPrintButtons() {
    document.querySelectorAll('[data-admin-print]').forEach((button) => {
      button.addEventListener('click', () => window.print());
    });
  }

  function initAdminToasts() {
    if (!window.bootstrap?.Toast) return;

    document.querySelectorAll('[data-admin-action-toast]').forEach((toastElement) => {
      if (toastElement.dataset.adminToastInitialized === 'true') return;
      toastElement.dataset.adminToastInitialized = 'true';
      window.bootstrap.Toast.getOrCreateInstance(toastElement).show();
    });
  }

  function initManagementModals() {
    if (!window.bootstrap?.Modal) return;

    document.querySelectorAll('[data-admin-management-modal]').forEach((modalElement) => {
      if (modalElement.dataset.adminModalInitialized === 'true') return;
      modalElement.dataset.adminModalInitialized = 'true';

      const form = modalElement.querySelector('form');
      const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
      const autoOpen = modalElement.hasAttribute('data-admin-auto-open');
      const cleanUrl = modalElement.dataset.adminCleanUrl || window.location.pathname;

      const resetForm = () => {
        if (!form) return;

        form.reset();
        form.classList.remove('was-validated');
        delete form.dataset.adminConfirmed;
        form.querySelectorAll('[data-validate]').forEach((field) => {
          field.classList.remove('is-invalid', 'is-valid');
          field.removeAttribute('aria-invalid');
          field.dataset.validationDirty = 'false';
        });
        form.querySelectorAll('.field-error').forEach((feedback) => {
          feedback.textContent = '';
        });
      };

      modalElement.addEventListener('shown.bs.modal', () => {
        window.requestAnimationFrame(() => {
          const invalidField = form?.querySelector('.is-invalid, [aria-invalid="true"]');
          const firstField = form?.querySelector('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])');
          (invalidField || firstField)?.focus({ preventScroll: true });
        });
      });

      modalElement.addEventListener('hidden.bs.modal', () => {
        if (autoOpen) {
          window.location.assign(cleanUrl);
          return;
        }

        resetForm();
      });

      if (autoOpen) {
        window.requestAnimationFrame(() => modal.show());
      }
    });
  }

  function initAdminConfirmations() {
    const modalElement = document.querySelector('[data-admin-management-confirm-modal]');
    if (!modalElement || !window.bootstrap?.Modal) return;

    const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
    const title = modalElement.querySelector('[data-admin-confirm-title]');
    const message = modalElement.querySelector('[data-admin-confirm-message]');
    const eyebrow = modalElement.querySelector('[data-admin-confirm-eyebrow]');
    const summary = modalElement.querySelector('[data-admin-confirm-summary]');
    const confirmButton = modalElement.querySelector('[data-admin-confirm-submit]');
    const confirmLabel = modalElement.querySelector('[data-admin-confirm-submit-label]');
    let pendingForm = null;
    let pendingSubmitter = null;
    let lastFocusedElement = null;

    if (!summary || !confirmButton) return;

    const clearSummary = () => {
      while (summary.firstChild) {
        summary.removeChild(summary.firstChild);
      }
    };

    const appendSummaryItem = (label, value) => {
      const term = document.createElement('dt');
      const description = document.createElement('dd');
      term.textContent = label;
      description.textContent = value;
      summary.appendChild(term);
      summary.appendChild(description);
    };

    const confirmationValue = (field) => {
      if (field.dataset.adminConfirmSensitive === 'true') {
        return field.value ? 'Provided (hidden)' : 'Not provided';
      }

      if (field.type === 'checkbox') {
        return field.checked
          ? (field.dataset.adminConfirmCheckedLabel || 'Yes')
          : (field.dataset.adminConfirmUncheckedLabel || 'No');
      }

      if (field.tagName === 'SELECT') {
        const selectedOption = field.options[field.selectedIndex];
        return selectedOption ? selectedOption.textContent.trim() : 'Not selected';
      }

      return field.value.trim() || 'Not provided';
    };

    const configureModal = (options) => {
      const tone = options.tone === 'danger' ? 'danger' : 'primary';
      if (title) title.textContent = options.title || 'Confirm changes';
      if (message) message.textContent = options.message || 'Review this action before continuing.';
      if (eyebrow) eyebrow.textContent = tone === 'danger' ? 'Confirm action' : 'Review changes';
      if (confirmLabel) confirmLabel.textContent = options.submitLabel || 'Confirm changes';
      confirmButton.classList.toggle('is-danger', tone === 'danger');
      confirmButton.classList.toggle('is-primary', tone !== 'danger');
      confirmButton.disabled = false;
      clearSummary();

      (options.items || []).forEach((item) => appendSummaryItem(item.label, item.value));
      summary.hidden = !(options.items || []).length;
      refreshIcons();
    };

    const showConfirmation = (form, submitter, options) => {
      pendingForm = form;
      pendingSubmitter = submitter;
      lastFocusedElement = submitter || document.activeElement;
      configureModal(options);
      modal.show();
    };

    document.addEventListener('smartqms:request-confirmation', (event) => {
      const form = event.target;
      if (!form?.matches?.('[data-admin-confirm-form]')) return;

      event.detail.handled = true;
      const items = Array.from(form.querySelectorAll('[data-admin-confirm-field]')).map((field) => ({
        label: field.dataset.adminConfirmLabel || field.name || 'Field',
        value: confirmationValue(field),
      }));

      showConfirmation(form, event.detail.submitter, {
        title: form.dataset.adminConfirmTitle,
        message: form.dataset.adminConfirmMessage,
        submitLabel: form.dataset.adminConfirmSubmitLabel,
        tone: form.dataset.adminConfirmTone,
        items,
      });
    });

    document.querySelectorAll('[data-admin-confirm-action]').forEach((button) => {
      const form = button.closest('form');
      if (!form) return;

      form.addEventListener('submit', (event) => {
        if (form.dataset.adminConfirmed === 'true') {
          delete form.dataset.adminConfirmed;
          return;
        }

        event.preventDefault();
        const items = button.dataset.adminConfirmItemValue
          ? [{
              label: button.dataset.adminConfirmItemLabel || 'Item',
              value: button.dataset.adminConfirmItemValue,
            }]
          : [];
        showConfirmation(form, button, {
          title: button.dataset.adminConfirmTitle,
          message: button.dataset.adminConfirmMessage,
          submitLabel: button.dataset.adminConfirmSubmitLabel,
          tone: button.dataset.adminConfirmTone,
          items,
        });
      });
    });

    confirmButton.addEventListener('click', () => {
      if (!pendingForm || confirmButton.disabled) return;

      const form = pendingForm;
      const submitter = pendingSubmitter;
      confirmButton.disabled = true;
      form.dataset.adminConfirmed = 'true';
      modal.hide();

      window.requestAnimationFrame(() => {
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit(submitter || undefined);
        } else {
          form.submit();
        }
      });
    });

    modalElement.addEventListener('hidden.bs.modal', () => {
      confirmButton.disabled = false;
      if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
        lastFocusedElement.focus();
      }
      pendingForm = null;
      pendingSubmitter = null;
      lastFocusedElement = null;
    });
  }

  function getPaginationItems(totalPages, currentPage) {
    if (totalPages <= 3) {
      return Array.from({ length: totalPages }, (_, index) => index + 1);
    }

    let startPage = currentPage <= 2 ? 1 : currentPage - 1;

    if (startPage + 2 > totalPages) {
      startPage = totalPages - 2;
    }

    return [startPage, startPage + 1, startPage + 2];
  }

  function createPaginationButton(label, options) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'admin-pagination-button';
    button.textContent = label;

    if (options.label) {
      button.setAttribute('aria-label', options.label);
    }

    if (options.page) {
      button.dataset.page = String(options.page);
    }

    if (options.action) {
      button.dataset.pageAction = options.action;
    }

    if (options.current) {
      button.classList.add('is-active');
      button.setAttribute('aria-current', 'page');
    }

    if (options.disabled) {
      button.disabled = true;
    }

    return button;
  }

  function initPaginatedTables() {
    document.querySelectorAll('[data-admin-paginated-table]').forEach((table) => {
      const tbody = table.tBodies[0];
      if (!tbody) return;

      const rows = Array.from(tbody.rows);
      const pageSize = Math.max(1, parseInt(table.dataset.pageSize || '10', 10));
      const itemLabel = table.dataset.paginationLabel || 'records';
      const totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
      const region = table.closest('[data-admin-paginated-region]') || table.parentElement;
      const pagination = region ? region.querySelector('[data-admin-pagination]') : null;
      const status = region ? region.querySelector('[data-admin-pagination-status]') : null;
      let currentPage = 1;

      if (!pagination) return;

      const render = () => {
        const startIndex = (currentPage - 1) * pageSize;
        const endIndex = Math.min(startIndex + pageSize, rows.length);

        rows.forEach((row, index) => {
          row.hidden = index < startIndex || index >= endIndex;
        });

        if (status) {
          status.textContent = rows.length
            ? 'Showing ' + (startIndex + 1) + '-' + endIndex + ' of ' + rows.length + ' ' + itemLabel
            : '';
        }

        pagination.innerHTML = '';

        if (rows.length <= pageSize) {
          pagination.hidden = true;
          return;
        }

        pagination.hidden = false;
        pagination.appendChild(createPaginationButton('Previous', {
          action: 'previous',
          disabled: currentPage === 1,
          label: 'Previous page',
        }));

        getPaginationItems(totalPages, currentPage).forEach((page) => {
          pagination.appendChild(createPaginationButton(String(page), {
            current: page === currentPage,
            label: 'Page ' + page,
            page,
          }));
        });

        pagination.appendChild(createPaginationButton('Next', {
          action: 'next',
          disabled: currentPage === totalPages,
          label: 'Next page',
        }));
      };

      pagination.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button || button.disabled) return;

        if (button.dataset.pageAction === 'previous') {
          currentPage = Math.max(1, currentPage - 1);
        } else if (button.dataset.pageAction === 'next') {
          currentPage = Math.min(totalPages, currentPage + 1);
        } else if (button.dataset.page) {
          currentPage = Math.min(totalPages, Math.max(1, parseInt(button.dataset.page, 10)));
        } else {
          return;
        }

        render();
      });

      render();
    });
  }

  function initAdminPage() {
    const root = document.querySelector('[data-admin-root]');
    if (!root || root.dataset.adminInitialized === 'true') return;
    root.dataset.adminInitialized = 'true';
    const initializers = [
      initPrintButtons,
      initAdminToasts,
      initManagementModals,
      initAdminConfirmations,
      initPaginatedTables,
      initAdminCharts,
      refreshIcons,
    ];

    initializers.forEach((initialize) => initialize());
  }

  document.addEventListener('smartqms:theme-changed', initAdminCharts);
  document.addEventListener('DOMContentLoaded', initAdminPage);
})();
