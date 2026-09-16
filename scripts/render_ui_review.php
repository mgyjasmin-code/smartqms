<?php
/** Render the real view markup with fictional data, without database access. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$reviewPhase = $argv[1] ?? 'after';
if (!in_array($reviewPhase, ['before', 'after'], true)) exit(1);
$root = dirname(__DIR__);
$reviewOutputDirectory = $root . '/docs/ui-ux/redesign/preview/' . $reviewPhase;
if (!is_dir($reviewOutputDirectory)) mkdir($reviewOutputDirectory, 0775, true);
putenv('SMARTQMS_APP_ENV=testing');
putenv('SMARTQMS_APP_URL=http://127.0.0.1:8766/' . $reviewPhase);
require_once $root . '/config/config.php';
require_once $root . '/modules/reports/report_utils.php';
$_SESSION['name'] = 'Demo Operator';
$healthCenterName = 'Barangay Health Center';
$healthCenterContact = 'Ask the front desk for assistance';
$queueOpen = '08:00';
$queueClose = '15:30';
$formatTime = static fn($value) => date('g:i A', strtotime($value));
$services = array_map(static fn($name) => ['service_name' => $name, 'description' => ''], ['General Consultation', 'Vaccination', 'Prenatal Care', 'Dental Services']);
$today = date('Y-m-d');
$window = ['window_id' => 1, 'window_name' => 'Counter 01', 'service_name' => 'General Consultation', 'status' => 'busy'];
$windowServices = $services;
$currentLifecycle = 'calling';
$current = ['ticket_id' => 1, 'ticket_number' => 'A-024', 'service_name' => 'General Consultation', 'entry_type' => 'walk-in', 'checked_in_at' => $today . ' 09:20:00', 'called_at' => $today . ' 09:32:00'];
$waiting = array_map(static fn($n) => ['ticket_id' => $n, 'ticket_number' => 'A-0' . $n, 'service_name' => 'General Consultation', 'entry_type' => $n % 2 ? 'online' : 'walk-in', 'checked_in_at' => $today . ' 09:2' . ($n - 25) . ':00', 'issued_at' => $today . ' 09:20:00'], range(25, 29));
$staffKpis = ['completed' => 18, 'active' => 1, 'waiting' => 5, 'voided' => 2];
$voidRemainingSeconds = 120;
$counts = ['total_today' => 46, 'serving' => 3, 'voided_today' => 2];
$avgWait = 12.4;
$hourRows = [['hour_label' => '08:00', 'tickets' => 12], ['hour_label' => '09:00', 'tickets' => 21], ['hour_label' => '10:00', 'tickets' => 13]];
$peakHour = $hourRows[1];
$waitRows = [['report_date' => $today, 'predicted_wait_min' => 11.2, 'actual_wait_min' => 12.4, 'mae' => 1.2]];
$latestWait = $waitRows[0];
$hourChart = ['type' => 'bar', 'labels' => ['08:00', '09:00', '10:00'], 'datasets' => [['label' => 'Tickets', 'data' => [12, 21, 13]]], 'unit' => 'tickets'];
$waitChart = ['type' => 'line', 'labels' => ['Sep 01', 'Sep 02', 'Sep 03', 'Sep 04', 'Sep 05'], 'datasets' => [['label' => 'Predicted wait', 'data' => [10, 14, 12, 15, 11.2]], ['label' => 'Actual wait', 'data' => [12, 16, 11, 17, 12.4]]], 'unit' => 'minutes'];
$ticket = ['ticket_id' => 1, 'ticket_number' => 'A-024', 'qr_code_path' => ''];
$projection = ['status' => 'waiting', 'ticket_number' => 'A-024', 'reference_number' => 'DEMO-024', 'service_name' => 'General Consultation', 'visit_date' => $today, 'people_ahead' => 3, 'predicted_wait_minutes' => 12, 'counter_label' => 'Assigned when called'];
$waitValue = 12;
$hasWaitEstimate = true;
$token = str_repeat('a', 32);
$isScheduled = false;
$showConfirmation = false;
$managementUrl = '';

foreach (['landing' => 'index.php', 'tracker' => 'views/public/tracker.php', 'staff' => 'views/staff/dashboard.php', 'admin' => 'views/admin/dashboard.php'] as $screen => $relative) {
    $publicActivePage = $screen === 'tracker' ? 'track' : 'home';
    $pageTitle = $screen === 'staff' ? 'Staff Dashboard' : 'Admin Dashboard';
    $pageHeading = $screen === 'staff' ? 'Queue Management' : ($reviewPhase === 'after' ? 'Health center overview' : 'Operational Analytics');
    $pageSubtitle = $screen === 'staff' ? 'Manage your counter and the next client in line.' : 'Today at your health center.';
    $activePage = 'dashboard';
    $staffBodyClass = 'staff-dashboard-page';
    $adminBodyClass = 'admin-dashboard-page';
    $staffLiveRefresh = false;
    $source = file_get_contents($root . '/' . $relative);
    $body = substr($source, strpos($source, '?>') + 2);
    $body = str_replace('__DIR__', var_export(dirname($root . '/' . $relative), true), $body);
    ob_start();
    if (in_array($screen, ['staff', 'admin'], true)) include $root . '/views/' . $screen . '/includes/header.php';
    eval('?>' . $body);
    $html = ob_get_clean();
    // A review artifact has no credentials and performs no operational requests.
    $html = preg_replace('/<script\b[^>]*src="[^"]*"[^>]*>\s*<\/script>/i', '', $html);
    $html = preg_replace('/<script\b[^>]*id="smartqms-runtime-config"[^>]*>.*?<\/script>/s', '', $html);
    $html = preg_replace('/(name="csrf_token"[^>]*value=")[^"]*/', '$1review-only', $html);
    $html = preg_replace('/(<meta name="csrf-token" content=")[^"]*/', '$1review-only', $html);
    $html = str_replace('data-admin-live-dashboard', 'data-review-dashboard', $html);
    $html = str_replace('data-public-ticket-tracker', 'data-review-ticket-tracker', $html);
    $html = str_replace('data-staff-action', 'data-review-action', $html);
    $html = str_replace('</head>', '<link rel="stylesheet" href="../review.css"></head>', $html);
    $html = preg_replace('/(<body\b[^>]*>)/', '$1<div class="review-notice">Design review · Fictional sample data · ' . ucfirst($reviewPhase) . '</div>', $html, 1);
    $html = str_replace('</body>', '<script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script><script src="assets/vendor/lucide/lucide.min.js"></script><script src="assets/vendor/chart.js/chart.umd.min.js"></script><script src="assets/js/theme.js"></script><script src="assets/js/language.js"></script><script src="assets/js/main.js"></script><script src="assets/js/shell.js"></script><script src="assets/js/admin.js"></script><script src="../review.js"></script></body>', $html);
    file_put_contents($reviewOutputDirectory . '/' . $screen . '.html', $html);
}
echo "Rendered four $reviewPhase screens using fictional data.\n";
