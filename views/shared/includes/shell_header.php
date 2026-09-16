<?php
/**
 * Shared authenticated SmartQMS application shell.
 *
 * Role adapters provide $appShell with role-specific navigation and content
 * while this partial owns the common responsive frame.
 */
$appShell = is_array($appShell ?? null) ? $appShell : [];
$appRole = (string) ($appShell['role'] ?? 'user');
$appTitle = (string) ($appShell['title'] ?? 'Smart QMS');
$appHeading = (string) ($appShell['heading'] ?? $appTitle);
$appSubtitle = (string) ($appShell['subtitle'] ?? '');
$appActivePage = (string) ($appShell['active_page'] ?? '');
$appName = (string) ($appShell['name'] ?? ($_SESSION['name'] ?? ucfirst($appRole)));
$appInitial = strtoupper(substr($appName, 0, 1));
$appRoleLabel = (string) ($appShell['role_label'] ?? ucfirst($appRole));
$appShowSidebar = (bool) ($appShell['show_sidebar'] ?? true);
$appBodyClass = trim('app-page admin-page app-' . $appRole . '-page ' . ($appShowSidebar ? '' : 'app-sidebarless ') . (string) ($appShell['body_class'] ?? ''));
$appMainId = (string) ($appShell['main_id'] ?? 'app-main');
$appMainClass = trim('app-main admin-main ' . (string) ($appShell['main_class'] ?? ''));
$appShowPageHeader = (bool) ($appShell['show_page_header'] ?? true);
$appNavItems = is_array($appShell['nav_items'] ?? null) ? $appShell['nav_items'] : [];
$appSearchDestinations = is_array($appShell['search_destinations'] ?? null) ? $appShell['search_destinations'] : [];
$appBodyData = is_array($appShell['body_data'] ?? null) ? $appShell['body_data'] : [];
$appLegacyThemeKey = (string) ($appShell['legacy_theme_key'] ?? '');
$appLogoutTitle = (string) ($appShell['logout_title'] ?? 'Log out of Smart QMS?');
$appLogoutDescription = (string) ($appShell['logout_description'] ?? 'You will need to sign in again to continue.');
$appSearchLabel = (string) ($appShell['search_label'] ?? ('Search ' . $appRoleLabel . ' pages'));
$appSearchPlaceholder = (string) ($appShell['search_placeholder'] ?? 'Search pages');
$appShowSearch = (bool) ($appShell['show_search'] ?? true);
$appStyles = is_array($appShell['styles'] ?? null) ? $appShell['styles'] : [];
$appActiveReport = (string) ($appShell['active_report'] ?? '');
$appRuntimeConfig = function_exists('smartqmsBrowserRuntimeConfig')
    ? smartqmsBrowserRuntimeConfig()
    : ['provider' => 'local', 'supabase' => ['enabled' => false], 'endpoints' => []];
$appRuntimeConfig['role'] = $appRole;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= csrfMetaTag() ?>
  <title><?= htmlspecialchars($appTitle) ?> -- SmartQMS</title>
  <script nonce="<?= htmlspecialchars(SMARTQMS_CSP_NONCE, ENT_QUOTES) ?>">
    (function () {
      try {
        var sharedKey = 'smartqms-theme';
        var legacyKey = <?= json_encode($appLegacyThemeKey) ?>;
        var savedTheme = window.localStorage.getItem(sharedKey);
        if (savedTheme !== 'dark' && savedTheme !== 'light' && legacyKey) {
          savedTheme = window.localStorage.getItem(legacyKey);
        }
        var theme = savedTheme === 'dark' || savedTheme === 'light'
          ? savedTheme
          : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-app-theme', theme);
        document.documentElement.setAttribute('data-admin-theme', theme);
        document.documentElement.setAttribute('data-staff-theme', theme);
        document.documentElement.setAttribute('data-bs-theme', theme);
      } catch (error) {
        document.documentElement.setAttribute('data-app-theme', 'light');
        document.documentElement.setAttribute('data-admin-theme', 'light');
        document.documentElement.setAttribute('data-staff-theme', 'light');
        document.documentElement.setAttribute('data-bs-theme', 'light');
      }
    })();
  </script>
  <link href="<?= assetUrl('vendor/twbs/bootstrap/dist/css/bootstrap.min.css') ?>" rel="stylesheet">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
  <?php if ($appRole === 'admin'): ?>
    <link rel="stylesheet" href="<?= assetUrl('assets/css/admin.css') ?>">
  <?php endif; ?>
  <?php foreach ($appStyles as $appStyle): ?>
    <link rel="stylesheet" href="<?= assetUrl((string) $appStyle) ?>">
  <?php endforeach; ?>
  <script nonce="<?= htmlspecialchars(SMARTQMS_CSP_NONCE, ENT_QUOTES) ?>" id="smartqms-runtime-config" type="application/json"><?= json_encode(
      $appRuntimeConfig,
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
  ) ?></script>
