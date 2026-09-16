<?php
/**
 * Focused report builder.
 */

function buildNoShowReport(mysqli $conn, array $range): array {
    $rows = reportFetchAll($conn, "
        SELECT DATE(COALESCE(qt.voided_at, qt.issued_at)) AS report_date,
               CONCAT(LPAD(HOUR(COALESCE(qt.voided_at, qt.issued_at)), 2, '0'), ':00') AS hour_label,
               hs.service_name,
               qt.lifecycle_status AS status,
               COALESCE(qt.voided_reason, 'No reason recorded') AS reason,
               COUNT(*) AS total
        FROM queue_tickets qt
        JOIN health_services hs ON hs.service_id = qt.service_id
        WHERE qt.lifecycle_status = 'void'
          AND DATE(COALESCE(qt.voided_at, qt.issued_at)) BETWEEN ? AND ?
        GROUP BY report_date, hour_label, hs.service_name, qt.lifecycle_status, reason
        ORDER BY report_date DESC, hour_label, hs.service_name
    ", 'ss', [$range['from'], $range['to']]);

    $columns = reportColumns([
        'report_date' => 'Date',
        'hour_label' => 'Hour',
        'service_name' => 'Service',
        'status' => 'Status',
        'reason' => 'Reason',
        'total' => 'Tickets',
    ]);

    if (!$rows) {
        return emptyReport('no_show', $range, $columns);
    }

    $total = array_sum(array_map(static fn($row) => (int) $row['total'], $rows));
    return [
        'key' => 'no_show',
        'title' => 'No-Show Report',
        'description' => reportDefinitions()['no_show']['description'],
        'range' => $range,
        'metrics' => [
            reportMetric('No-Shows', $total, 'Skipped and voided tickets'),
            reportMetric('Grouped Rows', count($rows)),
        ],
        'series' => array_map(static fn($row) => [
            'label' => $row['report_date'] . ' ' . $row['hour_label'],
            'value' => (int) $row['total'],
        ], $rows),
        'table' => ['columns' => $columns, 'rows' => $rows],
        'insight' => 'There were ' . $total . ' skipped or voided tickets in the selected period.',
    ];
}
