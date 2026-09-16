<?php

function adminManagementUiSource(string $relativePath): string {
    $source = file_get_contents(SMARTQMS_ROOT . '/' . $relativePath);
    if ($source === false) {
        throw new RuntimeException('Could not read ' . $relativePath);
    }
    return $source;
}

testCase('admin management pages use table first Bootstrap modal forms', function (): void {
    $views = [
        'views/admin/add_staff.php' => ['staffAccountModal', 'staff-account-modal-title', 'Add Staff'],
        'views/admin/services.php' => ['healthServiceModal', 'health-service-modal-title', 'Add Service'],
        'views/admin/windows.php' => ['serviceWindowModal', 'service-window-modal-title', 'Add Window'],
    ];

    foreach ($views as $path => [$modalId, $titleId, $addLabel]) {
        $source = adminManagementUiSource($path);
        assertStringContains('admin-management-shell container-fluid px-0', $source, $path);
        assertStringContains('class="modal fade admin-management-form-modal"', $source, $path);
        assertStringContains('id="' . $modalId . '"', $source, $path);
        assertStringContains('aria-labelledby="' . $titleId . '"', $source, $path);
        $dialogClasses = $path === 'views/admin/add_staff.php'
            ? 'modal-dialog modal-dialog-centered modal-xl'
            : 'modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl';
        assertStringContains($dialogClasses, $source, $path);
        assertStringContains('data-admin-management-modal', $source, $path);
        assertFalseValue(str_contains($source, 'data-validation-errors-only'), $path . ' uses native constraint validation.');
        assertFalseValue(str_contains($source, 'data-validate='), $path . ' does not retain duplicate JavaScript validation metadata.');
        assertFalseValue(str_contains($source, 'novalidate'), $path . ' must allow native browser validation.');
        assertStringContains('data-bs-toggle="modal"', $source, $path);
        assertStringContains('data-bs-target="#' . $modalId . '"', $source, $path);
        assertStringContains('<span>' . $addLabel . '</span>', $source, $path);
        assertStringContains('data-admin-clean-url=', $source, $path);
        assertStringContains('data-admin-auto-open="true"', $source, $path);
        assertStringContains('<?= csrfInput() ?>', $source, $path);
        assertStringContains('table-responsive admin-table-wrap', $source, $path);
        assertStringContains('table table-hover align-middle w-100 mb-0', $source, $path);
        assertFalseValue(str_contains($source, 'data-bs-toggle="collapse"'), $path . ' must not retain the management collapse trigger.');
        assertFalseValue(str_contains($source, 'data-admin-confirm-form'), $path . ' add/edit form must submit directly.');
        assertFalseValue(str_contains($source, 'data-admin-confirm-field'), $path . ' must not project form values into a review modal.');
    }
});

