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

/* Icon mapping for the service cards shown on the landing page. */
$serviceIcons = [
    'General Consultation' => 'clipboard-pulse',
    'Vaccination' => 'shield-plus',
    'Maternal Care' => 'heart-pulse',
    'Dental Care' => 'smile',
    'Laboratory' => 'test-tube-diagonal',
    'Medicine Refill' => 'pill',
];
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Reserve a visit and securely follow your barangay health-center queue.">
  <title>SmartQMS — <?= htmlspecialchars($healthCenterName) ?></title>
  <?php require __DIR__ . '/views/shared/includes/theme_boot.php'; ?>
  <!-- Note: Bootstrap Icons should be loaded locally if needed due to strict CSP. -->
  <link rel="stylesheet" href="<?= assetUrl('vendor/twbs/bootstrap/dist/css/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
</head>
<body class="public-journey-page public-landing-page d-flex flex-column min-vh-100">
  <a class="skip-link" href="#main-content">Skip to main content</a>
  <?php require __DIR__ . '/views/shared/includes/public_header.php'; ?>
  <main id="main-content" tabindex="-1">

    <!-- Hero — centered layout matching mockup -->
    <section class="public-hero public-landing-hero" aria-labelledby="public-hero-title">
      <div class="container public-shell-container text-center">
        <h1 id="public-hero-title" class="public-hero-title-centered">Reserve your visit.<br>Arrive with confidence.</h1>
        <p class="public-hero-copy mx-auto">Book a health-center visit, check in securely, and follow your place in line—without creating an account.</p>
        <div class="public-hero-actions d-flex flex-column flex-sm-row justify-content-center gap-3">
          <a class="btn btn-primary btn-lg" href="<?= APP_URL ?>/queue/join/">Book a Visit</a>
          <a class="btn btn-outline-primary btn-lg public-display-link" href="<?= APP_URL ?>/public-display/">Check Public Display</a>
        </div>
      </div>
    </section>

    <!-- How it works -->
    <section id="how-it-works" class="public-section public-how-section" aria-labelledby="journey-title">
      <div class="container public-shell-container">
        <div class="public-section-heading text-center mx-auto">
          <h2 id="journey-title">From booking to your turn</h2>
          <p>A reservation saves your visit date. Your place in line starts when you check in.</p>
        </div>
        <ol class="row g-3 g-lg-4 mt-4 list-unstyled public-journey-steps">
          <?php foreach ([['calendar-days', 'Reserve a date', 'Choose an available service and visit date. No account is required.'], ['scan-line', 'Check in on arrival', 'Present your private QR or reference to staff during check-in hours.'], ['list-ordered', 'Follow your queue', 'See your queue number, estimated wait, and where to go when called.']] as $stepIndex => [$icon, $title, $copy]): ?>
            <li class="col-md-4">
              <article class="card public-process-card h-100">
                <div class="card-body p-4">
                  <span class="public-step-number" aria-hidden="true"><?= $stepIndex + 1 ?></span>
                  <h3 class="h5"><?= htmlspecialchars($title) ?></h3>
                  <p class="mb-0"><?= htmlspecialchars($copy) ?></p>
                </div>
              </article>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </section>

    <!-- Services offered -->
    <section id="services" class="public-section public-services-section" aria-labelledby="services-title">
      <div class="container public-shell-container">
        <div class="public-section-heading text-center mx-auto">
          <h2 id="services-title">Care at your health center</h2>
          <p class="mb-0">Choose a service when you book. Available dates are shown in the booking form.</p>
        </div>
        <?php if ($services): ?>
          <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-4 public-landing-service-grid">
            <?php foreach ($services as $service):
              $iconName = $serviceIcons[$service['service_name']] ?? 'circle-check';
            ?>
              <div class="col">
                <article class="card public-landing-service-card h-100">
                  <div class="card-body p-4">
                    <span class="public-landing-service-icon" aria-hidden="true"><i data-lucide="<?= htmlspecialchars($iconName, ENT_QUOTES) ?>"></i></span>
                    <h3 class="h5 mt-3 mb-2"><?= htmlspecialchars($service['service_name']) ?></h3>
                    <p class="text-body-secondary mb-0"><?= htmlspecialchars($service['description'] ?: 'Available for your visit.') ?></p>
                  </div>
                </article>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="alert alert-info mt-4" role="status">Service information is temporarily unavailable. Please contact the health center.</div>
        <?php endif; ?>
      </div>
    </section>

    <!-- Frequently Asked Questions -->
    <section id="faq" class="public-section public-faq-section" aria-labelledby="faq-title">
      <div class="container public-form-container">
        <div class="public-section-heading text-center mx-auto">
          <h2 id="faq-title">Frequently Asked Questions</h2>
        </div>
        <div class="accordion mt-4" id="public-faq">
          <?php foreach ([['Do I need an account?', 'No. Public booking is account-free. Keep your private confirmation and management links.'], ['When do I receive a queue number?', 'Staff assigns it after confirming that you arrived on the reserved date.'], ['Can I change a reservation?', 'Yes, while it remains scheduled. Use the private management link from your confirmation.']] as $faqIndex => [$question, $answer]): ?>
            <div class="accordion-item"><h3 class="accordion-header" id="faq-heading-<?= $faqIndex ?>"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq-panel-<?= $faqIndex ?>" aria-expanded="false" aria-controls="faq-panel-<?= $faqIndex ?>"><?= htmlspecialchars($question) ?></button></h3><div id="faq-panel-<?= $faqIndex ?>" class="accordion-collapse collapse" aria-labelledby="faq-heading-<?= $faqIndex ?>" data-bs-parent="#public-faq"><div class="accordion-body"><?= htmlspecialchars($answer) ?></div></div></div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

  </main>
  <?php require __DIR__ . '/views/shared/includes/public_footer.php'; ?>
  <script src="<?= assetUrl('vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script><script src="<?= assetUrl('assets/vendor/lucide/lucide.min.js') ?>"></script><script src="<?= assetUrl('assets/js/theme.js') ?>"></script><script src="<?= assetUrl('assets/js/language.js') ?>"></script><script src="<?= assetUrl('assets/js/main.js') ?>"></script>
</body>
</html>
