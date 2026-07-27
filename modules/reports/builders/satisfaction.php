<?php
/**
 * Focused report builder.
 */

function buildSatisfactionReport(mysqli $conn, array $range): array {
    $rows = reportFetchAll($conn, "
        SELECT DATE(f.submitted_at) AS report_date,
               COALESCE(hs.service_name, 'Unassigned') AS service_name,
               COALESCE(sw.window_name, 'Unassigned') AS window_name,
               ROUND(AVG(f.rating), 2) AS avg_rating,
               COUNT(*) AS feedback_count
        FROM feedback f
        LEFT JOIN health_services hs ON hs.service_id = f.service_id
        LEFT JOIN service_windows sw ON sw.window_id = f.window_id
        WHERE DATE(f.submitted_at) BETWEEN ? AND ?
        GROUP BY report_date, service_name, window_name
        ORDER BY report_date DESC, service_name, window_name
    ", 'ss', [$range['from'], $range['to']]);

    $columns = reportColumns([
        'report_date' => 'Date',
        'service_name' => 'Service',
        'window_name' => 'Window',
        'avg_rating' => 'Avg Rating',
        'feedback_count' => 'Responses',
    ]);

    if (!$rows) {
        return emptyReport('satisfaction', $range, $columns);
    }

    $totalResponses = array_sum(array_map(static fn($row) => (int) $row['feedback_count'], $rows));
    $weighted = 0.0;
    foreach ($rows as $row) {
        $weighted += (float) $row['avg_rating'] * (int) $row['feedback_count'];
    }
    $overall = $totalResponses > 0 ? $weighted / $totalResponses : 0;

    return [
        'key' => 'satisfaction',
        'title' => 'Satisfaction Report',
        'description' => reportDefinitions()['satisfaction']['description'],
        'range' => $range,
        'metrics' => [
            reportMetric('Overall Rating', number_format($overall, 2) . '/5'),
            reportMetric('Responses', $totalResponses),
        ],
        'series' => array_map(static fn($row) => [
            'label' => $row['service_name'],
            'value' => (float) $row['avg_rating'],
        ], $rows),
        'table' => ['columns' => $columns, 'rows' => $rows],
        'insight' => 'Overall satisfaction is ' . number_format($overall, 2) . ' out of 5 for the selected period.',
    ];
}
