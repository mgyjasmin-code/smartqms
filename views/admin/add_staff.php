<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/../../modules/admin/staff_accounts.php';
requireLogin(ROLE_ADMIN);

$fieldErrors = [];
$oldInput = staffAccountDefaults();
$formError = '';
$success = '';
$notice = '';
$editId = (int) ($_GET['edit'] ?? 0);
$formAction = 'none';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = (string) ($_POST['action'] ?? 'create');

    if (!isValidCsrfToken()) {
        $formError = 'Security check failed. Please refresh the page and try again.';
    } elseif ($formAction === 'create' || $formAction === 'update') {
        $input = staffAccountInput($_POST);
        $oldInput = $input;
        $isUpdate = $formAction === 'update';
        $editId = $isUpdate ? (int) $input['staff_id'] : 0;
        $fieldErrors = validateStaffAccountInput($input, $isUpdate);
        $existingStaff = $isUpdate ? findStaffAccount($conn, $editId) : null;

        if ($isUpdate && !$existingStaff) {
            $formError = 'Staff account could not be found.';
        } elseif (!$fieldErrors) {
            $excludeUserId = (int) ($existingStaff['user_id'] ?? 0);
            if (staffEmailExists($conn, $input['email'], $excludeUserId)) {
                $fieldErrors['email'] = 'That email is already registered.';
            } elseif ($input['phone_number'] !== '' && staffPhoneExists($conn, $input['phone_number'], $excludeUserId)) {
                $fieldErrors['phone_number'] = 'That phone number is already registered.';
            } else {
                try {
                    if ($isUpdate) {
                        updateStaffAccount($conn, $input, (int) $_SESSION['user_id']);
                        $success = 'Staff account updated.';
                    } else {
                        createStaffAccount($conn, $input, (int) $_SESSION['user_id']);
                        $success = 'Staff account created. The staff member can now sign in and operate an assigned service window.';
                    }
                    $oldInput = staffAccountDefaults();
                    $editId = 0;
                } catch (Throwable $e) {
                    $formError = $isUpdate
                        ? 'Could not update the staff account. Please try again.'
                        : 'Could not create staff account. Please try again.';
                }
            }
        }
    } elseif ($formAction === 'delete') {
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        if (!isPositiveIdentifier($staffId)) {
            $formError = 'Choose a valid staff account.';
        } else {
            try {
                $result = deleteOrDeactivateStaffAccount($conn, $staffId, (int) $_SESSION['user_id']);
                if (!$result) {
                    $formError = 'Staff account could not be found.';
                } elseif ($result['had_history']) {
                    $notice = 'Staff activity history was preserved. The account was deactivated and its service window was unassigned.';
                } elseif ($result['soft_deleted']) {
                    $notice = 'The account could not be permanently deleted, so it was deactivated and unassigned.';
                } else {
                    $success = 'Staff account deleted.';
                }
            } catch (Throwable $e) {
                $formError = 'Could not delete the staff account. Please try again.';
            }
        }
    } else {
        $formError = 'Unsupported staff action.';
    }
}

$editStaff = null;
if ($editId > 0) {
    $editStaff = findStaffAccount($conn, $editId);
    if ($editStaff && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $oldInput = staffAccountRowToForm($editStaff);
    } elseif (!$editStaff && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $formError = 'Staff account could not be found.';
    }
}

$feedback = [
    'field_errors' => $fieldErrors,
    'old' => $oldInput,
    'form_error' => $formError,
];

$staffRows = listStaffAccounts($conn);
$isEditing = $editStaff !== null;
$isFormMutation = $_SERVER['REQUEST_METHOD'] !== 'POST' || $formAction === 'create' || $formAction === 'update';
$formModalOpen = $isEditing || ($isFormMutation && ($formError !== '' || !empty($fieldErrors)));

