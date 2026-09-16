<?php
/**
 * Focused report builder.
 */

function buildPeakHourReport(mysqli $conn, array $range): array {
    $rows = reportFetchAll($conn, "
        SELECT LPAD(HOUR(COALESCE(checked_in_at, issued_at)), 2, '0') AS hour_of_day,
               CONCAT(LPAD(HOUR(COALESCE(checked_in_at, issued_at)), 2, '0'), ':00') AS hour_label,
               COUNT(*) AS tickets
        FROM queue_tickets
        WHERE lifecycle_status <> 'scheduled'
          AND DATE(COALESCE(checked_in_at, issued_at)) BETWEEN ? AND ?
        GROUP BY HOUR(COALESCE(checked_in_at, issued_at))
        ORDER BY HOUR(COALESCE(checked_in_at, issued_at))
    ", 'ss', [$range['from'], $range['to']]);

    $columns = reportColumns([
        'hour_label' => 'Hour',
        'tickets' => 'Tickets',
    ]);

    if (!$rows) {
        return emptyReport('peak_hour', $range, $columns);
    }

    $peak = $rows[0];
    foreach ($rows as $row) {
        if ((int) $row['tickets'] > (int) $peak['tickets']) {
            $peak = $row;
        }
    }

    return [
        'key' => 'peak_hour',
        'title' => 'Peak Hour Analysis',
        'description' => reportDefinitions()['peak_hour']['description'],
        'range' => $range,
        'metrics' => [
            reportMetric('Peak Hour', $peak['hour_label'], (int) $peak['tickets'] . ' tickets'),
            reportMetric('Hours With Traffic', count($rows)),
        ],
        'series' => array_map(static fn($row) => [
            'label' => $row['hour_label'],
            'value' => (int) $row['tickets'],
        ], $rows),
        'table' => ['columns' => $columns, 'rows' => $rows],
        'insight' => 'The busiest hour was ' . $peak['hour_label'] . ' with ' . (int) $peak['tickets'] . ' tickets.',
    ];
}
