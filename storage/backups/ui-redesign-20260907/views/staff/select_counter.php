<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/../../modules/service_window/counter_claim.php';
requireLogin(ROLE_STAFF);

$staffId = getCurrentStaffId($conn);
$window = $staffId ? getStaffWindow($conn, (int) $staffId) : null;
if ($window && ($_GET['change'] ?? '') !== '1') {
    redirectTo('views/staff/dashboard.php');
}
$counters = $staffId ? staffEligibleCounters($conn, (int) $staffId) : [];
$feedback = consumeFormFeedback('counter_claim');
$pageTitle = 'Select a Service Counter';
$pageHeading = 'Select a Service Counter';
$pageSubtitle = 'Claim an available counter before starting queue operations.';
$activePage = '';
include __DIR__ . '/includes/header.php';
?>
<section class="staff-counter-selection" aria-labelledby="counter-selection-title">
  <div class="staff-section-heading">
    <div>
      <p class="staff-section-kicker">Start Shift</p>
      <h2 id="counter-selection-title">Available service counters</h2>
    </div>
  </div>
  <?php if ($feedback['form_error'] !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($feedback['form_error']) ?></div>
  <?php endif; ?>
  <?php if ($counters): ?>
    <div class="row g-3">
      <?php foreach ($counters as $counter): ?>
        <div class="col-12 col-md-6 col-xl-4">
          <article class="card h-100 staff-counter-option">
            <div class="card-body d-flex flex-column gap-3 p-4">
              <span class="staff-window-icon" aria-hidden="true"><i data-lucide="monitor"></i></span>
              <div>
                <h3 class="h5 mb-1"><?= htmlspecialchars($counter['window_name']) ?></h3>
                <p class="mb-0 text-body-secondary"><?= htmlspecialchars($counter['mapped_services'] ?: 'All configured services') ?></p>
              </div>
              <form method="post" action="<?= APP_URL ?>/modules/service_window/claim_counter.php" class="mt-auto">
                <?= csrfInput() ?>
                <input type="hidden" name="counter_id" value="<?= (int) $counter['window_id'] ?>">
                <button class="btn btn-primary w-100" type="submit" data-loading-text="Claiming counter...">Claim Counter</button>
              </form>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="staff-empty-state">
      <span><i data-lucide="monitor-x" aria-hidden="true"></i></span>
      <h2>No available counters</h2>
      <p>Ask an administrator to configure a counter or wait until another staff member releases one.</p>
    </div>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
