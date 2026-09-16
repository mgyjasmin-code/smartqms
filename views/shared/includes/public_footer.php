<?php
$footerQueueOpen = isset($conn) && $conn instanceof mysqli ? getSetting($conn, 'queue_open_time', '08:00') : '08:00';
$footerQueueClose = isset($conn) && $conn instanceof mysqli ? getSetting($conn, 'queue_close_time', '15:30') : '15:30';
$footerFormatTime = static function (string $time): string {
    $parsed = DateTimeImmutable::createFromFormat('!H:i', $time);
    return $parsed ? $parsed->format('g:i A') : $time;
};
?>
<footer class="public-footer mt-auto">
  <div class="container public-shell-container py-5">
    <div class="row g-4 align-items-start">
      <div class="col-lg-5">
        <a class="public-brand public-footer-brand" href="<?= APP_URL ?>/">
          <span class="public-brand-mark" aria-hidden="true"><img src="<?= assetUrl('assets/images/brand/smartqms-mark.svg') ?>" alt="" width="36" height="36"></span>
          <span>SmartQMS</span>
        </a>
        <p class="mt-3 mb-0">A privacy-conscious civic healthcare queue. Reserve your visit, arrive, and follow your place in line without creating an account.</p>
      </div>
      <div class="col-6 col-lg-3 offset-lg-1">
        <h2 class="h6">For clients</h2>
        <ul class="list-unstyled public-footer-links">
          <li><a href="<?= APP_URL ?>/queue/join/">Book a Visit</a></li>
          <li><a href="<?= APP_URL ?>/#secure-tracking">Track Queue</a></li>
          <li><a href="<?= APP_URL ?>/manage-reservation/">Manage Reservation</a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-3">
        <h2 class="h6">Operations</h2>
        <ul class="list-unstyled public-footer-links">
          <li><a href="<?= APP_URL ?>/login/">Staff/Admin Login</a></li>
          <li><span>Check-in: <?= htmlspecialchars($footerFormatTime($footerQueueOpen)) ?>–<?= htmlspecialchars($footerFormatTime($footerQueueClose)) ?></span></li>
          <li><span>Public displays never show names</span></li>
        </ul>
      </div>
    </div>
  </div>
  <div class="public-footer-legal">
    <div class="container public-shell-container d-flex flex-column flex-sm-row justify-content-between gap-2 py-3">
      <small>&copy; <?= date('Y') ?> SmartQMS</small>
      <small>Barangay health-center queue service</small>
    </div>
  </div>
</footer>