testCase('management modal forms preserve request fields and edit URLs', function (): void {
    $staff = adminManagementUiSource('views/admin/add_staff.php');
    foreach (['first_name', 'last_name', 'email', 'phone_number', 'password'] as $field) {
        assertStringContains('name="' . $field . '"', $staff);
    }
    assertStringContains('data-admin-modal-mode="<?= $isEditing ? \'edit\' : \'add\' ?>"', $staff);
    assertStringContains('data-bs-backdrop="true"', $staff);
    assertStringContains('data-bs-keyboard="true"', $staff);
    assertFalseValue(str_contains($staff, 'modal-dialog modal-dialog-centered modal-dialog-scrollable'), 'Staff form avoids a nested mobile scroll region.');
    assertStringContains('add_staff.php?edit=', $staff);
    assertStringContains("includes/management_confirmation_modal.php", $staff);
    assertStringContains('modal-dialog modal-dialog-centered modal-xl', $staff, 'Staff creation uses the spacious Bootstrap modal width.');
    assertStringContains('form-check form-switch admin-setting-switch', $staff);
    assertStringContains('<strong>Active account</strong>', $staff);
    assertFalseValue(str_contains($staff, 'Assign Window'), 'Window assignment is managed from the Service Windows page.');
    foreach (['<th>Staff member</th>', '<th>Email</th>', '<th>Job title</th>', '<th>Status</th>', '<th>Current counter</th>'] as $column) {
        assertStringContains($column, $staff);
    }
    assertFalseValue(str_contains($staff, '<th>Phone</th>'), 'Optional mobile numbers are edited in the form, not exposed in the account list.');
    assertStringContains('<th>Actions</th>', $staff);
    assertStringContains('data-lucide="pencil"', $staff);
    assertStringContains('data-lucide="user-round-x"', $staff);
    assertStringContains('class="admin-row-action is-icon-only"', $staff);
    assertStringContains('class="admin-row-action is-danger is-icon-only"', $staff);
    assertStringContains('aria-label="Edit <?= htmlspecialchars($staffName, ENT_QUOTES) ?> staff account"', $staff);
    assertStringContains('aria-label="Deactivate <?= htmlspecialchars($staffName, ENT_QUOTES) ?> staff account"', $staff);
    assertFalseValue(str_contains($staff, '<span>Edit</span>'), 'Staff Edit actions display only their SVG icon.');
    assertFalseValue(str_contains($staff, '<span>Deactivate</span>'), 'Staff Deactivate actions display only their SVG icon.');
    assertStringContains('data-admin-confirm-title="Deactivate this staff account?"', $staff);
    assertFalseValue(str_contains($staff, 'number_format(count($staffRows))'), 'The staff toolbar no longer displays a total pill.');
    assertStringContains('aria-label="Staff accounts table; scroll horizontally to view all columns"', $staff);
    assertStringContains('tabindex="0"', $staff);

    $services = adminManagementUiSource('views/admin/services.php');
    foreach (['action', 'service_id', 'service_code', 'service_encoded', 'service_name', 'queue_mode', 'description', 'display_order', 'fallback_duration_mins', 'is_active', 'is_hidden', 'priority_only'] as $field) {
        assertStringContains('name="' . $field . '"', $services);
    }
    assertStringContains('readonly aria-readonly="true"', $services);
    assertStringContains('<th>Service code</th>', $services);
    assertFalseValue(str_contains($services, '<th>Estimated duration</th>'));
    assertFalseValue(str_contains($services, '<th>Display order</th>'));
    assertFalseValue(str_contains($services, 'name="action" value="reorder"'));
    assertStringContains('services.php?edit=', $services);
    assertStringContains('data-admin-modal-mode="<?= $isEditing ? \'edit\' : \'add\' ?>"', $services);
    assertFalseValue(str_contains($services, '<th>Queue Handling</th>'), 'Legacy routing is no longer a normal service configuration column.');
    assertStringContains('name="queue_mode"', $services);
    assertStringContains('name="priority_only" value="0"', $services);
    assertStringContains('data-admin-confirm-action', $services);
    assertStringContains("includes/management_confirmation_modal.php", $services);
    assertFalseValue(str_contains($services, 'data-admin-confirm="'), 'Legacy browser confirmation must be removed.');

    $windows = adminManagementUiSource('views/admin/windows.php');
    foreach (['window_id', 'window_name', 'counter_number', 'location_description', 'window_type', 'service_id', 'service_ids[]', 'is_active', 'management_status'] as $field) {
        assertStringContains('name="' . $field . '"', $windows);
    }
    assertFalseValue(str_contains($windows, 'name="staff_id"'), 'Runtime staff assignment is not an Admin contract.');
    assertFalseValue(str_contains($windows, 'name="status"'), 'Runtime status is not an Admin contract.');
    assertStringContains('windows.php?edit=', $windows);
    assertStringContains('data-admin-modal-mode="<?= $isEditing ? \'edit\' : \'add\' ?>"', $windows);
    assertStringContains('modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl', $windows);
    assertStringContains('admin-service-choice-grid', $windows);
    assertStringContains('admin-status-choice-group', $windows);
    foreach (['<th>Counter number</th>', '<th>Services offered</th>', '<th>Status</th>'] as $column) {
        assertStringContains($column, $windows);
    }
    assertFalseValue(str_contains($windows, '<th>Configuration</th>'));
    assertFalseValue(str_contains($windows, '<th>Runtime Status</th>'));
    assertStringContains('class="d-flex flex-row align-items-center gap-2 flex-shrink-0"', $windows);
    assertFalseValue(str_contains($windows, 'number_format(count($windows))'), 'The window toolbar no longer displays a total pill.');
    assertStringContains('aria-label="Service windows table; scroll horizontally to view all columns"', $windows);
    assertStringContains('class="admin-row-action is-icon-only"', $windows);
    assertStringContains('aria-label="Edit <?= htmlspecialchars($window[\'window_name\'], ENT_QUOTES) ?> service window"', $windows);
    assertFalseValue(str_contains($windows, '<span>Edit</span>'), 'Window Edit actions display only their SVG icon.');
    assertFalseValue(str_contains($windows, '<span>Cancel Edit</span>'), 'The modal uses the same concise Cancel label as Staff Accounts.');
    assertFalseValue(str_contains($windows, 'data-lucide="<?= $isEditing ? \'save\' : \'plus\' ?>"'), 'Window modal footer actions remain text-only.');
    assertFalseValue(str_contains($windows, "includes/management_confirmation_modal.php"), 'Window add/edit no longer uses the review modal.');
});

