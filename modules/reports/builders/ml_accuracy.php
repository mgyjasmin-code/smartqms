<?php
/**
 * Accuracy report for the configured historical queue_data.csv source.
 *
 * Verified algorithm-comparison rows are preferred. Before the Python trainer
 * has been run, a clearly labelled queue-length baseline provides a truthful
 * chronological holdout measurement without pretending it is the deployed
 * SmartQMS model.
 */

function mlAccuracyMetrics(array $rows): array {
    $actual = array_map(static fn($row) => (float) $row['actual_wait_min'], $rows);
    $predicted = array_map(static fn($row) => (float) $row['predicted_wait_min'], $rows);
    $count = count($rows);
    $absoluteErrors = [];
    $squaredErrors = [];
    $percentageErrors = [];
    foreach ($actual as $index => $actualValue) {
        $error = $actualValue - $predicted[$index];
        $absoluteErrors[] = abs($error);
        $squaredErrors[] = $error ** 2;
        if (abs($actualValue) > PHP_FLOAT_EPSILON) {
            $percentageErrors[] = abs($error / $actualValue) * 100;
        }
    }

    $meanActual = array_sum($actual) / $count;
    $residual = array_sum($squaredErrors);
    $total = array_sum(array_map(
        static fn($value) => ($value - $meanActual) ** 2,
        $actual
    ));

    return [
        'mae' => array_sum($absoluteErrors) / $count,
        'rmse' => sqrt($residual / $count),
        'r2' => $total > PHP_FLOAT_EPSILON ? 1 - ($residual / $total) : null,
        'mape' => $percentageErrors ? array_sum($percentageErrors) / count($percentageErrors) : null,
    ];
}

