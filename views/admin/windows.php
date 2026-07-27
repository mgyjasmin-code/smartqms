<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/../../modules/admin/window_admin.php';
requireLogin(ROLE_ADMIN);

$fieldErrors = [];
$oldInput = windowAdminDefaults();
$formError = '';
$success = '';
$editId = (int) ($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = windowAdminInput($_POST);
    $windowId = (int) $input['window_id'];
    $windowName = $input['window_name'];
    $status = $input['status'];
    $editId = $windowId;
    $oldInput = $input;

    if (!isValidCsrfToken()) {
        $formError = 'Security check failed. Please refresh the page and try again.';
    }

    $fieldErrors = $formError === '' ? validateWindowAdminInput($input) : [];
    if ($formError === '' && !$fieldErrors) {
        $updated = saveAdminWindow($conn, $input);
        $success = $updated ? 'Service window updated.' : 'Service window created.';
        $editId = 0;
        $oldInput = windowAdminDefaults();
    }
}

$services = listWindowServiceAssignments($conn);
$staffRows = listWindowStaffAssignments($conn);
$windows = listAdminWindows($conn);

$editWindow = $editId > 0 ? findAdminWindow($windows, $editId) : null;
if ($editWindow && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $oldInput = windowRowToForm($editWindow);
}

$feedback = [
    'field_errors' => $fieldErrors,
    'old' => $oldInput,
    'form_error' => $formError,
];

