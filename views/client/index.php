<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_CLIENT);

$activeTicket = getActiveTicket($conn, (int) $_SESSION['user_id']);
$services = $conn->query("SELECT * FROM health_services WHERE is_active=1 ORDER BY display_order, service_name")->fetch_all(MYSQLI_ASSOC);
$queueFeedback = consumeFormFeedback('join_queue');
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= csrfMetaTag() ?>
  <title>Client Dashboard -- SmartQMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
</head>
<body class="client-page">
  <main class="client-shell">
    <div class="client-topbar">
      <div>
        <p class="client-eyebrow">Client Queue</p>
        <h1>Welcome, <?= htmlspecialchars($_SESSION['name'] ?? 'Client') ?></h1>
        <p>Choose a health service, classify this visit, and get your queue number.</p>
      </div>
      <div class="client-actions">
        <a class="btn btn-outline-primary" href="queue_status.php">
          <i class="bi bi-display" aria-hidden="true"></i>
          Queue Status
        </a>
        <button class="btn btn-outline-secondary" type="button" data-confirm-logout>
          <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
          Logout
        </button>
      </div>
    </div>

    <?php if ($queueFeedback['form_error']): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($queueFeedback['form_error']) ?></div><?php endif; ?>
    <?php if ($msg === 'registered'): ?><div class="alert alert-success">Registration complete. You are now signed in.</div><?php endif; ?>
    <?php if ($msg === 'login_verified'): ?><div class="alert alert-success">Login verified. Welcome back.</div><?php endif; ?>
    <?php if ($msg === 'password_reset'): ?><div class="alert alert-success">Password changed successfully. You are now signed in.</div><?php endif; ?>

    <?php if ($activeTicket): ?>
      <?php $activeTicket['people_ahead'] = peopleAhead($conn, $activeTicket); ?>
      <?php
        $activeQrPath = $activeTicket['qr_code_path'] ?? '';
        $activeQrFile = $activeQrPath ? __DIR__ . '/../../' . ltrim($activeQrPath, '/') : '';
        $hasActiveQrImage = $activeQrPath && is_file($activeQrFile);
      ?>
      <section class="client-panel active-ticket-panel">
        <div class="active-ticket-main">
          <div>
            <p class="panel-kicker">Current Ticket</p>
            <div class="queue-number"><?= htmlspecialchars($activeTicket['ticket_number']) ?></div>
            <h2><?= htmlspecialchars($activeTicket['service_name']) ?></h2>
            <p class="ticket-reference">Reference <?= htmlspecialchars($activeTicket['reference_number']) ?></p>
          </div>
          <?php if ($hasActiveQrImage): ?>
            <a class="active-ticket-qr" href="ticket.php" aria-label="Open digital ticket QR code">
              <img src="<?= htmlspecialchars(APP_URL . '/' . ltrim($activeQrPath, '/'), ENT_QUOTES) ?>" alt="QR code preview for ticket <?= htmlspecialchars($activeTicket['reference_number']) ?>">
              <span>Scan-ready ticket</span>
            </a>
          <?php endif; ?>
          <div class="ticket-status-stack">
            <span class="queue-status-pill queue-status-<?= htmlspecialchars($activeTicket['status']) ?>"><?= htmlspecialchars(strtoupper($activeTicket['status'])) ?></span>
            <div class="wait-estimate">
              <span>Estimated Wait</span>
              <strong><?= htmlspecialchars((string) ($activeTicket['predicted_wait_min'] ?? 'Calculating')) ?> min</strong>
            </div>
          </div>
        </div>
        <div class="queue-metrics">
          <div>
            <span>People Ahead</span>
            <strong><?= (int) $activeTicket['people_ahead'] ?></strong>
          </div>
          <div>
            <span>Classification</span>
            <strong><?= htmlspecialchars(ucfirst($activeTicket['client_type'])) ?></strong>
          </div>
          <div>
            <span>Window</span>
            <strong><?= htmlspecialchars($activeTicket['window_name'] ?? 'Not called yet') ?></strong>
          </div>
        </div>
        <div class="client-button-row">
          <a class="btn btn-primary" href="ticket.php">
            <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
            View Ticket
          </a>
          <a class="btn btn-outline-primary" href="queue_status.php">
            <i class="bi bi-activity" aria-hidden="true"></i>
            Live Status
          </a>
        </div>
      </section>
    <?php else: ?>
      <section class="client-panel queue-form-panel">
        <div class="panel-heading">
          <div>
            <p class="panel-kicker">New Queue Ticket</p>
            <h2>Get Queue Number</h2>
          </div>
          <span class="panel-note">One active ticket per account</span>
        </div>
        <?php $selectedType = oldFormValue($queueFeedback, 'client_type', 'regular'); ?>
        <?php $selectedServiceId = oldFormValue($queueFeedback, 'service_id'); ?>
        <form action="<?= postActionUrl('modules/queue/join_queue.php') ?>" method="POST" class="queue-entry-form js-validated-form" novalidate>
          <?= csrfInput() ?>
          <fieldset class="service-card-group">
            <legend>Choose Health Service</legend>
            <?php if (!$services): ?>
              <div class="queue-empty-state">No active health services are available right now.</div>
            <?php else: ?>
              <div class="service-card-grid">
                <?php foreach ($services as $service): ?>
                  <?php
                    $serviceId = (string) $service['service_id'];
                    $priorityOnly = (int) $service['priority_only'] === 1;
                    $selectedService = $selectedServiceId === $serviceId;
                    $disabledForType = $priorityOnly && !in_array($selectedType, ['senior', 'pwd'], true);
                  ?>
                  <label class="service-card<?= $selectedService ? ' is-selected' : '' ?><?= $disabledForType ? ' is-disabled' : '' ?>" data-service-card>
                    <input type="radio" name="service_id" value="<?= (int) $service['service_id'] ?>"
                           data-service-radio data-priority-only="<?= $priorityOnly ? '1' : '0' ?>"
                           <?= $selectedService ? ' checked' : '' ?> <?= $disabledForType ? ' disabled' : '' ?>>
                    <span class="service-card-icon"><i class="bi bi-clipboard2-pulse" aria-hidden="true"></i></span>
                    <span class="service-card-content">
                      <strong><?= htmlspecialchars($service['service_name']) ?></strong>
                      <?php if (!empty($service['description'])): ?>
                        <small><?= htmlspecialchars($service['description']) ?></small>
                      <?php else: ?>
                        <small>Get a queue number for this service.</small>
                      <?php endif; ?>
                    </span>
                    <?php if ($priorityOnly): ?>
                      <em>Priority only</em>
                    <?php endif; ?>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($queueFeedback, 'service_id')) ?></div>
          </fieldset>

          <fieldset class="classification-group client-classification">
            <legend>Client Classification</legend>
            <div class="classification-options">
              <label class="classification-card client-classification-card">
                <input type="radio" name="client_type" value="regular"<?= $selectedType === 'regular' ? ' checked' : '' ?>>
                <span class="classification-icon"><i class="bi bi-person" aria-hidden="true"></i></span>
                <strong>Regular</strong>
                <small>Standard visit</small>
              </label>
              <label class="classification-card client-classification-card">
                <input type="radio" name="client_type" value="senior"<?= $selectedType === 'senior' ? ' checked' : '' ?>>
                <span class="classification-icon"><i class="bi bi-person-hearts" aria-hidden="true"></i></span>
                <strong>Senior</strong>
                <small>Priority queue</small>
              </label>
              <label class="classification-card client-classification-card">
                <input type="radio" name="client_type" value="pwd"<?= $selectedType === 'pwd' ? ' checked' : '' ?>>
                <span class="classification-icon"><i class="bi bi-universal-access" aria-hidden="true"></i></span>
                <strong>PWD</strong>
                <small>Priority queue</small>
              </label>
            </div>
            <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($queueFeedback, 'client_type')) ?></div>
          </fieldset>

          <aside class="queue-prediction-card" data-prediction-preview aria-live="polite">
            <div>
              <span class="prediction-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></span>
              <div>
                <p>Estimated Wait Time</p>
                <strong data-prediction-wait>Select a service</strong>
              </div>
            </div>
            <dl>
              <div>
                <dt>People waiting</dt>
                <dd data-prediction-queue>--</dd>
              </div>
              <div>
                <dt>Active windows</dt>
                <dd data-prediction-windows>--</dd>
              </div>
              <div>
                <dt>Source</dt>
                <dd data-prediction-source>Ready</dd>
              </div>
            </dl>
          </aside>

          <button class="btn btn-primary queue-submit" type="submit" <?= !$services ? 'disabled' : '' ?>>
            <i class="bi bi-plus-circle" aria-hidden="true"></i>
            Join Queue
          </button>
        </form>
      </section>
    <?php endif; ?>
  </main>

  <div class="logout-modal" data-logout-modal hidden>
    <div class="logout-modal-backdrop" data-logout-cancel></div>
    <section class="logout-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="logout-modal-title" aria-describedby="logout-modal-copy" tabindex="-1">
      <button class="logout-modal-close" type="button" data-logout-cancel aria-label="Close logout confirmation">
        <i class="bi bi-x-lg" aria-hidden="true"></i>
      </button>
      <div class="logout-modal-icon" aria-hidden="true">
        <i class="bi bi-box-arrow-right"></i>
      </div>
      <h2 id="logout-modal-title">Log out of Smart QMS?</h2>
      <p id="logout-modal-copy">You will need to sign in again before joining or checking your queue.</p>
      <div class="logout-modal-actions">
        <button class="logout-modal-button logout-modal-button-secondary" type="button" data-logout-cancel>Cancel</button>
        <form action="<?= APP_URL ?>/modules/auth/logout.php" method="POST" class="m-0">
          <?= csrfInput() ?>
          <button class="logout-modal-button logout-modal-button-primary" type="submit" data-logout-confirm>Log out</button>
        </form>
      </div>
    </section>
  </div>

  <script src="<?= assetUrl('assets/js/main.js') ?>"></script>
  <script>
    (() => {
      const predictionUrl = '<?= APP_URL ?>/modules/queue/get_prediction.php';
      const serviceRadios = Array.from(document.querySelectorAll('[data-service-radio]'));
      const typeRadios = Array.from(document.querySelectorAll('input[name="client_type"]'));
      const waitEl = document.querySelector('[data-prediction-wait]');
      const queueEl = document.querySelector('[data-prediction-queue]');
      const windowsEl = document.querySelector('[data-prediction-windows]');
      const sourceEl = document.querySelector('[data-prediction-source]');

      if (!serviceRadios.length || !waitEl || !queueEl || !windowsEl || !sourceEl) return;

      function selectedClientType() {
        return document.querySelector('input[name="client_type"]:checked')?.value || 'regular';
      }

      function updateServiceAvailability() {
        const clientType = selectedClientType();
        const priorityAllowed = clientType === 'senior' || clientType === 'pwd';

        serviceRadios.forEach(radio => {
          const priorityOnly = radio.dataset.priorityOnly === '1';
          const disabled = priorityOnly && !priorityAllowed;
          radio.disabled = disabled;
          radio.closest('[data-service-card]')?.classList.toggle('is-disabled', disabled);
          if (disabled && radio.checked) radio.checked = false;
        });
      }

      function selectedService() {
        return serviceRadios.find(radio => radio.checked && !radio.disabled);
      }

      function setPreview(wait, queue, windows, source) {
        waitEl.textContent = wait;
        queueEl.textContent = queue;
        windowsEl.textContent = windows;
        sourceEl.textContent = source;
      }

      async function loadPrediction() {
        updateServiceAvailability();
        const service = selectedService();
        if (!service) {
          setPreview('Select a service', '--', '--', 'Ready');
          return;
        }

        setPreview('Calculating...', '--', '--', 'Loading');
        const params = new URLSearchParams({
          service_id: service.value,
          client_type: selectedClientType()
        });

        try {
          const response = await fetch(`${predictionUrl}?${params.toString()}`, { credentials: 'same-origin' });
          const payload = await response.json();
          if (!payload.success) throw new Error(payload.message || 'Prediction unavailable');
          const minutes = Number(payload.predicted_wait_minutes || 0).toLocaleString(undefined, {
            maximumFractionDigits: 1
          });
          setPreview(`~${minutes} minutes`, String(payload.queue_length ?? 0), String(payload.active_windows ?? 1), payload.source === 'ml' ? 'ML model' : 'Fallback');
        } catch (error) {
          setPreview('Estimate unavailable', '--', '--', 'Retry');
        }
      }

      serviceRadios.forEach(radio => radio.addEventListener('change', loadPrediction));
      typeRadios.forEach(radio => radio.addEventListener('change', loadPrediction));
      loadPrediction();
    })();
  </script>
</body>
</html>
