<?php
/**
 * Chart projections built from stable report result arrays.
 */

function reportChartConfig(array $report): array {
    $key = (string) ($report['key'] ?? '');
    $comparisonKeys = ['counter_performance', 'staff_productivity', 'satisfaction'];
    $seriesLimit = in_array($key, $comparisonKeys, true) ? 12 : 20;
    $series = array_slice($report['series'] ?? [], 0, $seriesLimit);
    $labels = array_map(static fn($point) => (string) ($point['label'] ?? ''), $series);

    if ($key === 'queue_summary') {
        $rows = $report['table']['rows'] ?? [];
        $statusOrder = ['waiting', 'calling', 'in-progress', 'completed', 'skipped', 'void'];
        $totalsByDate = [];
        foreach ($rows as $row) {
            $date = (string) ($row['report_date'] ?? '');
            $status = (string) ($row['status'] ?? '');
            if ($date === '' || $status === '') {
                continue;
            }
            $totalsByDate[$date][$status] = ($totalsByDate[$date][$status] ?? 0) + (int) ($row['total'] ?? 0);
        }
        ksort($totalsByDate);
        $dateLabels = array_slice(array_keys($totalsByDate), -14);
        $datasets = [];
        foreach ($statusOrder as $status) {
            $values = array_map(static fn($date) => (int) ($totalsByDate[$date][$status] ?? 0), $dateLabels);
            if (array_sum($values) === 0) {
                continue;
            }
            $datasets[] = [
                'label' => ucwords(str_replace('-', ' ', $status)),
                'data' => $values,
                'style_key' => str_replace('-', '_', $status),
            ];
        }

        return [
            'type' => 'bar',
            'stacked' => true,
            'labels' => $dateLabels,
            'datasets' => $datasets,
            'unit' => 'tickets',
            'summary' => (string) ($report['insight'] ?? ''),
        ];
    }

    if ($key === 'predicted_vs_actual') {
        return [
            'type' => 'line',
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Predicted wait',
                    'data' => array_map(static fn($point) => (float) ($point['predicted'] ?? 0), $series),
                    'style_key' => 'predicted',
                ],
                [
                    'label' => 'Actual wait',
                    'data' => array_map(static fn($point) => (float) ($point['actual'] ?? 0), $series),
                    'style_key' => 'actual',
                ],
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
                [
                    'label' => 'MAE',
                    'data' => array_map(static fn($row) => (float) ($row['mae'] ?? 0), $rows),
                    'style_key' => 'mae',
                ],
                [
                    'label' => 'RMSE',
                    'data' => array_map(static fn($row) => (float) ($row['rmse'] ?? 0), $rows),
                    'style_key' => 'rmse',
                ],
            ],
            'unit' => 'minutes',
            'summary' => (string) ($report['insight'] ?? ''),
        ];
    }

    if ($key === 'no_show') {
        $rows = $report['table']['rows'] ?? [];
        $totalsByReason = [];
        foreach ($rows as $row) {
            $reason = trim((string) ($row['reason'] ?? '')) ?: 'No reason recorded';
            $totalsByReason[$reason] = ($totalsByReason[$reason] ?? 0) + (int) ($row['total'] ?? 0);
        }
        arsort($totalsByReason);
        $totalsByReason = array_slice($totalsByReason, 0, 8, true);

        return [
            'type' => 'doughnut',
            'labels' => array_keys($totalsByReason),
            'datasets' => [[
                'label' => 'Tickets',
                'data' => array_values($totalsByReason),
                'style_key' => 'reason_breakdown',
            ]],
            'unit' => 'tickets',
            'summary' => (string) ($report['insight'] ?? ''),
        ];
    }

    if ($key === 'satisfaction') {
        $rows = $report['table']['rows'] ?? [];
        $serviceTotals = [];
        foreach ($rows as $row) {
            $service = (string) ($row['service_name'] ?? 'Unassigned');
            $responses = (int) ($row['feedback_count'] ?? 0);
            $serviceTotals[$service]['weighted'] = ($serviceTotals[$service]['weighted'] ?? 0.0)
                + ((float) ($row['avg_rating'] ?? 0) * $responses);
            $serviceTotals[$service]['responses'] = ($serviceTotals[$service]['responses'] ?? 0) + $responses;
        }
        uasort($serviceTotals, static function (array $a, array $b): int {
            $aRating = $a['responses'] > 0 ? $a['weighted'] / $a['responses'] : 0;
            $bRating = $b['responses'] > 0 ? $b['weighted'] / $b['responses'] : 0;
            return $bRating <=> $aRating;
        });
        $serviceTotals = array_slice($serviceTotals, 0, 12, true);

        return [
            'type' => 'horizontalBar',
            'labels' => array_keys($serviceTotals),
            'datasets' => [[
                'label' => 'Average rating',
                'data' => array_map(static fn($values) => $values['responses'] > 0
                    ? round($values['weighted'] / $values['responses'], 2)
                    : 0, array_values($serviceTotals)),
                'style_key' => 'rating',
            ]],
            'unit' => 'out of 5',
            'suggestedMax' => 5,
            'summary' => (string) ($report['insight'] ?? ''),
        ];
    }

    $type = match ($key) {
        'turnaround_time', 'daily_monthly_stats' => 'line',
        'counter_performance', 'staff_productivity' => 'horizontalBar',
        default => 'bar',
    };

    return [
        'type' => $type,
        'labels' => $labels,
        'datasets' => [[
            'label' => $report['title'] ?? 'Value',
            'data' => array_map(static fn($point) => (float) ($point['value'] ?? 0), $series),
            'style_key' => match ($key) {
                'turnaround_time' => 'turnaround',
                'daily_monthly_stats' => 'volume',
                'counter_performance' => 'counter_performance',
                'staff_productivity' => 'staff_productivity',
                default => 'primary',
            },
        ]],
        'unit' => $key === 'satisfaction' ? 'rating' : '',
        'summary' => (string) ($report['insight'] ?? ''),
    ];
}
