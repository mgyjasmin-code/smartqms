<?php
/**
 * Shared report queries for SmartQMS admin pages, JSON endpoints, and CSV export.
 */

function reportDefinitions(): array {
    return [
        'queue_summary' => [
            'label' => 'Queue Summary',
            'description' => 'Tickets grouped by day, service, window, and status.',
            'icon' => 'chart-column',
        ],
        'predicted_vs_actual' => [
            'label' => 'Predicted vs Actual Wait',
            'description' => 'Compares model estimates against completed ticket wait times.',
            'icon' => 'line-chart',
        ],
        'peak_hour' => [
            'label' => 'Peak Hour Analysis',
            'description' => 'Ticket volume by hour for the selected period.',
            'icon' => 'trending-up',
        ],
        'counter_performance' => [
            'label' => 'Service Counter Performance',
            'description' => 'Tickets served and average durations per service window.',
            'icon' => 'gauge',
        ],
        'turnaround_time' => [
            'label' => 'Customer Turnaround Time',
            'description' => 'Total time clients spent from ticket issue to completion.',
            'icon' => 'timer',
        ],
        'no_show' => [
            'label' => 'No-Show Report',
            'description' => 'Skipped and voided tickets by day, hour, service, and reason.',
            'icon' => 'user-x',
        ],
        'staff_productivity' => [
            'label' => 'Staff Productivity',
            'description' => 'Completed tickets and service duration by staff member.',
            'icon' => 'users',
        ],
        'ml_accuracy' => [
            'label' => 'ML Accuracy Report',
            'description' => 'Latest algorithm comparison metrics from model training.',
            'icon' => 'brain-circuit',
        ],
        'daily_monthly_stats' => [
            'label' => 'Daily and Monthly Stats',
            'description' => 'Ticket totals by day and month.',
            'icon' => 'calendar-days',
        ],
        'satisfaction' => [
            'label' => 'Satisfaction Report',
            'description' => 'Average feedback ratings by service, window, and day.',
            'icon' => 'star',
        ],
    ];
}

function reportAliases(): array {
    return [
        'daily_traffic' => 'daily_monthly_stats',
        'wait_time' => 'predicted_vs_actual',
        'service_efficiency' => 'counter_performance',
        'priority_impact' => 'queue_summary',
        'staff_performance' => 'staff_productivity',
        'monthly_summary' => 'daily_monthly_stats',
        'system_health' => 'ml_accuracy',
    ];
}

function normalizeReportKey(string $key): string {
    $key = trim($key);
    if ($key === '') {
        return 'queue_summary';
    }

    $aliases = reportAliases();
    return $aliases[$key] ?? $key;
}

function reportDateRange(array $source): array {
    $from = trim((string) ($source['from'] ?? $source['date_from'] ?? date('Y-m-01')));
    $to = trim((string) ($source['to'] ?? $source['date_to'] ?? date('Y-m-d')));

    $fromDate = DateTime::createFromFormat('Y-m-d', $from);
    $toDate = DateTime::createFromFormat('Y-m-d', $to);
    if (!$fromDate || !$toDate || $fromDate->format('Y-m-d') !== $from || $toDate->format('Y-m-d') !== $to) {
        throw new InvalidArgumentException('Dates must use YYYY-MM-DD format.');
    }

    if ($fromDate > $toDate) {
        throw new InvalidArgumentException('Start date must be before or equal to end date.');
    }

    return [
        'from' => $fromDate->format('Y-m-d'),
        'to' => $toDate->format('Y-m-d'),
    ];
}

