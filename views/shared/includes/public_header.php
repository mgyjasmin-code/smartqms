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

if ($publicActivePage === 'home') {
    $publicNavItems = [
        'home' => ['Home', APP_URL . '/'],
        'how-it-works' => ['How it works', '#how-it-works'],
        'services' => ['Services', '#services'],
        'faq' => ['FAQ', '#faq'],
    ];
} else {
    $publicNavItems = [
        'home' => ['Home', APP_URL . '/'],
        'book' => ['Book a Visit', APP_URL . '/queue/join/'],
        'track' => ['Track Queue', APP_URL . '/#secure-tracking'],
    ];
}
?>
<nav class="navbar navbar-expand-xl public-navbar sticky-top" aria-label="Public navigation">
  <div class="container public-shell-container">
    <a class="navbar-brand public-brand" href="<?= APP_URL ?>/" aria-label="SmartQMS home">
      <span class="public-brand-mark" aria-hidden="true"><img src="<?= assetUrl('assets/images/brand/smartqms-mark.svg') ?>" alt="" width="36" height="36"></span>
      <span>SmartQMS</span>
    </a>

    <button class="navbar-toggler public-navbar-toggler ms-auto" type="button" data-bs-toggle="offcanvas" data-bs-target="#publicNavigation" aria-controls="publicNavigation" aria-label="Open navigation">
      <i data-lucide="menu" aria-hidden="true"></i>
    </button>

    <div class="offcanvas offcanvas-end public-nav-offcanvas" tabindex="-1" id="publicNavigation" aria-label="Public navigation menu">
      <div class="offcanvas-header">
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="offcanvas" aria-label="Close navigation"></button>
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
          <?php require __DIR__ . '/public_theme_toggle.php'; ?>
          <a class="btn btn-lg <?= $publicActivePage === 'login' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= APP_URL ?>/login/"<?= $publicActivePage === 'login' ? ' aria-current="page"' : '' ?>>Login</a>
          <a class="btn btn-lg btn-primary" href="<?= APP_URL ?>/queue/join/">Book a Visit</a>
        </div>
      </div>
    </div>
  </div>
</nav>
