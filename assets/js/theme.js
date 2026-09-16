(function () {
  'use strict';

  var STORAGE_KEY = 'smartqms-theme';

  function currentTheme() {
    return document.documentElement.getAttribute('data-app-theme') === 'dark' ? 'dark' : 'light';
  }

  function render(button, theme) {
    var dark = theme === 'dark';
    button.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
    button.setAttribute('aria-pressed', dark ? 'true' : 'false');
    var path = button.querySelector('[data-sq-theme-icon]');
    if (path) {
      path.setAttribute('d', dark
        ? 'M12 3v2m0 14v2M3 12h2m14 0h2m-3.64-6.36-1.42 1.42M8.06 15.94l-1.42 1.42m10.72 0-1.42-1.42M8.06 8.06 6.64 6.64M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z'
        : 'M12 3a6 6 0 1 0 9 9 9 9 0 1 1-9-9Z');
    }
  }

  function apply(theme) {
    document.documentElement.setAttribute('data-app-theme', theme);
    document.documentElement.setAttribute('data-bs-theme', theme);
    document.querySelectorAll('[data-sq-theme-toggle]').forEach(function (button) {
      render(button, theme);
    });
  }

  function initialize() {
    if (document.documentElement.dataset.sqThemeReady === 'true') return;
    document.documentElement.dataset.sqThemeReady = 'true';
    apply(currentTheme());
    document.addEventListener('click', function (event) {
      var button = event.target.closest('[data-sq-theme-toggle]');
      if (!button) return;
      var next = currentTheme() === 'dark' ? 'light' : 'dark';
      try { window.localStorage.setItem(STORAGE_KEY, next); } catch (error) {}
      apply(next);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
})();
