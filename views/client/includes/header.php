<?php
$pageTitle = $pageTitle ?? 'Client Dashboard';
$pageHeading = $pageHeading ?? $pageTitle;
$pageSubtitle = $pageSubtitle ?? '';
$activePage = $activePage ?? 'dashboard';
$showClientPageHeader = $showClientPageHeader ?? true;

$clientSearchDestinations = [
    ['label' => 'Dashboard', 'description' => 'Join the queue and review your active ticket', 'icon' => 'layout-dashboard', 'url' => 'index.php', 'keywords' => 'home join queue ticket', 'type' => 'Page'],
    ['label' => 'Queue Status', 'description' => 'View live windows and waiting tickets', 'icon' => 'activity', 'url' => 'queue_status.php', 'keywords' => 'live queue waiting window', 'type' => 'Page'],
    ['label' => 'My Ticket', 'description' => 'Open your latest digital ticket', 'icon' => 'ticket', 'url' => 'ticket.php', 'keywords' => 'qr reference status ticket', 'type' => 'Page'],
];

$appShell = [
    'role' => 'client',
    'title' => $pageTitle,
    'heading' => $pageHeading,
    'subtitle' => $pageSubtitle,
    'active_page' => $activePage,
    'name' => $_SESSION['name'] ?? 'Client',
    'role_label' => 'Client',
    'body_class' => 'client-page',
    'main_id' => 'main-content',
    'main_class' => 'client-shell',
    'show_page_header' => $showClientPageHeader,
    'home_url' => 'index.php',
    'search_label' => 'Search client pages',
    'search_placeholder' => 'Search client pages',
    'search_destinations' => $clientSearchDestinations,
    'nav_items' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'url' => 'index.php'],
        ['key' => 'queue', 'label' => 'Queue Status', 'icon' => 'activity', 'url' => 'queue_status.php'],
        ['key' => 'ticket', 'label' => 'My Ticket', 'icon' => 'ticket', 'url' => 'ticket.php'],
    ],
    'body_data' => [
        'client-notification-url' => postActionUrl('modules/notifications/get_notifications.php'),
        'client-notification-read-url' => postActionUrl('modules/notifications/mark_read.php'),
    ],
    'logout_title' => 'Log out of Smart QMS?',
    'logout_description' => 'You will need to sign in again before joining or checking your queue.',
    'scripts' => ['assets/js/client.js'],
];

include __DIR__ . '/../../shared/includes/shell_header.php';
