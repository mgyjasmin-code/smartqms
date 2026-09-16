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
        $existingStaff = $isUpdate ? smartqmsAdminFindStaff($conn, $editId) : null;

        if ($isUpdate && !$existingStaff) {
            $formError = 'Staff account could not be found.';
        } elseif (!$fieldErrors) {
            $excludeUserId = (int) ($existingStaff['user_id'] ?? 0);
            if (smartqmsAdminStaffUsernameExists($conn, $input['username'], $excludeUserId)) {
                $fieldErrors['username'] = 'That username is already registered.';
            } elseif (smartqmsAdminStaffEmailExists($conn, $input['email'], $excludeUserId)) {
                $fieldErrors['email'] = 'That email is already registered.';
            } elseif ($input['phone_number'] !== '' && smartqmsAdminStaffPhoneExists($conn, $input['phone_number'], $excludeUserId)) {
                $fieldErrors['phone_number'] = 'That phone number is already registered.';
            } else {
                try {
                    if ($isUpdate) {
                        smartqmsAdminUpdateStaff($conn, $input, (int) $_SESSION['user_id']);
                        $success = 'Staff account updated.';
                    } else {
                        smartqmsAdminCreateStaff($conn, $input, (int) $_SESSION['user_id']);
                        $success = 'Staff account created. The staff member must change the temporary password before choosing an eligible window.';
                    }
                    $oldInput = staffAccountDefaults();
                    $editId = 0;
                } catch (DomainException $e) {
                    $formError = $e->getMessage();
                } catch (Throwable $e) {
                    $formError = $isUpdate
                        ? 'Could not update the staff account. Please try again.'
                        : 'Could not create staff account. Please try again.';
                }
            }
        }
    } elseif ($formAction === 'delete' || $formAction === 'deactivate') {
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        if (!isPositiveIdentifier($staffId)) {
            $formError = 'Choose a valid staff account.';
        } else {
            try {
                $result = smartqmsAdminDeactivateStaff($conn, $staffId, (int) $_SESSION['user_id']);
                if (!$result) {
                    $formError = 'Staff account could not be found.';
                } else {
                    $success = 'Staff account deactivated. Activity history and specialized capabilities were preserved.';
                }
            } catch (DomainException $e) {
                $formError = $e->getMessage();
            } catch (Throwable $e) {
                $formError = 'Could not deactivate the staff account. Please try again.';
            }
        }
    } else {
        $formError = 'Unsupported staff action.';
    }
}