testCase('destructive service confirmation is Bootstrap accessible and password remains private', function (): void {
    $modal = adminManagementUiSource('views/admin/includes/management_confirmation_modal.php');
    foreach ([
        'modal fade admin-management-confirm-modal',
        'modal-dialog-centered modal-dialog-scrollable',
        'aria-labelledby="admin-management-confirmation-title"',
        'aria-describedby="admin-management-confirmation-message"',
        'data-admin-confirm-back',
        'data-admin-confirm-submit',
    ] as $contract) {
        assertStringContains($contract, $modal);
    }
    assertFalseValue(str_contains($modal, 'data-lucide='), 'Confirmation footer actions must remain text-only.');

    $staff = adminManagementUiSource('views/admin/add_staff.php');
    $tablePosition = strpos($staff, '<table');
    assertTrueValue($tablePosition !== false);
    assertFalseValue(str_contains(substr($staff, $tablePosition), 'name="password"'), 'Staff table must never render a password field.');
    assertFalseValue(str_contains($staff, 'value="<?= htmlspecialchars(oldFormValue($feedback, \'password\')'), 'Password must not be repopulated.');
    foreach ([
        'class="admin-password-field"',
        'class="admin-password-toggle"',
        'data-password-toggle',
        'data-password-show-icon',
        'data-password-hide-icon',
        'aria-controls="password"',
        'data-field-error-for="password"',
    ] as $passwordToggleContract) {
        assertStringContains($passwordToggleContract, $staff);
    }
});

