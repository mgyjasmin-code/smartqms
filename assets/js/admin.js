(function () {
  const themeStorageKey = 'smartqms-admin-theme';

  function refreshIcons() {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }
  }

  function getCurrentTheme() {
    const currentTheme = document.documentElement.getAttribute('data-admin-theme');
    return currentTheme === 'dark' ? 'dark' : 'light';
  }

  function storeTheme(theme) {
    try {
      window.localStorage.setItem(themeStorageKey, theme);
    } catch (error) {
      return false;
    }

    return true;
  }

  function setTheme(theme, shouldStore) {
    const nextTheme = theme === 'dark' ? 'dark' : 'light';
    const toggles = document.querySelectorAll('[data-admin-theme-toggle]');
    const darkModeActive = nextTheme === 'dark';

    document.documentElement.setAttribute('data-admin-theme', nextTheme);

    if (shouldStore) {
      storeTheme(nextTheme);
    }

    toggles.forEach((toggle) => {
      const icon = toggle.querySelector('[data-admin-theme-icon]');

      toggle.setAttribute('aria-pressed', String(darkModeActive));
      toggle.setAttribute('aria-label', darkModeActive ? 'Switch to light mode' : 'Switch to dark mode');

      if (icon) {
        icon.setAttribute('data-lucide', darkModeActive ? 'sun' : 'moon');
      }
    });

    refreshIcons();
  }

  function initThemeToggle() {
    const toggles = document.querySelectorAll('[data-admin-theme-toggle]');
    if (!toggles.length) return;

    setTheme(getCurrentTheme(), false);

    toggles.forEach((toggle) => {
      toggle.addEventListener('click', () => {
        setTheme(getCurrentTheme() === 'dark' ? 'light' : 'dark', true);
      });
    });
  }

  function initSidebar() {
    const body = document.body;
    const toggle = document.querySelector('[data-admin-sidebar-toggle]');
    const closeTargets = document.querySelectorAll('[data-admin-sidebar-close]');
    const drawerQuery = window.matchMedia('(max-width: 1024px)');

    const setOpen = (isOpen) => {
      body.classList.toggle('admin-sidebar-open', isOpen);

      if (toggle) {
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? 'Close admin navigation' : 'Open admin navigation');
      }
    };

    if (toggle) {
      toggle.addEventListener('click', () => {
        setOpen(!body.classList.contains('admin-sidebar-open'));
      });
    }

    closeTargets.forEach((target) => {
      target.addEventListener('click', () => setOpen(false));
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        setOpen(false);
      }
    });

    const handleViewportChange = () => {
      if (!drawerQuery.matches) {
        setOpen(false);
      }
    };

    if (typeof drawerQuery.addEventListener === 'function') {
      drawerQuery.addEventListener('change', handleViewportChange);
    } else if (typeof drawerQuery.addListener === 'function') {
      drawerQuery.addListener(handleViewportChange);
    }
  }

  function initReportMenu() {
    document.querySelectorAll('[data-admin-submenu-toggle]').forEach((button) => {
      const target = document.getElementById(button.getAttribute('aria-controls'));
      if (!target) return;

      button.addEventListener('click', () => {
        const isOpen = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', String(!isOpen));
        target.classList.toggle('is-open', !isOpen);
        target.setAttribute('aria-hidden', String(isOpen));
      });
    });
  }

  function initPrintButtons() {
    document.querySelectorAll('[data-admin-print]').forEach((button) => {
      button.addEventListener('click', () => window.print());
    });
  }

  function initLogoutModal() {
    const modal = document.querySelector('[data-admin-logout-modal]');
    const openButtons = document.querySelectorAll('[data-admin-logout-open]');
    if (!modal || !openButtons.length) return;

    const dialog = modal.querySelector('[role="dialog"]');
    const closeButtons = modal.querySelectorAll('[data-admin-logout-close]');
    const form = modal.querySelector('[data-admin-logout-form]');
    const confirmButton = modal.querySelector('[data-admin-logout-confirm]');
    let lastFocusedElement = null;

    const closeModal = () => {
      modal.hidden = true;
      document.body.classList.remove('admin-modal-open');

      if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
        lastFocusedElement.focus();
      }
    };

    const openModal = (trigger) => {
      lastFocusedElement = trigger;
      modal.hidden = false;
      document.body.classList.add('admin-modal-open');

      window.requestAnimationFrame(() => {
        const cancelButton = modal.querySelector('[data-admin-logout-close]');
        if (cancelButton) {
          cancelButton.focus();
        } else if (dialog) {
          dialog.focus();
        }
      });
    };

    openButtons.forEach((button) => {
      button.addEventListener('click', () => openModal(button));
    });

    closeButtons.forEach((button) => {
      button.addEventListener('click', closeModal);
    });

    modal.addEventListener('click', (event) => {
      if (event.target === modal) {
        closeModal();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (!modal.hidden && event.key === 'Escape') {
        closeModal();
      }
    });

    if (form && confirmButton) {
      form.addEventListener('submit', () => {
        confirmButton.disabled = true;
        const label = confirmButton.querySelector('span');

        if (label) {
          label.textContent = 'Signing out...';
        }
      });
    }
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
            ? 'Showing ' + (startIndex + 1) + '-' + endIndex + ' of ' + rows.length + ' activity logs'
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

  document.addEventListener('DOMContentLoaded', () => {
    initThemeToggle();
    initSidebar();
    initReportMenu();
    initPrintButtons();
    initLogoutModal();
    initPaginatedTables();
    refreshIcons();
  });
})();