$editStaff = null;
if ($editId > 0) {
    $editStaff = smartqmsAdminFindStaff($conn, $editId);
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

$staffRows = smartqmsAdminListStaff($conn);
$isEditing = $editStaff !== null;
$isFormMutation = $_SERVER['REQUEST_METHOD'] !== 'POST' || $formAction === 'create' || $formAction === 'update';
$formModalOpen = $isEditing || ($isFormMutation && ($formError !== '' || !empty($fieldErrors)));

$pageTitle = 'Staff Accounts';
$pageHeading = 'Staff Accounts';
$pageSubtitle = 'Manage staff access. Staff choose an eligible counter when their shift begins.';
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

  <div class="modal fade admin-management-form-modal"
       id="staffAccountModal"
       tabindex="-1"
       aria-labelledby="staff-account-modal-title"
       aria-hidden="true"
       data-bs-backdrop="true"
       data-bs-keyboard="true"
       data-admin-management-modal
       data-admin-modal-mode="<?= $isEditing ? 'edit' : 'add' ?>"
       data-admin-clean-url="add_staff.php"<?= $formModalOpen ? ' data-admin-auto-open="true"' : '' ?>>
    <div class="modal-dialog modal-dialog-centered modal-xl">
      <form class="modal-content admin-settings-form js-validated-form" method="POST">
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
          <?php foreach (array_map('intval', (array) ($feedback['old']['capability_ids'] ?? [])) as $capabilityId): ?>
            <input type="hidden" name="capability_ids[]" value="<?= $capabilityId ?>">
          <?php endforeach; ?>
          <?php if ($feedback['form_error']): ?>
            <div class="admin-alert is-danger" role="alert">
              <i data-lucide="circle-alert" aria-hidden="true"></i>
              <span><?= htmlspecialchars($feedback['form_error']) ?></span>
            </div>
          <?php endif; ?>
          <div class="row g-4 admin-form-grid">
            <input type="hidden" name="username" value="<?= htmlspecialchars(oldFormValue($feedback, 'username'), ENT_QUOTES) ?>">

            <div class="col-12 admin-form-section-heading">
              <span class="admin-form-section-icon"><i data-lucide="user-round" aria-hidden="true"></i></span>
              <div>
                <h3>Staff profile</h3>
                <p>Basic information used throughout the staff workspace.</p>
              </div>
            </div>

            <div class="col-12 col-md-6 admin-field">
              <label class="form-label" for="job_title">Job Title <span class="fw-normal">(optional)</span></label>
              <?php $selectedJobTitle = oldFormValue($feedback, 'job_title_selection', oldFormValue($feedback, 'job_title')); ?>
              <select id="job_title" class="form-select admin-input<?= fieldInvalidClass($feedback, 'job_title') ?>" name="job_title" data-admin-job-title<?= fieldAriaInvalid($feedback, 'job_title') ?>>
                <option value="">No job title</option>
                <?php foreach (['Midwife', 'Nurse', 'Doctor', 'Barangay Health Worker', 'Other'] as $jobTitle): ?>
                  <option value="<?= htmlspecialchars($jobTitle, ENT_QUOTES) ?>"<?= $selectedJobTitle === $jobTitle ? ' selected' : '' ?>><?= htmlspecialchars($jobTitle) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback d-block field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'job_title')) ?></div>
            </div>

            <div class="col-12 col-md-6 admin-field" data-admin-custom-job-title<?= $selectedJobTitle === 'Other' ? '' : ' hidden' ?>>
              <label class="form-label" for="job_title_custom">Custom Job Title</label>
              <input id="job_title_custom" class="form-control admin-input" name="job_title_custom"
                     value="<?= htmlspecialchars(oldFormValue($feedback, 'job_title_custom'), ENT_QUOTES) ?>"
                     maxlength="100" autocomplete="organization-title"<?= $selectedJobTitle === 'Other' ? ' required' : '' ?>>
            </div>

            <div class="col-12 col-md-6 admin-field">
              <label class="form-label" for="first_name">First Name</label>
              <input id="first_name" class="form-control admin-input<?= fieldInvalidClass($feedback, 'first_name') ?>" name="first_name"
                     value="<?= htmlspecialchars(oldFormValue($feedback, 'first_name'), ENT_QUOTES) ?>"
                     autocomplete="given-name" required<?= fieldAriaInvalid($feedback, 'first_name') ?>>
              <div class="invalid-feedback d-block field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'first_name')) ?></div>
            </div>

            <div class="col-12 col-md-6 admin-field">
              <label class="form-label" for="last_name">Last Name</label>
              <input id="last_name" class="form-control admin-input<?= fieldInvalidClass($feedback, 'last_name') ?>" name="last_name"
                     value="<?= htmlspecialchars(oldFormValue($feedback, 'last_name'), ENT_QUOTES) ?>"
                     autocomplete="family-name" required<?= fieldAriaInvalid($feedback, 'last_name') ?>>
              <div class="invalid-feedback d-block field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'last_name')) ?></div>
            </div>

            <div class="col-12 admin-form-section-heading">
              <span class="admin-form-section-icon"><i data-lucide="contact" aria-hidden="true"></i></span>
              <div>
                <h3>Contact and sign-in</h3>
                <p>Credentials and optional contact details for this account.</p>
              </div>
            </div>

            <div class="col-12 col-md-6 admin-field">
              <label class="form-label" for="email">Email</label>
              <input id="email" class="form-control admin-input<?= fieldInvalidClass($feedback, 'email') ?>" type="email" name="email"
                     value="<?= htmlspecialchars(oldFormValue($feedback, 'email'), ENT_QUOTES) ?>"
                     autocomplete="email" required<?= fieldAriaInvalid($feedback, 'email') ?>>
              <div class="invalid-feedback d-block field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'email')) ?></div>
            </div>

            <div class="col-12 col-md-6 admin-field">
              <label class="form-label" for="phone_number">Phone Number <span class="fw-normal">(optional)</span></label>
              <input id="phone_number" class="form-control admin-input<?= fieldInvalidClass($feedback, 'phone_number') ?>" type="tel" name="phone_number"
                     value="<?= htmlspecialchars(oldFormValue($feedback, 'phone_number'), ENT_QUOTES) ?>"
                     inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" autocomplete="tel"
                     aria-describedby="phone_number-helper"<?= fieldAriaInvalid($feedback, 'phone_number') ?>>
              <div class="invalid-feedback d-block field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'phone_number')) ?></div>
            </div>

            <div class="col-12 admin-field">
              <label class="form-label" for="password"><?= $isEditing ? 'New Password ' : 'Temporary Password' ?><?= $isEditing ? '<span class="fw-normal">(optional)</span>' : '' ?></label>
              <div class="admin-password-field">
                <input id="password" class="form-control admin-input<?= fieldInvalidClass($feedback, 'password') ?>" type="password" name="password"
                       minlength="12" autocomplete="new-password"<?= $isEditing ? '' : ' required' ?><?= fieldAriaInvalid($feedback, 'password') ?>>
                <button class="admin-password-toggle" type="button" data-password-toggle
                        data-password-toggle-label="<?= $isEditing ? 'new password' : 'temporary password' ?>"
                        aria-label="Show <?= $isEditing ? 'new password' : 'temporary password' ?>"
                        aria-controls="password" aria-pressed="false"
                        title="Show <?= $isEditing ? 'new password' : 'temporary password' ?>">
                  <span data-password-show-icon aria-hidden="true"><i data-lucide="eye"></i></span>
                  <span data-password-hide-icon aria-hidden="true" hidden><i data-lucide="eye-off"></i></span>
                </button>
              </div>
              <p class="admin-field-helper"><?= $isEditing ? 'Leave blank to keep the current password. New passwords require 12+ characters with uppercase, lowercase, and a number.' : 'Use 12+ characters with uppercase, lowercase, and a number. Staff must replace this temporary password at first sign-in.' ?></p>
              <div class="invalid-feedback d-block field-error" data-field-error-for="password" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'password')) ?></div>
            </div>

            <div class="col-12 admin-form-section-heading">
              <span class="admin-form-section-icon"><i data-lucide="shield-check" aria-hidden="true"></i></span>
              <div>
                <h3>Account access</h3>
                <p>Control whether this staff member can sign in and claim a service counter.</p>
              </div>
            </div>

            <div class="col-12 admin-field">
              <?php $staffIsActive = oldFormValue($feedback, 'is_active', '1') === '1'; ?>
              <input type="hidden" name="is_active" value="0">
              <div class="form-check form-switch admin-setting-switch">
                <input id="staff_is_active" class="form-check-input" type="checkbox" role="switch" name="is_active" value="1"<?= $staffIsActive ? ' checked' : '' ?>>
                <label class="form-check-label" for="staff_is_active">
                  <strong>Active account</strong>
                  <span>Allow this staff member to sign in and use the staff workspace.</span>
                </label>
              </div>
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
              <th>Staff member</th>
              <th>Email</th>
              <th>Job title</th>
              <th>Status</th>
              <th>Current counter</th>
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
                <td data-label="Staff member"><strong><?= htmlspecialchars($staffName) ?></strong></td>
                <td data-label="Email"><?= htmlspecialchars($staff['email']) ?></td>
                <td data-label="Job title"><?= htmlspecialchars((string) ($staff['job_title'] ?: '—')) ?></td>
                <td data-label="Status">
                  <span class="admin-status-badge<?= $isActive ? ' is-active' : ' is-inactive' ?>">
                    <?= $isActive ? 'Active' : 'Inactive' ?>
                  </span>
                  <?php if ($windowStatus !== ''): ?>
                    <span class="admin-status-badge is-muted"><?= htmlspecialchars(ucfirst($windowStatus)) ?> window</span>
                  <?php endif; ?>
                  <?php if ((int) $staff['must_change_password'] === 1): ?>
                    <span class="admin-status-badge is-warning">Password change required</span>
                  <?php endif; ?>
                </td>
                <td data-label="Current counter">
                  <?= htmlspecialchars($staff['window_name'] ?? 'Not assigned') ?>
                  <?php if (!empty($staff['runtime_queue'])): ?>
                    <small class="d-block text-body-secondary"><?= htmlspecialchars($staff['runtime_queue']) ?></small>
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
                    <?php if ($isActive): ?>
                      <form method="POST">
                        <?= csrfInput() ?>
                        <input type="hidden" name="action" value="deactivate">
                        <input type="hidden" name="staff_id" value="<?= (int) $staff['staff_id'] ?>">
                        <button class="admin-row-action is-danger is-icon-only"
                                type="submit"
                                aria-label="Deactivate <?= htmlspecialchars($staffName, ENT_QUOTES) ?> staff account"
                                title="Deactivate staff account"
                                data-admin-confirm-action
                                data-admin-confirm-title="Deactivate this staff account?"
                                data-admin-confirm-message="The staff member will no longer be able to sign in. Runtime window ownership will be cleared while activity history and qualifications remain intact."
                                data-admin-confirm-submit-label="Deactivate staff"
                                data-admin-confirm-tone="danger"
                                data-admin-confirm-item-label="Staff account"
                                data-admin-confirm-item-value="<?= htmlspecialchars($staffName . ' — ' . $staff['email'], ENT_QUOTES) ?>">
                          <i data-lucide="user-round-x" aria-hidden="true"></i>
                        </button>
                      </form>
                    <?php endif; ?>
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
