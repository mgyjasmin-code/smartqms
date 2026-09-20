<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../modules/queue/public_intake.php';
require_once '../../modules/notifications/ticket_sms.php';

$formKey = 'public_queue_join';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $phone = normalizePhone((string) ($_POST['phone_number'] ?? ''));
    $visitDate = trim((string) ($_POST['visit_date'] ?? ''));
    $serviceId = filter_var($_POST['service_id'] ?? null, FILTER_VALIDATE_INT);
    $old = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone_number' => $phone,
        'visit_date' => $visitDate,
        'service_id' => $serviceId === false ? '' : (string) $serviceId,
    ];
    $errors = [];
    if ($firstName === '' || strlen($firstName) > 50) $errors['first_name'] = 'Enter a first name of 50 characters or fewer.';
    if ($lastName === '' || strlen($lastName) > 50) $errors['last_name'] = 'Enter a last name of 50 characters or fewer.';
    if (!isValidPhMobile($phone)) $errors['phone_number'] = 'Enter an 11-digit mobile number beginning with 09.';
    $parsedVisitDate = DateTimeImmutable::createFromFormat('!Y-m-d', $visitDate);
    $today = new DateTimeImmutable('today');
    if (!$parsedVisitDate || $parsedVisitDate->format('Y-m-d') !== $visitDate || $parsedVisitDate < $today || $parsedVisitDate > $today->modify('+30 days')) $errors['visit_date'] = 'Choose a visit date within the next 30 days.';
    if ($serviceId === false || (int) $serviceId < 1) $errors['service_id'] = 'Choose a health service.';

    requireValidCsrf('queue/join/', $formKey, $old);
    if ($errors) redirectWithFormFeedback('queue/join/', $formKey, $errors, $old);

    $phoneLimit = authThrottleStatus($conn, 'public_booking_phone', $phone, PUBLIC_BOOKING_PHONE_LIMIT, PUBLIC_BOOKING_WINDOW_SECONDS);
    $ipLimit = authThrottleStatus($conn, 'public_booking_ip', 'all', PUBLIC_BOOKING_IP_LIMIT, PUBLIC_BOOKING_WINDOW_SECONDS);
    if (!$phoneLimit['allowed'] || !$ipLimit['allowed']) {
        $retryAfter = max((int) $phoneLimit['retry_after'], (int) $ipLimit['retry_after']);
        redirectWithFormFeedback('queue/join/', $formKey, [], $old, authThrottleMessage($retryAfter));
    }

    try {
        $ticket = createPublicQueueTicket($conn, $firstName, $lastName, $phone, (int) $serviceId, 'regular', 'online', true, $visitDate);
        try {
            recordAuthAttempt($conn, 'public_booking_phone', $phone, PUBLIC_BOOKING_PHONE_LIMIT, PUBLIC_BOOKING_WINDOW_SECONDS);
            recordAuthAttempt($conn, 'public_booking_ip', 'all', PUBLIC_BOOKING_IP_LIMIT, PUBLIC_BOOKING_WINDOW_SECONDS);
        } catch (Throwable $rateLogError) {
            error_log('Public booking rate accounting failed; correlation=' . (defined('SMARTQMS_CORRELATION_ID') ? SMARTQMS_CORRELATION_ID : 'unavailable'));
        }
        $_SESSION['new_reservation_management'] = [
            'ticket_id' => (int) $ticket['ticket_id'],
            'token' => (string) $ticket['management_token'],
            'expires_at' => time() + 900,
        ];
        try {
            sendBookingConfirmationForTicket($conn, (int) $ticket['ticket_id']);
        } catch (Throwable $smsError) {
            error_log('SmartQMS booking SMS could not be recorded.');
        }
        redirectTo('queue/ticket/', ['token' => $ticket['ticket_token'], 'created' => '1']);
    } catch (DomainException $error) {
        redirectWithFormFeedback('queue/join/', $formKey, ['service_id' => $error->getMessage()], $old);
    } catch (Throwable $error) {
        error_log('Public queue intake failed: ' . $error->getMessage());
        redirectWithFormFeedback('queue/join/', $formKey, [], $old, 'The reservation could not be created. Please ask health-center staff for assistance.');
    }
}