</head>
<body
  class="<?= htmlspecialchars($appBodyClass, ENT_QUOTES) ?>"
  data-app-root
  data-app-role="<?= htmlspecialchars($appRole, ENT_QUOTES) ?>"
  <?= $appRole === 'admin' ? 'data-admin-root' : '' ?>
  <?= $appRole === 'client' ? 'data-client-root' : '' ?>
  <?php foreach ($appBodyData as $dataName => $dataValue): ?>
    data-<?= htmlspecialchars((string) $dataName, ENT_QUOTES) ?>="<?= htmlspecialchars((string) $dataValue, ENT_QUOTES) ?>"
  <?php endforeach; ?>
>
  <a class="app-skip-link admin-skip-link" href="#<?= htmlspecialchars($appMainId, ENT_QUOTES) ?>">Skip to main content</a>
  <div class="app-shell admin-shell"<?= $appRole === 'staff' ? ' data-staff-shell' : '' ?>>
    <?php if ($appShowSidebar): ?>
    <aside class="app-sidebar admin-sidebar" id="app-sidebar" aria-label="<?= htmlspecialchars($appRoleLabel) ?> navigation">
      <div class="app-sidebar-header admin-sidebar-header">
        <a class="app-brand admin-brand" href="<?= htmlspecialchars((string) ($appShell['home_url'] ?? 'index.php')) ?>" aria-label="Smart QMS <?= htmlspecialchars($appRoleLabel) ?> dashboard">
          <span class="app-brand-mark admin-brand-mark" aria-hidden="true">
            <img src="<?= assetUrl('assets/images/brand/smartqms-mark.svg') ?>" alt="" width="32" height="32">
          </span>
          <span class="app-brand-name admin-brand-name">Smart QMS</span>
        </a>
        <button class="app-sidebar-close admin-sidebar-close" type="button" data-app-sidebar-close aria-label="Close <?= htmlspecialchars($appRoleLabel) ?> navigation">
          <i data-lucide="x" aria-hidden="true"></i>
        </button>
      </div>

      <nav class="app-nav admin-nav">
        <p class="app-nav-label admin-nav-label"><?= htmlspecialchars((string) ($appShell['nav_label'] ?? ($appRoleLabel . ' workspace'))) ?></p>
        <?php foreach ($appNavItems as $navIndex => $navItem): ?>
          <?php
          $navKey = (string) ($navItem['key'] ?? '');
          $navChildren = is_array($navItem['children'] ?? null) ? $navItem['children'] : [];
          $navActive = $appActivePage === $navKey;
          ?>
          <?php if ($navChildren): ?>
            <?php $submenuId = 'app-submenu-' . preg_replace('/[^a-z0-9_-]+/i', '-', $navKey ?: (string) $navIndex); ?>
            <button class="app-nav-link admin-nav-link app-nav-toggle admin-nav-toggle<?= $navActive ? ' is-active' : '' ?>" type="button"
                    data-app-submenu-toggle aria-expanded="<?= $navActive ? 'true' : 'false' ?>" aria-controls="<?= htmlspecialchars($submenuId) ?>">
              <i data-lucide="<?= htmlspecialchars((string) ($navItem['icon'] ?? 'folder')) ?>" aria-hidden="true"></i>
              <span><?= htmlspecialchars((string) ($navItem['label'] ?? 'Section')) ?></span>
              <i class="app-nav-chevron admin-nav-chevron" data-lucide="chevron-down" aria-hidden="true"></i>
            </button>
            <div class="app-subnav admin-subnav<?= $navActive ? ' is-open' : '' ?>" id="<?= htmlspecialchars($submenuId) ?>" aria-hidden="<?= $navActive ? 'false' : 'true' ?>">
              <?php foreach ($navChildren as $child): ?>
                <?php $childActive = $appActiveReport !== '' && $appActiveReport === (string) ($child['key'] ?? ''); ?>
                <a class="app-subnav-link admin-subnav-link<?= $childActive ? ' is-active' : '' ?>" href="<?= htmlspecialchars((string) ($child['url'] ?? '#')) ?>"<?= $childActive ? ' aria-current="page"' : '' ?>>
                  <i class="app-subnav-icon admin-subnav-icon" data-lucide="<?= htmlspecialchars((string) ($child['icon'] ?? 'file')) ?>" aria-hidden="true"></i>
                  <span><?= htmlspecialchars((string) ($child['label'] ?? 'Page')) ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <a class="app-nav-link admin-nav-link<?= $navActive ? ' is-active' : '' ?>" href="<?= htmlspecialchars((string) ($navItem['url'] ?? '#')) ?>"<?= $navActive ? ' aria-current="page"' : '' ?><?= ($navItem['target'] ?? '') === '_blank' ? ' target="_blank" rel="noopener"' : '' ?>>
              <i data-lucide="<?= htmlspecialchars((string) ($navItem['icon'] ?? 'circle')) ?>" aria-hidden="true"></i>
              <span><?= htmlspecialchars((string) ($navItem['label'] ?? 'Page')) ?></span>
            </a>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
    </aside>
    <?php endif; ?>

    <div class="app-panel admin-panel">
      <header class="app-topbar admin-topbar">
        <?php if ($appShowSidebar): ?>
        <button class="app-icon-button admin-icon-button app-menu-toggle admin-menu-toggle" type="button" data-app-sidebar-toggle aria-label="Open <?= htmlspecialchars($appRoleLabel) ?> navigation" aria-expanded="false">
          <i data-lucide="menu" aria-hidden="true"></i>
        </button>
        <?php else: ?>
          <a class="app-topbar-brand" href="<?= htmlspecialchars((string) ($appShell['home_url'] ?? 'index.php')) ?>" aria-label="Smart QMS <?= htmlspecialchars($appRoleLabel) ?> home">
            <span class="app-topbar-brand-mark" aria-hidden="true"><img src="<?= assetUrl('assets/images/brand/smartqms-mark.svg') ?>" alt="" width="32" height="32"></span>
            <span>Smart QMS</span>
          </a>
        <?php endif; ?>

        <?php if ($appShowSearch): ?>
        <div class="app-search-collapse admin-header-search-collapse collapse width collapse-horizontal" id="appHeaderSearch">
          <form class="app-header-search admin-header-search" role="search" data-app-search novalidate>
            <label class="visually-hidden" for="app-global-search"><?= htmlspecialchars($appSearchLabel) ?></label>
            <div class="input-group">
              <span class="input-group-text" aria-hidden="true"><i data-lucide="search"></i></span>
              <input class="form-control" id="app-global-search" type="search"
                     placeholder="<?= htmlspecialchars($appSearchPlaceholder, ENT_QUOTES) ?>" autocomplete="off"
                     aria-autocomplete="list" aria-controls="app-search-results" aria-expanded="false" data-app-search-input>
              <button class="btn app-search-clear admin-search-clear" type="button" data-app-search-clear aria-label="Clear search" hidden>
                <i data-lucide="x" aria-hidden="true"></i>
              </button>
            </div>
            <div class="list-group app-search-results admin-search-results" id="app-search-results" role="listbox"
                 aria-label="<?= htmlspecialchars($appRoleLabel) ?> search results" data-app-search-results hidden>
              <?php foreach ($appSearchDestinations as $searchIndex => $destination): ?>
                <a class="list-group-item list-group-item-action app-search-option admin-search-option"
                   id="app-search-option-<?= (int) $searchIndex ?>"
                   href="<?= htmlspecialchars((string) ($destination['url'] ?? '#')) ?>"
                   role="option" aria-selected="false" data-app-search-option
                   data-app-search-text="<?= htmlspecialchars(strtolower((string) ($destination['label'] ?? '') . ' ' . (string) ($destination['description'] ?? '') . ' ' . (string) ($destination['keywords'] ?? '')), ENT_QUOTES) ?>"
                   hidden>
                  <span class="app-search-option-icon admin-search-option-icon" aria-hidden="true">
                    <i data-lucide="<?= htmlspecialchars((string) ($destination['icon'] ?? 'file')) ?>"></i>
                  </span>
                  <span class="app-search-option-copy admin-search-option-copy">
                    <strong><?= htmlspecialchars((string) ($destination['label'] ?? 'Page')) ?></strong>
                    <small><?= htmlspecialchars((string) ($destination['description'] ?? '')) ?></small>
                  </span>
                  <span class="app-search-option-type admin-search-option-type"><?= htmlspecialchars((string) ($destination['type'] ?? 'Page')) ?></span>
                </a>
              <?php endforeach; ?>
              <p class="app-search-empty admin-search-empty" data-app-search-empty hidden>No matching page.</p>
            </div>
          </form>
        </div>
        <?php endif; ?>

        <div class="app-topbar-actions admin-topbar-actions">
          <?php if ($appShowSearch): ?>
          <button class="app-icon-button admin-icon-button app-search-toggle admin-search-toggle" type="button"
                  data-bs-toggle="collapse" data-bs-target="#appHeaderSearch" aria-controls="appHeaderSearch"
                  aria-expanded="false" aria-label="Open <?= htmlspecialchars($appRoleLabel) ?> search" data-app-search-toggle>
            <i data-app-search-toggle-icon data-lucide="search" aria-hidden="true"></i>
          </button>
          <?php endif; ?>

          <?php if ($appRole === 'client'): ?>
            <div class="dropdown app-notification-center" data-client-notification-center>
              <button class="app-icon-button admin-icon-button position-relative" type="button" data-bs-toggle="dropdown"
                      aria-expanded="false" aria-label="Open notifications" data-client-notification-toggle>
                <i data-lucide="bell" aria-hidden="true"></i>
                <span class="app-notification-badge" data-client-notification-badge hidden>0</span>
              </button>
              <div class="dropdown-menu dropdown-menu-end app-notification-menu" aria-label="Notifications">
                <div class="app-notification-header">
                  <div><strong>Notifications</strong><small>Latest queue updates</small></div>
                  <button class="btn app-notification-retry" type="button" data-client-notification-retry hidden>Retry</button>
                </div>
                <div class="app-notification-list" data-client-notification-list aria-live="polite">
                  <p class="app-notification-state">Loading notifications...</p>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($appRole === 'staff'): ?>
            <div class="dropdown app-staff-tools">
              <button class="btn btn-outline-primary staff-tools-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i data-lucide="wrench" aria-hidden="true"></i><span>Tools</span><i data-lucide="chevron-down" aria-hidden="true"></i>
              </button>
              <ul class="dropdown-menu dropdown-menu-end staff-tools-menu">
                <li><a class="dropdown-item" href="<?= APP_URL ?>/staff/check-in/"><i data-lucide="scan-line" aria-hidden="true"></i>Arrival Check-In</a></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/staff/batch-printing/"><i data-lucide="printer" aria-hidden="true"></i>Batch Printing</a></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/public-display/" target="_blank" rel="noopener"><i data-lucide="presentation" aria-hidden="true"></i>Public Display</a></li>
              </ul>
            </div>
          <?php endif; ?>

          <div class="app-user-menu admin-user-menu" data-app-user-menu>
            <button class="app-user-button admin-user-button" type="button" data-app-user-menu-toggle aria-expanded="false"
                    aria-controls="app-user-menu-panel" aria-label="Open profile menu for <?= htmlspecialchars($appName, ENT_QUOTES) ?>">
              <span class="app-user-avatar admin-user-avatar" aria-hidden="true"><?= htmlspecialchars($appInitial) ?></span>
              <span class="app-user-copy admin-user-copy"><strong><?= htmlspecialchars($appName) ?></strong><small><?= htmlspecialchars($appRoleLabel) ?></small></span>
              <i data-lucide="chevron-down" aria-hidden="true"></i>
            </button>
            <div class="app-user-dropdown admin-user-dropdown" id="app-user-menu-panel" data-app-user-menu-panel hidden>
              <div class="app-user-dropdown-head admin-user-dropdown-head">
                <span class="app-user-avatar admin-user-avatar" aria-hidden="true"><?= htmlspecialchars($appInitial) ?></span>
                <div><strong><?= htmlspecialchars($appName) ?></strong><small><?= htmlspecialchars($appRoleLabel) ?></small></div>
              </div>
              <div class="app-user-theme-switch admin-user-theme-switch form-check form-switch">
                <label class="form-check-label" for="app-theme-switch">
                  <i data-app-theme-icon data-lucide="moon" aria-hidden="true"></i>
                  <span data-app-theme-label>Dark mode</span>
                </label>
                <input class="form-check-input" type="checkbox" role="switch" id="app-theme-switch"
                       data-app-theme-toggle aria-label="Dark mode">
              </div>
              <?php if ($appRole === 'client'): ?>
                <div class="app-user-language admin-user-language">
                  <?php require __DIR__ . '/language_control.php'; ?>
                </div>
              <?php endif; ?>
              <button class="app-user-menu-item admin-user-menu-item is-danger" type="button"
                      data-bs-toggle="modal" data-bs-target="#appLogoutModal" aria-controls="appLogoutModal">
                <i data-lucide="log-out" aria-hidden="true"></i><span>Logout</span>
              </button>
            </div>
          </div>
        </div>
      </header>

      <main id="<?= htmlspecialchars($appMainId, ENT_QUOTES) ?>" class="<?= htmlspecialchars($appMainClass, ENT_QUOTES) ?>" tabindex="-1">
        <?php if ($appShowPageHeader): ?>
          <section class="app-page-header admin-page-header">
            <h1><?= htmlspecialchars($appHeading) ?></h1>
            <?php if ($appSubtitle !== ''): ?><p><?= htmlspecialchars($appSubtitle) ?></p><?php endif; ?>
          </section>
        <?php endif; ?>
