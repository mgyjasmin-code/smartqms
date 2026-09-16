<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../modules/queue/arrival_service.php';
require_once __DIR__ . '/../../modules/queue/print_batch_service.php';
requireLogin(ROLE_STAFF);

$staffId = getCurrentStaffId($conn);
if (!$staffId) {
    http_response_code(403);
    exit('An active Staff profile is required.');
}
$services = listBatchPrintableServices($conn);
$feedback = consumeFormFeedback('batch_print');
$pageTitle = 'Batch Printing';
$pageHeading = 'Batch Printing';
$pageSubtitle = 'Reserve and print physical queue-number slips for today.';
$activePage = 'batch_printing';
include __DIR__ . '/includes/header.php';
?>
<section class="staff-batch-shell container-fluid px-0">
  <?php if (!empty($feedback['form_error'])): ?>
    <div class="alert alert-danger" role="alert">
      <?= htmlspecialchars((string) $feedback['form_error']) ?>
    </div>
  <?php endif; ?>
  <article class="staff-panel-card staff-batch-card">
    <div class="staff-batch-intro">
      <span class="staff-arrival-icon"><i data-lucide="printer" aria-hidden="true"></i></span>
      <div><h2>Generate today’s preprinted tickets</h2><p>Each range is reserved before its A4 PDF is downloaded. A batch may contain up to 200 numbers.</p></div>
    </div>
    <form class="row g-3 align-items-end js-validated-form" method="post" action="<?= APP_URL ?>/modules/queue/create_print_batch.php">
      <?= csrfInput() ?>
      <div class="col-12 col-lg-6">
        <label class="form-label" for="batch-service">Health Service</label>
        <select class="form-select" id="batch-service" name="service_id" required>
          <option value="">Choose a service</option>
          <?php foreach ($services as $service): ?>
            <option value="<?= (int) $service['service_id'] ?>"<?= (string) ($feedback['old']['service_id'] ?? '') === (string) $service['service_id'] ? ' selected' : '' ?>><?= htmlspecialchars($service['service_code'] . ' — ' . $service['service_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label" for="batch-start">Starting Number</label>
        <input class="form-control" id="batch-start" name="start_number" type="number" min="1" max="9999" value="<?= htmlspecialchars((string) ($feedback['old']['start_number'] ?? '1')) ?>" required>
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label" for="batch-end">Ending Number</label>
        <input class="form-control" id="batch-end" name="end_number" type="number" min="1" max="9999" value="<?= htmlspecialchars((string) ($feedback['old']['end_number'] ?? '8')) ?>" required>
      </div>
      <div class="col-12 col-lg-2 d-grid">
        <button class="btn btn-primary" type="submit" data-loading-text="Generating PDF...">Generate PDF</button>
      </div>
    </form>
    <p class="staff-batch-note"><i data-lucide="info" aria-hidden="true"></i> Layout: A4, two columns by four rows. Slips contain no client information.</p>
  </article>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