testCase('shared validation and admin behavior own confirmation without window confirm', function (): void {
    $main = adminManagementUiSource('assets/js/main.js');
    assertStringContains("new CustomEvent('smartqms:request-confirmation'", $main);
    assertStringContains("form.hasAttribute('data-admin-confirm-form')", $main);
    assertStringContains('event.submitter || null', $main);
    assertStringContains('function clearServerFieldError', $main);
    assertStringContains('function initPasswordConfirmations', $main);
    assertStringContains("form.querySelectorAll('[data-confirm-password-for]')", $main);
    assertStringContains("confirmation.setCustomValidity(mismatch ? 'Password entries do not match.' : '');", $main);
    assertStringContains("typeof form.checkValidity === 'function'", $main);
    assertStringContains('form.reportValidity?.();', $main);
    assertStringContains("input.addEventListener('input', clearServerError);", $main);
    assertFalseValue(str_contains($main, 'function validateField'));
    assertFalseValue(str_contains($main, 'data-validation-errors-only'));
    assertFalseValue(str_contains($main, "classList.toggle('is-valid'"));
    assertFalseValue(str_contains($main, "classList.toggle('was-validated'"));

    $admin = adminManagementUiSource('assets/js/admin.js');
    foreach ([
        'function initManagementModals',
        'function initAdminToasts',
        'function initAdminConfirmations',
        "addEventListener('shown.bs.modal'",
        "addEventListener('hidden.bs.modal'",
        'form.reset()',
        'window.location.assign(cleanUrl)',
        "modalElement.hasAttribute('data-admin-auto-open')",
        "addEventListener('smartqms:request-confirmation'",
        "'Provided (hidden)'",
        'window.bootstrap.Modal.getOrCreateInstance',
        'window.bootstrap.Toast.getOrCreateInstance',
        'form.requestSubmit',
        "form.querySelectorAll('.is-invalid, .is-valid, [aria-invalid=\"true\"]')",
        "form.querySelectorAll('[data-confirm-password-for]')",
        "field.setCustomValidity('')",
    ] as $contract) {
        assertStringContains($contract, $admin);
    }
    assertFalseValue(str_contains($admin, 'window.confirm('));
});

testCase('admin management styling is full width token driven and responsive', function (): void {
    $css = adminManagementUiSource('assets/css/admin.css');
    assertStringContains(
        '.admin-kpi-icon.is-blue { background: var(--admin-primary); color: var(--sq-button-primary-text); }',
        $css
    );
    assertStringContains('.admin-management-shell {', $css);
    assertStringContains('max-width: none;', $css);
    assertStringContains('.admin-management-form-modal', $css);
    assertStringContains('.admin-management-form-modal .admin-field {', $css);
    assertStringContains('.admin-management-confirm-modal', $css);
    assertStringContains('.admin-confirm-summary', $css);
    assertStringContains('#staffAccountModal .admin-field', $css);
    assertStringContains('#staffAccountModal .modal-dialog,', $css);
    assertStringContains('#staffAccountModal .modal-content,', $css);
    assertStringContains('overflow-y: auto;', $css);
    assertStringContains('.admin-management-shell {', $css);
    assertStringContains('animation: none;', $css);
    assertStringContains('transform: none;', $css);
    assertStringContains('align-content: start;', $css);
    assertStringContains('.admin-management-form-modal .modal-footer .admin-action-button:not(.is-primary):hover', $css);
    assertStringContains('.admin-management-confirm-modal .modal-footer .admin-action-button:not(.is-primary):not(.is-danger):hover', $css);
    assertStringContains('color: var(--admin-text);', $css);
    assertStringContains('.admin-management-page input.form-control,', $css);
    assertStringContains('.admin-management-page select.form-select {', $css);
    assertStringContains('height: 44px;', $css);
    assertStringContains('.admin-select {', $css);
    assertStringContains('appearance: none;', $css);
    assertStringContains('-webkit-appearance: none;', $css);
    assertStringContains('--admin-select-indicator:', $css);
    assertStringContains('background-image: var(--admin-select-indicator);', $css);
    assertStringContains('background-position: right 12px center;', $css);
    assertFalseValue(str_contains($css, 'appearance: auto;'), 'Admin selects must not render a native arrow over Bootstrap’s indicator.');
    assertStringContains('.admin-management-page .admin-checkbox-row .form-check-input {', $css);
    assertStringContains('float: none;', $css);
    assertStringContains('flex: 0 0 20px;', $css);
    assertStringContains('.admin-staff-page .admin-row-action.is-icon-only', $css);
    assertStringContains('.admin-windows-page .admin-row-action.is-icon-only', $css);
    assertStringContains('.admin-services-page .admin-row-action.is-icon-only', $css);
    assertStringContains('.admin-services-page .admin-row-actions {', $css);
    assertStringContains('min-width: 148px;', $css);
    assertStringContains('background-color: var(--admin-button-bg);', $css);
    assertStringContains('background-color: var(--admin-danger-soft);', $css);
    assertStringContains('.admin-services-page .admin-row-actions form {', $css);
    assertStringContains('flex: 0 0 44px;', $css);
    assertStringContains('.admin-staff-page .admin-pagination-status,', $css);
    assertStringContains('.admin-services-page .admin-pagination-status,', $css);
    assertStringContains('text-align: center;', $css);
    assertStringContains('.admin-management-toolbar > * {', $css);
    assertStringContains('.admin-management-toolbar > div:last-child .admin-action-button {', $css);
    assertFalseValue(str_contains($css, '.admin-staff-page .admin-management-toolbar {'));
    assertStringContains('.admin-password-toggle {', $css);
    assertStringContains('grid-template-columns: repeat(2, minmax(0, 1fr));', $css);
    assertStringContains('.admin-management-confirm-modal .modal-footer {', $css);
    assertStringContains('--admin-on-danger: #FFFFFF;', $css);
    assertStringContains('width: 44px;', $css);
    assertStringContains('--bs-table-hover-color: var(--admin-text);', $css);
    assertStringContains('.admin-management-table > tbody > tr:nth-child(odd) > td', $css);
    assertStringContains('background: var(--admin-surface);', $css);
    assertStringContains('.admin-staff-page .admin-management-table-card .admin-table-wrap', $css);
    assertStringContains('.admin-windows-page .admin-management-table-card .admin-table-wrap', $css);
    assertStringContains('.admin-services-page .admin-management-table-card .admin-table-wrap', $css);
    assertStringContains('overscroll-behavior-inline: contain;', $css);
    assertStringContains('.admin-staff-page .admin-data-table[data-admin-paginated-table]', $css);
    assertStringContains('.admin-windows-page .admin-data-table[data-admin-paginated-table]', $css);
    assertStringContains('display: table;', $css);
    assertStringContains('background: var(--admin-surface);', $css);
    assertStringContains('--admin-modal-backdrop:', $css);
    assertFalseValue(str_contains($css, '.admin-management-form-collapse'));
    assertFalseValue(str_contains($css, '.admin-management-form-card'));
    assertFalseValue(str_contains($css, 'grid-template-columns: minmax(320px, 0.42fr) minmax(0, 0.58fr)'));
});