$pageTitle = 'Staff Accounts';
$pageHeading = 'Staff Accounts';
$pageSubtitle = 'Register staff members who can call the next ticket, mark clients complete, and manage their assigned service window.';
$activePage = 'staff';
$adminBodyClass = 'admin-management-page admin-staff-page';
include __DIR__ . '/includes/header.php';
?>
<section class="admin-management-shell container-fluid px-0">
  <?php if ($feedback['form_error'] && !$formModalOpen): ?>
    <div class="admin-alert is-danger" role="alert">
      <i data-lucide="circle-alert" aria-hidden="true"></i>
      <span><?= htmlspecialchars($feedback['form_error']) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="admin-alert is-success" role="status">
      <i data-lucide="check-circle-2" aria-hidden="true"></i>
      <span><?= htmlspecialchars($success) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($notice): ?>
    <div class="admin-alert is-info" role="status">
      <i data-lucide="info" aria-hidden="true"></i>
      <span><?= htmlspecialchars($notice) ?></span>
    </div>
  <?php endif; ?>

  <div class="modal fade admin-management-form-modal"
       id="staffAccountModal"
       tabindex="-1"
       aria-labelledby="staff-account-modal-title"
       aria-hidden="true"
       data-admin-management-modal
       data-admin-modal-mode="<?= $isEditing ? 'edit' : 'add' ?>"
       data-admin-clean-url="add_staff.php"<?= $formModalOpen ? ' data-admin-auto-open="true"' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <form class="modal-content admin-settings-form js-validated-form" method="POST" novalidate>
        <div class="modal-header">
          <div class="admin-management-modal-heading">
            <span class="admin-management-icon"><i data-lucide="<?= $isEditing ? 'user-round-pen' : 'user-plus' ?>" aria-hidden="true"></i></span>
            <div>
              <p class="admin-section-kicker mb-1"><?= $isEditing ? 'Update Staff' : 'Register Staff' ?></p>
              <h2 class="modal-title fs-5" id="staff-account-modal-title"><?= $isEditing ? 'Edit Staff Account' : 'Add Staff Account' ?></h2>
            </div>
          </div>
          <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close staff form"></button>
        </div>

        <div class="modal-body">
          <?= csrfInput() ?>
          <input type="hidden" name="action" value="<?= $isEditing ? 'update' : 'create' ?>">
          <input type="hidden" name="staff_id" value="<?= (int) oldFormValue($feedback, 'staff_id', '0') ?>">
          <?php if ($feedback['form_error']): ?>
            <div class="admin-alert is-danger" role="alert">
              <i data-lucide="circle-alert" aria-hidden="true"></i>
              <span><?= htmlspecialchars($feedback['form_error']) ?></span>
            </div>
          <?php endif; ?>
          <div class="row g-2 admin-form-grid">
            <div class="col-12 col-md-6 admin-field">
              <label class="form-label" for="first_name">First Name</label>
              <input id="first_name" class="form-control admin-input<?= fieldInvalidClass($feedback, 'first_name') ?>" name="first_name"
                     value="<?= htmlspecialchars(oldFormValue($feedback, 'first_name'), ENT_QUOTES) ?>"
                     autocomplete="given-name" required data-validate="required"<?= fieldAriaInvalid($feedback, 'first_name') ?>>
              <div class="invalid-feedback d-block field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'first_name')) ?></div>
            </div>

            <div class="col-12 col-md-6 admin-field">
              <label class="form-label" for="last_name">Last Name</label>
              <input id="last_name" class="form-control admin-input<?= fieldInvalidClass($feedback, 'last_name') ?>" name="last_name"
                     value="<?= htmlspecialchars(oldFormValue($feedback, 'last_name'), ENT_QUOTES) ?>"
                     autocomplete="family-name" required data-validate="required"<?= fieldAriaInvalid($feedback, 'last_name') ?>>
              <div class="invalid-feedback d-block field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'last_name')) ?></div>
            </div>

            <div class="col-12 col-md-6 admin-field">
              <label class="form-label" for="email">Email</label>
              <input id="email" class="form-control admin-input<?= fieldInvalidClass($feedback, 'email') ?>" type="email" name="email"
                     value="<?= htmlspecialchars(oldFormValue($feedback, 'email'), ENT_QUOTES) ?>"
                     autocomplete="email" required data-validate="email"<?= fieldAriaInvalid($feedback, 'email') ?>>
              <div class="invalid-feedback d-block field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'email')) ?></div>
            </div>

            <div class="col-12 col-md-6 admin-field">
              <label class="form-label" for="phone_number">Phone Number <span class="fw-normal">(optional)</span></label>
              <input id="phone_number" class="form-control admin-input<?= fieldInvalidClass($feedback, 'phone_number') ?>" type="tel" name="phone_number"
                     value="<?= htmlspecialchars(oldFormValue($feedback, 'phone_number'), ENT_QUOTES) ?>"
                     inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" autocomplete="tel"
                     aria-describedby="phone_number-helper" data-validate="phone"<?= fieldAriaInvalid($feedback, 'phone_number') ?>>
              <div class="invalid-feedback d-block field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'phone_number')) ?></div>
            </div>

            <div class="col-12 admin-field">
              <label class="form-label" for="password"><?= $isEditing ? 'New Password ' : 'Temporary Password' ?><?= $isEditing ? '<span class="fw-normal">(optional)</span>' : '' ?></label>
              <div class="admin-password-field">
                <input id="password" class="form-control admin-input<?= fieldInvalidClass($feedback, 'password') ?>" type="password" name="password"
                       minlength="8" autocomplete="new-password"<?= $isEditing ? '' : ' required' ?> data-validate="password"<?= fieldAriaInvalid($feedback, 'password') ?>>
                <button class="admin-password-toggle" type="button" data-password-toggle
                        data-password-toggle-label="<?= $isEditing ? 'new password' : 'temporary password' ?>"
                        aria-label="Show <?= $isEditing ? 'new password' : 'temporary password' ?>"
                        aria-controls="password" aria-pressed="false"
                        title="Show <?= $isEditing ? 'new password' : 'temporary password' ?>">
                  <span data-password-show-icon aria-hidden="true"><i data-lucide="eye"></i></span>
                  <span data-password-hide-icon aria-hidden="true" hidden><i data-lucide="eye-off"></i></span>
                </button>
              </div>
              <p class="admin-field-helper"><?= $isEditing ? 'Leave blank to keep the current password. New passwords require at least 8 characters.' : 'Use at least 8 characters. Staff can sign in with this email and password.' ?></p>
              <div class="invalid-feedback d-block field-error" data-field-error-for="password" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'password')) ?></div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn admin-action-button" type="button" data-bs-dismiss="modal" data-admin-management-cancel>
            <span>Cancel</span>
          </button>
          <button class="btn admin-action-button is-primary" type="submit" data-loading-text="<?= $isEditing ? 'Saving changes...' : 'Creating staff...' ?>">
            <span><?= $isEditing ? 'Save Changes' : 'Create Staff' ?></span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <article class="card admin-card admin-table-card admin-management-table-card" data-admin-paginated-region>
      <header class="admin-management-card-header admin-management-toolbar">
        <div>
          <p class="admin-section-kicker">Operators</p>
          <h2>Registered staff</h2>
        </div>
        <div class="d-flex flex-row align-items-center gap-2 flex-shrink-0">
          <button class="btn admin-action-button is-primary"
                  type="button"
                  data-bs-toggle="modal"
                  data-bs-target="#staffAccountModal"
                  aria-controls="staffAccountModal">
            <i data-lucide="user-plus" aria-hidden="true"></i>
            <span>Add Staff</span>
          </button>
        </div>
      </header>

      <div class="table-responsive admin-table-wrap"
           role="region"
           aria-label="Staff accounts table; scroll horizontally to view all columns"
           tabindex="0">
        <table class="table table-hover align-middle w-100 mb-0 admin-data-table admin-management-table" data-admin-paginated-table data-page-size="8" data-pagination-label="staff accounts">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Assigned Window</th>
              <th>Service</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($staffRows as $staff): ?>
              <?php
                $staffName = trim((string) $staff['first_name'] . ' ' . (string) $staff['last_name']);
                $isActive = (int) $staff['is_active'] === 1;
                $windowStatus = (string) ($staff['window_status'] ?? '');
              ?>
              <tr>
                <td data-label="Name"><strong><?= htmlspecialchars($staffName) ?></strong></td>
                <td data-label="Email"><?= htmlspecialchars($staff['email']) ?></td>
                <td data-label="Phone"><?= htmlspecialchars($staff['phone_number'] ?: 'Not provided') ?></td>
                <td data-label="Assigned Window"><?= htmlspecialchars($staff['window_name'] ?? 'Unassigned') ?></td>
                <td data-label="Service"><?= htmlspecialchars($staff['service_name'] ?? 'No service assigned') ?></td>
                <td data-label="Status">
                  <span class="admin-status-badge<?= $isActive ? ' is-active' : ' is-inactive' ?>">
                    <?= $isActive ? 'Active' : 'Inactive' ?>
                  </span>
                  <?php if ($windowStatus !== ''): ?>
                    <span class="admin-status-badge is-muted"><?= htmlspecialchars(ucfirst($windowStatus)) ?> window</span>
                  <?php endif; ?>
                </td>
                <td data-label="Actions">
                  <div class="admin-row-actions">
                    <a class="admin-row-action is-icon-only"
                       href="add_staff.php?edit=<?= (int) $staff['staff_id'] ?>"
                       aria-label="Edit <?= htmlspecialchars($staffName, ENT_QUOTES) ?> staff account"
                       title="Edit staff account">
                      <i data-lucide="pencil" aria-hidden="true"></i>
                    </a>
                    <form method="POST">
                      <?= csrfInput() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="staff_id" value="<?= (int) $staff['staff_id'] ?>">
                      <button class="admin-row-action is-danger is-icon-only"
                              type="submit"
                              aria-label="Delete <?= htmlspecialchars($staffName, ENT_QUOTES) ?> staff account"
                              title="Delete staff account"
                              data-admin-confirm-action
                              data-admin-confirm-title="Delete this staff account?"
                              data-admin-confirm-message="The account will be permanently deleted when it has no operational history. Otherwise, it will be deactivated and unassigned so audit records remain intact."
                              data-admin-confirm-submit-label="Delete staff"
                              data-admin-confirm-tone="danger"
                              data-admin-confirm-item-label="Staff account"
                              data-admin-confirm-item-value="<?= htmlspecialchars($staffName . ' — ' . $staff['email'], ENT_QUOTES) ?>">
                        <i data-lucide="trash-2" aria-hidden="true"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if (!$staffRows): ?>
          <div class="admin-empty-state">
            <p>No staff accounts yet. Create the first operator account.</p>
            <button class="btn admin-action-button is-primary"
                    type="button"
                    data-bs-toggle="modal"
                    data-bs-target="#staffAccountModal"
                    aria-controls="staffAccountModal">
              <i data-lucide="user-plus" aria-hidden="true"></i>
              <span>Add Staff</span>
            </button>
          </div>
        <?php endif; ?>
      </div>

      <p class="admin-pagination-status" data-admin-pagination-status aria-live="polite"></p>
      <nav class="admin-pagination" aria-label="Staff account pagination" data-admin-pagination></nav>
  </article>
</section>

<?php include __DIR__ . '/includes/management_confirmation_modal.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
