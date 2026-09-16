<?php
require_once __DIR__ . '/../../../modules/reports/report_core.php';

$pageTitle = $pageTitle ?? 'Admin Dashboard';
$pageHeading = $pageHeading ?? $pageTitle;
$pageSubtitle = $pageSubtitle ?? '';
$activePage = $activePage ?? '';
$activeReport = $activeReport ?? '';
$adminBodyClass = trim((string) ($adminBodyClass ?? ''));

$reportItems = [
    'queue_summary' => ['label' => 'Queue Summary', 'icon' => 'chart-column'],
    'predicted_vs_actual' => ['label' => 'Predicted vs Actual', 'icon' => 'line-chart'],
    'peak_hour' => ['label' => 'Peak Hour Analysis', 'icon' => 'trending-up'],
    'counter_performance' => ['label' => 'Counter Performance', 'icon' => 'gauge'],
    'turnaround_time' => ['label' => 'Turnaround Time', 'icon' => 'timer'],
    'no_show' => ['label' => 'No-Show Report', 'icon' => 'user-x'],
    'staff_productivity' => ['label' => 'Staff Productivity', 'icon' => 'users'],
    'ml_accuracy' => ['label' => 'ML Accuracy', 'icon' => 'brain-circuit'],
    'daily_monthly_stats' => ['label' => 'Daily/Monthly Stats', 'icon' => 'calendar-days'],
    'satisfaction' => ['label' => 'Satisfaction', 'icon' => 'star'],
];
$reportChildren = [];
foreach ($reportItems as $reportKey => $reportItem) {
    $reportChildren[] = [
        'key' => $reportKey,
        'label' => $reportItem['label'],
        'icon' => $reportItem['icon'],
        'url' => 'reports.php?report=' . urlencode($reportKey),
    ];
}

$adminSearchDestinations = [
    ['label' => 'Dashboard', 'description' => 'Operational analytics and queue overview', 'icon' => 'layout-dashboard', 'url' => 'dashboard.php', 'keywords' => 'home overview metrics analytics', 'type' => 'Page'],
    ['label' => 'Staff Accounts', 'description' => 'Create and manage staff accounts', 'icon' => 'users', 'url' => 'add_staff.php', 'keywords' => 'staff employee operator account', 'type' => 'Page'],
    ['label' => 'Health Services', 'description' => 'Configure client queue services', 'icon' => 'clipboard-list', 'url' => 'services.php', 'keywords' => 'health service catalog availability', 'type' => 'Page'],
    ['label' => 'Service Windows', 'description' => 'Assign counters, staff, and services', 'icon' => 'panel-top', 'url' => 'windows.php', 'keywords' => 'window counter station assignment', 'type' => 'Page'],
];
foreach (reportDefinitions() as $reportKey => $reportDefinition) {
    $adminSearchDestinations[] = [
        'label' => (string) $reportDefinition['label'],
        'description' => (string) $reportDefinition['description'],
        'icon' => (string) $reportDefinition['icon'],
        'url' => 'reports.php?report=' . urlencode((string) $reportKey),
        'keywords' => 'report analytics ' . str_replace('_', ' ', (string) $reportKey),
        'type' => 'Report',
    ];
}

$appShell = [
    'role' => 'admin',
    'title' => $pageTitle,
    'heading' => $pageHeading,
    'subtitle' => $pageSubtitle,
    'active_page' => $activePage,
    'active_report' => $activeReport,
    'name' => $_SESSION['name'] ?? 'System Administrator',
    'role_label' => 'Administrator',
    'body_class' => $adminBodyClass,
    'main_id' => 'admin-main',
    'home_url' => 'dashboard.php',
    'legacy_theme_key' => 'smartqms-admin-theme',
    'search_label' => 'Search administrator pages and reports',
    'search_placeholder' => 'Search pages and reports',
    'nav_label' => 'Admin workspace',
    'search_destinations' => $adminSearchDestinations,
    'nav_items' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'url' => 'dashboard.php'],
        ['key' => 'staff', 'label' => 'Staff Accounts', 'icon' => 'user-plus', 'url' => 'add_staff.php'],
        ['key' => 'services', 'label' => 'Health Services', 'icon' => 'clipboard-list', 'url' => 'services.php'],
        ['key' => 'windows', 'label' => 'Service Windows', 'icon' => 'panel-top', 'url' => 'windows.php'],
        ['key' => 'reports', 'label' => 'Reports', 'icon' => 'file-text', 'children' => $reportChildren],
    ],
    'logout_title' => 'Log out of admin?',
    'logout_description' => 'You will return to the sign-in screen and need to sign in again before managing Smart QMS.',
    'scripts' => [
        'assets/js/client/services/permissions.js',
        'assets/js/client/services/services.js',
        'assets/js/client/services/branches.js',
        'assets/js/client/services/queue.js',
        'assets/js/client/services/realtime.js',
        'assets/js/admin.js',
    ],
    'load_chart' => true,
];

include __DIR__ . '/../../shared/includes/shell_header.php';