$isEditing = $editWindow !== null;
$formModalOpen = $isEditing || $formError !== '' || !empty($fieldErrors);
$pageTitle = 'Service Windows';
$pageHeading = 'Service Windows';
$pageSubtitle = 'Assign staff accounts to service counters so they can call, complete, and skip queue tickets.';
$activePage = 'windows';
$adminBodyClass = 'admin-management-page admin-windows-page';
include __DIR__ . '/includes/header.php';
?>
<section class="admin-management-shell container-fluid px-0">
  <?php if ($feedback['form_error'] && !$formModalOpen): ?>
    <div class="admin-alert is-danger" role="alert">
      <i data-lucide="circle-alert" aria-hidden="true"></i>
      <span><?= htmlspecialchars($feedback['form_error']) ?></span>
    </div>
  <?php endif; ?>

  <div class="modal fade admin-management-form-modal"
       id="serviceWindowModal"
       tabindex="-1"
       aria-labelledby="service-window-modal-title"
       aria-hidden="true"
       data-admin-management-modal
       data-admin-modal-mode="<?= $isEditing ? 'edit' : 'add' ?>"
       data-admin-clean-url="windows.php"<?= $formModalOpen ? ' data-admin-auto-open="true"' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <form class="modal-content admin-settings-form js-validated-form" method="POST" data-validation-errors-only novalidate>
        <div class="modal-header">
          <div class="admin-management-modal-heading">
            <span class="admin-management-icon"><i data-lucide="panel-top" aria-hidden="true"></i></span>
            <div>
              <p class="admin-section-kicker mb-1"><?= $isEditing ? 'Update Window' : 'Create Window' ?></p>
              <h2 class="modal-title fs-5" id="service-window-modal-title"><?= $isEditing ? 'Edit Service Window' : 'Add Service Window' ?></h2>
            </div>
          </div>
          <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close service window form"></button>
        </div>

        <div class="modal-body">
          <?= csrfInput() ?>
          <input type="hidden" name="window_id" value="<?= htmlspecialchars(oldFormValue($feedback, 'window_id')) ?>">

          <?php if ($feedback['form_error']): ?>
            <div class="admin-alert is-danger" role="alert">
              <i data-lucide="circle-alert" aria-hidden="true"></i>
              <span><?= htmlspecialchars($feedback['form_error']) ?></span>
            </div>
          <?php endif; ?>

          <div class="row g-2 admin-form-grid">
            <div class="col-12 admin-field">
              <label class="form-label" for="window_name">Window Name</label>
              <input id="window_name" class="form-control admin-input<?= fieldInvalidClass($feedback, 'window_name') ?>" name="window_name"
                     value="<?= htmlspecialchars(oldFormValue($feedback, 'window_name'), ENT_QUOTES) ?>"
                     placeholder="Window 1" required data-validate="required"<?= fieldAriaInvalid($feedback, 'window_name') ?>>
              <div class="invalid-feedback d-block field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'window_name')) ?></div>
            </div>

            <div class="col-12 admin-field">
              <label class="form-label" for="service_id">Service</label>
              <select id="service_id" class="form-select admin-input admin-select" name="service_id">
                <option value="0"<?= oldFormValue($feedback, 'service_id', '0') === '0' ? ' selected' : '' ?>>Unassigned</option>
                <?php foreach ($services as $service): ?>
                  <?php $selectedService = oldFormValue($feedback, 'service_id', '0') === (string) $service['service_id']; ?>
                  <option value="<?= (int) $service['service_id'] ?>"<?= $selectedService ? ' selected' : '' ?>><?= htmlspecialchars($service['service_name']) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (!$services): ?>
                <p class="admin-field-helper">No active services are available. Add a service before assigning this window.</p>
              <?php endif; ?>
            </div>

            <div class="col-12 col-md-6 admin-field">
              <label class="form-label" for="staff_id">Staff</label>
              <select id="staff_id" class="form-select admin-input admin-select" name="staff_id">
                <option value="0"<?= oldFormValue($feedback, 'staff_id', '0') === '0' ? ' selected' : '' ?>>Unassigned</option>
                <?php foreach ($staffRows as $staff): ?>
                  <?php $selectedStaff = oldFormValue($feedback, 'staff_id', '0') === (string) $staff['staff_id']; ?>
                  <option value="<?= (int) $staff['staff_id'] ?>"<?= $selectedStaff ? ' selected' : '' ?>><?= htmlspecialchars($staff['staff_name']) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (!$staffRows): ?>
                <p class="admin-field-helper">Create a staff account before assigning an operator.</p>
              <?php endif; ?>
            </div>

            <div class="col-12 col-md-6 admin-field">
              <label class="form-label" for="status">Status</label>
              <?php $selectedStatus = oldFormValue($feedback, 'status', 'closed'); ?>
              <select id="status" class="form-select admin-input admin-select<?= fieldInvalidClass($feedback, 'status') ?>" name="status"
                      required data-validate="required"<?= fieldAriaInvalid($feedback, 'status') ?>>
                <option value="closed"<?= $selectedStatus === 'closed' ? ' selected' : '' ?>>Closed</option>
                <option value="open"<?= $selectedStatus === 'open' ? ' selected' : '' ?>>Open</option>
                <option value="busy"<?= $selectedStatus === 'busy' ? ' selected' : '' ?>>Busy</option>
              </select>
              <div class="invalid-feedback d-block field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'status')) ?></div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn admin-action-button" type="button" data-bs-dismiss="modal" data-admin-management-cancel>
            <span>Cancel</span>
          </button>
          <button class="btn admin-action-button is-primary" type="submit" data-loading-text="<?= $isEditing ? 'Saving window...' : 'Creating window...' ?>">
            <span><?= $isEditing ? 'Save Changes' : 'Create Window' ?></span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <article class="card admin-card admin-table-card admin-management-table-card" data-admin-paginated-region>
      <header class="admin-management-card-header admin-management-toolbar">
        <div>
          <p class="admin-section-kicker">Operating Counters</p>
          <h2>Configured windows</h2>
        </div>
        <div class="d-flex flex-row align-items-center gap-2 flex-shrink-0">
          <button class="btn admin-action-button is-primary"
                  type="button"
                  data-bs-toggle="modal"
                  data-bs-target="#serviceWindowModal"
                  aria-controls="serviceWindowModal">
            <i data-lucide="plus" aria-hidden="true"></i>
            <span>Add Window</span>
          </button>
        </div>
      </header>

      <div class="table-responsive admin-table-wrap"
           role="region"
           aria-label="Service windows table; scroll horizontally to view all columns"
           tabindex="0">
        <table class="table table-hover align-middle w-100 mb-0 admin-data-table admin-management-table" data-admin-paginated-table data-page-size="8" data-pagination-label="service windows">
          <thead>
            <tr>
              <th>Window</th>
              <th>Service</th>
              <th>Staff</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($windows as $window): ?>
              <?php $status = (string) $window['status']; ?>
              <tr>
                <td data-label="Window"><strong><?= htmlspecialchars($window['window_name']) ?></strong></td>
                <td data-label="Service"><?= htmlspecialchars($window['service_name'] ?? 'Unassigned') ?></td>
                <td data-label="Staff"><?= htmlspecialchars($window['staff_name'] ?? 'Unassigned') ?></td>
                <td data-label="Status">
                  <span class="admin-status-badge is-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                </td>
                <td data-label="Actions">
                  <div class="admin-row-actions">
                    <a class="admin-row-action is-icon-only"
                       href="windows.php?edit=<?= (int) $window['window_id'] ?>"
                       aria-label="Edit <?= htmlspecialchars($window['window_name'], ENT_QUOTES) ?> service window"
                       title="Edit service window">
                      <i data-lucide="pencil" aria-hidden="true"></i>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if (!$windows): ?>
          <div class="admin-empty-state">
            <p>No service windows yet. Create one and assign it to a staff account.</p>
            <button class="btn admin-action-button is-primary"
                    type="button"
                    data-bs-toggle="modal"
                    data-bs-target="#serviceWindowModal"
                    aria-controls="serviceWindowModal">
              <i data-lucide="plus" aria-hidden="true"></i>
              <span>Add Window</span>
            </button>
          </div>
        <?php endif; ?>
      </div>

      <p class="admin-pagination-status" data-admin-pagination-status aria-live="polite"></p>
      <nav class="admin-pagination" aria-label="Service windows pagination" data-admin-pagination></nav>
  </article>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
