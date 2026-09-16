<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../modules/queue/public_intake.php';
requireLogin(ROLE_STAFF);

$staffId = getCurrentStaffId($conn);
if (!$staffId) {
    http_response_code(403);
    exit('An active Staff profile is required.');
}
$staffWindow = getStaffWindow($conn, (int) $staffId);
$arrivalServices = $staffWindow ? getWindowServices($conn, $staffWindow) : [];

$pageTitle = 'Arrival Check-In';
$pageHeading = 'Arrival Check-In';
$pageSubtitle = 'Confirm online arrivals by QR or reference, or register a walk-in client.';
$activePage = 'check_in';
$staffPageScripts = [
    'assets/vendor/instascan/instascan.min.js',
    'assets/js/staff_arrival_scanner.js',
];
include __DIR__ . '/includes/header.php';
?>

<section class="staff-arrival-shell" data-staff-arrival
         data-arrival-lookup-url="<?= APP_URL ?>/modules/queue/staff_arrival_lookup.php"
         data-arrival-confirm-url="<?= APP_URL ?>/modules/queue/staff_check_in.php">
  <ul class="nav nav-tabs staff-arrival-tabs" id="staff-arrival-tabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="arrival-scan-tab" data-bs-toggle="tab" data-bs-target="#arrival-scan"
              type="button" role="tab" aria-controls="arrival-scan" aria-selected="true">
        <i data-lucide="scan-line" aria-hidden="true"></i><span>Scan QR</span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="arrival-reference-tab" data-bs-toggle="tab" data-bs-target="#arrival-reference"
              type="button" role="tab" aria-controls="arrival-reference" aria-selected="false">
        <i data-lucide="keyboard" aria-hidden="true"></i><span>Manual Input</span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="arrival-walk-in-tab" data-bs-toggle="tab" data-bs-target="#arrival-walk-in"
              type="button" role="tab" aria-controls="arrival-walk-in" aria-selected="false">
        <i data-lucide="user-plus" aria-hidden="true"></i><span>Walk-In</span>
      </button>
    </li>
  </ul>

  <div class="tab-content staff-arrival-content" id="staff-arrival-tab-content">
    <section class="tab-pane fade show active" id="arrival-scan" role="tabpanel" aria-labelledby="arrival-scan-tab" tabindex="0">
      <div class="staff-arrival-panel">
        <div class="staff-arrival-panel-copy">
          <span class="staff-arrival-icon"><i data-lucide="scan-qr-code" aria-hidden="true"></i></span>
          <div>
            <h2>Scan an appointment QR code</h2>
            <p>The camera starts only after you select Start Camera. SmartQMS prefers a rear camera when one is available.</p>
          </div>
        </div>
        <div class="staff-camera-frame" data-arrival-camera-frame hidden>
          <video data-arrival-camera playsinline muted aria-label="QR camera preview"></video>
          <span class="staff-camera-guide" aria-hidden="true"></span>
        </div>
        <div class="staff-arrival-actions">
          <div class="staff-camera-select" data-arrival-camera-select-wrap hidden>
            <label class="form-label" for="arrival-camera-select">Camera</label>
            <select class="form-select" id="arrival-camera-select" data-arrival-camera-select aria-label="Choose a camera"></select>
          </div>
          <button class="btn btn-primary" type="button" data-arrival-camera-start>
            <i data-lucide="camera" aria-hidden="true"></i><span>Start Camera</span>
          </button>
          <button class="btn btn-outline-secondary" type="button" data-arrival-camera-stop hidden>Stop Camera</button>
        </div>
        <p class="staff-arrival-help" data-arrival-camera-status role="status" aria-live="polite">
          If camera scanning is unavailable, use Manual Input with the printed reference number.
        </p>
      </div>
    </section>

    <section class="tab-pane fade" id="arrival-reference" role="tabpanel" aria-labelledby="arrival-reference-tab" tabindex="0">
      <form class="staff-arrival-panel" data-arrival-reference-form method="post"
            action="<?= APP_URL ?>/modules/queue/staff_arrival_lookup.php">
        <?= csrfInput() ?>
        <input type="hidden" name="lookup_type" value="reference">
        <div>
          <h2>Find an appointment by reference</h2>
          <p>Enter the exact reference printed below the client’s QR code.</p>
        </div>
        <div class="row g-3 align-items-end">
          <div class="col-12 col-lg">
            <label class="form-label" for="arrival-reference-number">Reference Number</label>
            <input class="form-control text-uppercase" id="arrival-reference-number" name="lookup_value"
                   maxlength="40" placeholder="BHC-2026-0001" autocomplete="off" required>
          </div>
          <div class="col-12 col-lg-auto">
            <button class="btn btn-primary w-100" type="submit" data-loading-text="Finding appointment...">
              Find Appointment
            </button>
          </div>
        </div>
      </form>
    </section>

    <section class="tab-pane fade" id="arrival-walk-in" role="tabpanel" aria-labelledby="arrival-walk-in-tab" tabindex="0">
      <form class="staff-arrival-panel" data-staff-walk-in-form method="post"
            action="<?= APP_URL ?>/modules/queue/staff_walk_in.php">
        <?= csrfInput() ?>
        <div>
          <h2>Register a walk-in client</h2>
          <p>The queue number is assigned immediately because the client is physically present.</p>
        </div>
        <?php if (!$staffWindow): ?>
          <div class="alert alert-warning d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3" role="alert">
            <span>Claim a service counter before registering a walk-in client.</span>
            <a class="btn btn-outline-primary" href="<?= APP_URL ?>/staff/select-counter/">Select Counter</a>
          </div>
        <?php elseif (!$arrivalServices): ?>
          <div class="alert alert-warning" role="alert">Your active counter has no available health services. Ask an administrator to update its service assignments.</div>
        <?php endif; ?>
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label" for="walk-in-first">First Name</label>
            <input class="form-control" id="walk-in-first" name="first_name" maxlength="50" autocomplete="off" required>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="walk-in-last">Last Name</label>
            <input class="form-control" id="walk-in-last" name="last_name" maxlength="50" autocomplete="off" required>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="walk-in-phone">Phone Number</label>
            <input class="form-control" id="walk-in-phone" name="phone_number" type="tel" inputmode="numeric"
                   pattern="09[0-9]{9}" maxlength="11" placeholder="09XXXXXXXXX" autocomplete="off" required>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="walk-in-service">Health Service</label>
            <select class="form-select" id="walk-in-service" name="service_id" required<?= !$arrivalServices ? ' disabled' : '' ?>>
              <option value="">Choose a service</option>
              <?php foreach ($arrivalServices as $service): ?>
                <option value="<?= (int) $service['service_id'] ?>"><?= htmlspecialchars($service['service_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 d-flex justify-content-end">
            <button class="btn btn-primary" type="submit" data-loading-text="Creating ticket..."<?= !$arrivalServices ? ' disabled' : '' ?>>Confirm Walk-In</button>
          </div>
        </div>
      </form>
    </section>
  </div>
</section>

<section class="staff-arrival-result" data-arrival-lookup-result hidden aria-labelledby="arrival-result-title">
  <div class="staff-section-heading staff-section-heading-flat">
    <div>
      <p class="staff-section-kicker">Appointment Details</p>
      <h2 id="arrival-result-title">Confirm the client before check-in</h2>
    </div>
    <button class="btn btn-outline-secondary" type="button" data-arrival-clear>Clear</button>
  </div>
  <dl class="staff-arrival-details">
    <div><dt>Client</dt><dd data-arrival-detail="client_name">—</dd></div>
    <div><dt>Phone</dt><dd data-arrival-detail="phone_number">—</dd></div>
    <div><dt>Service</dt><dd data-arrival-detail="service_name">—</dd></div>
    <div><dt>Reference</dt><dd data-arrival-detail="reference_number">—</dd></div>
    <div><dt>Visit Date</dt><dd data-arrival-detail="visit_date">—</dd></div>
    <div><dt>Status</dt><dd data-arrival-detail="status">—</dd></div>
    <div><dt>Valid until</dt><dd data-arrival-detail="scheduled_expires_at">—</dd></div>
  </dl>
  <p class="alert alert-info mb-3" data-arrival-readiness role="status" aria-live="polite" hidden></p>
  <div class="d-flex flex-wrap justify-content-end gap-2">
    <button class="btn btn-primary" type="button" data-arrival-open-confirm>Check In Client</button>
  </div>
</section>

<div class="modal fade app-confirm-modal" id="arrivalCheckInModal" tabindex="-1"
     aria-labelledby="arrival-check-in-title" aria-describedby="arrival-check-in-description" aria-hidden="true"
     data-arrival-confirm-modal>
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title fs-5" id="arrival-check-in-title">Confirm arrival check-in?</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close confirmation"></button>
      </div>
      <div class="modal-body">
        <p id="arrival-check-in-description">This assigns the next queue number and places the client into the strict FIFO waiting line.</p>
        <p class="staff-arrival-confirm-name" data-arrival-confirm-name></p>
      </div>
      <div class="modal-footer">
        <button class="btn admin-action-button" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn admin-action-button is-primary" type="button" data-arrival-confirm-submit
                data-loading-text="Checking in...">Yes, check-in</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade staff-arrival-ticket-modal" id="arrivalTicketModal" tabindex="-1"
     aria-labelledby="arrival-ticket-title" aria-hidden="true" data-arrival-ticket-modal>
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <p class="staff-section-kicker mb-1">Waiting Ticket</p>
          <h2 class="modal-title fs-5" id="arrival-ticket-title">Client added to the queue</h2>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close ticket"></button>
      </div>
      <div class="modal-body" data-arrival-print-area>
        <div class="staff-arrival-ticket-number" data-arrival-ticket="ticket_number">—</div>
        <dl class="staff-arrival-details staff-arrival-ticket-details">
          <div><dt>Client</dt><dd data-arrival-ticket="client_name">—</dd></div>
          <div><dt>Reference</dt><dd data-arrival-ticket="reference_number">—</dd></div>
          <div><dt>Service</dt><dd data-arrival-ticket="service_name">—</dd></div>
          <div><dt>Status</dt><dd>Waiting in Line</dd></div>
        </dl>
      </div>
      <div class="modal-footer">
        <button class="btn admin-action-button" type="button" data-bs-dismiss="modal">Done</button>
        <button class="btn admin-action-button is-primary" type="button" data-arrival-print>Print Ticket</button>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
