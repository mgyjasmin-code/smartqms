<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/modules/queue/public_intake.php';

expireScheduledQueueTickets($conn);
$services = smartqmsPublicQueueSchemaReady($conn) ? publicQueueServices($conn) : [];
$queueOpen = getSetting($conn, 'queue_open_time', '08:00');
$queueClose = getSetting($conn, 'queue_close_time', '15:30');
$healthCenterName = getSetting($conn, 'bhc_name', 'Barangay Health Center');
$healthCenterContact = trim(getSetting($conn, 'bhc_contact', '')) ?: 'Ask the front desk for assistance';
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
          <p class="public-center-name"><i data-lucide="heart-pulse" aria-hidden="true"></i> <?= htmlspecialchars($healthCenterName) ?></p>
          <h1 id="public-hero-title">Your next visit,<br>made simpler.</h1>
          <p class="public-hero-copy">Book a date for your health-center visit. Check in when you arrive, then follow your place in line.</p>
          <div class="public-hero-actions d-flex flex-column flex-sm-row gap-3"><a class="btn btn-primary btn-lg" href="<?= APP_URL ?>/queue/join/"><i data-lucide="calendar-plus" aria-hidden="true"></i> Book a Visit</a><a class="btn btn-outline-primary btn-lg" href="#secure-tracking"><i data-lucide="scan-line" aria-hidden="true"></i> Track My Queue</a></div>
          <p class="public-hero-assurance"><i data-lucide="circle-check" aria-hidden="true"></i> No account needed. Your queue number is assigned at check-in.</p>
        </div>
        <div class="col-lg-5"><aside class="card public-before-visit" aria-labelledby="before-visit-title"><div class="card-body p-4 p-xl-5">
          <span class="public-visit-symbol" aria-hidden="true"><i data-lucide="calendar-check-2"></i></span><h2 class="h3" id="before-visit-title">Before your visit</h2>
          <dl class="public-fact-list mb-0"><div><dt><i data-lucide="clock-3" aria-hidden="true"></i> Check-in hours</dt><dd><?= htmlspecialchars($formatTime($queueOpen)) ?>–<?= htmlspecialchars($formatTime($queueClose)) ?></dd></div><div><dt><i data-lucide="scan-line" aria-hidden="true"></i> Keep your confirmation</dt><dd>Bring your booking reference or private QR code and the information required for your service.</dd></div><div><dt><i data-lucide="list-ordered" aria-hidden="true"></i> On arrival</dt><dd>Staff will check you in and give you your queue number.</dd></div></dl>
        </div></aside></div>
      </div></div>
    </section>

    <section id="secure-tracking" class="public-section public-secure-tracking" aria-labelledby="tracking-title"><div class="container public-form-container"><div class="card public-lookup-card"><div class="card-body p-4 p-md-5"><div class="row align-items-center g-4">
      <div class="col-lg-5"><h2 id="tracking-title">Already checked in?</h2><p class="mb-0">Scan the QR code on your ticket or open your private tracking link to see your place in line.</p></div>
      <div class="col-lg-7"><form method="get" action="<?= APP_URL ?>/track/" class="row g-3 js-validated-form"><div class="col-12"><label class="form-label" for="tracking-token">Have a tracking code?</label><input class="form-control form-control-lg" id="tracking-token" name="token" pattern="[a-fA-F0-9]{32}|[a-fA-F0-9]{64}" maxlength="64" autocomplete="off" spellcheck="false" required aria-describedby="tracking-token-help"><div class="form-text" id="tracking-token-help">Enter the private code after <strong>token=</strong> in your tracking link. Keep it private.</div></div><div class="col-12"><button class="btn btn-primary" type="submit">Open Tracker <i data-lucide="arrow-right" aria-hidden="true"></i></button></div></form></div>
    </div></div></div></div></section>

    <section class="public-section public-how-section" aria-labelledby="journey-title"><div class="container public-shell-container"><div class="public-section-heading"><h2 id="journey-title">From booking to your turn</h2><p>A reservation saves your visit date. Your place in line starts when you check in.</p></div><ol class="row g-3 g-lg-4 mt-3 list-unstyled public-journey-steps">
      <?php foreach ([['calendar-days', 'Reserve a date', 'Choose an available service and visit date. No account is required.'], ['scan-line', 'Check in on arrival', 'Present your private QR or reference to staff during check-in hours.'], ['list-ordered', 'Follow your queue', 'See your queue number, estimated wait, and where to go when called.']] as $stepIndex => [$icon, $title, $copy]): ?><li class="col-md-4"><article class="public-journey-step h-100"><span class="public-step-number"><?= $stepIndex + 1 ?></span><div><h3 class="h5"><?= htmlspecialchars($title) ?></h3><p class="mb-0"><?= htmlspecialchars($copy) ?></p></div></article></li><?php endforeach; ?>
    </ol></div></section>

    <section class="public-section public-services-section" aria-labelledby="services-title"><div class="container public-shell-container"><div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3"><div class="public-section-heading mb-0"><h2 id="services-title">Care at your health center</h2><p class="mb-0">Choose a service when you book. Available dates are shown in the booking form.</p></div><a class="public-arrow-link" href="<?= APP_URL ?>/queue/join/">Choose a service <i data-lucide="arrow-right" aria-hidden="true"></i></a></div>
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
