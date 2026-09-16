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
            $name = $oldInput['service_name'];
            try {
                if ($isUpdate) {
                    smartqmsAdminUpdateService($conn, $oldInput);
                    logActivity($conn, 'service_updated', $name);
                    $success = 'Service updated.';
                } else {
                    smartqmsAdminCreateService($conn, $oldInput, (int) $_SESSION['user_id']);
                    logActivity($conn, 'service_created', $name);
                    $success = 'Service created. Join availability is calculated from operating rules and staffed windows.';
                }

                $oldInput = serviceFormDefaults();
                $editId = 0;
            } catch (Throwable $e) {
                $formError = $isUpdate ? 'Could not update the service.' : 'Could not add the service.';
            }
        }
    } elseif ($action === 'reorder') {
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $direction = (string) ($_POST['direction'] ?? '');
        if (!isPositiveIdentifier($serviceId) || !in_array($direction, ['up', 'down'], true)) {
            $formError = 'Choose a valid service ordering action.';
        } elseif (smartqmsAdminMoveService($conn, $serviceId, $direction, (int) $_SESSION['user_id'])) {
            $success = 'Service display order updated.';
        } else {
            $notice = 'That service is already at the requested edge of the list.';
        }
    } elseif ($action === 'toggle') {
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $isActive = (int) ($_POST['is_active'] ?? 0);
        if (!isPositiveIdentifier($serviceId)) {
            $formError = 'Choose a valid service.';
        } else {
            smartqmsAdminSetServiceActive($conn, $serviceId, $isActive);
            logActivity($conn, 'service_availability_changed', 'service_id=' . $serviceId . ', enabled=' . $isActive);
            $success = $isActive === 1 ? 'Service is available for queueing.' : 'Service is unavailable for queueing.';
        }
    } elseif ($action === 'delete') {
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        if (!isPositiveIdentifier($serviceId)) {
            $formError = 'Choose a valid service.';
        } else {
            $result = smartqmsAdminDeleteService($conn, $serviceId);

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
    $editService = smartqmsAdminFindService($conn, $editId);
    if ($editService && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $oldInput = serviceRowToForm($editService);
    }
}

$feedback = [
    'field_errors' => $fieldErrors,
    'old' => $oldInput,
    'form_error' => $formError,
];

$isEditing = $editService !== null;
$services = smartqmsAdminListServices($conn, true);
$nextServiceValues = smartqmsAdminNextServiceValues($conn);
$suggestedServiceCode = $isEditing ? (string) $editService['service_code'] : $nextServiceValues['code'];
$suggestedEncoded = $isEditing ? (int) $editService['service_encoded'] : $nextServiceValues['encoded'];
$suggestedDisplayOrder = $isEditing ? (int) $editService['display_order'] : $nextServiceValues['display_order'];

$formModalOpen = $isEditing || $formError !== '' || !empty($fieldErrors);
$pageTitle = 'Health Services';
$pageHeading = 'Health Services';
$pageSubtitle = 'Configure client-facing services, routing mode, ordering, and general queue availability.';
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
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
      <form class="modal-content admin-settings-form js-validated-form" method="POST">
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
          <input type="hidden" name="queue_mode" value="<?= htmlspecialchars(oldFormValue($feedback, 'queue_mode', 'central')) ?>">
          <input type="hidden" name="priority_only" value="0">
          <input type="hidden" name="display_order" value="<?= (int) $suggestedDisplayOrder ?>">

          <?php if ($feedback['form_error']): ?>
            <div class="admin-alert is-danger" role="alert">
              <i data-lucide="circle-alert" aria-hidden="true"></i>
              <span><?= htmlspecialchars($feedback['form_error']) ?></span>
            </div>
          <?php endif; ?>

          <div class="row g-4 admin-form-grid">
            <input type="hidden" name="service_encoded" value="<?= (int) $suggestedEncoded ?>">
            <input type="hidden" name="fallback_duration_mins" value="<?= htmlspecialchars(oldFormValue($feedback, 'fallback_duration_mins', '15'), ENT_QUOTES) ?>">

            <div class="col-12 admin-form-section-heading">
              <span class="admin-form-section-icon"><i data-lucide="clipboard-list" aria-hidden="true"></i></span>
              <div>
                <h3>Service details</h3>
                <p>Define the service clients will recognize when joining the queue.</p>
              </div>
            </div>

            <div class="col-12 col-lg-4 admin-field">
              <label class="form-label" for="service_code">Service Code</label>
              <div class="input-group admin-readonly-group">
                <span class="input-group-text" aria-hidden="true"><i data-lucide="hash"></i></span>
                <input id="service_code" class="form-control admin-input admin-system-field" name="service_code"
                       value="<?= htmlspecialchars($suggestedServiceCode, ENT_QUOTES) ?>" readonly aria-readonly="true">
              </div>
            </div>

            <div class="col-12 col-lg-8 admin-field">
              <label class="form-label" for="service_name">Service Name</label>
              <input id="service_name" class="form-control admin-input<?= fieldInvalidClass($feedback, 'service_name') ?>" name="service_name"
                     value="<?= htmlspecialchars(oldFormValue($feedback, 'service_name'), ENT_QUOTES) ?>"
                     maxlength="100" placeholder="Medical Consultations" required<?= fieldAriaInvalid($feedback, 'service_name') ?>>
              <div class="invalid-feedback d-block field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'service_name')) ?></div>
            </div>

            <div class="col-12 admin-field">
              <label class="form-label" for="description">Client Description</label>
              <textarea id="description" class="form-control admin-input admin-textarea" name="description" rows="4"
                        placeholder="Briefly describe when clients should choose this service."><?= htmlspecialchars(oldFormValue($feedback, 'description'), ENT_QUOTES) ?></textarea>
            </div>

            <div class="col-12 admin-form-section-heading">
              <span class="admin-form-section-icon"><i data-lucide="users-round" aria-hidden="true"></i></span>
              <div>
                <h3>Client access</h3>
                <p>Manage whether clients can see and select this service.</p>
              </div>
            </div>

            <div class="col-12 col-lg-6 admin-field">
              <?php $selectedActive = oldFormValue($feedback, 'is_active', '1') === '1'; ?>
              <input type="hidden" name="is_active" value="0">
              <div class="form-check form-switch admin-setting-switch">
                <input id="service_is_active" class="form-check-input" type="checkbox" role="switch" name="is_active" value="1"<?= $selectedActive ? ' checked' : '' ?>>
                <label class="form-check-label" for="service_is_active">
                  <strong>Available to clients</strong>
                  <span>Allow clients to choose this service when queueing is open.</span>
                </label>
              </div>
            </div>

            <div class="col-12 col-lg-6 admin-field">
              <?php $serviceIsVisible = oldFormValue($feedback, 'is_hidden', '0') !== '1'; ?>
              <input type="hidden" name="is_hidden" value="1">
              <div class="form-check form-switch admin-setting-switch">
                <input id="service_is_visible" class="form-check-input" type="checkbox" role="switch" name="is_hidden" value="0"<?= $serviceIsVisible ? ' checked' : '' ?>>
                <label class="form-check-label" for="service_is_visible">
                  <strong>Visible in the public service list</strong>
                  <span>Show this service on the landing and booking pages.</span>
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
              <th>Service code</th>
              <th>Service</th>
              <th>Client availability</th>
              <th>Client visibility</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($services as $service): ?>
              <?php
                $isActive = (int) $service['is_active'] === 1;
              ?>
              <tr>
                <td data-label="Service code"><code class="admin-code-value"><?= htmlspecialchars($service['service_code']) ?></code></td>
                <td data-label="Service">
                  <strong><?= htmlspecialchars($service['service_name']) ?></strong>
                  <?php if (!empty($service['description'])): ?>
                    <small class="admin-muted-line"><?= htmlspecialchars($service['description']) ?></small>
                  <?php endif; ?>
                </td>
                <td data-label="Client availability">
                  <span class="admin-status-badge<?= $isActive ? ' is-active' : ' is-inactive' ?>">
                    <?= $isActive ? 'Available' : 'Unavailable' ?>
                  </span>
                </td>
                <td data-label="Client visibility">
                  <span class="admin-status-badge<?= (int) ($service['is_hidden'] ?? 0) === 1 ? ' is-inactive' : ' is-active' ?>">
                    <?= (int) ($service['is_hidden'] ?? 0) === 1 ? 'Hidden' : 'Visible' ?>
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