$feedback = consumeFormFeedback($formKey);
$services = smartqmsPublicQueueSchemaReady($conn) ? publicQueueServices($conn) : [];
$minimumVisitDate = date('Y-m-d');
$maximumVisitDate = date('Y-m-d', strtotime('+30 days'));
$initialStep = isset($feedback['field_errors']['service_id']) ? 1 : (!empty($feedback['field_errors']) ? 2 : 1);
$stepLabels = ['Choose Service', 'Appointment Details', 'Review'];
$fieldLabels = [
    'service_id' => 'Choose a health service',
    'first_name' => 'First name',
    'last_name' => 'Last name',
    'phone_number' => 'Mobile number',
    'visit_date' => 'Appointment date',
];
$fieldAnchors = [
    'service_id' => 'public-service-id',
    'first_name' => 'public-first-name',
    'last_name' => 'public-last-name',
    'phone_number' => 'public-phone',
    'visit_date' => 'public-visit-date',
];
$publicActivePage = 'book';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Book a Visit — SmartQMS</title>
  <?php require __DIR__ . '/../../views/shared/includes/theme_boot.php'; ?>
  <link rel="stylesheet" href="<?= assetUrl('vendor/twbs/bootstrap/dist/css/bootstrap.min.css') ?>">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
</head>
<body class="public-journey-page public-queue-page public-booking-page d-flex flex-column min-vh-100">
  <a class="skip-link" href="#main-content">Skip to booking form</a>
  <?php require __DIR__ . '/../../views/shared/includes/public_header.php'; ?>

  <main id="main-content" class="public-workflow-main flex-grow-1" tabindex="-1">
    <div class="container-xl public-form-container">
      <header class="public-workflow-heading">
        <h1 id="join-title">Choose the care you need</h1>
        <p>Select one service. You'll add your visit date and contact details next.</p>
      </header>

      <section class="card shadow-sm" aria-labelledby="join-title">
        <div class="card-body p-3 p-md-4">
          <div class="mb-4" aria-label="Booking progress">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-2"><strong data-booking-progress-label>Step <?= $initialStep ?> of 3</strong><span class="text-body-secondary" data-booking-progress-name><?= $stepLabels[$initialStep - 1] ?></span></div>
            <div class="progress" role="progressbar" aria-label="Booking progress" aria-valuemin="1" aria-valuemax="3" aria-valuenow="<?= $initialStep ?>"><div class="progress-bar" data-booking-progress-bar style="width: <?= number_format(($initialStep / 3) * 100, 2, '.', '') ?>%"></div></div>
          </div>

          <?php if ($feedback['form_error'] !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($feedback['form_error']) ?></div><?php endif; ?>
          <?php if (!empty($feedback['field_errors'])): ?>
            <div class="alert alert-danger public-booking-error-summary" role="alert" tabindex="-1" data-booking-error-summary>
              <strong>Please correct the following:</strong>
              <ul class="mb-0 mt-2">
                <?php foreach ($feedback['field_errors'] as $field => $message): ?>
                  <?php if (isset($fieldLabels[$field], $fieldAnchors[$field])): ?><li><a href="#<?= htmlspecialchars($fieldAnchors[$field], ENT_QUOTES) ?>"><?= htmlspecialchars($fieldLabels[$field]) ?>: <?= htmlspecialchars((string) $message) ?></a></li><?php endif; ?>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <?php if (!$services): ?><div class="alert alert-warning" role="alert">Public booking is temporarily unavailable. Please ask the front desk for assistance.</div><?php endif; ?>

          <form method="post" class="public-booking-form d-grid gap-4" action="<?= APP_URL ?>/queue/join/" data-public-booking data-initial-step="<?= $initialStep ?>">
            <?= csrfInput() ?>
            <fieldset class="public-booking-step" data-booking-step="1">
              <legend class="h5 mb-2">Choose a service</legend>
              <p class="text-body-secondary mb-3">Select the configured service you plan to visit.</p>
              <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3" id="public-service-id" role="radiogroup" aria-describedby="service-choice-error">
                <?php foreach ($services as $service): $serviceKey = 'public-service-' . (int) $service['service_id']; $selected = oldFormValue($feedback, 'service_id') === (string) $service['service_id']; ?>
                  <div class="col">
                    <input class="btn-check<?= fieldInvalidClass($feedback, 'service_id') ?>" type="radio" name="service_id" id="<?= $serviceKey ?>" value="<?= (int) $service['service_id'] ?>" data-booking-service-input data-service-name="<?= htmlspecialchars($service['service_name'], ENT_QUOTES) ?>" required<?= $selected ? ' checked' : '' ?><?= fieldAriaInvalid($feedback, 'service_id') ?>>
                    <label class="btn booking-native-button public-service-option d-flex align-items-center justify-content-between gap-3 text-start w-100 h-100" for="<?= $serviceKey ?>">
                      <span class="public-service-option-copy"><span class="public-service-option-title"><?= htmlspecialchars($service['service_name']) ?></span><?php if (trim((string) $service['description']) !== ''): ?><span class="public-service-option-description"><?= htmlspecialchars($service['description']) ?></span><?php endif; ?></span>
                      <span class="public-service-option-indicator" aria-hidden="true"></span>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
              <p class="invalid-feedback<?= fieldError($feedback, 'service_id') !== '' ? ' d-block' : '' ?>" id="service-choice-error"><?= htmlspecialchars(fieldError($feedback, 'service_id')) ?></p>
            </fieldset>

            <fieldset class="public-booking-step" data-booking-step="2">
              <legend class="h5 mb-2">Appointment details</legend>
              <p class="text-body-secondary mb-3">Enter your contact details and choose your visit date.</p>
              <div class="row g-4 public-booking-details-grid">
                <div class="col-12 col-md-6"><label class="form-label booking-native-label" for="public-first-name">First name</label><input class="form-control booking-native-control<?= fieldInvalidClass($feedback, 'first_name') ?>" id="public-first-name" name="first_name" maxlength="50" required autocomplete="given-name" value="<?= htmlspecialchars(oldFormValue($feedback, 'first_name'), ENT_QUOTES) ?>"<?= fieldAriaInvalid($feedback, 'first_name') ?>><div class="invalid-feedback"><?= htmlspecialchars(fieldError($feedback, 'first_name')) ?></div></div>
                <div class="col-12 col-md-6"><label class="form-label booking-native-label" for="public-last-name">Last name</label><input class="form-control booking-native-control<?= fieldInvalidClass($feedback, 'last_name') ?>" id="public-last-name" name="last_name" maxlength="50" required autocomplete="family-name" value="<?= htmlspecialchars(oldFormValue($feedback, 'last_name'), ENT_QUOTES) ?>"<?= fieldAriaInvalid($feedback, 'last_name') ?>><div class="invalid-feedback"><?= htmlspecialchars(fieldError($feedback, 'last_name')) ?></div></div>
                <div class="col-12 col-md-6"><label class="form-label booking-native-label" for="public-phone">Mobile number</label><input class="form-control booking-native-control<?= fieldInvalidClass($feedback, 'phone_number') ?>" id="public-phone" name="phone_number" type="tel" inputmode="numeric" pattern="09[0-9]{9}" maxlength="11" required autocomplete="tel" placeholder="09XXXXXXXXX" value="<?= htmlspecialchars(oldFormValue($feedback, 'phone_number'), ENT_QUOTES) ?>" aria-describedby="public-phone-help<?= fieldError($feedback, 'phone_number') !== '' ? ' public-phone-error' : '' ?>"<?= fieldAriaInvalid($feedback, 'phone_number') ?>><div class="form-text" id="public-phone-help">Enter an 11-digit Philippine mobile number beginning with 09.</div><div class="invalid-feedback" id="public-phone-error"><?= htmlspecialchars(fieldError($feedback, 'phone_number')) ?></div></div>
                <div class="col-12 col-md-6">
                  <label class="form-label booking-native-label" for="public-visit-date">Appointment date</label>
                  <input class="form-control booking-native-control<?= fieldInvalidClass($feedback, 'visit_date') ?>" id="public-visit-date" name="visit_date" type="date" min="<?= $minimumVisitDate ?>" max="<?= $maximumVisitDate ?>" required value="<?= htmlspecialchars(oldFormValue($feedback, 'visit_date', $minimumVisitDate), ENT_QUOTES) ?>"<?= fieldError($feedback, 'visit_date') !== '' ? ' aria-describedby="public-visit-date-error"' : '' ?><?= fieldAriaInvalid($feedback, 'visit_date') ?>>
                  <div class="invalid-feedback" id="public-visit-date-error"><?= htmlspecialchars(fieldError($feedback, 'visit_date')) ?></div>
                </div>
              </div>
            </fieldset>

            <fieldset class="public-booking-step" data-booking-step="3">
              <legend class="h5 mb-2">Review your information</legend>
              <p class="text-body-secondary mb-3">Please make sure everything looks right before confirming your appointment.</p>
              <div class="list-group public-booking-review-list">
                <div class="list-group-item d-flex flex-column flex-sm-row justify-content-between gap-1 gap-sm-3"><span>Service</span><strong data-booking-review="service">—</strong></div>
                <div class="list-group-item d-flex flex-column flex-sm-row justify-content-between gap-1 gap-sm-3"><span>Visit date</span><strong data-booking-review="date">—</strong></div>
                <div class="list-group-item d-flex flex-column flex-sm-row justify-content-between gap-1 gap-sm-3"><span>Name</span><strong data-booking-review="name">—</strong></div>
                <div class="list-group-item d-flex flex-column flex-sm-row justify-content-between gap-1 gap-sm-3"><span>Mobile</span><strong data-booking-review="phone">—</strong></div>
                <div class="list-group-item d-flex flex-column flex-sm-row justify-content-between gap-1 gap-sm-3"><span>Check-in period</span><strong>8:00 AM–3:30 PM</strong></div>
              </div>
            </fieldset>

            <div class="d-grid gap-2 d-sm-flex justify-content-sm-end pt-3 border-top"><a class="btn btn-outline-secondary booking-native-button btn-lg public-booking-action" href="<?= APP_URL ?>/" data-booking-home<?= $initialStep !== 1 ? ' hidden' : '' ?>>Return to Home</a><button class="btn btn-outline-secondary booking-native-button btn-lg public-booking-action" type="button" data-booking-back hidden>Back</button><button class="btn btn-primary booking-native-button btn-lg public-booking-action" type="button" data-booking-next<?= !$services ? ' disabled' : '' ?> hidden>Continue</button><button class="btn btn-primary booking-native-button btn-lg public-booking-action" type="submit" data-booking-submit data-loading-text="Creating appointment…"<?= !$services ? ' disabled' : '' ?>><span class="spinner-border spinner-border-sm me-2" aria-hidden="true" data-booking-spinner hidden></span><span data-booking-submit-label>Confirm Appointment</span></button></div>
            <div class="public-booking-processing" role="status" aria-live="polite" data-booking-processing hidden><span class="spinner-border" aria-hidden="true"></span><span>Creating your appointment securely…</span></div>
          </form>
        </div>
      </section>
    </div>
  </main>

  <?php require __DIR__ . '/../../views/shared/includes/public_footer.php'; ?>
  <script src="<?= assetUrl('vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= assetUrl('assets/vendor/lucide/lucide.min.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/theme.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/language.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/main.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/public_booking.js') ?>"></script>
</body>
</html>
