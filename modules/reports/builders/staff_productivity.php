<?php
/**
 * Focused report builder.
 */

function buildStaffProductivityReport(mysqli $conn, array $range): array {
    $rows = reportFetchAll($conn, "
        SELECT s.staff_id,
               CONCAT(u.first_name, ' ', u.last_name) AS staff_name,
               COALESCE(sw.window_name, 'Unassigned') AS window_name,
               COUNT(qt.ticket_id) AS tickets_served,
               ROUND(AVG(CASE WHEN qt.ticket_id IS NOT NULL THEN wl.actual_wait_min END), 2) AS avg_wait_min,
               ROUND(AVG(CASE WHEN qt.ticket_id IS NOT NULL THEN wl.actual_service_dur END) / 60, 2) AS avg_service_min
        FROM staff s
        JOIN users u ON u.user_id = s.user_id
        LEFT JOIN service_windows sw ON sw.staff_id = s.staff_id
        LEFT JOIN wait_time_logs wl ON wl.staff_id = s.staff_id
        LEFT JOIN queue_tickets qt ON qt.ticket_id = wl.ticket_id
             AND qt.status = 'completed'
             AND qt.lifecycle_status = 'completed'
             AND qt.completed_at IS NOT NULL
             AND wl.actual_wait_min IS NOT NULL
             AND DATE(qt.completed_at) BETWEEN ? AND ?
        GROUP BY s.staff_id, staff_name, window_name
        ORDER BY tickets_served DESC, staff_name
    ", 'ss', [$range['from'], $range['to']]);

    $columns = reportColumns([
        'staff_name' => 'Staff',
        'window_name' => 'Window',
        'tickets_served' => 'Served',
        'avg_wait_min' => 'Avg Wait Min',
        'avg_service_min' => 'Avg Service Min',
    ]);

    if (!$rows) {
        return emptyReport('staff_productivity', $range, $columns);
    }

    $top = $rows[0];
    return [
        'key' => 'staff_productivity',
        'title' => 'Staff Productivity',
        'description' => reportDefinitions()['staff_productivity']['description'],
        'range' => $range,
        'metrics' => [
            reportMetric('Staff Listed', count($rows)),
            reportMetric('Top Staff', $top['staff_name'], (int) $top['tickets_served'] . ' completed'),
        ],
        'series' => array_map(static fn($row) => [
            'label' => $row['staff_name'],
            'value' => (int) $row['tickets_served'],
        ], $rows),
        'table' => ['columns' => $columns, 'rows' => $rows],
        'insight' => $top['staff_name'] . ' has the highest completed ticket count for this period.',
    ];
}
