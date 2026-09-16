<?php
/**
 * Focused report builder.
 */

function buildQueueSummaryReport(mysqli $conn, array $range): array {
    $rows = reportFetchAll($conn, "
        SELECT DATE(COALESCE(qt.checked_in_at, qt.issued_at)) AS report_date,
               hs.service_name,
               COALESCE(sw.window_name, 'Unassigned') AS window_name,
               qt.lifecycle_status AS status,
               COUNT(*) AS total
        FROM queue_tickets qt
        JOIN health_services hs ON hs.service_id = qt.service_id
        LEFT JOIN service_windows sw ON sw.window_id = qt.window_id
        WHERE qt.lifecycle_status <> 'scheduled'
          AND DATE(COALESCE(qt.checked_in_at, qt.issued_at)) BETWEEN ? AND ?
        GROUP BY report_date, hs.service_name, window_name, qt.lifecycle_status
        ORDER BY report_date DESC, hs.service_name, window_name, qt.lifecycle_status
    ", 'ss', [$range['from'], $range['to']]);

    $columns = reportColumns([
        'report_date' => 'Date',
        'service_name' => 'Service',
        'window_name' => 'Window',
        'status' => 'Status',
        'total' => 'Tickets',
    ]);

    if (!$rows) {
        return emptyReport('queue_summary', $range, $columns);
    }

    $summary = reportFetchOne($conn, "
        SELECT COUNT(*) AS total,
               SUM(lifecycle_status = 'waiting') AS waiting,
               SUM(lifecycle_status IN ('calling', 'in-progress')) AS serving,
               SUM(lifecycle_status = 'completed') AS completed
        FROM queue_tickets
        WHERE lifecycle_status <> 'scheduled'
          AND DATE(COALESCE(checked_in_at, issued_at)) BETWEEN ? AND ?
    ", 'ss', [$range['from'], $range['to']]);

    return [
        'key' => 'queue_summary',
        'title' => 'Queue Summary',
        'description' => reportDefinitions()['queue_summary']['description'],
        'range' => $range,
        'metrics' => [
            reportMetric('Total Tickets', (int) ($summary['total'] ?? 0), 'Checked-in queue records'),
            reportMetric('Waiting', (int) ($summary['waiting'] ?? 0), 'Still queued'),
            reportMetric('Calling / In Service', (int) ($summary['serving'] ?? 0), 'Currently at counters'),
            reportMetric('Completed', (int) ($summary['completed'] ?? 0), 'Finished service'),
        ],
        'series' => array_map(static fn($row) => [
            'label' => $row['report_date'] . ' ' . $row['service_name'],
            'value' => (int) $row['total'],
        ], $rows),
        'table' => ['columns' => $columns, 'rows' => $rows],
        'insight' => 'The selected period has ' . (int) ($summary['total'] ?? 0) . ' tickets across configured health services.',
    ];
}
