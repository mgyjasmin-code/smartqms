<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/modules/queue/public_intake.php';

expireScheduledQueueTickets($conn);
$services = smartqmsPublicQueueSchemaReady($conn) ? publicQueueServices($conn) : [];
$queueOpen = getSetting($conn, 'queue_open_time', '08:00');
$queueClose = getSetting($conn, 'queue_close_time', '15:30');
$healthCenterName = getSetting($conn, 'bhc_name', 'Barangay Health Center');
$healthCenterContact = getSetting($conn, 'bhc_contact', 'Ask the front desk for assistance');
$formatTime = static fn(string $time): string => date('g:i A', strtotime($time));
$publicActivePage = 'home';
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Reserve a visit and securely follow your barangay health-center queue.">
  <title>SmartQMS — <?= htmlspecialchars($healthCenterName) ?></title>
  <?php require __DIR__ . '/views/shared/includes/theme_boot.php'; ?>
  <link rel="stylesheet" href="<?= assetUrl('vendor/twbs/bootstrap/dist/css/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
</head>
<body class="public-journey-page public-landing-page d-flex flex-column min-vh-100">
  <a class="skip-link" href="#main-content">Skip to main content</a>
  <?php require __DIR__ . '/views/shared/includes/public_header.php'; ?>
  <main id="main-content" tabindex="-1">
    <section class="public-hero public-landing-hero" aria-labelledby="public-hero-title">
      <div class="container public-shell-container"><div class="row align-items-center g-4 g-xl-5">
        <div class="col-lg-7">
          <p class="public-kicker"><i data-lucide="heart-pulse" aria-hidden="true"></i> <?= htmlspecialchars($healthCenterName) ?></p>
          <h1 id="public-hero-title">Plan your visit. Check in. Follow your place.</h1>
          <p class="public-hero-copy">Reserve a health-center visit without creating an account. Your fair FIFO position begins only after staff confirms your arrival.</p>
          <div class="public-hero-actions d-flex flex-column flex-sm-row gap-3"><a class="btn btn-primary btn-lg" href="<?= APP_URL ?>/queue/join/"><i data-lucide="calendar-plus" aria-hidden="true"></i> Book a Visit</a><a class="btn btn-outline-primary btn-lg" href="#secure-tracking"><i data-lucide="shield-check" aria-hidden="true"></i> Secure Tracking</a></div>
        </div>
        <div class="col-lg-5"><aside class="card public-before-visit" aria-labelledby="before-visit-title"><div class="card-body p-4 p-xl-5">
          <p class="public-kicker mb-2">Before you visit</p><h2 class="h3" id="before-visit-title">What to prepare</h2>
          <dl class="public-fact-list mb-0"><div><dt><i data-lucide="clock-3" aria-hidden="true"></i> Check-in hours</dt><dd><?= htmlspecialchars($formatTime($queueOpen)) ?>–<?= htmlspecialchars($formatTime($queueClose)) ?></dd></div><div><dt><i data-lucide="contact" aria-hidden="true"></i> Bring</dt><dd>Your private QR or tracking link and the information required for your service.</dd></div><div><dt><i data-lucide="stethoscope" aria-hidden="true"></i> Available now</dt><dd><?= count($services) ?> configured service<?= count($services) === 1 ? '' : 's' ?></dd></div></dl>
        </div></aside></div>
      </div></div>
    </section>

    <section id="secure-tracking" class="public-section public-secure-tracking" aria-labelledby="tracking-title"><div class="container public-form-container"><div class="card public-lookup-card"><div class="card-body p-4 p-md-5"><div class="row align-items-center g-4">
      <div class="col-lg-5"><p class="public-kicker">Private status</p><h2 id="tracking-title">Open your secure tracker</h2><p class="mb-0">Paste the private token from your confirmation link. Sequential reference numbers no longer reveal queue details.</p></div>
      <div class="col-lg-7"><form method="get" action="<?= APP_URL ?>/track/" class="row g-2 js-validated-form"><div class="col-sm"><label class="form-label" for="tracking-token">Private tracking token</label><input class="form-control form-control-lg" id="tracking-token" name="token" pattern="[a-fA-F0-9]{32}|[a-fA-F0-9]{64}" maxlength="64" autocomplete="off" required aria-describedby="tracking-token-help"><div class="form-text" id="tracking-token-help">Use the token after <strong>token=</strong> in your private link.</div></div><div class="col-sm-auto d-grid align-self-end"><button class="btn btn-primary btn-lg" type="submit"><i data-lucide="arrow-right" aria-hidden="true"></i> Open Tracker</button></div></form></div>
    </div></div></div></div></section>

    <section class="public-section public-how-section" aria-labelledby="journey-title"><div class="container public-shell-container"><div class="public-section-heading text-center mx-auto"><p class="public-kicker">Your visit</p><h2 id="journey-title">Three clear steps</h2><p>Booking reserves a date. Physical check-in establishes the queue order.</p></div><ol class="row g-3 g-lg-4 mt-3 list-unstyled public-journey-steps">
      <?php foreach ([['calendar-days', 'Reserve a date', 'Choose an available service and visit date. No account is required.'], ['scan-line', 'Check in on arrival', 'Present your private QR or reference to staff during check-in hours.'], ['list-ordered', 'Follow the live queue', 'Use your private tracker; public boards show queue numbers, never client names.']] as $stepIndex => [$icon, $title, $copy]): ?><li class="col-md-4"><article class="card public-process-card h-100"><div class="card-body p-4"><span class="public-step-number"><?= $stepIndex + 1 ?></span><span class="public-icon-tile"><i data-lucide="<?= $icon ?>" aria-hidden="true"></i></span><h3 class="h5 mt-3"><?= htmlspecialchars($title) ?></h3><p class="mb-0"><?= htmlspecialchars($copy) ?></p></div></article></li><?php endforeach; ?>
    </ol></div></section>

    <section class="public-section public-services-section" aria-labelledby="services-title"><div class="container public-shell-container"><div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3"><div class="public-section-heading mb-0"><p class="public-kicker">Service directory</p><h2 id="services-title">Active health services</h2><p class="mb-0">This list comes from the health center’s current configuration.</p></div><a class="public-arrow-link" href="<?= APP_URL ?>/queue/join/">Choose a service <i data-lucide="arrow-right" aria-hidden="true"></i></a></div>
      <?php if ($services): ?><ul class="public-service-directory list-unstyled mt-4 mb-0"><?php foreach ($services as $service): ?><li><i data-lucide="circle-check" aria-hidden="true"></i><span><strong><?= htmlspecialchars($service['service_name']) ?></strong><?php if (!empty($service['description'])): ?><small><?= htmlspecialchars($service['description']) ?></small><?php endif; ?></span></li><?php endforeach; ?></ul><?php else: ?><div class="alert alert-info mt-4" role="status">Service information is temporarily unavailable. Please contact the health center.</div><?php endif; ?>
    </div></section>

    <section class="public-section public-visit-privacy" aria-labelledby="privacy-title"><div class="container public-shell-container"><div class="row g-4 align-items-start"><div class="col-lg-5"><p class="public-kicker">Prepared and private</p><h2 id="privacy-title">Know what happens to your information</h2><p>SmartQMS uses your contact details to manage the visit. Names are restricted to staff workflows and never shown on the public display.</p><p class="mb-0"><strong>Need help?</strong> <?= htmlspecialchars($healthCenterContact) ?></p></div><div class="col-lg-7"><ul class="public-privacy-list list-unstyled mb-0"><li><i data-lucide="shield-check" aria-hidden="true"></i><span><strong>Private links</strong> protect tracking and reservation changes.</span></li><li><i data-lucide="list-ordered" aria-hidden="true"></i><span><strong>Strict FIFO</strong> begins at confirmed physical check-in.</span></li><li><i data-lucide="accessibility" aria-hidden="true"></i><span><strong>Accessible controls</strong> support keyboard use, clear focus, and reduced motion.</span></li></ul></div></div></div></section>

    <section class="public-section public-faq-section" aria-labelledby="faq-title"><div class="container public-form-container"><div class="public-section-heading text-center mx-auto"><p class="public-kicker">Frequently asked questions</p><h2 id="faq-title">Quick answers</h2></div><div class="accordion mt-4" id="public-faq">
      <?php foreach ([['Do I need an account?', 'No. Public booking is account-free. Keep your private tracking and management links.'], ['When do I receive a queue number?', 'Staff assigns it after confirming that you arrived on the reserved date.'], ['Can I change a reservation?', 'Yes, while it remains scheduled. Use the private management link from your confirmation.']] as $faqIndex => [$question, $answer]): ?><div class="accordion-item"><h3 class="accordion-header" id="faq-heading-<?= $faqIndex ?>"><button class="accordion-button<?= $faqIndex === 0 ? '' : ' collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq-panel-<?= $faqIndex ?>" aria-expanded="<?= $faqIndex === 0 ? 'true' : 'false' ?>" aria-controls="faq-panel-<?= $faqIndex ?>"><?= htmlspecialchars($question) ?></button></h3><div id="faq-panel-<?= $faqIndex ?>" class="accordion-collapse collapse<?= $faqIndex === 0 ? ' show' : '' ?>" aria-labelledby="faq-heading-<?= $faqIndex ?>" data-bs-parent="#public-faq"><div class="accordion-body"><?= htmlspecialchars($answer) ?></div></div></div><?php endforeach; ?>
    </div></div></section>
  </main>
  <?php require __DIR__ . '/views/shared/includes/public_footer.php'; ?>
  <script src="<?= assetUrl('vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script><script src="<?= assetUrl('assets/vendor/lucide/lucide.min.js') ?>"></script><script src="<?= assetUrl('assets/js/theme.js') ?>"></script><script src="<?= assetUrl('assets/js/language.js') ?>"></script><script src="<?= assetUrl('assets/js/main.js') ?>"></script>
</body>
</html>
