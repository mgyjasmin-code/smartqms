(() => {
  const frame = document.getElementById('preview');
  const screen = document.getElementById('screen');
  const width = document.getElementById('width');
  const theme = document.getElementById('theme');
  let phase = 'after';
  const notes = {
    landing: 'Booking leads the page, followed by private tracking, arrival steps, and available services.',
    tracker: 'The queue number, people ahead, and waiting estimate stay together. On phones, the QR section follows the ticket.',
    staff: 'The current ticket comes before summary metrics. Recall, Start, Skip, and Void have visible labels outside the scrolling table.',
    admin: 'Comparable metrics lead the overview. Each chart has an expandable table of exact values.'
  };
  function applyTheme() {
    const doc = frame.contentDocument;
    if (!doc) return;
    for (const attribute of ['appTheme', 'adminTheme', 'staffTheme', 'bsTheme']) doc.documentElement.dataset[attribute] = theme.value;
    doc.dispatchEvent(new CustomEvent('smartqms:theme-changed', {detail:{theme:theme.value}}));
  }
  function resize() { frame.style.width = width.value + 'px'; frame.style.height = Number(width.value) < 768 ? '844px' : '1000px'; }
  function render() {
    const url = phase + '/' + screen.value + '.html';
    frame.src = url;
    frame.title = screen.selectedOptions[0].textContent + ', ' + phase + ' redesign';
    document.getElementById('open-preview').href = url;
    document.getElementById('screen-note').textContent = notes[screen.value];
    document.querySelectorAll('[data-phase]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.phase === phase)));
    document.getElementById('screenshots').innerHTML = ['desktop','mobile'].flatMap(size => ['before','after'].map(version => {
      const file = 'screenshots/' + screen.value + '-' + version + '-' + size + '.png';
      const label = `${version === 'before' ? 'Before' : 'After'} · ${size === 'desktop' ? '1440px desktop' : '390px mobile'}`;
      return `<figure><a href="${file}" target="_blank" rel="noopener"><img src="${file}" alt="${screen.selectedOptions[0].textContent}, ${label}" loading="lazy"></a><figcaption>${label}</figcaption></figure>`;
    })).join('');
    resize();
  }
  frame.addEventListener('load', applyTheme);
  theme.addEventListener('change', applyTheme);
  width.addEventListener('change', resize);
  screen.addEventListener('change', render);
  document.querySelectorAll('[data-phase]').forEach(button => button.addEventListener('click', () => { phase = button.dataset.phase; render(); }));
  render();
})();
