<?php
/**
 * SmartQMS queue lifecycle maintenance task.
 *
 * Schedule with Windows Task Scheduler once per minute:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\smartqms\scripts\process_queue_timeouts.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../modules/queue/arrival_service.php';
require_once __DIR__ . '/../modules/service_window/ticket_actions.php';

try {
    $expiredScheduled = expireScheduledQueueTickets($conn);
    $timeoutMinutes = max(1, (int) getSetting($conn, 'void_timeout_minutes', '5'));
    $expiredCalling = voidExpiredCallingTicketsSystemWide($conn, $timeoutMinutes);
    echo json_encode([
        'success' => true,
        'scheduled_expired' => (int) $expiredScheduled,
        'calling_voided' => (int) ($expiredCalling['voided'] ?? 0),
        'timeout_minutes' => $timeoutMinutes,
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $error) {
    error_log('SmartQMS queue timeout task failed: ' . $error->getMessage());
    fwrite(STDERR, json_encode([
        'success' => false,
        'error' => 'Queue timeout processing failed.',
    ]) . PHP_EOL);
    exit(1);
}
