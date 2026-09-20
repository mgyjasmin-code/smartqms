<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../modules/queue/reservation_management.php';

header('Cache-Control: no-store, private, max-age=0');
$incomingToken = strtolower(trim((string) ($_GET['token'] ?? '')));
if ($incomingToken !== '') {
    $authorized = publicManagedReservationByToken($conn, $incomingToken);
    if ($authorized) {
        session_regenerate_id(true);
        $_SESSION['reservation_manage_ticket_id'] = (int) $authorized['ticket_id'];
        $_SESSION['reservation_manage_expires_at'] = time() + 900;
        unset($_SESSION['new_reservation_management']);
        header('Location: ' . APP_URL . '/manage-reservation/', true, 303);
        exit;
    }
}

$sessionTicketId = (int) ($_SESSION['reservation_manage_ticket_id'] ?? 0);
$sessionExpiresAt = (int) ($_SESSION['reservation_manage_expires_at'] ?? 0);
if ($sessionExpiresAt < time()) {
    unset($_SESSION['reservation_manage_ticket_id'], $_SESSION['reservation_manage_expires_at']);
    $sessionTicketId = 0;
}
$action = (string) ($_POST['action'] ?? 'lookup');
$error = $incomingToken !== '' ? 'This cancellation link is invalid, expired, or revoked.' : '';
$success = '';
$reservation = $sessionTicketId > 0 ? reservationManagementProjection(publicManagedReservationBySession($conn, $sessionTicketId) ?? []) : null;
if ($reservation && $reservation['reference_number'] === '') {
    $reservation = null;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!isValidCsrfToken()) {
        $error = 'Your session expired. Refresh this page and try again.';
    } elseif ($sessionTicketId < 1 || !$reservation) {
        $error = 'Open the private management link from your reservation confirmation.';
    } else {
        try {
            if ($action === 'cancel') {
                $reservation = cancelSessionReservation($conn, $sessionTicketId);
                unset($_SESSION['reservation_manage_ticket_id'], $_SESSION['reservation_manage_expires_at']);
                $success = 'Your reservation was cancelled.';
            } else {
                throw new DomainException('Unsupported reservation action.');
            }
        } catch (DomainException $exception) {
            $error = 'The reservation could not be verified or can no longer be changed.';
        } catch (Throwable $exception) {
            error_log('Public reservation management failed: ' . $exception->getMessage());
            $error = 'The reservation could not be changed right now. Please try again.';
        }
    }
}

$publicActivePage = 'manage';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cancel Reservation — SmartQMS</title>
  <?php require __DIR__ . '/../views/shared/includes/theme_boot.php'; ?>
  <link rel="stylesheet" href="<?= assetUrl('vendor/twbs/bootstrap/dist/css/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
</head>
<body class="public-journey-page public-queue-page d-flex flex-column min-vh-100">
  <a class="skip-link" href="#main-content">Skip to reservation management</a>
  <?php require __DIR__ . '/../views/shared/includes/public_header.php'; ?>
  <main id="main-content" class="public-workflow-main flex-grow-1" tabindex="-1">
    <div class="container public-form-container">
    <header class="public-workflow-heading"><p class="public-kicker">Private reservation tools</p><h1 id="manage-title">Cancel your reservation</h1><p>Open the private link provided when the reservation was created.</p></header>
    <section class="public-queue-card card mx-auto" aria-labelledby="manage-title">
      <header class="public-queue-card-header">
        <span class="public-queue-mark" aria-hidden="true"><img src="<?= assetUrl('assets/images/brand/smartqms-mark.svg') ?>" alt="" width="34" height="34"></span>
        <div>
          <p>Private reservation tools</p>
          <h2 class="h4">Verify your visit</h2>
          <span>Your private link grants a short, time-limited cancellation session.</span>
        </div>
      </header>
      <div class="card-body p-4 p-md-5">
        <?php if ($error !== ''): ?>
          <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
          <div class="alert alert-success" role="status"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (!$reservation): ?>
          <div class="text-center py-4"><span class="public-success-icon is-neutral"><i data-lucide="link-2-off" aria-hidden="true"></i></span><h2 class="h4 mt-3">Private link required</h2><p class="text-body-secondary">For your privacy, a reference number and phone digits can no longer authorize changes.</p><a class="btn btn-primary" href="<?= APP_URL ?>/queue/join/">Book a new visit</a></div>
        <?php else: ?>
          <section aria-labelledby="managed-reservation-title">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
              <div><p class="public-kicker mb-1">Verified reservation</p><h2 id="managed-reservation-title" class="h4 mb-0"><?= htmlspecialchars($reservation['reference_number']) ?></h2></div>
              <span class="sq-status-badge <?= $reservation['can_manage'] ? 'is-scheduled' : 'is-voided' ?>"><i data-lucide="<?= $reservation['can_manage'] ? 'calendar-clock' : 'circle-x' ?>" aria-hidden="true"></i><?= $reservation['can_manage'] ? 'Scheduled' : 'Cancelled' ?></span>
            </div>
            <dl class="public-booking-review mb-4">
              <div><dt>Service</dt><dd><?= htmlspecialchars($reservation['service_name']) ?></dd></div>
              <div><dt>Visit date</dt><dd><?= $reservation['visit_date'] !== '' ? htmlspecialchars(date('F j, Y', strtotime($reservation['visit_date']))) : '—' ?></dd></div>
            </dl>

            <?php if ($reservation['can_manage']): ?>
              <button class="btn btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#cancelReservationModal">Cancel Reservation</button>
            <?php else: ?>
              <p class="mb-0 text-body-secondary">This reservation is no longer active and cannot be changed.</p>
            <?php endif; ?>
          </section>
        <?php endif; ?>
      </div>
    </section>
    </div>
  </main>

  <?php if ($reservation && $reservation['can_manage']): ?>
    <div class="modal fade" id="cancelReservationModal" tabindex="-1" aria-labelledby="cancel-reservation-title" aria-describedby="cancel-reservation-description" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" method="post">
          <?= csrfInput() ?>
          <input type="hidden" name="action" value="cancel">
          <div class="modal-header"><h2 class="modal-title fs-5" id="cancel-reservation-title">Cancel this reservation?</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close cancellation confirmation"></button></div>
          <div class="modal-body"><p id="cancel-reservation-description">This removes the visit reservation. It does not affect anyone already checked into the live queue.</p></div>
          <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Keep Reservation</button><button class="btn btn-danger" type="submit" data-loading-text="Cancelling...">Cancel Reservation</button></div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <?php require __DIR__ . '/../views/shared/includes/public_footer.php'; ?>
  <script src="<?= assetUrl('vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= assetUrl('assets/vendor/lucide/lucide.min.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/theme.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/language.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/main.js') ?>"></script>
</body>
</html>