function mlQueueDataBaseline(): ?array {
    $datasetPath = dirname(__DIR__, 3) . '/ml/dataset/queue_data.csv';
    $handle = @fopen($datasetPath, 'rb');
    if ($handle === false) {
        return null;
    }

    try {
        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if (!is_array($header)) {
            return null;
        }
        $columns = array_flip($header);
        $isDatabaseExport = array_key_exists('actual_wait_minutes', $columns);
        $requiredColumns = $isDatabaseExport
            ? ['actual_wait_minutes', 'queue_length']
            : ['arrival_time', 'wait_time', 'queue_length'];
        foreach ($requiredColumns as $required) {
            if (!array_key_exists($required, $columns)) {
                return null;
            }
        }

        $observations = [];
        $latestDatasetDate = null;
        while (($record = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $waitColumn = $isDatabaseExport ? 'actual_wait_minutes' : 'wait_time';
            $wait = filter_var(
                $record[$columns[$waitColumn]] ?? null,
                FILTER_VALIDATE_FLOAT
            );
            $queueLength = filter_var(
                $record[$columns['queue_length']] ?? null,
                FILTER_VALIDATE_INT
            );
            $capturedAt = $isDatabaseExport
                ? DateTimeImmutable::createFromFormat(
                    'Y-m-d H:i:s',
                    trim((string) ($record[$columns['observed_at'] ?? -1] ?? ''))
                )
                : DateTimeImmutable::createFromFormat(
                    'd-m-Y H.i',
                    trim((string) ($record[$columns['arrival_time']] ?? ''))
                );
            if ($wait === false || $queueLength === false) {
                continue;
            }
            if ($wait < 0 || $wait > 480 || $queueLength < 0 || $queueLength > 500) {
                continue;
            }
            $observations[] = [
                'queue_length' => $queueLength,
                'actual_wait_min' => (float) $wait,
            ];
            if ($capturedAt !== false
                && ($latestDatasetDate === null || $capturedAt > $latestDatasetDate)) {
                $latestDatasetDate = $capturedAt;
            }
        }
    } finally {
        fclose($handle);
    }

    $total = count($observations);
    if ($total < 10) {
        return null;
    }

    $trainCount = max(1, (int) floor($total * 0.8));
    if ($trainCount >= $total) {
        return null;
    }
    $train = array_slice($observations, 0, $trainCount);
    $holdout = array_slice($observations, $trainCount);
    $overallMean = array_sum(array_column($train, 'actual_wait_min')) / count($train);
    $queueSums = [];
    $queueCounts = [];
    foreach ($train as $row) {
        $key = (string) $row['queue_length'];
        $queueSums[$key] = ($queueSums[$key] ?? 0.0) + $row['actual_wait_min'];
        $queueCounts[$key] = ($queueCounts[$key] ?? 0) + 1;
    }

    $evaluated = array_map(
        static function (array $row) use ($queueSums, $queueCounts, $overallMean): array {
            $key = (string) $row['queue_length'];
            $prediction = isset($queueCounts[$key])
                ? $queueSums[$key] / $queueCounts[$key]
                : $overallMean;
            return [
                'actual_wait_min' => $row['actual_wait_min'],
                'predicted_wait_min' => $prediction,
            ];
        },
        $holdout
    );
    $metrics = mlAccuracyMetrics($evaluated);

    return [
        'row' => [
            'algorithm' => 'Queue-length baseline',
            'run_date' => date('Y-m-d'),
            'mae' => number_format($metrics['mae'], 4, '.', ''),
            'rmse' => number_format($metrics['rmse'], 4, '.', ''),
            'r2' => $metrics['r2'] === null
                ? null
                : number_format($metrics['r2'], 4, '.', ''),
            'mape' => $metrics['mape'] === null
                ? null
                : number_format($metrics['mape'], 2, '.', ''),
            'is_best' => 1,
            'dataset_used' => 'ml/dataset/queue_data.csv',
            'sample_size' => count($holdout),
        ],
        'total' => $total,
        'train_count' => count($train),
        'holdout_count' => count($holdout),
        'dataset_date' => $latestDatasetDate?->format('Y-m-d') ?? '',
    ];
}

function buildMlAccuracyReport(mysqli $conn, array $range): array {
    $columns = reportColumns([
        'algorithm' => 'Algorithm',
        'run_date' => 'Run Date',
        'mae' => 'MAE',
        'rmse' => 'RMSE',
        'r2' => 'R2',
        'mape' => 'MAPE',
        'is_best' => 'Best',
        'dataset_used' => 'Data Source',
        'sample_size' => 'Samples',
    ]);

    $trainingRows = reportFetchAll($conn, "
        SELECT algorithm,
               run_date,
               ROUND(mae, 4) AS mae,
               ROUND(rmse, 4) AS rmse,
               ROUND(r2, 4) AS r2,
               ROUND(mape, 2) AS mape,
               is_best,
               dataset_used,
               sample_size
        FROM ml_comparison_logs
        WHERE dataset_used = 'ml/dataset/queue_data.csv'
          AND run_date BETWEEN ? AND ?
        ORDER BY run_date DESC, is_best DESC, mae ASC, algorithm
    ", 'ss', [$range['from'], $range['to']]);

    if ($trainingRows) {
        $latestRunDate = $trainingRows[0]['run_date'];
        $rows = array_values(array_filter(
            $trainingRows,
            static fn($row) => $row['run_date'] === $latestRunDate
        ));
        $best = $rows[0];
        foreach ($rows as $row) {
            if ((int) $row['is_best'] === 1) {
                $best = $row;
                break;
            }
        }
        $insight = $best['algorithm'] . ' is the latest verified queue_data.csv model, with MAE ' . $best['mae'] . ' minutes.';
        $latestData = $best['run_date'] ?? '';
    } else {
        $baseline = mlQueueDataBaseline();
        if ($baseline === null) {
            return emptyReport(
                'ml_accuracy',
                $range,
                $columns,
                'ml/dataset/queue_data.csv is missing or has fewer than ten valid observations.'
            );
        }
        $rows = [$baseline['row']];
        $best = $rows[0];
        $latestData = $baseline['dataset_date'];
        $insight = 'Baseline accuracy uses ' . $baseline['total']
            . ' valid queue_data.csv rows: ' . $baseline['train_count']
            . ' chronological training rows and ' . $baseline['holdout_count']
            . ' holdout rows. Run ml/train_model.py for the verified four-algorithm comparison.';
    }

    return [
        'key' => 'ml_accuracy',
        'title' => 'ML Accuracy Report',
        'description' => reportDefinitions()['ml_accuracy']['description'],
        'range' => $range,
        'metrics' => [
            reportMetric('Model', $best['algorithm'], 'ml/dataset/queue_data.csv'),
            reportMetric('MAE', $best['mae'] . ' min', 'Average absolute error'),
            reportMetric('Samples', $best['sample_size']),
            reportMetric('Latest Data', $latestData),
        ],
        'series' => array_map(static fn($row) => [
            'label' => $row['algorithm'],
            'value' => (float) $row['mae'],
        ], $rows),
        'table' => ['columns' => $columns, 'rows' => $rows],
        'insight' => $insight,
    ];
}
