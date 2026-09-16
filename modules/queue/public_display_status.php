<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(false, ['error' => 'Method not allowed.'], 405);
}

header('Cache-Control: no-store, public');
$hasLifecycle = smartqmsTableHasColumn($conn, 'queue_tickets', 'lifecycle_status');
$condition = $hasLifecycle
    ? "qt.lifecycle_status IN ('calling','in-progress')"
    : "qt.status = 'serving'";
$result = $conn->query("
    SELECT qt.ticket_id, qt.ticket_number, qt.called_at,
           sw.window_name AS counter_label, hs.service_name
    FROM queue_tickets qt
    JOIN health_services hs ON hs.service_id = qt.service_id
    JOIN service_windows sw ON sw.window_id = qt.window_id
    WHERE {$condition}
    ORDER BY qt.called_at DESC, qt.ticket_id DESC
");

$servingRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$waitingCondition = $hasLifecycle ? "qt.lifecycle_status = 'waiting'" : "qt.status = 'waiting'";
$waitingResult = $conn->query("
    SELECT qt.ticket_number, qt.checked_in_at, hs.service_name
    FROM queue_tickets qt
    JOIN health_services hs ON hs.service_id = qt.service_id
    WHERE {$waitingCondition} AND qt.ticket_number IS NOT NULL
    ORDER BY COALESCE(qt.checked_in_at, qt.issued_at) ASC, qt.ticket_id ASC
");
$waitingRows = $waitingResult ? $waitingResult->fetch_all(MYSQLI_ASSOC) : [];
$recentResult = $conn->query("
    SELECT qt.ticket_id, qt.ticket_number, qt.called_at,
           sw.window_name AS counter_label
    FROM queue_tickets qt
    JOIN service_windows sw ON sw.window_id = qt.window_id
    WHERE qt.called_at IS NOT NULL
      AND qt.ticket_number IS NOT NULL
      AND DATE(qt.called_at) = CURDATE()
    ORDER BY qt.called_at DESC, qt.ticket_id DESC
    LIMIT 8
");
$recentRows = $recentResult ? $recentResult->fetch_all(MYSQLI_ASSOC) : [];
$serving = array_map(static fn(array $row): array => [
    'ticket_id' => (int) $row['ticket_id'],
    'ticket_number' => (string) $row['ticket_number'],
    'counter_label' => (string) $row['counter_label'],
    'service_name' => (string) $row['service_name'],
    'called_at' => (string) $row['called_at'],
], $servingRows);
$waiting = array_map(static fn(array $row): array => [
    'ticket_number' => (string) $row['ticket_number'],
    'service_name' => (string) $row['service_name'],
    'checked_in_at' => (string) ($row['checked_in_at'] ?? ''),
], $waitingRows);
$recent = array_map(static fn(array $row): array => [
    'ticket_id' => (int) $row['ticket_id'],
    'ticket_number' => (string) $row['ticket_number'],
    'counter_label' => (string) $row['counter_label'],
    'called_at' => (string) $row['called_at'],
], $recentRows);
jsonResponse(true, [
    'data' => $serving,
    'serving' => $serving,
    'waiting' => $waiting,
    'recent' => $recent,
    'generated_at' => date(DATE_ATOM),
]);
