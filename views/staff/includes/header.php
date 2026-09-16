<?php
$pageTitle = $pageTitle ?? 'Staff Workspace';
$pageHeading = $pageHeading ?? $pageTitle;
$pageSubtitle = $pageSubtitle ?? '';
$activePage = $activePage ?? 'dashboard';
$showStaffPageHeader = $showStaffPageHeader ?? true;
$staffBodyClass = trim((string) ($staffBodyClass ?? ''));
$staffLiveRefresh = (bool) ($staffLiveRefresh ?? false);
$staffPageScripts = is_array($staffPageScripts ?? null) ? $staffPageScripts : [];

$staffSearchDestinations = [
    ['label' => 'Queue Management', 'description' => 'Call and manage waiting tickets', 'icon' => 'users', 'url' => APP_URL . '/views/staff/dashboard.php', 'keywords' => 'dashboard queue call complete skip void', 'type' => 'Page'],
    ['label' => 'Arrival Check-In', 'description' => 'Scan, search, or register arriving clients', 'icon' => 'scan-line', 'url' => APP_URL . '/staff/check-in/', 'keywords' => 'arrival scan qr reference manual walk in', 'type' => 'Page'],
    ['label' => 'Batch Printing', 'description' => 'Reserve and print physical queue numbers', 'icon' => 'printer', 'url' => APP_URL . '/staff/batch-printing/', 'keywords' => 'batch print pdf reserve ticket numbers', 'type' => 'Page'],
    ['label' => 'Public Display', 'description' => 'Open the read-only queue display', 'icon' => 'presentation', 'url' => APP_URL . '/public-display/', 'keywords' => 'public display serving waiting mirror', 'type' => 'Display'],
];

$appShell = [
    'role' => 'staff',
    'title' => $pageTitle,
    'heading' => $pageHeading,
    'subtitle' => $pageSubtitle,
    'active_page' => $activePage,
    'name' => $_SESSION['name'] ?? 'Staff',
    'role_label' => 'Staff',
    'body_class' => trim('staff-page ' . $staffBodyClass),
    'main_id' => 'staff-main',
    'main_class' => 'staff-main',
    'show_page_header' => $showStaffPageHeader,
    'home_url' => APP_URL . '/views/staff/dashboard.php',
    'legacy_theme_key' => 'smartqms-staff-theme',
    'search_label' => 'Search staff pages',
    'search_placeholder' => 'Search staff workspace',
    'search_destinations' => $staffSearchDestinations,
    'show_search' => false,
    'show_sidebar' => false,
    'body_data' => [
        'queue-branch-id' => (string) ($window['_provider_branch_id'] ?? ''),
        'staff-live-refresh' => $staffLiveRefresh ? 'true' : 'false',
    ],
    'nav_items' => [],
    'logout_title' => 'Log out of staff workspace?',
    'logout_description' => 'You will need to sign in again before managing your service window.',
    'scripts' => array_merge([
        'assets/js/client/services/permissions.js',
        'assets/js/client/services/queue.js',
        'assets/js/client/services/realtime.js',
    ], $staffPageScripts, [
        'assets/js/staff.js',
    ]),
];

include __DIR__ . '/../../shared/includes/shell_header.php';
