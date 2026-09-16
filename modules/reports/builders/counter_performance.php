<?php
/**
 * Focused report builder.
 */

function buildCounterPerformanceReport(mysqli $conn, array $range): array {
    $rows = reportFetchAll($conn, "
        SELECT COALESCE(sw.window_name, 'Unassigned') AS window_name,
               COALESCE(hs.service_name, 'Unassigned') AS service_name,
               COUNT(wl.log_id) AS tickets_served,
               ROUND(AVG(wl.actual_wait_min), 2) AS avg_wait_min,
               ROUND(AVG(wl.actual_service_dur) / 60, 2) AS avg_service_min
        FROM wait_time_logs wl
        JOIN queue_tickets qt ON qt.ticket_id = wl.ticket_id
        LEFT JOIN service_windows sw ON sw.window_id = qt.window_id
        LEFT JOIN health_services hs ON hs.service_id = qt.service_id
        WHERE qt.status = 'completed'
          AND qt.lifecycle_status = 'completed'
          AND qt.completed_at IS NOT NULL
          AND wl.actual_wait_min IS NOT NULL
          AND DATE(qt.completed_at) BETWEEN ? AND ?
        GROUP BY window_name, service_name
        ORDER BY tickets_served DESC, window_name
    ", 'ss', [$range['from'], $range['to']]);

    $columns = reportColumns([
        'window_name' => 'Window',
        'service_name' => 'Service',
        'tickets_served' => 'Served',
        'avg_wait_min' => 'Avg Wait Min',
        'avg_service_min' => 'Avg Service Min',
    ]);

    if (!$rows) {
        return emptyReport('counter_performance', $range, $columns);
    }

    $top = $rows[0];
    return [
        'key' => 'counter_performance',
        'title' => 'Service Counter Performance',
        'description' => reportDefinitions()['counter_performance']['description'],
        'range' => $range,
        'metrics' => [
            reportMetric('Windows Served', count($rows)),
            reportMetric('Top Window', $top['window_name'], (int) $top['tickets_served'] . ' tickets'),
        ],
        'series' => array_map(static fn($row) => [
            'label' => $row['window_name'],
            'value' => (int) $row['tickets_served'],
        ], $rows),
        'table' => ['columns' => $columns, 'rows' => $rows],
        'insight' => $top['window_name'] . ' handled the highest completed volume in this period.',
    ];
}
