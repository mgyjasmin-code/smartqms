<?php
$pageTitle = $pageTitle ?? 'Admin Dashboard';
$pageHeading = $pageHeading ?? $pageTitle;
$pageSubtitle = $pageSubtitle ?? '';
$activePage = $activePage ?? '';
$activeReport = $activeReport ?? '';
$adminBodyClass = trim('admin-page ' . ($adminBodyClass ?? ''));
$adminName = $_SESSION['name'] ?? 'Administrator';
$adminInitial = strtoupper(substr((string) $adminName, 0, 1));
$adminRoleLabel = 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= csrfMetaTag() ?>
  <title><?= htmlspecialchars($pageTitle) ?> -- SmartQMS</title>
  <script>
    (function () {
      try {
        var key = 'smartqms-admin-theme';
        var savedTheme = window.localStorage.getItem(key);
        var theme = savedTheme === 'dark' || savedTheme === 'light'
          ? savedTheme
          : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-admin-theme', theme);
      } catch (error) {
        document.documentElement.setAttribute('data-admin-theme', 'light');
      }
    })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= assetUrl('vendor/twbs/bootstrap/dist/css/bootstrap.min.css') ?>" rel="stylesheet">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/admin.css') ?>">
</head>
<body class="<?= htmlspecialchars($adminBodyClass, ENT_QUOTES) ?>" data-admin-root>
  <a class="admin-skip-link" href="#admin-main">Skip to main content</a>
  <div class="admin-shell">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="admin-panel">
      <header class="admin-topbar">
        <button class="admin-icon-button admin-menu-toggle" type="button" data-admin-sidebar-toggle aria-label="Open admin navigation" aria-expanded="false">
          <i data-lucide="menu" aria-hidden="true"></i>
        </button>

        <div class="admin-topbar-actions">
          <button class="admin-icon-button admin-theme-toggle" type="button" data-admin-theme-toggle aria-label="Switch to dark mode" aria-pressed="false">
            <i data-admin-theme-icon data-lucide="moon" aria-hidden="true"></i>
          </button>
          <div class="admin-user-menu" data-admin-user-menu>
            <button class="admin-user-button" type="button" data-admin-user-menu-toggle aria-expanded="false" aria-controls="admin-user-menu-panel">
              <span class="admin-user-avatar" aria-hidden="true"><?= htmlspecialchars($adminInitial) ?></span>
              <span class="admin-user-copy">
                <strong><?= htmlspecialchars($adminName) ?></strong>
                <small><?= htmlspecialchars($adminRoleLabel) ?></small>
              </span>
              <i data-lucide="chevron-down" aria-hidden="true"></i>
            </button>
            <div class="admin-user-dropdown" id="admin-user-menu-panel" data-admin-user-menu-panel hidden>
              <div class="admin-user-dropdown-head">
                <span class="admin-user-avatar" aria-hidden="true"><?= htmlspecialchars($adminInitial) ?></span>
                <div>
                  <strong><?= htmlspecialchars($adminName) ?></strong>
                  <small><?= htmlspecialchars($adminRoleLabel) ?></small>
                </div>
              </div>
              <button
                class="admin-user-menu-item is-danger"
                type="button"
                data-admin-logout-open
                data-bs-toggle="modal"
                data-bs-target="#adminLogoutModal"
                aria-controls="adminLogoutModal"
              >
                <i data-lucide="log-out" aria-hidden="true"></i>
                <span>Logout</span>
              </button>
            </div>
          </div>
        </div>
      </header>

      <main id="admin-main" class="admin-main" tabindex="-1">
        <section class="admin-page-header">
          <h1><?= htmlspecialchars($pageHeading) ?></h1>
          <?php if ($pageSubtitle !== ''): ?>
            <p><?= htmlspecialchars($pageSubtitle) ?></p>
          <?php endif; ?>
        </section>