function reportFetchAll(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Could not prepare report query: ' . $conn->error);
    }

    if ($types !== '') {
        $bind = [$types];
        foreach ($params as $index => $value) {
            $bind[] = &$params[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function reportFetchOne(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $rows = reportFetchAll($conn, $sql, $types, $params);
    return $rows[0] ?? [];
}

function reportMetric(string $label, mixed $value, string $note = ''): array {
    return [
        'label' => $label,
        'value' => is_numeric($value) ? (string) $value : (string) $value,
        'note' => $note,
    ];
}

function reportColumns(array $labels): array {
    $columns = [];
    foreach ($labels as $key => $label) {
        $columns[] = ['key' => $key, 'label' => $label];
    }
    return $columns;
}

function emptyReport(string $key, array $range, array $columns, string $insight = 'No records found for the selected period.'): array {
    $definitions = reportDefinitions();
    $definition = $definitions[$key] ?? ['label' => $key, 'description' => ''];
    return [
        'key' => $key,
        'title' => $definition['label'],
        'description' => $definition['description'],
        'range' => $range,
        'metrics' => [reportMetric('Records', '0', 'No matching rows')],
        'series' => [],
        'table' => ['columns' => $columns, 'rows' => []],
        'insight' => $insight,
    ];
}

function buildQueueSummaryReport(mysqli $conn, array $range): array {
    $rows = reportFetchAll($conn, "
        SELECT DATE(qt.issued_at) AS report_date,
               hs.service_name,
               COALESCE(sw.window_name, 'Unassigned') AS window_name,
               qt.status,
               COUNT(*) AS total
        FROM queue_tickets qt
        JOIN health_services hs ON hs.service_id = qt.service_id
        LEFT JOIN service_windows sw ON sw.window_id = qt.window_id
        WHERE DATE(qt.issued_at) BETWEEN ? AND ?
        GROUP BY report_date, hs.service_name, window_name, qt.status
        ORDER BY report_date DESC, hs.service_name, window_name, qt.status
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
               SUM(status = 'waiting') AS waiting,
               SUM(status = 'serving') AS serving,
               SUM(status = 'completed') AS completed
        FROM queue_tickets
        WHERE DATE(issued_at) BETWEEN ? AND ?
    ", 'ss', [$range['from'], $range['to']]);

    return [
        'key' => 'queue_summary',
        'title' => 'Queue Summary',
        'description' => reportDefinitions()['queue_summary']['description'],
        'range' => $range,
        'metrics' => [
            reportMetric('Total Tickets', (int) ($summary['total'] ?? 0), 'All statuses'),
            reportMetric('Waiting', (int) ($summary['waiting'] ?? 0), 'Still queued'),
            reportMetric('Serving', (int) ($summary['serving'] ?? 0), 'Currently at windows'),
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

function buildPredictedVsActualReport(mysqli $conn, array $range): array {
    $rows = reportFetchAll($conn, "
        SELECT qt.ticket_number,
               DATE(qt.completed_at) AS report_date,
               hs.service_name,
               ROUND(wl.predicted_wait_min, 2) AS predicted_wait_min,
               ROUND(wl.actual_wait_min, 2) AS actual_wait_min,
               ROUND(ABS(wl.predicted_wait_min - wl.actual_wait_min), 2) AS error_min
        FROM wait_time_logs wl
        JOIN queue_tickets qt ON qt.ticket_id = wl.ticket_id
        JOIN health_services hs ON hs.service_id = qt.service_id
        WHERE qt.completed_at IS NOT NULL
          AND wl.predicted_wait_min IS NOT NULL
          AND wl.actual_wait_min IS NOT NULL
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

function buildPeakHourReport(mysqli $conn, array $range): array {
    $rows = reportFetchAll($conn, "
        SELECT LPAD(HOUR(issued_at), 2, '0') AS hour_of_day,
               CONCAT(LPAD(HOUR(issued_at), 2, '0'), ':00') AS hour_label,
               COUNT(*) AS tickets
        FROM queue_tickets
        WHERE DATE(issued_at) BETWEEN ? AND ?
        GROUP BY HOUR(issued_at)
        ORDER BY HOUR(issued_at)
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
        WHERE qt.completed_at IS NOT NULL
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

function buildNoShowReport(mysqli $conn, array $range): array {
    $rows = reportFetchAll($conn, "
        SELECT DATE(COALESCE(qt.voided_at, qt.issued_at)) AS report_date,
               CONCAT(LPAD(HOUR(COALESCE(qt.voided_at, qt.issued_at)), 2, '0'), ':00') AS hour_label,
               hs.service_name,
               qt.status,
               COALESCE(qt.voided_reason, 'No reason recorded') AS reason,
               COUNT(*) AS total
        FROM queue_tickets qt
        JOIN health_services hs ON hs.service_id = qt.service_id
        WHERE qt.status IN ('voided', 'skipped')
          AND DATE(COALESCE(qt.voided_at, qt.issued_at)) BETWEEN ? AND ?
        GROUP BY report_date, hour_label, hs.service_name, qt.status, reason
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
             AND qt.completed_at IS NOT NULL
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

function buildMlAccuracyReport(mysqli $conn, array $range): array {
    $rows = reportFetchAll($conn, "
        SELECT algorithm,
               run_date,
               ROUND(mae, 4) AS mae,
               ROUND(rmse, 4) AS rmse,
               ROUND(r2, 4) AS r2,
               ROUND(mape, 2) AS mape,
               is_best,
               dataset_used,
               sample_size
        FROM v_ml_latest_comparison
        ORDER BY is_best DESC, mae ASC, algorithm
    ");

    $columns = reportColumns([
        'algorithm' => 'Algorithm',
        'run_date' => 'Run Date',
        'mae' => 'MAE',
        'rmse' => 'RMSE',
        'r2' => 'R2',
        'mape' => 'MAPE',
        'is_best' => 'Best',
        'dataset_used' => 'Dataset',
        'sample_size' => 'Samples',
    ]);

    if (!$rows) {
        return emptyReport('ml_accuracy', $range, $columns, 'No ML comparison rows found. Run ml/compare_algorithms.py to populate this report.');
    }

    $best = $rows[0];
    foreach ($rows as $row) {
        if ((int) $row['is_best'] === 1) {
            $best = $row;
            break;
        }
    }

    return [
        'key' => 'ml_accuracy',
        'title' => 'ML Accuracy Report',
        'description' => reportDefinitions()['ml_accuracy']['description'],
        'range' => $range,
        'metrics' => [
            reportMetric('Best Algorithm', $best['algorithm'], 'MAE ' . $best['mae']),
            reportMetric('Algorithms', count($rows)),
            reportMetric('Latest Run', $best['run_date'] ?? ''),
        ],
        'series' => array_map(static fn($row) => [
            'label' => $row['algorithm'],
            'value' => (float) $row['mae'],
        ], $rows),
        'table' => ['columns' => $columns, 'rows' => $rows],
        'insight' => $best['algorithm'] . ' is currently marked as the best model with MAE ' . $best['mae'] . '.',
    ];
}

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

function buildReport(mysqli $conn, string $key, array $range): array {
    $key = normalizeReportKey($key);
    return match ($key) {
        'queue_summary' => buildQueueSummaryReport($conn, $range),
        'predicted_vs_actual' => buildPredictedVsActualReport($conn, $range),
        'peak_hour' => buildPeakHourReport($conn, $range),
        'counter_performance' => buildCounterPerformanceReport($conn, $range),
        'turnaround_time' => buildTurnaroundTimeReport($conn, $range),
        'no_show' => buildNoShowReport($conn, $range),
        'staff_productivity' => buildStaffProductivityReport($conn, $range),
        'ml_accuracy' => buildMlAccuracyReport($conn, $range),
        'daily_monthly_stats' => buildDailyMonthlyStatsReport($conn, $range),
        'satisfaction' => buildSatisfactionReport($conn, $range),
        default => throw new InvalidArgumentException('Unknown report type.'),
    };
}

function outputReportJson(mysqli $conn, string $defaultKey): void {
    requireLogin(ROLE_ADMIN);
    header('Content-Type: application/json');

    try {
        $range = reportDateRange($_GET);
        $key = normalizeReportKey((string) ($_GET['report'] ?? $defaultKey));
        jsonResponse(true, ['data' => buildReport($conn, $key, $range)]);
    } catch (InvalidArgumentException $e) {
        jsonResponse(false, [
            'error' => $e->getMessage(),
            'field_errors' => ['report' => $e->getMessage()],
        ], 422);
    } catch (Throwable $e) {
        jsonResponse(false, ['error' => 'Could not build report.'], 500);
    }
}
?>
