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
    $editId = $windowId;
    $oldInput = $input;

    if (!isValidCsrfToken()) {
        $formError = 'Security check failed. Please refresh the page and try again.';
    }

    $fieldErrors = $formError === '' ? validateWindowAdminRouting($conn, $input) : [];
    if ($formError === '' && !$fieldErrors) {
        try {
            $updated = smartqmsAdminSaveWindow($conn, $input);
            $success = $updated ? 'Service window configuration updated.' : 'Service window created.';
            $editId = 0;
            $oldInput = windowAdminDefaults();
        } catch (DomainException $error) {
            $formError = $error->getMessage();
        } catch (Throwable $error) {
            $formError = 'Could not save the service window configuration.';
        }
    }
}

$services = smartqmsAdminListWindowServices($conn);
$windows = smartqmsAdminListWindows($conn);
$suggestedCounterNumber = 1;
foreach ($windows as $windowRow) {
    $suggestedCounterNumber = max(
        $suggestedCounterNumber,
        (int) ($windowRow['counter_number'] ?? $windowRow['window_id'] ?? 0) + 1
    );
}

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
$pageSubtitle = 'Configure physical counters and the services each counter can serve.';
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
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
      <form class="modal-content admin-settings-form js-validated-form" method="POST">
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
          <input type="hidden" name="service_id" value="<?= htmlspecialchars(oldFormValue($feedback, 'service_id', '0')) ?>">
          <input type="hidden" name="window_type" value="<?= htmlspecialchars(oldFormValue($feedback, 'window_type', 'shared'), ENT_QUOTES) ?>">
          <?php
            $postedCounterNumber = (int) oldFormValue($feedback, 'counter_number');
            $selectedCounterNumber = $postedCounterNumber > 0 ? $postedCounterNumber : $suggestedCounterNumber;
            $selectedWindowName = trim(oldFormValue($feedback, 'window_name'));
            if ($selectedWindowName === '') {
                $selectedWindowName = 'Window ' . $selectedCounterNumber;
            }
            $selectedManagementStatus = oldFormValue($feedback, 'management_status', 'active');
            $selectedManagementActive = $selectedManagementStatus === 'maintenance' ? 2 : ($selectedManagementStatus === 'active' ? 1 : 0);
          ?>
          <input type="hidden" name="window_name" value="<?= htmlspecialchars($selectedWindowName, ENT_QUOTES) ?>">
          <input type="hidden" name="is_active" value="<?= $selectedManagementActive ?>">

          <?php if ($feedback['form_error']): ?>
            <div class="admin-alert is-danger" role="alert">
              <i data-lucide="circle-alert" aria-hidden="true"></i>
              <span><?= htmlspecialchars($feedback['form_error']) ?></span>
            </div>
          <?php endif; ?>

          <div class="row g-4 admin-form-grid">
            <div class="col-12 admin-form-section-heading">
              <span class="admin-form-section-icon"><i data-lucide="panel-top" aria-hidden="true"></i></span>
              <div>
                <h3>Counter details</h3>
                <p>Identify the physical counter and where clients can find it.</p>
              </div>
            </div>

            <div class="col-12 col-lg-4 admin-field">
              <label class="form-label" for="counter_number">Counter Number</label>
              <div class="input-group admin-readonly-group">
                <span class="input-group-text" aria-hidden="true"><i data-lucide="hash"></i></span>
                <input id="counter_number" class="form-control admin-input admin-system-field<?= fieldInvalidClass($feedback, 'counter_number') ?>"
                       name="counter_number" value="<?= $selectedCounterNumber ?>" readonly aria-readonly="true"<?= fieldAriaInvalid($feedback, 'counter_number') ?>>
              </div>
              <div class="invalid-feedback d-block field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'counter_number')) ?></div>
            </div>

            <div class="col-12 col-lg-8 admin-field">
              <label class="form-label" for="location_description">Location</label>
              <input id="location_description" class="form-control admin-input" name="location_description"
                     value="<?= htmlspecialchars(oldFormValue($feedback, 'location_description'), ENT_QUOTES) ?>" maxlength="255"
                     placeholder="e.g. Ground floor, beside reception" required>
            </div>

            <div class="col-12 admin-field">
              <fieldset>
                <legend class="form-label">Services Offered</legend>
                <p class="admin-field-helper mb-3">Select every health service this counter is equipped to handle.</p>
                <?php $selectedServiceIds = normalizeCapabilityIds($feedback['old']['service_ids'] ?? []); ?>
                <div class="admin-service-choice-grid">
                  <?php foreach ($services as $service): ?>
                    <?php $serviceChoiceId = 'window_service_' . (int) $service['service_id']; ?>
                    <input id="<?= $serviceChoiceId ?>" class="btn-check" type="checkbox" name="service_ids[]" value="<?= (int) $service['service_id'] ?>" autocomplete="off"<?= in_array((int) $service['service_id'], $selectedServiceIds, true) ? ' checked' : '' ?>>
                    <label class="btn admin-service-choice" for="<?= $serviceChoiceId ?>">
                      <span class="admin-service-choice-mark" aria-hidden="true"><i data-lucide="check"></i></span>
                      <span><strong><?= htmlspecialchars($service['service_name']) ?></strong><small><?= htmlspecialchars($service['service_code']) ?></small></span>
                    </label>
                  <?php endforeach; ?>
                </div>
                <?php if (fieldError($feedback, 'service_ids') !== ''): ?><p class="field-error" role="alert"><?= htmlspecialchars(fieldError($feedback, 'service_ids')) ?></p><?php endif; ?>
                <?php if (!$services): ?><p class="admin-field-helper">Create an active health service before configuring a counter.</p><?php endif; ?>
              </fieldset>
            </div>

            <div class="col-12 admin-form-section-heading">
              <span class="admin-form-section-icon"><i data-lucide="activity" aria-hidden="true"></i></span>
              <div>
                <h3>Counter status</h3>
                <p>Only active counters can be claimed by staff.</p>
              </div>
            </div>

            <div class="col-12 admin-field">
              <fieldset>
                <legend class="visually-hidden">Counter status</legend>
                <div class="admin-status-choice-group" role="radiogroup" aria-label="Counter status">
                  <?php foreach ([
                    'active' => ['Active', 'Available for staff to claim', 'circle-check'],
                    'inactive' => ['Inactive', 'Unavailable until reactivated', 'circle-slash'],
                    'maintenance' => ['Under maintenance', 'Temporarily unavailable for servicing', 'wrench'],
                  ] as $statusValue => [$statusLabel, $statusHelp, $statusIcon]): ?>
                    <?php $statusChoiceId = 'window_status_' . $statusValue; ?>
                    <input id="<?= $statusChoiceId ?>" class="btn-check" type="radio" name="management_status" value="<?= $statusValue ?>"<?= $selectedManagementStatus === $statusValue ? ' checked' : '' ?> required>
                    <label class="btn admin-status-choice" for="<?= $statusChoiceId ?>">
                      <i data-lucide="<?= $statusIcon ?>" aria-hidden="true"></i>
                      <span><strong><?= $statusLabel ?></strong><small><?= $statusHelp ?></small></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </fieldset>
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
              <th>Counter number</th>
              <th>Location</th>
              <th>Services offered</th>
              <th>Status</th>
              <th>Current Staff</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($windows as $window): ?>
              <?php
                $runtimeStatus = (string) $window['status'];
                $managementStatus = (string) ($window['management_status'] ?? ((int) $window['is_active'] === 1 ? 'active' : 'inactive'));
                $statusLabel = match ($managementStatus) {
                    'maintenance' => 'Under maintenance',
                    'inactive' => 'Inactive',
                    default => 'Active',
                };
                $statusClass = match ($managementStatus) {
                    'maintenance' => ' is-warning',
                    'inactive' => ' is-inactive',
                    default => ' is-active',
                };
              ?>
              <tr>
                <td data-label="Counter number">
                  <strong class="admin-counter-number">#<?= str_pad((string) (int) ($window['counter_number'] ?? $window['window_id']), 3, '0', STR_PAD_LEFT) ?></strong>
                  <small class="admin-muted-line"><?= htmlspecialchars($window['window_name']) ?></small>
                </td>
                <td data-label="Location"><?= htmlspecialchars((string) ($window['location_description'] ?: '—')) ?></td>
                <td data-label="Services offered"><?= htmlspecialchars($window['queue_handled'] ?? 'All services') ?></td>
                <td data-label="Status">
                  <span class="admin-status-badge<?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span>
                  <?php if ($managementStatus === 'active' && $runtimeStatus !== 'closed'): ?>
                    <small class="admin-muted-line">Runtime: <?= htmlspecialchars(ucfirst($runtimeStatus)) ?></small>
                  <?php endif; ?>
                </td>
                <td data-label="Current Staff"><?= htmlspecialchars($window['staff_name'] ?? 'Unoccupied') ?></td>
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
            <p>No service windows yet. Create a physical counter for staff to select at runtime.</p>
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
