<?php
/**
 * Shared read-only staff window context.
 * Keeps Dashboard and My Window aligned with the existing queue queries.
 */

$staffId = getCurrentStaffId($conn);
$window = $staffId ? getStaffWindow($conn, $staffId) : null;
$current = null;
$waiting = [];
$voidTimeoutMinutes = max(1, (int) getSetting($conn, 'void_timeout_minutes', '10'));
$voidRemainingSeconds = null;

if ($window) {
    $windowId = (int) $window['window_id'];
    $serviceId = (int) $window['service_id'];
    $current = getStaffWindowCurrentTicket($conn, $windowId);

    if ($serviceId > 0) {
        $waiting = getStaffWindowWaitingTickets($conn, $serviceId, 20);
    }
}

$voidRemainingSeconds = staffVoidRemainingSeconds(
    $current['called_at'] ?? null,
    $voidTimeoutMinutes
);

$currentClientTypeLabel = $current
    ? staffClientTypeLabel($current['client_type'] ?? 'regular')
    : '';
$currentIsPriority = $current && in_array($current['client_type'], ['senior', 'pwd'], true);
?>
