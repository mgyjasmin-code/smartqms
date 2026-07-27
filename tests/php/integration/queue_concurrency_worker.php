<?php
/**
 * Child process used only by verify_queue_concurrency.php.
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once SMARTQMS_ROOT . '/modules/queue/qr_generate.php';
require_once SMARTQMS_ROOT . '/modules/queue/ticket_service.php';

$userId = (int) ($argv[1] ?? 0);
$serviceId = (int) ($argv[2] ?? 0);
$clientType = normalizeQueueClientType((string) ($argv[3] ?? 'regular'));
$readyPath = (string) ($argv[4] ?? '');
$goPath = (string) ($argv[5] ?? '');

if ($userId < 1 || $serviceId < 1 || $readyPath === '' || $goPath === '') {
    fwrite(STDERR, 'Invalid concurrency-worker arguments.' . PHP_EOL);
    exit(2);
}

try {
    $conn = testDatabaseConnection();
    $snapshot = queuePredictionSnapshot($conn, $serviceId, $clientType);
    if (!$snapshot) {
        throw new RuntimeException('Prediction snapshot was not available.');
    }

    $warmupReference = 'CHARACTERIZATION-WARMUP-' . getmypid();
    $warmupQrPath = generateQR($warmupReference, (string) getmypid());
    removeGeneratedQueueQr($warmupQrPath);

    touch($readyPath);
    $deadline = microtime(true) + 10;
    while (!is_file($goPath)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Concurrency barrier timed out.');
        }
        usleep(10000);
    }

    $startedAt = microtime(true);
    $result = createQueueTicket(
        $conn,
        $userId,
        $serviceId,
        $clientType,
        queuePriorityLevelForClientType($clientType),
        $snapshot,
        null
    );
    $result['worker_user_id'] = $userId;
    $result['duration_seconds'] = round(microtime(true) - $startedAt, 4);
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL);
    exit(0);
} catch (Throwable $error) {
    fwrite(
        STDERR,
        'user_id=' . $userId . ' connection_id='
        . (isset($conn) && $conn instanceof mysqli ? $conn->thread_id : 0)
        . ' ' . get_class($error) . ': ' . $error->getMessage() . PHP_EOL
    );
    exit(1);
}
