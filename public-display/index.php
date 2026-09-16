<?php
require_once '../config/config.php';
?>
<!doctype html>
<html lang="en" class="public-display-document">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Live Queue Display — SmartQMS</title>
  <?php require __DIR__ . '/../views/shared/includes/theme_boot.php'; ?>
  <link rel="stylesheet" href="<?= APP_URL ?>/vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/display.css') ?>">
</head>
<body class="public-display-page">
  <main class="public-display-shell container-fluid" data-public-display
        data-status-url="<?= APP_URL ?>/modules/queue/public_display_status.php">
    <header class="public-display-header card" aria-label="Live queue display header">
      <div class="public-display-brand">
        <img src="<?= assetUrl('assets/images/brand/smartqms-mark.svg') ?>" alt="" width="48" height="48">
        <div>
          <h1>Live Queue Display</h1>
          <p>Barangay Health Center</p>
        </div>
      </div>

      <div class="public-display-clock" aria-label="Current Manila date and time">
        <time data-display-time datetime="<?= date(DATE_ATOM) ?>"><?= htmlspecialchars(date('g:i:s A')) ?></time>
        <span data-display-date><?= htmlspecialchars(date('l, F j, Y')) ?></span>
      </div>

    </header>

    <div class="public-display-board row g-3">
      <section class="public-display-column public-display-column-serving col-12 col-md-7" aria-labelledby="now-serving-title">
        <article class="public-display-panel public-display-now-serving card h-100" data-display-now-serving>
          <header class="public-display-panel-header card-header">
            <div>
              <span class="public-display-panel-icon"><i data-lucide="radio" aria-hidden="true"></i></span>
              <div>
                <p>Current calls</p>
                <h2 id="now-serving-title">Now Serving</h2>
              </div>
            </div>
            <span class="public-display-count" data-display-active-count>0 active</span>
          </header>

          <div class="public-display-panel-body card-body">
            <section class="public-display-featured" data-display-featured data-state="empty" aria-live="assertive" aria-atomic="true">
              <p data-display-featured-label>Waiting for the next call</p>
              <strong data-display-featured-number>—</strong>
              <div class="public-display-destination">
                <span>Proceed to</span>
                <b data-display-featured-counter>—</b>
              </div>
              <small data-display-featured-service>The next queue number will appear here.</small>
            </section>

            <section class="public-display-other-active" aria-label="Other active service windows">
              <h3>Other active windows</h3>
              <div class="public-display-active-list" data-display-active-list>
                <p class="public-display-empty">No other windows are currently serving.</p>
              </div>
              <p class="public-display-overflow" data-display-active-overflow hidden></p>
            </section>
          </div>
        </article>
      </section>

      <aside class="public-display-column public-display-column-waiting col-12 col-md-5" aria-labelledby="waiting-queue-title">
        <article class="public-display-panel public-display-waiting card h-100">
          <header class="public-display-panel-header card-header">
            <div>
              <span class="public-display-panel-icon"><i data-lucide="users-round" aria-hidden="true"></i></span>
              <div>
                <p>First-in, first-out</p>
                <h2 id="waiting-queue-title">Waiting Queue</h2>
              </div>
            </div>
            <span class="public-display-count" data-display-waiting-count>0 waiting</span>
          </header>

          <div class="public-display-waiting-list card-body" data-display-waiting aria-live="polite">
            <p class="public-display-empty">No clients are waiting right now.</p>
          </div>
          <footer class="public-display-panel-footer card-footer" data-display-waiting-overflow hidden></footer>
        </article>
      </aside>
    </div>
  </main>

  <script src="<?= assetUrl('assets/vendor/lucide/lucide.min.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/public_display.js') ?>"></script>
</body>
</html>
