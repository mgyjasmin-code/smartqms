<?php
/**
 * Chart projections built from stable report result arrays.
 */

function reportChartConfig(array $report): array {
    $series = array_slice($report['series'] ?? [], 0, 20);
    $key = (string) ($report['key'] ?? '');
    $labels = array_map(static fn($point) => (string) ($point['label'] ?? ''), $series);

    if ($key === 'predicted_vs_actual') {
        return [
            'type' => 'line',
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Predicted wait', 'data' => array_map(static fn($point) => (float) ($point['predicted'] ?? 0), $series)],
                ['label' => 'Actual wait', 'data' => array_map(static fn($point) => (float) ($point['actual'] ?? 0), $series)],
            ],
            'unit' => 'minutes',
            'summary' => (string) ($report['insight'] ?? ''),
        ];
    }

    if ($key === 'ml_accuracy') {
        $rows = $report['table']['rows'] ?? [];
        return [
            'type' => 'bar',
            'labels' => array_map(static fn($row) => (string) ($row['algorithm'] ?? ''), $rows),
            'datasets' => [
                ['label' => 'MAE', 'data' => array_map(static fn($row) => (float) ($row['mae'] ?? 0), $rows)],
                ['label' => 'RMSE', 'data' => array_map(static fn($row) => (float) ($row['rmse'] ?? 0), $rows)],
            ],
            'unit' => 'minutes',
            'summary' => (string) ($report['insight'] ?? ''),
        ];
    }

    return [
        'type' => in_array($key, ['turnaround_time', 'daily_monthly_stats', 'satisfaction'], true) ? 'line' : 'bar',
        'labels' => $labels,
        'datasets' => [[
            'label' => $report['title'] ?? 'Value',
            'data' => array_map(static fn($point) => (float) ($point['value'] ?? 0), $series),
        ]],
        'unit' => $key === 'satisfaction' ? 'rating' : '',
        'summary' => (string) ($report['insight'] ?? ''),
    ];
}