testCase('admin action feedback uses reusable Bootstrap toasts', function (): void {
    $partial = adminManagementUiSource('views/admin/includes/action_toasts.php');
    foreach ([
        'toast-container position-fixed',
        'class="toast admin-action-toast',
        'role="status"',
        'aria-live="polite"',
        'aria-atomic="true"',
        'data-bs-autohide="true"',
        'data-bs-delay="5000"',
        'data-bs-dismiss="toast"',
        'data-admin-action-toast',
    ] as $contract) {
        assertStringContains($contract, $partial);
    }

    $footer = adminManagementUiSource('views/admin/includes/footer.php');
    $sharedFooter = adminManagementUiSource('views/shared/includes/shell_footer.php');
    assertStringContains("\$appActionToasts = is_array(\$adminActionToasts", $footer);
    assertStringContains("shared/includes/shell_footer.php", $footer);
    assertStringContains("['tone' => 'success', 'message' => \$success]", $sharedFooter);
    assertStringContains("['tone' => 'info', 'message' => \$notice]", $sharedFooter);
    assertStringContains('data-app-action-toast', $sharedFooter);

    foreach ([
        'views/admin/add_staff.php',
        'views/admin/services.php',
        'views/admin/windows.php',
    ] as $path) {
        $source = adminManagementUiSource($path);
        assertFalseValue(
            str_contains($source, 'class="admin-alert is-success"'),
            $path . ' must render successful action feedback through the shared toast partial.'
        );
    }

    $css = adminManagementUiSource('assets/css/admin.css');
    assertStringContains('.admin-toast-container {', $css);
    assertStringContains('.admin-action-toast {', $css);
    assertStringContains('background: var(--admin-surface);', $css);
    assertStringContains('color: var(--admin-text);', $css);
});

