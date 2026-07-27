<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_CLIENT);

$activeTicket = getActiveTicket($conn, (int) $_SESSION['user_id']);
$services = $conn->query("SELECT * FROM health_services WHERE is_active=1 ORDER BY display_order, service_name")->fetch_all(MYSQLI_ASSOC);
$queueFeedback = consumeFormFeedback('join_queue');
$msg = $_GET['msg'] ?? '';
$clientSuccessMessages = [
    'registered' => 'Registration complete. You are now signed in.',
    'login_verified' => 'Login verified. Welcome back.',
    'password_reset' => 'Password changed successfully. You are now signed in.',
];
$appActionToasts = isset($clientSuccessMessages[$msg])
    ? [['tone' => 'success', 'message' => $clientSuccessMessages[$msg]]]
    : [];
$pageTitle = 'Client Dashboard';
$pageHeading = 'Welcome, ' . ($_SESSION['name'] ?? 'Client');
$pageSubtitle = 'Choose a health service, classify this visit, and get your queue number.';
$activePage = 'dashboard';
include __DIR__ . '/includes/header.php';
?>

    <?php if ($queueFeedback['form_error']): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($queueFeedback['form_error']) ?></div><?php endif; ?>

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
            <span class="status-badge badge-<?= htmlspecialchars($activeTicket['status']) ?>"><?= htmlspecialchars($activeTicket['status']) ?></span>
            <div class="wait-estimate">
              <span>Estimated Wait</span>
              <strong><?= $activeTicket['predicted_wait_min'] !== null
                  ? htmlspecialchars(rtrim(rtrim(number_format((float) $activeTicket['predicted_wait_min'], 1), '0'), '.') . ' min')
                  : 'Estimate pending' ?></strong>
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
        <form action="<?= postActionUrl('modules/queue/join_queue.php') ?>" method="POST" class="queue-entry-form js-validated-form" novalidate
              data-prediction-root data-prediction-url="<?= APP_URL ?>/modules/queue/get_prediction.php">
          <?= csrfInput() ?>
          <fieldset class="service-card-group" aria-describedby="service-selection-error">
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
            <div id="service-selection-error" class="field-error" data-field-error-for="service_id" aria-live="polite"><?= htmlspecialchars(fieldError($queueFeedback, 'service_id')) ?></div>
          </fieldset>

          <fieldset class="classification-group client-classification" aria-describedby="classification-error">
            <legend>Client Classification</legend>
            <div class="classification-options">
              <label class="classification-card client-classification-card">
                <input type="radio" name="client_type" value="regular"<?= $selectedType === 'regular' ? ' checked' : '' ?>>
                <span class="classification-icon"><i data-lucide="user-round" aria-hidden="true"></i></span>
                <strong>Regular</strong>
                <small>Standard visit</small>
              </label>
              <label class="classification-card client-classification-card">
                <input type="radio" name="client_type" value="senior"<?= $selectedType === 'senior' ? ' checked' : '' ?>>
                <span class="classification-icon"><i data-lucide="heart-handshake" aria-hidden="true"></i></span>
                <strong>Senior</strong>
                <small>Priority queue</small>
              </label>
              <label class="classification-card client-classification-card">
                <input type="radio" name="client_type" value="pwd"<?= $selectedType === 'pwd' ? ' checked' : '' ?>>
                <span class="classification-icon"><i data-lucide="accessibility" aria-hidden="true"></i></span>
                <strong>PWD</strong>
                <small>Priority queue</small>
              </label>
            </div>
            <div id="classification-error" class="field-error" data-field-error-for="client_type" aria-live="polite"><?= htmlspecialchars(fieldError($queueFeedback, 'client_type')) ?></div>
          </fieldset>

          <aside class="queue-prediction-card" data-prediction-preview data-state="idle" aria-live="polite" aria-atomic="true">
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
<?php include __DIR__ . '/includes/footer.php'; ?>
