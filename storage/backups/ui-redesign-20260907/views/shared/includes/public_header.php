<?php
/**
 * Shared public navigation.
 *
 * Expected variables:
 * - $publicActivePage: home|book|track|manage|login
 */
$publicActivePage = in_array(($publicActivePage ?? 'home'), ['home', 'book', 'track', 'manage', 'login'], true)
    ? $publicActivePage
    : 'home';
$publicNavItems = [
    'home' => ['Home', APP_URL . '/'],
    'book' => ['Book a Visit', APP_URL . '/queue/join/'],
    'track' => ['Track Queue', APP_URL . '/#secure-tracking'],
    'manage' => ['Manage Reservation', APP_URL . '/manage-reservation/'],
];
?>
<nav class="navbar navbar-expand-xl public-navbar sticky-top" aria-label="Public navigation">
  <div class="container public-shell-container">
    <a class="navbar-brand public-brand" href="<?= APP_URL ?>/" aria-label="SmartQMS home">
      <span class="public-brand-mark" aria-hidden="true"><img src="<?= assetUrl('assets/images/brand/smartqms-mark.svg') ?>" alt="" width="36" height="36"></span>
      <span>SmartQMS</span>
    </a>

    <div class="public-navbar-priority d-flex align-items-center gap-2 ms-auto d-xl-none">
      <a class="btn btn-primary btn-sm" href="<?= APP_URL ?>/queue/join/">Book</a>
      <a class="btn btn-outline-primary btn-sm" href="<?= APP_URL ?>/#secure-tracking">Track</a>
    </div>

    <button class="navbar-toggler public-navbar-toggler ms-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#publicNavigation" aria-controls="publicNavigation" aria-label="Open navigation">
      <i data-lucide="menu" aria-hidden="true"></i>
    </button>

    <div class="offcanvas offcanvas-end public-nav-offcanvas" tabindex="-1" id="publicNavigation" aria-labelledby="public-navigation-title">
      <div class="offcanvas-header">
        <h2 class="offcanvas-title h5 mb-0" id="public-navigation-title">SmartQMS navigation</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close navigation"></button>
      </div>
      <div class="offcanvas-body align-items-xl-center">
        <ul class="navbar-nav public-nav-list mx-xl-auto mb-3 mb-xl-0">
          <?php foreach ($publicNavItems as $page => [$label, $href]): ?>
            <li class="nav-item">
              <a class="nav-link<?= $publicActivePage === $page ? ' active' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES) ?>"<?= $publicActivePage === $page ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($label) ?></a>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="public-nav-tools d-flex flex-column flex-xl-row align-items-stretch align-items-xl-center gap-2">
          <?php require __DIR__ . '/language_control.php'; ?>
          <?php require __DIR__ . '/public_theme_toggle.php'; ?>
          <a class="btn <?= $publicActivePage === 'login' ? 'btn-secondary' : 'btn-outline-secondary' ?>" href="<?= APP_URL ?>/login/"<?= $publicActivePage === 'login' ? ' aria-current="page"' : '' ?>>Staff/Admin Login</a>
        </div>
      </div>
    </div>
  </div>
</nav>