testCase('health services mirrors the staff table workflow without count pills', function (): void {
    $services = adminManagementUiSource('views/admin/services.php');
    foreach ([
        'aria-label="Health services table; scroll horizontally to view all columns"',
        'class="admin-row-action is-icon-only"',
        'data-bs-target="#healthServiceModal"',
        '<span>Add Service</span>',
    ] as $contract) {
        assertStringContains($contract, $services);
    }

    assertFalseValue(str_contains($services, 'aria-label="Service counts"'));
    assertFalseValue(str_contains($services, '$activeCount'));
    assertFalseValue(str_contains($services, '<span>Edit</span>'));
    assertFalseValue(str_contains($services, '<span>Delete</span>'));
});

testCase('admin sidebar brand is left aligned and uses the healthcare queue icon system', function (): void {
    $sidebar = adminManagementUiSource('views/admin/includes/sidebar.php');
    assertStringContains('class="admin-brand"', $sidebar);
    assertStringContains('class="admin-brand-mark"', $sidebar);
    assertStringContains('data-lucide="heart-pulse"', $sidebar);
    assertStringContains('class="admin-brand-name">Smart QMS</span>', $sidebar);
    assertFalseValue(str_contains($sidebar, 'class="ms-2">Smart QMS</span>'));

    $css = adminManagementUiSource('assets/css/admin.css');
    assertStringContains('.admin-sidebar-header {', $css);
    assertStringContains('justify-content: flex-start;', $css);
    assertStringContains('padding: 0 24px;', $css);
    assertStringContains('text-align: left;', $css);
    assertStringContains('.admin-brand-mark {', $css);
    assertStringContains('background: var(--admin-primary-soft);', $css);
});

testCase('admin header provides a responsive Bootstrap navigation search', function (): void {
    $header = adminManagementUiSource('views/admin/includes/header.php');
    $sharedHeader = adminManagementUiSource('views/shared/includes/shell_header.php');
    foreach ([
        'app-search-collapse admin-header-search-collapse collapse width collapse-horizontal',
        'id="appHeaderSearch"',
        'role="search"',
        'data-app-search',
        'data-app-search-input',
        'aria-autocomplete="list"',
        'aria-controls="app-search-results"',
        'role="listbox"',
        'data-app-search-option',
        'data-bs-toggle="collapse"',
        'data-bs-target="#appHeaderSearch"',
        'data-app-search-toggle-icon',
        'app-user-menu-item admin-user-menu-item',
        'data-app-theme-toggle',
        'data-app-theme-label',
        'class="form-check-input" type="checkbox" role="switch"',
        'aria-label="Dark mode"',
    ] as $contract) {
        assertStringContains($contract, $sharedHeader);
    }
    foreach ([
        'foreach (reportDefinitions()',
        "'url' => 'dashboard.php'",
        "'url' => 'add_staff.php'",
        "'url' => 'services.php'",
        "'url' => 'windows.php'",
        "'url' => 'reports.php?report='",
    ] as $contract) {
        assertStringContains($contract, $header);
    }
    assertFalseValue(str_contains($header, 'class="admin-icon-button admin-theme-toggle"'));
    assertFalseValue(str_contains($header, "'url' => 'activity_log.php'"));
    assertFalseValue(str_contains($header, "'url' => 'settings.php'"));

    $admin = adminManagementUiSource('assets/js/shell.js');
    foreach ([
        'function initSearch',
        "form.dataset.appInitialized === 'true'",
        "event.key === 'Escape'",
        "collapse.addEventListener('shown.bs.collapse'",
        "collapse.addEventListener('show.bs.collapse'",
        "topbar?.classList.add('is-search-open')",
        "setAttribute('data-lucide', 'x')",
        "window.localStorage.setItem(THEME_KEY",
        "toggle.matches('input[role=\"switch\"]')",
        "toggle.checked = dark",
        'initSearch(root)',
    ] as $contract) {
        assertStringContains($contract, $admin);
    }

    $css = adminManagementUiSource('assets/css/admin.css') . adminManagementUiSource('assets/css/style.css');
    foreach ([
        '.admin-header-search-collapse {',
        '.admin-header-search .input-group:focus-within {',
        '.admin-search-results {',
        '.admin-search-option.is-active {',
        '.admin-icon-button.admin-search-toggle {',
        '.admin-header-search-collapse.collapsing {',
        '.app-topbar.is-search-open .app-notification-center',
        '.app-user-menu {',
        '@media (min-width: 769px) {',
        'flex-wrap: nowrap;',
        'flex: 1 1 auto;',
        'background: var(--admin-surface);',
        'color: var(--admin-text);',
        'outline: 3px solid var(--admin-focus-ring);',
    ] as $contract) {
        assertStringContains($contract, $css);
    }
    assertTrueValue(
        (bool) preg_match('/\.admin-header-search \.input-group\s*\{[^}]*background:\s*var\(--sq-surface\);/s', $css),
        'The Admin search icon and field must share the neutral surface background.'
    );
});

