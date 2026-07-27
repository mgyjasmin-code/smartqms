<?php
/**
 * Focused report builder.
 */

function buildDailyMonthlyStatsReport(mysqli $conn, array $range): array {
    $daily = reportFetchAll($conn, "
        SELECT DATE(issued_at) AS period_label, 'daily' AS period_type, COUNT(*) AS tickets
        FROM queue_tickets
        WHERE DATE(issued_at) BETWEEN ? AND ?
        GROUP BY DATE(issued_at)
        ORDER BY period_label DESC
    ", 'ss', [$range['from'], $range['to']]);

    $monthly = reportFetchAll($conn, "
        SELECT DATE_FORMAT(issued_at, '%Y-%m') AS period_label, 'monthly' AS period_type, COUNT(*) AS tickets
        FROM queue_tickets
        WHERE DATE(issued_at) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(issued_at, '%Y-%m')
        ORDER BY period_label DESC
    ", 'ss', [$range['from'], $range['to']]);

    $rows = array_merge($daily, $monthly);
    $columns = reportColumns([
        'period_type' => 'Period Type',
        'period_label' => 'Period',
        'tickets' => 'Tickets',
    ]);

    if (!$rows) {
        return emptyReport('daily_monthly_stats', $range, $columns);
    }

    $total = array_sum(array_map(static fn($row) => (int) $row['tickets'], $daily));
    return [
        'key' => 'daily_monthly_stats',
        'title' => 'Daily and Monthly Stats',
        'description' => reportDefinitions()['daily_monthly_stats']['description'],
        'range' => $range,
        'metrics' => [
            reportMetric('Total Tickets', $total, 'Daily rows only'),
            reportMetric('Daily Periods', count($daily)),
            reportMetric('Monthly Periods', count($monthly)),
        ],
        'series' => array_map(static fn($row) => [
            'label' => $row['period_label'],
            'value' => (int) $row['tickets'],
        ], array_reverse($daily)),
        'table' => ['columns' => $columns, 'rows' => $rows],
        'insight' => 'The selected period has ' . $total . ' tickets across ' . count($daily) . ' active day(s).',
    ];
}
