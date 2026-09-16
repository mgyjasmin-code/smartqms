(() => {
  lucide.createIcons();
  const notice = () => {
    document.querySelector('.review-message')?.remove();
    const message = document.createElement('p');
    message.className = 'review-message';
    message.setAttribute('role', 'status');
    message.textContent = 'This is a visual review with sample data. Queue actions are available in the application.';
    document.body.append(message);
    setTimeout(() => message.remove(), 4000);
  };
  document.addEventListener('submit', event => { event.preventDefault(); notice(); });
  document.addEventListener('click', event => {
    if (event.target.closest('[data-review-action], [data-app-logout], [data-admin-logout]')) { event.preventDefault(); notice(); }
    const link = event.target.closest('a');
    if (link && !link.getAttribute('href').startsWith('#')) { event.preventDefault(); notice(); }
  });
})();
