<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/../../modules/settings/service_catalog.php';
requireLogin(ROLE_ADMIN);

$fieldErrors = [];
$oldInput = serviceFormDefaults();
$formError = '';
$success = '';
$notice = '';
$editId = (int) ($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'create');

    if (!isValidCsrfToken()) {
        $formError = 'Security check failed. Please refresh the page and try again.';
    } elseif ($action === 'create' || $action === 'update') {
        $oldInput = serviceHtmlInput($_POST);
        $isUpdate = $action === 'update';
        $editId = $isUpdate ? (int) $oldInput['service_id'] : 0;
        $fieldErrors = validateServiceHtmlInput($oldInput, $isUpdate);

        if (!$fieldErrors) {
            $serviceId = (int) $oldInput['service_id'];
            $code = strtoupper($oldInput['service_code']);
            $name = $oldInput['service_name'];
            if (duplicateHealthServiceCode($conn, $code, $serviceId)) {
                $fieldErrors['service_code'] = 'That service code is already in use.';
            } else {
                try {
                    if ($isUpdate) {
                        updateHealthServiceFromHtml($conn, $oldInput);
                        logActivity($conn, 'service_updated', $name);
                        $success = 'Service updated.';
                    } else {
                        createHealthService(
                            $conn,
                            $code,
                            $name,
                            (int) $oldInput['service_encoded'],
                            $oldInput['description'],
                            (int) $oldInput['priority_only'],
                            (int) $oldInput['display_order'],
                            (int) $_SESSION['user_id'],
                            (int) $oldInput['is_active']
                        );
                        logActivity($conn, 'service_added', $name);
                        $success = 'Service added. Active services immediately appear in the client queue choices.';
                    }

                    $oldInput = serviceFormDefaults();
                    $editId = 0;
                } catch (Throwable $e) {
                    $formError = $isUpdate ? 'Could not update the service.' : 'Could not add the service.';
                }
            }
        }
    } elseif ($action === 'toggle') {
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $isActive = (int) ($_POST['is_active'] ?? 0);
        if (!isPositiveIdentifier($serviceId)) {
            $formError = 'Choose a valid service.';
        } else {
            setHealthServiceActive($conn, $serviceId, $isActive);
            logActivity($conn, 'service_toggled', 'service_id=' . $serviceId);
            $success = $isActive === 1 ? 'Service restored to the client queue.' : 'Service hidden from the client queue.';
        }
    } elseif ($action === 'delete') {
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        if (!isPositiveIdentifier($serviceId)) {
            $formError = 'Choose a valid service.';
        } else {
            $result = deleteOrDeactivateHealthService($conn, $serviceId);

            if (!$result) {
                $formError = 'Service could not be found.';
            } else {
                if ($result['had_ticket_history']) {
                    $notice = 'Service has ticket history, so it was removed from the client queue instead of being permanently deleted.';
                } elseif ($result['soft_deleted']) {
                    $notice = 'Service could not be permanently deleted, so it was hidden from the client queue.';
                } else {
                    $success = 'Service deleted.';
                }

                logActivity($conn, 'service_deleted', (string) $result['service']['service_name']);
            }
        }
    } else {
        $formError = 'Unsupported service action.';
    }
}

$editService = null;
if ($editId > 0) {
    $editService = findHealthService($conn, $editId);
    if ($editService && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $oldInput = serviceRowToForm($editService);
    }
}

$feedback = [
    'field_errors' => $fieldErrors,
    'old' => $oldInput,
    'form_error' => $formError,
];

$services = listHealthServices($conn, true);

