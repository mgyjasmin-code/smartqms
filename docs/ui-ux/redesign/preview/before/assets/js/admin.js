(function () {

  let adminQueueUnsubscribe = null;
  let adminRefreshTimer = null;
  let adminRefreshInFlight = false;

  function refreshIcons() {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }
  }

  const activeCharts = new Map();
  let activePrintButton = null;
  let prePrintDocumentTitle = '';
  let printRestoreTimer = null;
  let printInProgress = false;

  function initAdminCharts() {
    if (!window.Chart) return;
    const styles = getComputedStyle(document.documentElement);
    const textColor = styles.getPropertyValue('--admin-chart-text').trim() || '#1F2937';
    const mutedColor = styles.getPropertyValue('--admin-chart-muted').trim() || '#596273';
    const gridColor = styles.getPropertyValue('--admin-chart-grid').trim() || '#D8DEE8';
    const primaryColor = styles.getPropertyValue('--admin-primary').trim() || '#147BFE';
    const secondaryBlue = styles.getPropertyValue('--sq-action-blue-hover').trim() || '#3B92FF';
    const deepBlue = styles.getPropertyValue('--sq-link').trim() || '#075DBD';
    const lightBlue = styles.getPropertyValue('--sq-blue-300').trim() || '#9BCBFF';
    const amber = styles.getPropertyValue('--admin-chart-series-amber').trim() || '#F59E0B';
    const violet = styles.getPropertyValue('--admin-chart-series-violet').trim() || '#7C3AED';
    const cyan = styles.getPropertyValue('--admin-chart-series-cyan').trim() || '#0891B2';
    const rose = styles.getPropertyValue('--admin-chart-series-rose').trim() || '#E11D48';
    const slate = styles.getPropertyValue('--admin-chart-series-slate').trim() || '#64748B';
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
      const palette = [primaryColor, amber, violet, cyan, rose, slate, deepBlue, secondaryBlue, lightBlue];
      const semanticSeriesStyles = {
        primary: { color: primaryColor, dash: [], pointStyle: 'circle' },
        predicted: { color: primaryColor, dash: [], pointStyle: 'circle' },
        actual: { color: amber, dash: [8, 5], pointStyle: 'triangle' },
        waiting: { color: amber, dash: [], pointStyle: 'rectRounded' },
        calling: { color: cyan, dash: [], pointStyle: 'rectRot' },
        in_progress: { color: violet, dash: [], pointStyle: 'triangle' },
        completed: { color: primaryColor, dash: [], pointStyle: 'circle' },
        skipped: { color: slate, dash: [3, 3], pointStyle: 'rect' },
        void: { color: rose, dash: [8, 4], pointStyle: 'crossRot' },
        mae: { color: violet, dash: [], pointStyle: 'rectRounded' },
        rmse: { color: cyan, dash: [], pointStyle: 'triangle' },
        rating: { color: amber, dash: [], pointStyle: 'circle' },
        turnaround: { color: violet, dash: [], pointStyle: 'rectRot' },
        volume: { color: primaryColor, dash: [], pointStyle: 'circle' },
        counter_performance: { color: cyan, dash: [], pointStyle: 'rectRounded' },
        staff_productivity: { color: violet, dash: [], pointStyle: 'triangle' },
      };
      const requestedType = ['line', 'bar', 'horizontalBar', 'doughnut'].includes(chartData.type)
        ? chartData.type
        : 'bar';
      const isHorizontal = requestedType === 'horizontalBar';
      const isCircular = requestedType === 'doughnut';
      const type = isHorizontal ? 'bar' : requestedType;
      const stacked = Boolean(chartData.stacked);
      const scrollContent = canvas.closest('[data-admin-chart-scroll-content]');
      const longestLabelLength = labels.reduce(
        (length, label) => Math.max(length, String(label || '').length),
        0
      );
      const basePointWidth = type === 'bar' && !isHorizontal ? 104 : 84;
      const maximumPointWidth = type === 'bar' && !isHorizontal ? 180 : 150;
      const pointWidth = Math.min(
        maximumPointWidth,
        Math.max(basePointWidth, Math.ceil(longestLabelLength * 6.8) + 32)
      );
      const minimumChartWidth = isHorizontal || isCircular
        ? 320
        : Math.max(320, (labels.length * pointWidth) + 96);

      scrollContent?.style.setProperty('--admin-chart-min-width', `${minimumChartWidth}px`);

      const normalizedDatasets = datasets.map((dataset, index) => {
        const seriesStyle = semanticSeriesStyles[dataset.style_key] || {
          color: palette[index % palette.length],
          dash: [],
          pointStyle: ['circle', 'triangle', 'rectRounded', 'rectRot'][index % 4],
        };
        const seriesColor = seriesStyle.color;
        return {
          label: dataset.label || `Series ${index + 1}`,
          data: dataset.data || [],
          borderColor: isCircular ? canvas.ownerDocument.documentElement.style.backgroundColor || '#FFFFFF' : seriesColor,
          backgroundColor: isCircular
            ? labels.map((_, labelIndex) => `${palette[labelIndex % palette.length]}D9`)
            : type === 'line' ? seriesColor : `${seriesColor}C7`,
          borderWidth: isCircular ? 2 : 2,
          borderDash: type === 'line' ? seriesStyle.dash : [],
          borderRadius: type === 'bar' ? 6 : 0,
          pointRadius: type === 'line' ? 3 : 0,
          pointHoverRadius: type === 'line' ? 5 : 0,
          pointStyle: seriesStyle.pointStyle,
          pointBackgroundColor: seriesColor,
          pointBorderColor: '#FFFFFF',
          pointBorderWidth: type === 'line' ? 1.5 : 0,
          tension: type === 'line' ? 0.25 : 0,
          fill: false,
          maxBarThickness: isHorizontal ? 34 : 72,
        };
      });

      let scales;
      if (!isCircular) {
        scales = isHorizontal
          ? {
              x: {
                beginAtZero: true,
                suggestedMax: Number(chartData.suggestedMax) || undefined,
                stacked,
                ticks: { color: mutedColor },
                grid: { color: gridColor },
              },
              y: {
                stacked,
                ticks: { color: mutedColor, autoSkip: false },
                grid: { display: false },
              },
            }
          : {
              x: {
                stacked,
                ticks: { color: mutedColor, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
                grid: { display: false },
              },
              y: {
                beginAtZero: true,
                suggestedMax: Number(chartData.suggestedMax) || undefined,
                stacked,
                ticks: { color: mutedColor },
                grid: { color: gridColor },
              },
            };
      }

      const chart = new window.Chart(canvas, {
        type,
        data: { labels, datasets: normalizedDatasets },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          indexAxis: isHorizontal ? 'y' : 'x',
          cutout: isCircular ? '58%' : undefined,
          animation: reducedMotion ? false : { duration: 250 },
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: {
              display: datasets.length > 1 || isCircular,
              position: isCircular ? 'right' : 'top',
              labels: { color: textColor, usePointStyle: true, boxWidth: 10, padding: 16 },
            },
            tooltip: {
              callbacks: {
                label: (context) => `${context.dataset.label}: ${context.formattedValue}${chartData.unit ? ` ${chartData.unit}` : ''}`,
              },
            },
          },
          scales,
        },
      });
      activeCharts.set(canvas, chart);
    });
  }

  function resizeActiveCharts() {
    activeCharts.forEach((chart) => chart.resize());
  }

  function prepareAdminPrint() {
    if (!printInProgress) {
      prePrintDocumentTitle = document.title;
    }
    printInProgress = true;
    if (printRestoreTimer) {
      clearTimeout(printRestoreTimer);
      printRestoreTimer = null;
    }
    const reportTitle = document.querySelector('.admin-report-title h2')?.textContent?.trim();
    if (reportTitle) document.title = `SmartQMS - ${reportTitle}`;
    document.body.classList.add('admin-print-mode');
    resizeActiveCharts();
  }

  function restoreAdminPrint() {
    if (printRestoreTimer) {
      clearTimeout(printRestoreTimer);
      printRestoreTimer = null;
    }
    document.body.classList.remove('admin-print-mode');
    if (prePrintDocumentTitle) document.title = prePrintDocumentTitle;
    prePrintDocumentTitle = '';
    if (activePrintButton) {
      activePrintButton.disabled = false;
      activePrintButton.removeAttribute('aria-busy');
    }
    activePrintButton = null;
    printInProgress = false;
    window.requestAnimationFrame(resizeActiveCharts);
  }

  function scheduleAdminPrintRestore(delay = 1200) {
    if (!printInProgress) return;
    if (printRestoreTimer) clearTimeout(printRestoreTimer);
    printRestoreTimer = setTimeout(restoreAdminPrint, delay);
  }

  function openAdminPrintDialog(button) {
    if (printInProgress) return;
    activePrintButton = button;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    prepareAdminPrint();

    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(() => {
        resizeActiveCharts();
        try {
          window.print();
        } finally {
          button.removeAttribute('aria-busy');
          scheduleAdminPrintRestore();
        }
      });
    });
  }

  function initPrintButtons() {
    document.querySelectorAll('[data-admin-print]').forEach((button) => {
      if (button.dataset.adminPrintInitialized === 'true') return;
      button.dataset.adminPrintInitialized = 'true';
      button.disabled = false;
      button.removeAttribute('aria-busy');
      button.addEventListener('click', () => openAdminPrintDialog(button));
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

      const syncWindowRoutingFields = () => {
        const typeField = form?.querySelector('[data-admin-window-type]');
        const serviceFieldWrap = form?.querySelector('[data-admin-specialized-service-field]');
        const serviceField = serviceFieldWrap?.querySelector('select');
        if (!typeField || !serviceFieldWrap || !serviceField) return;

        const specialized = typeField.value === 'specialized';
        serviceFieldWrap.hidden = !specialized;
        serviceField.required = specialized;
        serviceField.disabled = !specialized;
        if (!specialized) {
          serviceField.value = '';
          serviceField.setCustomValidity('');
        }
      };

      const windowTypeField = form?.querySelector('[data-admin-window-type]');
      windowTypeField?.addEventListener('change', syncWindowRoutingFields);
      syncWindowRoutingFields();

      const resetForm = () => {
        if (!form) return;

        form.reset();
        form.classList.remove('was-validated');
        delete form.dataset.adminConfirmed;
        form.querySelectorAll('.is-invalid, .is-valid, [aria-invalid="true"]').forEach((field) => {
          field.classList.remove('is-invalid', 'is-valid');
          field.removeAttribute('aria-invalid');
        });
        form.querySelectorAll('[data-confirm-password-for]').forEach((field) => field.setCustomValidity(''));
        form.querySelectorAll('.field-error').forEach((feedback) => {
          feedback.textContent = '';
        });
        syncWindowRoutingFields();
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

  function initJobTitleFields() {
    document.querySelectorAll('[data-admin-job-title]').forEach((select) => {
      if (select.dataset.jobTitleInitialized === 'true') return;
      select.dataset.jobTitleInitialized = 'true';
      const form = select.form;
      const field = form?.querySelector('[data-admin-custom-job-title]');
      const input = field?.querySelector('input');
      if (!field || !input) return;
      const sync = () => {
        const custom = select.value === 'Other';
        field.hidden = !custom;
        input.required = custom;
        if (!custom) input.setCustomValidity('');
      };
      select.addEventListener('change', sync);
      sync();
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
      initJobTitleFields,
      initPaginatedTables,
      initAdminCharts,
      refreshIcons,
      initAdminLiveDashboard,
    ];

    initializers.forEach((initialize) => initialize());
  }

  async function refreshAdminDashboard() {
    if (adminRefreshInFlight || document.hidden) return;
    adminRefreshInFlight = true;
    try {
      const response = await fetch(window.location.href, {
        credentials: 'same-origin',
        headers: { 'X-SmartQMS-Partial': 'main' }
      });
      if (!response.ok) throw new Error('Dashboard refresh failed.');
      const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
      const incoming = parsed.querySelector('#admin-main');
      const current = document.querySelector('#admin-main');
      if (!incoming || !current) throw new Error('Dashboard returned an invalid response.');
      activeCharts.forEach(chart => chart.destroy());
      activeCharts.clear();
      current.innerHTML = incoming.innerHTML;
      initAdminCharts();
      refreshIcons();
    } catch (error) {
      // Realtime is an enhancement; the server-rendered dashboard remains usable.
    } finally {
      adminRefreshInFlight = false;
    }
  }

  function initAdminLiveDashboard() {
    if (adminQueueUnsubscribe || !document.querySelector('[data-admin-live-dashboard]')) return;
    const subscribe = window.SmartQmsData?.subscribeToQueue;
    if (typeof subscribe !== 'function') return;
    adminQueueUnsubscribe = subscribe('', (payload, error, metadata = {}) => {
      if (error || !payload?.success || metadata.source === 'initial') return;
      window.clearTimeout(adminRefreshTimer);
      adminRefreshTimer = window.setTimeout(refreshAdminDashboard, 180);
    }, { interval: 10000 });
  }

  document.addEventListener('smartqms:theme-changed', initAdminCharts);
  document.addEventListener('DOMContentLoaded', initAdminPage);
  window.addEventListener('beforeprint', prepareAdminPrint);
  window.addEventListener('afterprint', restoreAdminPrint);
  window.matchMedia?.('print').addEventListener?.('change', (event) => {
    if (event.matches) {
      prepareAdminPrint();
    } else {
      restoreAdminPrint();
    }
  });
  window.addEventListener('focus', () => scheduleAdminPrintRestore(250));
  window.addEventListener('pageshow', () => {
    if (printInProgress || document.body.classList.contains('admin-print-mode')) {
      restoreAdminPrint();
    }
  });
  window.addEventListener('pagehide', () => {
    window.clearTimeout(adminRefreshTimer);
    if (printRestoreTimer) clearTimeout(printRestoreTimer);
    adminQueueUnsubscribe?.();
    adminQueueUnsubscribe = null;
  });
})();
