<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../modules/queue/public_intake.php';

$token = strtolower(trim((string) ($_GET['token'] ?? '')));
$ticket = publicQueueTicketByToken($conn, $token);
if (!$ticket) http_response_code(404);
$projection = $ticket ? publicQueueTicketProjection($conn, $ticket) : null;
$isScheduled = $projection && $projection['status'] === 'scheduled';
$showConfirmation = $isScheduled && (string) ($_GET['created'] ?? '') === '1';
$newManagement = $_SESSION['new_reservation_management'] ?? null;
$managementUrl = '';
if ($showConfirmation && is_array($newManagement)
    && (int) ($newManagement['ticket_id'] ?? 0) === (int) ($ticket['ticket_id'] ?? 0)
    && (int) ($newManagement['expires_at'] ?? 0) >= time()
    && isValidPublicTicketToken((string) ($newManagement['token'] ?? ''))) {
    $managementUrl = APP_URL . '/manage-reservation/?token=' . rawurlencode((string) $newManagement['token']);
}
$publicActivePage = 'track';
$waitValue = $projection['predicted_wait_minutes'] ?? null;
$hasWaitEstimate = is_numeric($waitValue) && is_finite((float) $waitValue) && (float) $waitValue >= 0;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $showConfirmation ? 'Appointment Confirmed' : ($ticket && $ticket['ticket_number'] ? htmlspecialchars($ticket['ticket_number']) : 'Queue Tracker') ?> — SmartQMS</title>
  <?= csrfMetaTag() ?>
  <?php require __DIR__ . '/../shared/includes/theme_boot.php'; ?>
  <link rel="stylesheet" href="<?= assetUrl('vendor/twbs/bootstrap/dist/css/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
