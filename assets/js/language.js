(() => {
  'use strict';

  const storageKey = 'smartqms-language';
  const supported = new Set(['en', 'fil']);

  const savedLanguage = () => {
    try {
      const saved = window.localStorage.getItem(storageKey);
      if (supported.has(saved)) return saved;
    } catch (_) {}
    return 'en';
  };

  const applyLanguage = (language) => {
    const nextLanguage = supported.has(language) ? language : 'en';
    document.documentElement.lang = nextLanguage;
    document.querySelectorAll('[data-sq-language]').forEach((control) => {
      control.value = nextLanguage;
      control.setAttribute('aria-label', nextLanguage === 'fil' ? 'Wika: Filipino' : 'Language: English');
    });
    document.querySelectorAll('[data-i18n-en]').forEach((element) => {
      const english = element.dataset.i18nEn || '';
      const filipino = element.dataset.i18nFil || english;
      element.textContent = nextLanguage === 'fil' ? filipino : english;
    });
    window.dispatchEvent(new CustomEvent('smartqms:languagechange', { detail: { language: nextLanguage } }));
  };

  const initialize = () => {
    if (document.documentElement.dataset.sqLanguageReady === 'true') return;
    document.documentElement.dataset.sqLanguageReady = 'true';
    applyLanguage(savedLanguage());
    document.addEventListener('change', (event) => {
      const control = event.target.closest('[data-sq-language]');
      if (!control) return;
      const language = supported.has(control.value) ? control.value : 'en';
      try { window.localStorage.setItem(storageKey, language); } catch (_) {}
      applyLanguage(language);
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize, { once: true });
  } else {
    initialize();
  }
})();
