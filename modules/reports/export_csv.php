<?php
/**
 * SmartQMS CSV export backed by the shared report query layer.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/report_utils.php';
requireLogin(ROLE_ADMIN);

try {
    $range = reportDateRange($_GET);
    $reportKey = normalizeReportKey((string) ($_GET['report'] ?? 'queue_summary'));
    $report = buildReport($conn, $reportKey, $range);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
    header('Content-Type: text/plain; charset=utf-8');
    echo $e instanceof InvalidArgumentException ? $e->getMessage() : 'Could not export report.';
    exit();
}

$safeReport = preg_replace('/[^a-z0-9_]+/', '_', $report['key']);
$filename = 'smartqms_' . $safeReport . '_' . $range['from'] . '_' . $range['to'] . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

function safeCsvCell(mixed $value): mixed {
    if (!is_string($value)) {
        return $value;
    }

    return preg_match('/^[=\-+@\t\r\n]/', $value) ? "'" . $value : $value;
}

fputcsv($output, ['SmartQMS Report', $report['title']]);
fputcsv($output, ['From', $range['from'], 'To', $range['to']]);
fputcsv($output, []);

$columns = $report['table']['columns'] ?? [];
if ($columns) {
    fputcsv($output, array_map(static fn($column) => $column['label'], $columns));
    foreach ($report['table']['rows'] ?? [] as $row) {
        $line = [];
        foreach ($columns as $column) {
            $line[] = safeCsvCell($row[$column['key']] ?? '');
        }
        fputcsv($output, $line);
    }
}

fclose($output);
?>
