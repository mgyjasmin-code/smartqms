<?php
/**
 * Focused report builder.
 */

function buildTurnaroundTimeReport(mysqli $conn, array $range): array {
    $rows = reportFetchAll($conn, "
        SELECT DATE(completed_at) AS report_date,
               COUNT(*) AS completed_tickets,
               ROUND(AVG(TIMESTAMPDIFF(MINUTE, issued_at, completed_at)), 2) AS avg_turnaround_min,
               MIN(TIMESTAMPDIFF(MINUTE, issued_at, completed_at)) AS min_turnaround_min,
               MAX(TIMESTAMPDIFF(MINUTE, issued_at, completed_at)) AS max_turnaround_min
        FROM queue_tickets
        WHERE completed_at IS NOT NULL
          AND DATE(completed_at) BETWEEN ? AND ?
        GROUP BY report_date
        ORDER BY report_date DESC
    ", 'ss', [$range['from'], $range['to']]);

    $columns = reportColumns([
        'report_date' => 'Date',
        'completed_tickets' => 'Completed',
        'avg_turnaround_min' => 'Avg Turnaround Min',
        'min_turnaround_min' => 'Min',
        'max_turnaround_min' => 'Max',
    ]);

    if (!$rows) {
        return emptyReport('turnaround_time', $range, $columns);
    }

    $overall = array_sum(array_map(static fn($row) => (float) $row['avg_turnaround_min'], $rows)) / count($rows);
    return [
        'key' => 'turnaround_time',
        'title' => 'Customer Turnaround Time',
        'description' => reportDefinitions()['turnaround_time']['description'],
        'range' => $range,
        'metrics' => [
            reportMetric('Avg Turnaround', number_format($overall, 2) . ' min'),
            reportMetric('Days Reported', count($rows)),
        ],
        'series' => array_map(static fn($row) => [
            'label' => $row['report_date'],
            'value' => (float) $row['avg_turnaround_min'],
        ], array_reverse($rows)),
        'table' => ['columns' => $columns, 'rows' => $rows],
        'insight' => 'Average daily turnaround was ' . number_format($overall, 2) . ' minutes.',
    ];
}