</head>
<body class="public-journey-page public-queue-page d-flex flex-column min-vh-100">
  <a class="skip-link" href="#main-content">Skip to ticket status</a>
  <?php require __DIR__ . '/../shared/includes/public_header.php'; ?>

  <main id="main-content" class="public-workflow-main flex-grow-1" tabindex="-1">
    <div class="container public-form-container">
      <?php if (!$ticket): ?>
        <section class="card public-empty-card mx-auto p-4 p-md-5 text-center"><span class="public-success-icon is-neutral"><i data-lucide="search-x" aria-hidden="true"></i></span><h1 class="h3 mt-3">Ticket not found</h1><p class="text-body-secondary mb-4">This private tracking link is invalid or no longer available.</p><div><a class="btn btn-primary" href="<?= APP_URL ?>/#secure-tracking">Use a private tracking token</a></div></section>
      <?php elseif ($showConfirmation): ?>
        <section class="public-confirmation text-center" aria-labelledby="confirmation-title">
          <div class="public-confirmation-print-brand">
            <img src="<?= assetUrl('assets/images/brand/smartqms-mark.svg') ?>" alt="" width="44" height="44">
            <div><strong>SmartQMS</strong><span>Barangay Health Center Appointment</span></div>
          </div>
          <span class="public-success-icon"><i data-lucide="check" aria-hidden="true"></i></span>
          <h1 id="confirmation-title" class="mt-3">Appointment confirmed</h1>
          <p class="public-confirmation-lead">Save this confirmation and show your reference or QR code at check-in.</p>
          <div class="card public-confirmation-card mt-4">
            <div class="card-body public-confirmation-card-body">
              <div class="public-confirmation-layout">
                <div class="public-confirmation-reference-block text-center">
                  <p class="public-kicker mb-2 justify-content-center">Appointment reference</p>
                  <div class="public-confirmation-reference"><?= htmlspecialchars($projection['reference_number']) ?></div>
                </div>
                <aside class="public-confirmation-qr-panel text-center" aria-label="Appointment QR code">
                  <?php if (!empty($ticket['qr_code_path'])): ?><img class="public-confirmation-qr" src="<?= APP_URL ?>/<?= htmlspecialchars($ticket['qr_code_path'], ENT_QUOTES) ?>" alt="QR code for this appointment's private tracker" width="240" height="240"><?php endif; ?>
                  <strong>Present this QR code at check-in</strong>
                  <small>It opens your private appointment link.</small>
                </aside>
                <dl class="public-confirmation-details text-start mb-0">
                  <div><dt>Full name</dt><dd><?= htmlspecialchars($projection['client_name']) ?></dd></div>
                  <div><dt>Chosen service</dt><dd><?= htmlspecialchars($projection['service_name']) ?></dd></div>
                  <div><dt>Appointment date</dt><dd><?= htmlspecialchars(date('F j, Y', strtotime((string) $projection['visit_date']))) ?></dd></div>
                  <div><dt>Check-in period</dt><dd>8:00 AM–3:30 PM</dd></div>
                </dl>
                <div class="alert alert-info public-confirmation-notice mb-0" role="note"><i data-lucide="info" aria-hidden="true"></i><span><strong>Check in during the period shown above.</strong> Staff will assign your queue number after you arrive.</span></div>
              </div>
            </div>
          </div>
          <div class="public-confirmation-actions d-flex flex-column flex-sm-row justify-content-center gap-3 mt-4"><a class="btn btn-outline-primary btn-lg" href="<?= APP_URL ?>/"><i data-lucide="house" aria-hidden="true"></i> Return home</a><a class="btn btn-primary btn-lg" href="<?= APP_URL ?>/modules/queue/confirmation_pdf.php?token=<?= rawurlencode($token) ?>"><i data-lucide="download" aria-hidden="true"></i> Print</a></div>
          <?php if ($managementUrl !== ''): ?><p class="public-confirmation-manage mt-3 mb-0"><a href="<?= htmlspecialchars($managementUrl, ENT_QUOTES) ?>"><i data-lucide="calendar-x" aria-hidden="true"></i> Cancel this appointment</a></p><?php endif; ?>
        </section>
      <?php else: ?>
        <section class="public-tracker" data-public-ticket-tracker data-token="<?= htmlspecialchars($token, ENT_QUOTES) ?>" data-status-url="<?= APP_URL ?>/modules/queue/public_ticket_status.php" data-feedback-url="<?= APP_URL ?>/modules/feedback/public_submit.php">
          <header class="public-workflow-heading public-tracker-heading"><div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3"><div><h1><?= $isScheduled ? 'Your visit is reserved' : 'Your place in line' ?></h1><p data-service-name><?= htmlspecialchars($projection['service_name']) ?></p></div><button class="btn btn-outline-primary" type="button" data-enable-sound aria-pressed="false"><i data-lucide="bell" aria-hidden="true"></i> Enable alerts</button></div></header>

          <div class="public-status-panel" data-status-panel data-status="<?= htmlspecialchars($projection['status'], ENT_QUOTES) ?>" role="status" aria-live="polite"><span class="public-status-icon" aria-hidden="true"><i data-lucide="<?= $isScheduled ? 'calendar-clock' : 'clock-3' ?>"></i></span><div><p data-status-label><?= htmlspecialchars(ucwords(str_replace('-', ' ', $projection['status']))) ?></p><h2 data-status-message><?= $isScheduled ? 'Arrive during check-in hours and present your QR code or reference.' : 'Your live queue status is ready.' ?></h2><time class="public-status-updated" data-last-updated datetime="<?= date(DATE_ATOM) ?>">Last updated just now</time></div></div>
          <p class="public-update-error" data-update-error role="status" aria-live="polite" hidden><i data-lucide="wifi-off" aria-hidden="true"></i><span>Updates are temporarily unavailable. Showing the last received information; retrying automatically.</span></p>

          <div class="public-tracker-grid" data-ticket-metadata>
            <article class="card public-ticket-card"><div class="card-body p-4">
              <div class="public-ticket-number-block"><span><?= $isScheduled ? 'Queue number after check-in' : 'Your queue number' ?></span><strong data-ticket-number data-queue-number><?= htmlspecialchars($projection['ticket_number'] ?: 'Not assigned yet') ?></strong></div>
              <dl class="public-queue-summary">
                <div><dt>People ahead</dt><dd data-people-ahead><?= $isScheduled ? 'After check-in' : number_format($projection['people_ahead']) ?></dd></div>
                <div><dt>Estimated wait</dt><dd data-wait-display><?= $isScheduled ? 'After check-in' : ($hasWaitEstimate ? '<span data-wait-minutes>' . number_format((float) $waitValue, 1) . '</span> min' : 'Temporarily unavailable') ?></dd></div>
              </dl>
              <p class="public-wait-note">Waiting times are estimates and may change as clients are served.</p>
              <dl class="public-ticket-details mt-4">
                <div><dt>Counter</dt><dd data-counter-label><?= htmlspecialchars($projection['counter_label'] ?: 'Assigned when called') ?></dd></div>
                <div><dt>Status</dt><dd data-status-detail><?= $isScheduled ? 'Visit reserved' : htmlspecialchars(ucwords(str_replace('-', ' ', $projection['status']))) ?></dd></div>
                <div><dt>Visit date</dt><dd><?= htmlspecialchars(date('F j, Y', strtotime((string) $projection['visit_date']))) ?></dd></div>
                <div><dt>Reference</dt><dd><?= htmlspecialchars($projection['reference_number']) ?></dd></div>
              </dl>
            </div></article>
            <article class="card public-ticket-qr-card"><div class="card-body p-4 text-center"><h2 class="h5">Keep your place handy</h2><p class="text-body-secondary"><?= $isScheduled ? 'Present this code when you physically arrive.' : 'Scan to reopen this tracker on another device.' ?></p><?php if (!empty($ticket['qr_code_path'])): ?><img src="<?= APP_URL ?>/<?= htmlspecialchars($ticket['qr_code_path'], ENT_QUOTES) ?>" alt="QR code for this ticket's private tracker" width="220" height="220"><?php else: ?><p class="public-wait-note">Keep the private link to this page so you can return to your tracker.</p><?php endif; ?></div></article>
          </div>

          <div class="public-ticket-actions d-flex flex-column flex-sm-row gap-3"><button class="btn btn-primary flex-fill" type="button" data-print-page><i data-lucide="printer" aria-hidden="true"></i> Print Ticket</button><?php if (!$isScheduled): ?><a class="btn btn-outline-primary flex-fill" href="<?= APP_URL ?>/track/">Open another private link</a><?php endif; ?></div>

          <section class="card public-feedback-gate" data-feedback-gate hidden aria-labelledby="public-feedback-title"><div class="card-body p-4 p-md-5"><p class="public-kicker">Service complete</p><h2 id="public-feedback-title">How was your visit?</h2><p class="text-body-secondary">Submit one anonymous rating. Do not include medical or personal information.</p><form data-public-feedback-form><?= csrfInput() ?><input type="hidden" name="ticket_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>"><fieldset><legend class="form-label">Rating</legend><div class="public-rating-grid"><?php for ($rating = 1; $rating <= 5; $rating++): ?><label><input type="radio" name="rating" value="<?= $rating ?>" required><span><?= $rating ?><small>star<?= $rating === 1 ? '' : 's' ?></small></span></label><?php endfor; ?></div></fieldset><div class="mt-3"><label class="form-label" for="public-feedback-comment">Comments (optional)</label><textarea class="form-control" id="public-feedback-comment" name="comment" rows="4" maxlength="1000"></textarea></div><p class="public-feedback-error" data-feedback-error role="alert" hidden></p><button class="btn btn-primary w-100 mt-4" type="submit" data-loading-text="Submitting…">Submit Feedback</button></form></div></section>
          <section class="card public-feedback-complete text-center" data-feedback-complete hidden><div class="card-body p-5"><h2 class="h4">Thank you</h2><p class="mb-0 text-body-secondary">This ticket is complete and your feedback has been recorded.</p></div></section>
        </section>
      <?php endif; ?>
    </div>
  </main>

  <?php require __DIR__ . '/../shared/includes/public_footer.php'; ?>
  <script src="<?= assetUrl('vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= assetUrl('assets/vendor/lucide/lucide.min.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/theme.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/language.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/main.js') ?>"></script>
  <?php if ($ticket && !$showConfirmation): ?><script src="<?= assetUrl('assets/js/public_queue.js') ?>"></script><?php endif; ?>
</body>
</html>
