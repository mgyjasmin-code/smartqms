<?php
/**
 * Stable report definitions, date ranges, query helpers, and table contracts.
 */

function reportDefinitions(): array {
    return [
        'queue_summary' => ['label' => 'Queue Summary', 'description' => 'Tickets grouped by day, service, window, and status.', 'icon' => 'chart-column'],
        'predicted_vs_actual' => ['label' => 'Predicted vs Actual Wait', 'description' => 'Compares model estimates against completed ticket wait times.', 'icon' => 'line-chart'],
        'peak_hour' => ['label' => 'Peak Hour Analysis', 'description' => 'Ticket volume by hour for the selected period.', 'icon' => 'trending-up'],
        'counter_performance' => ['label' => 'Service Counter Performance', 'description' => 'Tickets served and average durations per service window.', 'icon' => 'gauge'],
        'turnaround_time' => ['label' => 'Customer Turnaround Time', 'description' => 'Total time clients spent from ticket issue to completion.', 'icon' => 'timer'],
        'no_show' => ['label' => 'No-Show Report', 'description' => 'Skipped and voided tickets by day, hour, service, and reason.', 'icon' => 'user-x'],
        'staff_productivity' => ['label' => 'Staff Productivity', 'description' => 'Completed tickets and service duration by staff member.', 'icon' => 'users'],
        'ml_accuracy' => ['label' => 'ML Accuracy Report', 'description' => 'Verified training metrics or a chronological baseline from ml/dataset/queue_data.csv.', 'icon' => 'brain-circuit'],
        'daily_monthly_stats' => ['label' => 'Daily and Monthly Stats', 'description' => 'Ticket totals by day and month.', 'icon' => 'calendar-days'],
        'satisfaction' => ['label' => 'Satisfaction Report', 'description' => 'Average feedback ratings by service, window, and day.', 'icon' => 'star'],
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
    return $key === '' ? 'queue_summary' : (reportAliases()[$key] ?? $key);
}

function reportDateRange(array $source): array {
    $from = trim((string) ($source['from'] ?? $source['date_from'] ?? date('Y-m-01')));
    $to = trim((string) ($source['to'] ?? $source['date_to'] ?? date('Y-m-d')));
    if (!isValidReportDate($from) || !isValidReportDate($to)) {
        throw new InvalidArgumentException('Dates must use YYYY-MM-DD format.');
    }
    $fromDate = DateTime::createFromFormat('Y-m-d', $from);
    $toDate = DateTime::createFromFormat('Y-m-d', $to);
    if ($fromDate > $toDate) {
        throw new InvalidArgumentException('Start date must be before or equal to end date.');
    }
    return ['from' => $fromDate->format('Y-m-d'), 'to' => $toDate->format('Y-m-d')];
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
    return reportFetchAll($conn, $sql, $types, $params)[0] ?? [];
}

function reportMetric(string $label, mixed $value, string $note = ''): array {
    return ['label' => $label, 'value' => (string) $value, 'note' => $note];
}

function reportColumns(array $labels): array {
    $columns = [];
    foreach ($labels as $key => $label) {
        $columns[] = ['key' => $key, 'label' => $label];
    }
    return $columns;
}

function emptyReport(string $key, array $range, array $columns, string $insight = 'No records found for the selected period.'): array {
    $definition = reportDefinitions()[$key] ?? ['label' => $key, 'description' => ''];
    return [
        'key' => $key,
        'title' => $definition['label'],
        'description' => $definition['description'],
        'range' => $range,
        'metrics' => [reportMetric('Records', '0', 'No matching rows')],
        'series' => [],
        'chart' => ['type' => 'bar', 'labels' => [], 'datasets' => [], 'unit' => '', 'summary' => $insight],
        'table' => ['columns' => $columns, 'rows' => []],
        'insight' => $insight,
    ];
}
