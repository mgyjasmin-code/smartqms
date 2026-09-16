<script nonce="<?= htmlspecialchars(SMARTQMS_CSP_NONCE, ENT_QUOTES) ?>">
  (function () {
    try {
      var saved = window.localStorage.getItem('smartqms-theme');
      var theme = saved === 'dark' || saved === 'light'
        ? saved
        : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-app-theme', theme);
      document.documentElement.setAttribute('data-bs-theme', theme);
    } catch (error) {
      document.documentElement.setAttribute('data-app-theme', 'light');
      document.documentElement.setAttribute('data-bs-theme', 'light');
    }
  })();
</script>