$isEditing = $editService !== null;
$formModalOpen = $isEditing || $formError !== '' || !empty($fieldErrors);
$pageTitle = 'Health Services';
$pageHeading = 'Health Services';
$pageSubtitle = 'Control which services clients can choose before they receive a virtual QR queue ticket.';
$activePage = 'services';
$adminBodyClass = 'admin-management-page admin-services-page';
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
       id="healthServiceModal"
       tabindex="-1"
       aria-labelledby="health-service-modal-title"
       aria-hidden="true"
       data-admin-management-modal
       data-admin-modal-mode="<?= $isEditing ? 'edit' : 'add' ?>"
       data-admin-clean-url="services.php"<?= $formModalOpen ? ' data-admin-auto-open="true"' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
      <form class="modal-content admin-settings-form js-validated-form" method="POST" data-validation-errors-only novalidate>
        <div class="modal-header">
          <div class="admin-management-modal-heading">
            <span class="admin-management-icon"><i data-lucide="clipboard-list" aria-hidden="true"></i></span>
            <div>
              <p class="admin-section-kicker mb-1"><?= $isEditing ? 'Update Service' : 'Add Service' ?></p>
              <h2 class="modal-title fs-5" id="health-service-modal-title"><?= $isEditing ? 'Edit Health Service' : 'Add Health Service' ?></h2>
            </div>
          </div>
          <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close health service form"></button>
        </div>

        <div class="modal-body">
          <?= csrfInput() ?>
          <input type="hidden" name="action" value="<?= $isEditing ? 'update' : 'create' ?>">
          <input type="hidden" name="service_id" value="<?= htmlspecialchars(oldFormValue($feedback, 'service_id')) ?>">

          <?php if ($feedback['form_error']): ?>
            <div class="admin-alert is-danger" role="alert">
              <i data-lucide="circle-alert" aria-hidden="true"></i>
              <span><?= htmlspecialchars($feedback['form_error']) ?></span>
            </div>
          <?php endif; ?>

          <div class="row g-3 admin-form-grid">
            <div class="col-12 col-md-6 admin-field">
              <label class="form-label" for="service_code">Service Code</label>
              <input id="service_code" class="form-control admin-input<?= fieldInvalidClass($feedback, 'service_code') ?>" name="service_code"
                     value="<?= htmlspecialchars(oldFormValue($feedback, 'service_code'), ENT_QUOTES) ?>"
                     maxlength="10" placeholder="SVC-009" required data-validate="required"<?= fieldAriaInvalid($feedback, 'service_code') ?>>
              <div class="invalid-feedback d-block field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'service_code')) ?></div>
            </div>

            <div class="col-12 col-md-6 admin-field">
              <label class="form-label" for="service_encoded">ML Value</label>
              <input id="service_encoded" class="form-control admin-input<?= fieldInvalidClass($feedback, 'service_encoded') ?>" type="number"
                     name="service_encoded" value="<?= htmlspecialchars(oldFormValue($feedback, 'service_encoded'), ENT_QUOTES) ?>"
                     min="1" max="127" required data-validate="required"<?= fieldAriaInvalid($feedback, 'service_encoded') ?>>
              <div class="invalid-feedback d-block field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'service_encoded')) ?></div>
            </div>

            <div class="col-12 admin-field">
              <label class="form-label" for="service_name">Service Name</label>
              <input id="service_name" class="form-control admin-input<?= fieldInvalidClass($feedback, 'service_name') ?>" name="service_name"
                     value="<?= htmlspecialchars(oldFormValue($feedback, 'service_name'), ENT_QUOTES) ?>"
                     maxlength="100" placeholder="Medical Consultations" required data-validate="required"<?= fieldAriaInvalid($feedback, 'service_name') ?>>
              <div class="invalid-feedback d-block field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'service_name')) ?></div>
            </div>

            <div class="col-12 admin-field">
              <label class="form-label" for="description">Client Description</label>
              <textarea id="description" class="form-control admin-input admin-textarea" name="description" rows="4"
                        placeholder="Briefly describe when clients should choose this service."><?= htmlspecialchars(oldFormValue($feedback, 'description'), ENT_QUOTES) ?></textarea>
            </div>

            <div class="col-12 col-md-6 admin-field">
              <label class="form-label" for="display_order">Display Order</label>
              <input id="display_order" class="form-control admin-input<?= fieldInvalidClass($feedback, 'display_order') ?>" type="number"
                     name="display_order" value="<?= htmlspecialchars(oldFormValue($feedback, 'display_order', '0'), ENT_QUOTES) ?>"
                     min="0" max="127" required data-validate="required"<?= fieldAriaInvalid($feedback, 'display_order') ?>>
              <div class="invalid-feedback d-block field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'display_order')) ?></div>
            </div>

            <div class="col-12 col-md-6 admin-field">
              <label class="form-label" for="is_active">Availability</label>
              <?php $selectedActive = oldFormValue($feedback, 'is_active', '1'); ?>
              <select id="is_active" class="form-select admin-input admin-select" name="is_active">
                <option value="1"<?= $selectedActive === '1' ? ' selected' : '' ?>>Active for clients</option>
                <option value="0"<?= $selectedActive === '0' ? ' selected' : '' ?>>Hidden from clients</option>
              </select>
            </div>

            <div class="col-12">
              <div class="form-check admin-checkbox-row">
                <input id="priority_only" class="form-check-input" type="checkbox" name="priority_only" value="1"<?= oldFormValue($feedback, 'priority_only', '0') === '1' ? ' checked' : '' ?>>
                <label class="form-check-label" for="priority_only">
                  <strong>Priority clients only</strong>
                  <small>Senior citizen and PWD clients can select this service.</small>
                </label>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn admin-action-button" type="button" data-bs-dismiss="modal" data-admin-management-cancel>
            <span><?= $isEditing ? 'Cancel Edit' : 'Cancel' ?></span>
          </button>
          <button class="btn admin-action-button is-primary" type="submit" data-loading-text="<?= $isEditing ? 'Saving service...' : 'Adding service...' ?>">
            <span><?= $isEditing ? 'Save Changes' : 'Create Service' ?></span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <article class="card admin-card admin-table-card admin-management-table-card" data-admin-paginated-region>
      <header class="admin-management-card-header admin-management-toolbar">
        <div>
          <p class="admin-section-kicker">Client Queue Choices</p>
          <h2>Configured services</h2>
        </div>
        <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2">
          <?php if ($isEditing): ?>
            <a class="btn admin-action-button" href="services.php">
              <i data-lucide="x" aria-hidden="true"></i>
              <span>Cancel Edit</span>
            </a>
          <?php else: ?>
            <button class="btn admin-action-button is-primary"
                    type="button"
                    data-bs-toggle="modal"
                    data-bs-target="#healthServiceModal"
                    aria-controls="healthServiceModal">
              <i data-lucide="plus" aria-hidden="true"></i>
              <span>Add Service</span>
            </button>
          <?php endif; ?>
        </div>
      </header>

      <div class="table-responsive admin-table-wrap"
           role="region"
           aria-label="Health services table; scroll horizontally to view all columns"
           tabindex="0">
        <table class="table table-hover align-middle w-100 mb-0 admin-data-table admin-management-table" data-admin-paginated-table data-page-size="8" data-pagination-label="services">
          <thead>
            <tr>
              <th>Service</th>
              <th>Code</th>
              <th>ML</th>
              <th>Order</th>
              <th>Priority</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($services as $service): ?>
              <?php
                $isActive = (int) $service['is_active'] === 1;
                $isPriority = (int) $service['priority_only'] === 1;
              ?>
              <tr>
                <td data-label="Service">
                  <strong><?= htmlspecialchars($service['service_name']) ?></strong>
                  <?php if (!empty($service['description'])): ?>
                    <small class="admin-muted-line"><?= htmlspecialchars($service['description']) ?></small>
                  <?php endif; ?>
                </td>
                <td data-label="Code"><?= htmlspecialchars($service['service_code']) ?></td>
                <td data-label="ML"><?= (int) $service['service_encoded'] ?></td>
                <td data-label="Order"><?= (int) $service['display_order'] ?></td>
                <td data-label="Priority">
                  <span class="admin-status-badge<?= $isPriority ? ' is-priority' : ' is-muted' ?>">
                    <?= $isPriority ? 'Priority only' : 'All clients' ?>
                  </span>
                </td>
                <td data-label="Status">
                  <span class="admin-status-badge<?= $isActive ? ' is-active' : ' is-inactive' ?>">
                    <?= $isActive ? 'Active' : 'Hidden' ?>
                  </span>
                </td>
                <td data-label="Actions">
                  <div class="admin-row-actions">
                    <a class="admin-row-action is-icon-only"
                       href="services.php?edit=<?= (int) $service['service_id'] ?>"
                       aria-label="Edit <?= htmlspecialchars($service['service_name'], ENT_QUOTES) ?> service"
                       title="Edit service">
                      <i data-lucide="pencil" aria-hidden="true"></i>
                    </a>
                    <form method="POST">
                      <?= csrfInput() ?>
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="service_id" value="<?= (int) $service['service_id'] ?>">
                      <input type="hidden" name="is_active" value="<?= $isActive ? 0 : 1 ?>">
                      <button class="admin-row-action is-icon-only"
                              type="submit"
                              aria-label="<?= $isActive ? 'Hide' : 'Restore' ?> <?= htmlspecialchars($service['service_name'], ENT_QUOTES) ?> service"
                              title="<?= $isActive ? 'Hide' : 'Restore' ?> service"
                              data-admin-confirm-action
                              data-admin-confirm-title="<?= $isActive ? 'Hide this service?' : 'Restore this service?' ?>"
                              data-admin-confirm-message="<?= $isActive ? 'Clients will no longer see this service as a queue choice.' : 'Clients will be able to select this service again.' ?>"
                              data-admin-confirm-submit-label="<?= $isActive ? 'Hide service' : 'Restore service' ?>"
                              data-admin-confirm-tone="<?= $isActive ? 'danger' : 'primary' ?>"
                              data-admin-confirm-item-label="Service"
                              data-admin-confirm-item-value="<?= htmlspecialchars($service['service_name'], ENT_QUOTES) ?>">
                        <i data-lucide="<?= $isActive ? 'eye-off' : 'eye' ?>" aria-hidden="true"></i>
                      </button>
                    </form>
                    <form method="POST">
                      <?= csrfInput() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="service_id" value="<?= (int) $service['service_id'] ?>">
                      <button class="admin-row-action is-danger is-icon-only"
                              type="submit"
                              aria-label="Delete <?= htmlspecialchars($service['service_name'], ENT_QUOTES) ?> service"
                              title="Delete service"
                              data-admin-confirm-action
                              data-admin-confirm-title="Delete this service?"
                              data-admin-confirm-message="If this service has ticket history, SmartQMS will hide it from clients instead of permanently deleting it."
                              data-admin-confirm-submit-label="Delete service"
                              data-admin-confirm-tone="danger"
                              data-admin-confirm-item-label="Service"
                              data-admin-confirm-item-value="<?= htmlspecialchars($service['service_name'], ENT_QUOTES) ?>">
                        <i data-lucide="trash-2" aria-hidden="true"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if (!$services): ?>
          <div class="admin-empty-state">
            <p>No health services are configured yet. Add one to populate the client queue screen.</p>
            <button class="btn admin-action-button is-primary"
                    type="button"
                    data-bs-toggle="modal"
                    data-bs-target="#healthServiceModal"
                    aria-controls="healthServiceModal">
              <i data-lucide="plus" aria-hidden="true"></i>
              <span>Add Service</span>
            </button>
          </div>
        <?php endif; ?>
      </div>

      <p class="admin-pagination-status" data-admin-pagination-status aria-live="polite"></p>
      <nav class="admin-pagination" aria-label="Health services pagination" data-admin-pagination></nav>
  </article>
</section>

<?php include __DIR__ . '/includes/management_confirmation_modal.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
