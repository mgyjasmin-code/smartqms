(() => {
  const root = document.documentElement;
  const device = document.querySelector('[data-gallery-device]');
  const screenSelect = document.querySelector('[data-gallery-screen]');
  const stateSelect = document.querySelector('[data-gallery-state]');
  const themeButton = document.querySelector('[data-gallery-theme]');
  const stateOverlay = document.querySelector('[data-state-overlay]');
  const stateTitle = document.querySelector('[data-state-title]');
  const stateCopy = document.querySelector('[data-state-copy]');
  const stateMessages = {
    empty: ['No records yet', 'The primary next action remains available in this empty state.'],
    loading: ['Loading current information', 'Controls stay stable while authoritative data is requested.'],
    error: ['Information could not be loaded', 'A clear retry path is shown without losing entered values.']
  };

  screenSelect.addEventListener('change', () => {
    document.querySelectorAll('[data-screen]').forEach((screen) => {
      screen.hidden = screen.dataset.screen !== screenSelect.value;
    });
  });

  document.querySelectorAll('[data-gallery-width]').forEach((button) => {
    button.addEventListener('click', () => {
      device.style.setProperty('--preview-width', `${button.dataset.galleryWidth}px`);
      document.querySelectorAll('[data-gallery-width]').forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
    });
  });

  themeButton.addEventListener('click', () => {
    const dark = root.getAttribute('data-gallery-theme') !== 'dark';
    root.setAttribute('data-gallery-theme', dark ? 'dark' : 'light');
    themeButton.setAttribute('aria-pressed', String(dark));
    themeButton.textContent = dark ? 'Light theme' : 'Dark theme';
  });

  stateSelect.addEventListener('change', () => {
    const state = stateSelect.value;
    stateOverlay.hidden = state === 'default';
    if (stateMessages[state]) {
      [stateTitle.textContent, stateCopy.textContent] = stateMessages[state];
    }
  });
})();
