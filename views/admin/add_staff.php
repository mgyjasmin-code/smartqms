<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/../../modules/admin/staff_accounts.php';
requireLogin(ROLE_ADMIN);

$fieldErrors = [];
$oldInput = staffAccountDefaults();
$formError = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = staffAccountInput($_POST);
    $firstName = $input['first_name'];
    $lastName = $input['last_name'];
    $email = $input['email'];
    $oldInput = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
    ];

    if (!isValidCsrfToken()) {
        $formError = 'Security check failed. Please refresh the page and try again.';
    }

    $fieldErrors = $formError === '' ? validateStaffAccountInput($input) : [];

    if ($formError === '' && !$fieldErrors) {
        if (staffEmailExists($conn, $email)) {
            $fieldErrors['email'] = 'That email is already registered.';
        } else {
            try {
                createStaffAccount($conn, $input, (int) $_SESSION['user_id']);
                $success = 'Staff account created. The staff member can now sign in and operate an assigned service window.';
                $oldInput = staffAccountDefaults();
            } catch (Throwable $e) {
                $formError = 'Could not create staff account. Please try again.';
            }
        }
    }
}

$feedback = [
    'field_errors' => $fieldErrors,
    'old' => $oldInput,
    'form_error' => $formError,
];

$staffRows = listStaffAccounts($conn);

$pageTitle = 'Staff Accounts';
$pageHeading = 'Staff Accounts';
$pageSubtitle = 'Register staff members who can call the next ticket, mark clients complete, and manage their assigned service window.';
$activePage = 'staff';
$adminBodyClass = 'admin-management-page admin-staff-page';
include __DIR__ . '/includes/header.php';
?>
<section class="admin-management-shell">
  <?php if ($feedback['form_error']): ?>
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

  <div class="admin-management-grid">
    <article class="admin-card admin-management-form-card">
      <header class="admin-management-card-header">
        <div>
          <p class="admin-section-kicker">Register Staff</p>
          <h2>Create operator account</h2>
        </div>
        <span class="admin-management-icon"><i data-lucide="user-plus" aria-hidden="true"></i></span>
      </header>

      <form class="admin-settings-form js-validated-form" method="POST" novalidate>
        <?= csrfInput() ?>
        <div class="admin-form-grid">
          <div class="admin-field">
            <label for="first_name">First Name</label>
            <input id="first_name" class="admin-input<?= fieldInvalidClass($feedback, 'first_name') ?>" name="first_name"
                   value="<?= htmlspecialchars(oldFormValue($feedback, 'first_name'), ENT_QUOTES) ?>"
                   autocomplete="given-name" required data-validate="required"<?= fieldAriaInvalid($feedback, 'first_name') ?>>
            <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'first_name')) ?></div>
          </div>

          <div class="admin-field">
            <label for="last_name">Last Name</label>
            <input id="last_name" class="admin-input<?= fieldInvalidClass($feedback, 'last_name') ?>" name="last_name"
                   value="<?= htmlspecialchars(oldFormValue($feedback, 'last_name'), ENT_QUOTES) ?>"
                   autocomplete="family-name" required data-validate="required"<?= fieldAriaInvalid($feedback, 'last_name') ?>>
            <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'last_name')) ?></div>
          </div>

          <div class="admin-field admin-field-wide">
            <label for="email">Email</label>
            <input id="email" class="admin-input<?= fieldInvalidClass($feedback, 'email') ?>" type="email" name="email"
                   value="<?= htmlspecialchars(oldFormValue($feedback, 'email'), ENT_QUOTES) ?>"
                   autocomplete="email" required data-validate="email"<?= fieldAriaInvalid($feedback, 'email') ?>>
            <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'email')) ?></div>
          </div>

          <div class="admin-field admin-field-wide">
            <label for="password">Temporary Password</label>
            <input id="password" class="admin-input<?= fieldInvalidClass($feedback, 'password') ?>" type="password" name="password"
                   minlength="8" autocomplete="new-password" required data-validate="password"<?= fieldAriaInvalid($feedback, 'password') ?>>
            <p class="admin-field-helper">Use at least 8 characters. Staff can sign in with this email and password.</p>
            <div class="field-error" aria-live="polite"><?= htmlspecialchars(fieldError($feedback, 'password')) ?></div>
          </div>
        </div>

        <div class="admin-form-actions">
          <button class="admin-action-button is-dark" type="submit" data-loading-text="Creating staff...">
            <i data-lucide="user-check" aria-hidden="true"></i>
            <span>Create Staff</span>
          </button>
          <a class="admin-action-button" href="windows.php">
            <i data-lucide="panel-top" aria-hidden="true"></i>
            <span>Assign Window</span>
          </a>
        </div>
      </form>
    </article>

    <article class="admin-card admin-table-card admin-management-table-card" data-admin-paginated-region>
      <header class="admin-management-card-header">
        <div>
          <p class="admin-section-kicker">Operators</p>
          <h2>Registered staff</h2>
        </div>
        <span class="admin-pill"><?= number_format(count($staffRows)) ?> total</span>
      </header>

      <div class="admin-table-wrap">
        <table class="admin-data-table admin-management-table" data-admin-paginated-table data-page-size="8" data-pagination-label="staff accounts">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Assigned Window</th>
              <th>Service</th>
              <th>Status</th>
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
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if (!$staffRows): ?>
          <div class="admin-empty-state">No staff accounts yet. Create the first staff account using the form.</div>
        <?php endif; ?>
      </div>

      <p class="admin-pagination-status" data-admin-pagination-status aria-live="polite"></p>
      <nav class="admin-pagination" aria-label="Staff account pagination" data-admin-pagination></nav>
    </article>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