testCase('admin navigation and public entry points exclude removed activity log and settings features', function (): void {
    $sidebar = adminManagementUiSource('views/admin/includes/sidebar.php');

    assertFalseValue(str_contains($sidebar, 'activity_log.php'));
    assertFalseValue(str_contains($sidebar, '>Activity Logs<'));
    assertFalseValue(str_contains($sidebar, 'settings.php'));
    assertFalseValue(str_contains($sidebar, '>Settings<'));

    foreach ([
        'views/admin/activity_log.php',
        'views/admin/settings.php',
        'modules/settings/system_settings.php',
        'modules/settings/settings_store.php',
    ] as $removedPath) {
        assertFalseValue(
            is_file(SMARTQMS_ROOT . '/' . $removedPath),
            $removedPath . ' must remain removed.'
        );
    }
});

testCase('admin logout uses a Bootstrap modal and preserves its secure form contract', function (): void {
    $header = adminManagementUiSource('views/shared/includes/shell_header.php');
    assertStringContains('data-bs-toggle="modal"', $header);
    assertStringContains('data-bs-target="#appLogoutModal"', $header);
    assertStringContains("assetUrl('vendor/twbs/bootstrap/dist/css/bootstrap.min.css')", $header);

    $footer = adminManagementUiSource('views/shared/includes/shell_footer.php');
    foreach ([
        'modal fade app-logout-modal admin-logout-bootstrap-modal',
        'id="appLogoutModal"',
        'modal-dialog modal-dialog-centered',
        'aria-labelledby="app-logout-title"',
        'aria-describedby="app-logout-description"',
        'class="btn-close"',
        'data-bs-dismiss="modal"',
        "postActionUrl('modules/auth/logout.php')",
        'method="POST"',
        '<?= csrfInput() ?>',
        "assetUrl('vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js')",
    ] as $contract) {
        assertStringContains($contract, $footer);
    }
    assertFalseValue(str_contains($footer, 'admin-modal-scrim'));
    assertFalseValue(str_contains($footer, 'data-admin-logout-close'));

    $admin = adminManagementUiSource('assets/js/admin.js');
    assertFalseValue(str_contains($admin, 'function initLogoutModal'));
    assertStringContains("addEventListener('hidden.bs.modal'", $admin);

    $css = adminManagementUiSource('assets/css/admin.css');
    assertStringContains('.admin-logout-bootstrap-modal', $css);
    assertStringContains('.admin-logout-bootstrap-modal .modal-footer .admin-action-button:not(.is-primary):not(.is-danger):hover', $css);
    assertStringContains('.admin-logout-bootstrap-modal .admin-action-button.is-danger:focus-visible', $css);
    assertStringContains('background: var(--admin-soft-hover);', $css);
    assertStringContains('color: var(--admin-on-danger);', $css);
    assertFalseValue(str_contains($css, '.admin-modal-scrim'));
    assertFalseValue(str_contains($css, '.admin-logout-modal {'));
});
