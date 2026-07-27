<?php

testCase('ML training uses the requested queue_data CSV and rejects synthetic artifacts', function (): void {
    $pipeline = file_get_contents(SMARTQMS_ROOT . '/ml/data_pipeline.py') ?: '';
    assertStringContains('TRAINING_SOURCE = "ml/dataset/queue_data.csv"', $pipeline);
    assertStringContains('DATASET_PATH', $pipeline);
    assertStringContains('pd.read_csv(path)', $pipeline);
    assertStringContains('"arrival_time"', $pipeline);
    assertStringContains('"wait_time"', $pipeline);
    assertStringContains('"queue_length"', $pipeline);
    assertStringContains('"actual_wait_minutes"', $pipeline);
    assertFalseValue(str_contains($pipeline, 'synthetic_queue_data.csv'));

    $dataset = SMARTQMS_ROOT . '/ml/dataset/queue_data.csv';
    assertTrueValue(is_file($dataset));
    $handle = fopen($dataset, 'rb');
    assertTrueValue($handle !== false);
    $header = fgetcsv($handle, 0, ',', '"', '\\');
    fclose($handle);
    assertSameValue(
        ['arrival_time', 'start_time', 'finish_time', 'wait_time', 'queue_length'],
        $header
    );

    $application = file_get_contents(SMARTQMS_ROOT . '/ml/app.py') ?: '';
    assertStringContains('legacy or synthetic artifacts are disabled', $application);
    assertStringContains('MODEL_METADATA_PATH', $application);
    assertStringContains('metadata.get("training_source") != TRAINING_SOURCE', $application);
    assertStringContains('pd.DataFrame([parsed], columns=FEATURE_COLS)', $application);

    $training = file_get_contents(SMARTQMS_ROOT . '/ml/training.py') ?: '';
    assertStringContains('os.replace(temporary_path, destination)', $training);
    assertStringContains('os.replace(temporary_metadata_path, metadata_destination)', $training);
    assertStringContains('safe_mape', $training);
    assertStringContains('frame.iloc[:-test_size]', $training);
});

testCase('dashboard and ML reports reject corrupt waits and use queue_data CSV', function (): void {
    $dashboard = file_get_contents(SMARTQMS_ROOT . '/views/admin/dashboard.php') ?: '';
    assertStringContains('TIMESTAMPDIFF(', $dashboard);
    assertStringContains('BETWEEN 0 AND 28800', $dashboard);
    assertFalseValue(str_contains($dashboard, 'AVG(wl.actual_wait_min)'));
    assertFalseValue(str_contains($dashboard, 'AVG(actual_wait_min)'));

    $predictionReport = file_get_contents(
        SMARTQMS_ROOT . '/modules/reports/builders/predicted_vs_actual.php'
    ) ?: '';
    assertStringContains('BETWEEN 0 AND 28800', $predictionReport);
    assertStringContains("reportMetric('Valid Samples'", $predictionReport);

    $accuracyReport = file_get_contents(
        SMARTQMS_ROOT . '/modules/reports/builders/ml_accuracy.php'
    ) ?: '';
    assertStringContains("dataset_used = 'ml/dataset/queue_data.csv'", $accuracyReport);
    assertStringContains('/ml/dataset/queue_data.csv', $accuracyReport);
    assertStringContains('Queue-length baseline', $accuracyReport);
    assertStringContains('chronological training rows', $accuracyReport);
});
