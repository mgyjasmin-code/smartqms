<?php
/**
 * Export completed SmartQMS queue observations for model training.
 *
 * Usage: php scripts/export_ml_history.php
 * The output is replaced atomically and contains database observations only.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$destination = __DIR__ . '/../ml/dataset/queue_data.csv';
$temporary = $destination . '.tmp';

$sql = "
    SELECT qt.completed_at AS observed_at,
           COALESCE(wl.queue_length, 0) AS queue_length,
           COALESCE(wl.hour_of_day, HOUR(COALESCE(qt.checked_in_at, qt.issued_at))) AS hour_of_day,
           COALESCE(wl.day_of_week, DAYOFWEEK(COALESCE(qt.checked_in_at, qt.issued_at)) - 1) AS day_of_week,
           COALESCE(wl.service_type_encoded, hs.service_encoded) AS service_type_encoded,
           COALESCE(
               wl.client_type_encoded,
               CASE qt.client_type WHEN 'senior' THEN 1 WHEN 'pwd' THEN 2 ELSE 0 END
           ) AS client_type_encoded,
           GREATEST(1, COALESCE(wl.active_windows, 1)) AS active_windows,
           COALESCE(
               NULLIF(wl.avg_service_time, 0),
               NULLIF(wl.actual_service_dur, 0) / 60,
               " . (smartqmsTableHasColumn($conn, 'health_services', 'fallback_duration_mins')
                    ? 'hs.fallback_duration_mins'
                    : '15') . "
           ) AS avg_service_time,
           ROUND(
               TIMESTAMPDIFF(
                   SECOND,
                   COALESCE(qt.checked_in_at, qt.issued_at),
                   COALESCE(qt.started_at, qt.served_at)
               ) / 60,
               2
           ) AS actual_wait_minutes
    FROM wait_time_logs wl
    JOIN queue_tickets qt ON qt.ticket_id = wl.ticket_id
    JOIN health_services hs ON hs.service_id = qt.service_id
    WHERE qt.status = 'completed'
      AND qt.lifecycle_status = 'completed'
      AND qt.completed_at IS NOT NULL
      AND COALESCE(qt.started_at, qt.served_at) IS NOT NULL
      AND TIMESTAMPDIFF(
              SECOND,
              COALESCE(qt.checked_in_at, qt.issued_at),
              COALESCE(qt.started_at, qt.served_at)
          ) BETWEEN 0 AND 28800
    ORDER BY qt.completed_at, qt.ticket_id
";

$result = $conn->query($sql);
$handle = fopen($temporary, 'wb');
if ($handle === false) {
    fwrite(STDERR, "Could not open the temporary ML export file.\n");
    exit(1);
}

$headers = [
    'observed_at',
    'queue_length',
    'hour_of_day',
    'day_of_week',
    'service_type_encoded',
    'client_type_encoded',
    'active_windows',
    'avg_service_time',
    'actual_wait_minutes',
];
fputcsv($handle, $headers);

$rowCount = 0;
while ($row = $result->fetch_assoc()) {
    fputcsv($handle, array_map(static fn(string $header): mixed => $row[$header], $headers));
    $rowCount++;
}

fflush($handle);
fclose($handle);

if (!rename($temporary, $destination)) {
    @unlink($temporary);
    fwrite(STDERR, "Could not replace ml/dataset/queue_data.csv.\n");
    exit(1);
}

fwrite(STDOUT, "Exported {$rowCount} completed SmartQMS queue observation(s) to ml/dataset/queue_data.csv.\n");
if ($rowCount < 30) {
    fwrite(STDOUT, "Training remains gated until at least 30 valid observations are available.\n");
}
