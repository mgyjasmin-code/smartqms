<?php

testCase('report definitions retain all ten report keys', function (): void {
    $definitions = reportDefinitions();
    assertSameValue(10, count($definitions));
    foreach ([
        'queue_summary', 'predicted_vs_actual', 'peak_hour', 'counter_performance',
        'turnaround_time', 'no_show', 'staff_productivity', 'ml_accuracy',
        'daily_monthly_stats', 'satisfaction',
    ] as $key) {
        assertTrueValue(isset($definitions[$key]), 'Missing report definition: ' . $key);
    }
});

testCase('report aliases retain current normalization', function (): void {
    assertSameValue('daily_monthly_stats', normalizeReportKey('daily_traffic'));
    assertSameValue('predicted_vs_actual', normalizeReportKey('wait_time'));
    assertSameValue('counter_performance', normalizeReportKey('service_efficiency'));
    assertSameValue('queue_summary', normalizeReportKey(''));
});

testCase('report date range retains defaults and rejects invalid ranges', function (): void {
    $default = reportDateRange([]);
    assertSameValue(date('Y-m-01'), $default['from']);
    assertSameValue(date('Y-m-d'), $default['to']);

    assertThrowsException(
        static fn() => reportDateRange(['from' => '2026/01/01', 'to' => '2026-01-31']),
        InvalidArgumentException::class,
        'YYYY-MM-DD'
    );
    assertThrowsException(
        static fn() => reportDateRange(['from' => '2026-02-01', 'to' => '2026-01-01']),
        InvalidArgumentException::class,
        'before or equal'
    );
});

testCase('empty report and chart contracts retain expected keys', function (): void {
    $range = ['from' => '2099-01-01', 'to' => '2099-01-31'];
    $report = emptyReport('queue_summary', $range, reportColumns(['total' => 'Total']));
    assertArrayHasKeys(['key', 'title', 'description', 'range', 'metrics', 'series', 'chart', 'table', 'insight'], $report);
    assertArrayHasKeys(['type', 'labels', 'datasets', 'unit', 'summary'], $report['chart']);
    assertArrayHasKeys(['columns', 'rows'], $report['table']);
});
