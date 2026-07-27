<?php
$pageTitle = $pageTitle ?? 'Staff Workspace';
$pageHeading = $pageHeading ?? $pageTitle;
$pageSubtitle = $pageSubtitle ?? '';
$activePage = $activePage ?? 'dashboard';
$showStaffPageHeader = $showStaffPageHeader ?? true;

$staffSearchDestinations = [
    ['label' => 'Queue Management', 'description' => 'Call and manage waiting tickets', 'icon' => 'users', 'url' => 'dashboard.php', 'keywords' => 'dashboard queue call complete skip void', 'type' => 'Page'],
    ['label' => 'My Window', 'description' => 'Review the assigned service window', 'icon' => 'monitor', 'url' => 'window.php', 'keywords' => 'window counter service status', 'type' => 'Page'],
    ['label' => 'Activity Log', 'description' => 'Review recent ticket actions', 'icon' => 'history', 'url' => 'activity_log.php', 'keywords' => 'history activity audit actions', 'type' => 'Page'],
];

$appShell = [
    'role' => 'staff',
    'title' => $pageTitle,
    'heading' => $pageHeading,
    'subtitle' => $pageSubtitle,
    'active_page' => $activePage,
    'name' => $_SESSION['name'] ?? 'Staff',
    'role_label' => 'Staff',
    'body_class' => 'staff-page',
    'main_id' => 'staff-main',
    'main_class' => 'staff-main',
    'show_page_header' => $showStaffPageHeader,
    'home_url' => 'dashboard.php',
    'legacy_theme_key' => 'smartqms-staff-theme',
    'search_label' => 'Search staff pages',
    'search_placeholder' => 'Search staff workspace',
    'search_destinations' => $staffSearchDestinations,
    'nav_items' => [
        ['key' => 'dashboard', 'label' => 'Queue Management', 'icon' => 'users', 'url' => 'dashboard.php'],
        ['key' => 'window', 'label' => 'My Window', 'icon' => 'monitor', 'url' => 'window.php'],
        ['key' => 'activity', 'label' => 'Activity Log', 'icon' => 'history', 'url' => 'activity_log.php'],
    ],
    'logout_title' => 'Log out of staff workspace?',
    'logout_description' => 'You will need to sign in again before managing your service window.',
    'scripts' => ['assets/js/staff.js'],
];

include __DIR__ . '/../../shared/includes/shell_header.php';

if ($showStaffPageHeader): ?>
  <p class="staff-page-kicker">Staff Operations</p>
<?php endif; ?>

<div class="staff-action-status" data-staff-status role="status" aria-live="polite" tabindex="-1" hidden>
  <i data-lucide="info" aria-hidden="true"></i>
  <span data-staff-status-message></span>
  <button type="button" data-staff-status-clear aria-label="Clear status message"><i data-lucide="x" aria-hidden="true"></i></button>
</div>
