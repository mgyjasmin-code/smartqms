<?php
/**
 * Focused report builder.
 */

function buildPredictedVsActualReport(mysqli $conn, array $range): array {
    $rows = reportFetchAll($conn, "
        SELECT qt.ticket_number,
               DATE(qt.completed_at) AS report_date,
               hs.service_name,
               ROUND(wl.predicted_wait_min, 2) AS predicted_wait_min,
               ROUND(
                   TIMESTAMPDIFF(
                       SECOND,
                       COALESCE(qt.checked_in_at, qt.issued_at),
                       COALESCE(qt.started_at, qt.served_at)
                   ) / 60,
                   2
               ) AS actual_wait_min,
               ROUND(
                   ABS(
                       wl.predicted_wait_min -
                       TIMESTAMPDIFF(
                           SECOND,
                           COALESCE(qt.checked_in_at, qt.issued_at),
                           COALESCE(qt.started_at, qt.served_at)
                       ) / 60
                   ),
                   2
               ) AS error_min
        FROM wait_time_logs wl
        JOIN queue_tickets qt ON qt.ticket_id = wl.ticket_id
        JOIN health_services hs ON hs.service_id = qt.service_id
        WHERE qt.status = 'completed'
          AND qt.lifecycle_status = 'completed'
          AND qt.completed_at IS NOT NULL
          AND COALESCE(qt.started_at, qt.served_at) IS NOT NULL
          AND wl.predicted_wait_min IS NOT NULL
          AND wl.predicted_wait_min BETWEEN 0 AND 480
          AND TIMESTAMPDIFF(
                  SECOND,
                  COALESCE(qt.checked_in_at, qt.issued_at),
                  COALESCE(qt.started_at, qt.served_at)
              ) BETWEEN 0 AND 28800
          AND DATE(qt.completed_at) BETWEEN ? AND ?
        ORDER BY qt.completed_at DESC
    ", 'ss', [$range['from'], $range['to']]);

    $columns = reportColumns([
        'ticket_number' => 'Ticket',
        'report_date' => 'Date',
        'service_name' => 'Service',
        'predicted_wait_min' => 'Predicted Min',
        'actual_wait_min' => 'Actual Min',
        'error_min' => 'Error Min',
    ]);

    if (!$rows) {
        return emptyReport('predicted_vs_actual', $range, $columns);
    }

    $mae = array_sum(array_map(static fn($row) => (float) $row['error_min'], $rows)) / count($rows);
    $avgPredicted = array_sum(array_map(static fn($row) => (float) $row['predicted_wait_min'], $rows)) / count($rows);
    $avgActual = array_sum(array_map(static fn($row) => (float) $row['actual_wait_min'], $rows)) / count($rows);

    return [
        'key' => 'predicted_vs_actual',
        'title' => 'Predicted vs Actual Wait',
        'description' => reportDefinitions()['predicted_vs_actual']['description'],
        'range' => $range,
        'metrics' => [
            reportMetric('MAE', number_format($mae, 2) . ' min', 'Average absolute error'),
            reportMetric('Avg Predicted', number_format($avgPredicted, 2) . ' min'),
            reportMetric('Avg Actual', number_format($avgActual, 2) . ' min'),
            reportMetric('Valid Samples', count($rows), 'Completed live queue observations'),
        ],
        'series' => array_map(static fn($row) => [
            'label' => $row['ticket_number'],
            'predicted' => (float) $row['predicted_wait_min'],
            'actual' => (float) $row['actual_wait_min'],
        ], array_reverse(array_slice($rows, 0, 20))),
        'table' => ['columns' => $columns, 'rows' => $rows],
        'insight' => 'Model wait predictions were off by an average of ' . number_format($mae, 2) . ' minutes for completed tickets.',
    ];
}
