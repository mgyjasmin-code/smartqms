<?php
/**
 * Compatibility aggregator for all SmartQMS report builders.
 */

require_once __DIR__ . '/report_core.php';
require_once __DIR__ . '/report_chart.php';
require_once __DIR__ . '/builders/queue_summary.php';
require_once __DIR__ . '/builders/predicted_vs_actual.php';
require_once __DIR__ . '/builders/peak_hour.php';
require_once __DIR__ . '/builders/counter_performance.php';
require_once __DIR__ . '/builders/turnaround_time.php';
require_once __DIR__ . '/builders/no_show.php';
require_once __DIR__ . '/builders/staff_productivity.php';
require_once __DIR__ . '/builders/ml_accuracy.php';
require_once __DIR__ . '/builders/daily_monthly_stats.php';
require_once __DIR__ . '/builders/satisfaction.php';

function buildReport(mysqli $conn, string $key, array $range): array {
    $key = normalizeReportKey($key);
    $report = match ($key) {
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
    $report['chart'] = reportChartConfig($report);
    return $report;
}


require_once __DIR__ . '/report_http.php';
