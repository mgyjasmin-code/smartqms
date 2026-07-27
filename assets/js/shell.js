/**
 * Shared authenticated shell behavior for Admin, Staff, and Client roles.
 */
(function () {
  'use strict';

  const THEME_KEY = 'smartqms-theme';

  function renderIcons() {
    if (window.lucide?.createIcons) window.lucide.createIcons();
  }

  function setTheme(theme, storeTheme) {
    const normalized = theme === 'dark' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-app-theme', normalized);
    document.documentElement.setAttribute('data-admin-theme', normalized);
    document.documentElement.setAttribute('data-staff-theme', normalized);
    document.querySelectorAll('[data-app-theme-toggle]').forEach(toggle => {
      const next = normalized === 'dark' ? 'light' : 'dark';
      toggle.setAttribute('aria-label', `Switch to ${next} mode`);
      toggle.setAttribute('aria-pressed', String(normalized === 'dark'));
      const label = toggle.querySelector('[data-app-theme-label]');
      const icon = toggle.querySelector('[data-app-theme-icon]');
      if (label) label.textContent = `${next[0].toUpperCase()}${next.slice(1)} mode`;
      if (icon) icon.setAttribute('data-lucide', normalized === 'dark' ? 'sun' : 'moon');
    });
    if (storeTheme) {
      try { window.localStorage.setItem(THEME_KEY, normalized); } catch (error) {}
    }
    document.dispatchEvent(new CustomEvent('smartqms:theme-changed', {
      detail: { theme: normalized }
    }));
    renderIcons();
  }

  function initTheme(root) {
    root.querySelectorAll('[data-app-theme-toggle]').forEach(toggle => {
      if (toggle.dataset.appInitialized === 'true') return;
      toggle.dataset.appInitialized = 'true';
      toggle.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-app-theme') || 'light';
        setTheme(current === 'dark' ? 'light' : 'dark', true);
      });
    });
    setTheme(document.documentElement.getAttribute('data-app-theme') || 'light', false);
  }

  function initSidebar(root) {
    const open = root.querySelector('[data-app-sidebar-toggle]');
    const closeTargets = root.querySelectorAll('[data-app-sidebar-close]');
    if (!open || open.dataset.appInitialized === 'true') return;
    open.dataset.appInitialized = 'true';
    let returnFocus = null;
    const setOpen = isOpen => {
      document.body.classList.toggle('app-sidebar-open', isOpen);
      document.body.classList.toggle('admin-sidebar-open', isOpen);
      open.setAttribute('aria-expanded', String(isOpen));
      if (isOpen) {
        returnFocus = document.activeElement;
        root.querySelector('.app-sidebar-close')?.focus();
      } else if (returnFocus instanceof HTMLElement) {
        returnFocus.focus();
      }
    };
    open.addEventListener('click', () => setOpen(true));
    closeTargets.forEach(target => target.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && document.body.classList.contains('app-sidebar-open')) setOpen(false);
    });
  }

  function initSubmenus(root) {
    root.querySelectorAll('[data-app-submenu-toggle]').forEach(toggle => {
      if (toggle.dataset.appInitialized === 'true') return;
      toggle.dataset.appInitialized = 'true';
      toggle.addEventListener('click', () => {
        const panel = document.getElementById(toggle.getAttribute('aria-controls') || '');
        if (!panel) return;
        const open = toggle.getAttribute('aria-expanded') !== 'true';
        toggle.setAttribute('aria-expanded', String(open));
        panel.classList.toggle('is-open', open);
        panel.setAttribute('aria-hidden', String(!open));
      });
    });
  }

  function initUserMenu(root) {
    const menu = root.querySelector('[data-app-user-menu]');
    const toggle = menu?.querySelector('[data-app-user-menu-toggle]');
    const panel = menu?.querySelector('[data-app-user-menu-panel]');
    if (!menu || !toggle || !panel || menu.dataset.appInitialized === 'true') return;
    menu.dataset.appInitialized = 'true';
    const setOpen = open => {
      menu.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', String(open));
      panel.hidden = !open;
    };
    toggle.addEventListener('click', event => {
      event.stopPropagation();
      setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });
    document.addEventListener('click', event => {
      if (!menu.contains(event.target)) setOpen(false);
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
        setOpen(false);
        toggle.focus();
      }
    });
  }

  function initSearch(root) {
    const collapse = root.querySelector('#appHeaderSearch');
    const form = root.querySelector('[data-app-search]');
    const input = form?.querySelector('[data-app-search-input]');
    const results = form?.querySelector('[data-app-search-results]');
    const clear = form?.querySelector('[data-app-search-clear]');
    const empty = form?.querySelector('[data-app-search-empty]');
    const options = Array.from(form?.querySelectorAll('[data-app-search-option]') || []);
    const toggle = root.querySelector('[data-app-search-toggle]');
    const topbar = root.querySelector('.app-topbar');
    if (!collapse || !form || !input || !results || !toggle || form.dataset.appInitialized === 'true') return;
    form.dataset.appInitialized = 'true';

    const resetResults = () => {
      options.forEach(option => {
        option.hidden = true;
        option.setAttribute('aria-selected', 'false');
      });
      if (empty) empty.hidden = true;
      results.hidden = true;
      input.setAttribute('aria-expanded', 'false');
    };
    const filter = () => {
      const query = input.value.trim().toLowerCase();
      clear.hidden = query === '';
      if (!query) {
        resetResults();
        return;
      }
      let visible = 0;
      options.forEach(option => {
        const match = (option.dataset.appSearchText || '').includes(query);
        option.hidden = !match;
        if (match) visible += 1;
      });
      if (empty) empty.hidden = visible !== 0;
      results.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    };
    input.addEventListener('input', filter);
    clear?.addEventListener('click', () => {
      input.value = '';
      resetResults();
      input.focus();
    });
    collapse.addEventListener('show.bs.collapse', () => {
      topbar?.classList.add('is-search-open');
      toggle.setAttribute('aria-label', 'Close search');
      toggle.querySelector('[data-app-search-toggle-icon]')?.setAttribute('data-lucide', 'x');
      renderIcons();
    });
    collapse.addEventListener('shown.bs.collapse', () => input.focus());
    collapse.addEventListener('hidden.bs.collapse', () => {
      topbar?.classList.remove('is-search-open');
      toggle.setAttribute('aria-label', 'Open search');
      toggle.querySelector('[data-app-search-toggle-icon]')?.setAttribute('data-lucide', 'search');
      input.value = '';
      resetResults();
      renderIcons();
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && collapse.classList.contains('show')) {
        window.bootstrap?.Collapse.getOrCreateInstance(collapse).hide();
        toggle.focus();
      }
    });
  }

  function initToasts(root) {
    root.querySelectorAll('[data-app-action-toast]').forEach(element => {
      if (element.dataset.appInitialized === 'true') return;
      element.dataset.appInitialized = 'true';
      window.bootstrap?.Toast.getOrCreateInstance(element, { delay: 5000 }).show();
    });
  }

  function initShell() {
    const root = document.querySelector('[data-app-root]');
    if (!root || root.dataset.appShellInitialized === 'true') return;
    root.dataset.appShellInitialized = 'true';
    initTheme(root);
    initSidebar(root);
    initSubmenus(root);
    initUserMenu(root);
    initSearch(root);
    initToasts(root);
    renderIcons();
  }

  document.addEventListener('DOMContentLoaded', initShell);
})();
