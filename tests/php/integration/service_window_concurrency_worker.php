<?php
/**
 * Child process used only by verify_service_window_concurrency.php.
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once SMARTQMS_ROOT . '/modules/service_window/ticket_actions.php';

$action = (string) ($argv[1] ?? '');
$staffUserId = (int) ($argv[2] ?? 0);
$staffId = (int) ($argv[3] ?? 0);
$ticketId = (int) ($argv[4] ?? 0);
$readyPath = (string) ($argv[5] ?? '');
$goPath = (string) ($argv[6] ?? '');

if (
    !in_array($action, ['call', 'complete', 'skip', 'void', 'manual_void'], true)
    || $staffUserId < 1
    || $staffId < 1
    || $readyPath === ''
    || $goPath === ''
) {
    fwrite(STDERR, 'Invalid service-window concurrency-worker arguments.' . PHP_EOL);
    exit(2);
}

try {
    $conn = testDatabaseConnection();
    $_SESSION['user_id'] = $staffUserId;
    $_SESSION['staff_id'] = $staffId;
    $_SESSION['role'] = ROLE_STAFF;

    touch($readyPath);
    $deadline = microtime(true) + 10;
    while (!is_file($goPath)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Concurrency barrier timed out.');
        }
        usleep(10000);
    }

    $startedAt = microtime(true);
    $result = match ($action) {
        'call' => callNextTicketForStaff($conn, $staffId),
        'complete' => completeTicketForStaff($conn, $staffId, $ticketId),
        'skip' => skipTicketForStaff($conn, $staffId, $ticketId),
        'void' => voidExpiredTicketsForStaff($conn, $staffId, 10),
        'manual_void' => voidTicketForStaff($conn, $staffId, $ticketId),
    };
    $result['worker_action'] = $action;
    $result['duration_seconds'] = round(microtime(true) - $startedAt, 4);
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL);
    exit(0);
} catch (Throwable $error) {
    fwrite(
        STDERR,
        'action=' . $action . ' connection_id='
        . (isset($conn) && $conn instanceof mysqli ? $conn->thread_id : 0)
        . ' ' . get_class($error) . ': ' . $error->getMessage() . PHP_EOL
    );
    exit(1);
}
