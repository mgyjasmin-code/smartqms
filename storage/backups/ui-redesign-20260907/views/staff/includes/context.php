<?php
/**
 * Shared read-only staff window context.
 * Provides the Queue Management dashboard with the existing queue queries.
 */

$staffId = getCurrentStaffId($conn);
$voidTimeoutMinutes = max(1, (int) getSetting($conn, 'void_timeout_minutes', '5'));
$workspace = smartqmsStaffWorkspaceContext(
    $conn,
    (int) ($staffId ?? 0),
    (int) ($_SESSION['user_id'] ?? 0),
    $voidTimeoutMinutes
);
$window = $workspace['window'];
$current = $workspace['current'];
$waiting = $workspace['waiting'];
$windowServices = $window ? getWindowServices($conn, $window) : [];
$staffKpis = [
    'tickets_today' => 0,
    'active' => 0,
    'waiting' => count($waiting),
    'completed' => 0,
    'voided' => 0,
];

if ($window && smartqmsDataProviderMode() !== 'supabase') {
    $staffKpis = getStaffWindowKpis($conn, $window);
}

$voidRemainingSeconds = staffVoidRemainingSeconds(
    $current['called_at'] ?? null,
    $voidTimeoutMinutes
);

$currentClientTypeLabel = $current
    ? staffClientTypeLabel($current['client_type'] ?? 'regular')
    : '';
$currentIsPriority = $current && in_array($current['client_type'], ['senior', 'pwd'], true);
$currentLifecycle = $current
    ? (string) ($current['lifecycle_status'] ?? 'in-progress')
    : '';
?>
