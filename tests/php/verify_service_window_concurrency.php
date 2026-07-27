<?php
/**
 * Isolated multi-process verification for Batch 5 state-transition locks.
 */

require_once __DIR__ . '/bootstrap.php';

function serviceWindowConcurrencyTempPath(string $label): string {
    $path = tempnam(sys_get_temp_dir(), 'smartqms_window_' . $label . '_');
    if ($path === false) {
        throw new RuntimeException('Could not allocate a concurrency coordination file.');
    }
    unlink($path);
    return $path;
}

function runServiceWindowConcurrentActions(array $requests): array {
    $goPath = serviceWindowConcurrencyTempPath('go');
    $workerPath = __DIR__ . '/integration/service_window_concurrency_worker.php';
    $processes = [];

    try {
        foreach ($requests as $index => $request) {
            $readyPath = serviceWindowConcurrencyTempPath('ready');
            $command = implode(' ', array_map('escapeshellarg', [
                PHP_BINARY,
                $workerPath,
                (string) $request['action'],
                (string) $request['staff_user_id'],
                (string) $request['staff_id'],
                (string) ($request['ticket_id'] ?? 0),
                $readyPath,
                $goPath,
            ]));
            $pipes = [];
            $process = proc_open($command, [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes, SMARTQMS_ROOT);
            if (!is_resource($process)) {
                throw new RuntimeException('Could not start service-window concurrency worker.');
            }
            fclose($pipes[0]);
            $processes[$index] = [
                'process' => $process,
                'stdout' => $pipes[1],
                'stderr' => $pipes[2],
                'ready' => $readyPath,
            ];
        }

        $deadline = microtime(true) + 10;
        do {
            $allReady = true;
            foreach ($processes as $worker) {
                if (!is_file($worker['ready'])) {
                    $allReady = false;
                    break;
                }
            }
            if ($allReady) {
                break;
            }
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Workers did not reach the service-window concurrency barrier.');
            }
            usleep(10000);
        } while (true);

        touch($goPath);
        $results = [];
        $failures = [];
        foreach ($processes as $index => $worker) {
            $stdout = trim(stream_get_contents($worker['stdout']));
            $stderr = trim(stream_get_contents($worker['stderr']));
            fclose($worker['stdout']);
            fclose($worker['stderr']);
            $exitCode = proc_close($worker['process']);
            $processes[$index]['process'] = null;
            if ($exitCode !== 0) {
                $failures[] = $stderr;
                continue;
            }
            $results[] = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        }
        if ($failures) {
            throw new RuntimeException('Service-window worker failed: ' . implode(' | ', $failures));
        }
        return $results;
    } finally {
        foreach ($processes as $worker) {
            if (is_resource($worker['stdout'])) {
                fclose($worker['stdout']);
            }
            if (is_resource($worker['stderr'])) {
                fclose($worker['stderr']);
            }
            if (is_resource($worker['process'])) {
                proc_terminate($worker['process']);
                proc_close($worker['process']);
            }
            if (is_file($worker['ready'])) {
                unlink($worker['ready']);
            }
        }
        if (is_file($goPath)) {
            unlink($goPath);
        }
    }
}

function createServiceWindowConcurrencyFixtures(mysqli $conn): array {
    $prefix = testFixturePrefix() . 'window_concurrency_';
    $stale = $conn->query("
        SELECT COUNT(*) AS cnt
        FROM users
        WHERE email LIKE '{$prefix}%@example.test'
    ")->fetch_assoc();
    if ((int) ($stale['cnt'] ?? 0) !== 0) {
        throw new RuntimeException('Stale service-window concurrency fixtures exist.');
    }

    $passwordHash = password_hash('characterization_password', PASSWORD_BCRYPT);
    $firstName = 'Characterization';
    $staffLastName = 'Window Concurrency Staff';
    $staffEmail = $prefix . 'staff@example.test';
    $staffRole = ROLE_STAFF;
    $verified = 1;
    $stmt = $conn->prepare("
        INSERT INTO users (first_name, last_name, email, password_hash, role, is_verified)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('sssssi', $firstName, $staffLastName, $staffEmail, $passwordHash, $staffRole, $verified);
    $stmt->execute();
    $staffUserId = (int) $conn->insert_id;

    $department = 'Characterization';
    $stmt = $conn->prepare('INSERT INTO staff (user_id, department) VALUES (?, ?)');
    $stmt->bind_param('is', $staffUserId, $department);
    $stmt->execute();
    $staffId = (int) $conn->insert_id;

    $clientIds = [];
    foreach (['a', 'b'] as $suffix) {
        $lastName = 'Window Client ' . strtoupper($suffix);
        $email = $prefix . $suffix . '@example.test';
        $clientRole = ROLE_CLIENT;
        $stmt = $conn->prepare("
            INSERT INTO users (first_name, last_name, email, password_hash, role, is_verified)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('sssssi', $firstName, $lastName, $email, $passwordHash, $clientRole, $verified);
        $stmt->execute();
        $clientIds[] = (int) $conn->insert_id;
    }

    $serviceCode = 'CHAR-WC';
    $serviceName = 'Characterization Window Concurrency';
    $encoded = 118;
    $order = 118;
    $stmt = $conn->prepare("
        INSERT INTO health_services
          (service_code, service_name, service_encoded, display_order)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param('ssii', $serviceCode, $serviceName, $encoded, $order);
    $stmt->execute();
    $serviceId = (int) $conn->insert_id;

    $windowName = 'Characterization Concurrency Window';
    $windowStatus = 'open';
    $stmt = $conn->prepare("
        INSERT INTO service_windows (window_name, service_id, staff_id, status)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param('siis', $windowName, $serviceId, $staffId, $windowStatus);
    $stmt->execute();

    return [
        'staff_user_id' => $staffUserId,
        'staff_id' => $staffId,
        'client_ids' => $clientIds,
        'service_id' => $serviceId,
        'window_id' => (int) $conn->insert_id,
    ];
}

function insertServiceWindowConcurrencyTicket(
    mysqli $conn,
    array $fixtures,
    int $clientIndex,
    string $suffix,
    string $status,
    int $priority = 0
): int {
    $userId = (int) $fixtures['client_ids'][$clientIndex];
    $serviceId = (int) $fixtures['service_id'];
    $windowId = $status === 'serving' ? (int) $fixtures['window_id'] : null;
    $reference = 'BHC-2096-' . $suffix;
    $ticketNumber = 'X-' . $suffix;
    $clientType = $priority > 0 ? 'senior' : 'regular';
    $calledAt = $status === 'serving'
        ? date('Y-m-d H:i:s', time() - 900)
        : null;
    $servedAt = $calledAt;
    $stmt = $conn->prepare("
        INSERT INTO queue_tickets
          (user_id, window_id, service_id, reference_number, ticket_number,
           client_type, priority_level, status, called_at, served_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        'iiisssisss',
        $userId,
        $windowId,
        $serviceId,
        $reference,
        $ticketNumber,
        $clientType,
        $priority,
        $status,
        $calledAt,
        $servedAt
    );
    $stmt->execute();
    $ticketId = (int) $conn->insert_id;
    if ($status === 'serving') {
        $stmt = $conn->prepare('INSERT INTO wait_time_logs (ticket_id, predicted_wait_min) VALUES (?, 5)');
        $stmt->bind_param('i', $ticketId);
        $stmt->execute();
        $conn->query("UPDATE service_windows SET status='busy' WHERE window_id=" . (int) $fixtures['window_id']);
    }
    return $ticketId;
}

function cleanupServiceWindowConcurrencyTickets(mysqli $conn, array $fixtures): void {
    $serviceId = (int) $fixtures['service_id'];
    $tickets = $conn->query("
        SELECT ticket_id
        FROM queue_tickets
        WHERE service_id={$serviceId}
    ")->fetch_all(MYSQLI_ASSOC);
    if ($tickets) {
        $ticketIds = implode(',', array_map(
            static fn(array $ticket): int => (int) $ticket['ticket_id'],
            $tickets
        ));
        $conn->query("DELETE FROM feedback WHERE ticket_id IN ({$ticketIds})");
        $conn->query("DELETE FROM notifications WHERE ticket_id IN ({$ticketIds})");
        $conn->query("DELETE FROM wait_time_logs WHERE ticket_id IN ({$ticketIds})");
        $conn->query("DELETE FROM activity_logs WHERE ticket_id IN ({$ticketIds})");
        $conn->query("DELETE FROM queue_tickets WHERE ticket_id IN ({$ticketIds})");
    }
    $conn->query("UPDATE service_windows SET status='open' WHERE window_id=" . (int) $fixtures['window_id']);
}

function cleanupServiceWindowConcurrencyFixtures(mysqli $conn, array $fixtures): void {
    cleanupServiceWindowConcurrencyTickets($conn, $fixtures);
    $windowId = (int) $fixtures['window_id'];
    $staffId = (int) $fixtures['staff_id'];
    $serviceId = (int) $fixtures['service_id'];
    $userIds = array_merge([(int) $fixtures['staff_user_id']], array_map('intval', $fixtures['client_ids']));
    $idList = implode(',', $userIds);
    $conn->query("DELETE FROM service_windows WHERE window_id={$windowId}");
    $conn->query("DELETE FROM staff WHERE staff_id={$staffId}");
    $conn->query("DELETE FROM health_services WHERE service_id={$serviceId}");
    $conn->query("DELETE FROM sms_logs WHERE user_id IN ({$idList})");
    $conn->query("DELETE FROM activity_logs WHERE user_id IN ({$idList})");
    $conn->query("DELETE FROM users WHERE user_id IN ({$idList})");
}

function actionRequest(array $fixtures, string $action, int $ticketId = 0): array {
    return [
        'action' => $action,
        'staff_user_id' => (int) $fixtures['staff_user_id'],
        'staff_id' => (int) $fixtures['staff_id'],
        'ticket_id' => $ticketId,
    ];
}

$conn = testDatabaseConnection();
$fixtures = [];
$exitCode = 0;
try {
    $fixtures = createServiceWindowConcurrencyFixtures($conn);

    fwrite(STDOUT, '[RUN] duplicate concurrent Call Next' . PHP_EOL);
    insertServiceWindowConcurrencyTicket($conn, $fixtures, 0, '9901', 'waiting', 1);
    insertServiceWindowConcurrencyTicket($conn, $fixtures, 1, '9902', 'waiting', 0);
    $callResults = runServiceWindowConcurrentActions([
        actionRequest($fixtures, 'call'),
        actionRequest($fixtures, 'call'),
    ]);
    assertSameValue(1, count(array_filter(
        $callResults,
        static fn(array $result): bool => $result['status'] === 'success'
    )));
    assertSameValue(1, count(array_filter(
        $callResults,
        static fn(array $result): bool => $result['status'] === 'already_serving'
    )));
    $servingCount = (int) $conn->query("
        SELECT COUNT(*) AS cnt
        FROM queue_tickets
        WHERE service_id=" . (int) $fixtures['service_id'] . " AND status='serving'
    ")->fetch_assoc()['cnt'];
    assertSameValue(1, $servingCount);
    assertSameValue('busy', $conn->query("
        SELECT status FROM service_windows WHERE window_id=" . (int) $fixtures['window_id']
    )->fetch_assoc()['status']);
    cleanupServiceWindowConcurrencyTickets($conn, $fixtures);

    fwrite(STDOUT, '[RUN] Complete versus timeout' . PHP_EOL);
    $completeVoidTicket = insertServiceWindowConcurrencyTicket($conn, $fixtures, 0, '9911', 'serving');
    $completeVoidResults = runServiceWindowConcurrentActions([
        actionRequest($fixtures, 'complete', $completeVoidTicket),
        actionRequest($fixtures, 'void', $completeVoidTicket),
    ]);
    $completeResult = array_values(array_filter(
        $completeVoidResults,
        static fn(array $result): bool => $result['worker_action'] === 'complete'
    ))[0];
    $voidResult = array_values(array_filter(
        $completeVoidResults,
        static fn(array $result): bool => $result['worker_action'] === 'void'
    ))[0];
    $finalStatus = $conn->query("
        SELECT status FROM queue_tickets WHERE ticket_id={$completeVoidTicket}
    ")->fetch_assoc()['status'];
    assertContainsValue($finalStatus, ['completed', 'voided']);
    if ($finalStatus === 'completed') {
        assertSameValue('success', $completeResult['status']);
        assertSameValue(0, (int) $voidResult['voided']);
    } else {
        assertSameValue('ticket_not_found', $completeResult['status']);
        assertSameValue(1, (int) $voidResult['voided']);
    }
    assertSameValue('open', $conn->query("
        SELECT status FROM service_windows WHERE window_id=" . (int) $fixtures['window_id']
    )->fetch_assoc()['status']);
    cleanupServiceWindowConcurrencyTickets($conn, $fixtures);

    fwrite(STDOUT, '[RUN] Skip versus Complete' . PHP_EOL);
    $skipCompleteTicket = insertServiceWindowConcurrencyTicket($conn, $fixtures, 1, '9921', 'serving');
    $skipCompleteResults = runServiceWindowConcurrentActions([
        actionRequest($fixtures, 'skip', $skipCompleteTicket),
        actionRequest($fixtures, 'complete', $skipCompleteTicket),
    ]);
    $successfulTransitions = array_values(array_filter(
        $skipCompleteResults,
        static fn(array $result): bool => $result['status'] === 'success'
    ));
    $losingTransitions = array_values(array_filter(
        $skipCompleteResults,
        static fn(array $result): bool => $result['status'] === 'ticket_not_found'
    ));
    assertSameValue(1, count($successfulTransitions));
    assertSameValue(1, count($losingTransitions));
    $finalStatus = $conn->query("
        SELECT status FROM queue_tickets WHERE ticket_id={$skipCompleteTicket}
    ")->fetch_assoc()['status'];
    assertContainsValue($finalStatus, ['skipped', 'completed']);
    assertSameValue('open', $conn->query("
        SELECT status FROM service_windows WHERE window_id=" . (int) $fixtures['window_id']
    )->fetch_assoc()['status']);
    cleanupServiceWindowConcurrencyTickets($conn, $fixtures);

    foreach (['complete', 'skip', 'void'] as $raceIndex => $opponentAction) {
        fwrite(STDOUT, '[RUN] Manual Void versus ' . ucfirst($opponentAction) . PHP_EOL);
        $manualVoidTicket = insertServiceWindowConcurrencyTicket(
            $conn,
            $fixtures,
            $raceIndex % 2,
            (string) (9931 + $raceIndex),
            'serving'
        );
        $raceResults = runServiceWindowConcurrentActions([
            actionRequest($fixtures, 'manual_void', $manualVoidTicket),
            actionRequest($fixtures, $opponentAction, $manualVoidTicket),
        ]);
        $manualResult = array_values(array_filter(
            $raceResults,
            static fn(array $result): bool => $result['worker_action'] === 'manual_void'
        ))[0];
        $opponentResult = array_values(array_filter(
            $raceResults,
            static fn(array $result): bool => $result['worker_action'] === $opponentAction
        ))[0];
        $terminal = $conn->query("
            SELECT status, voided_reason
            FROM queue_tickets
            WHERE ticket_id={$manualVoidTicket}
        ")->fetch_assoc();
        assertContainsValue($terminal['status'], ['completed', 'skipped', 'voided']);

        if ($manualResult['status'] === 'success') {
            assertSameValue('voided', $terminal['status']);
            assertSameValue('Voided manually by staff', $terminal['voided_reason']);
            if ($opponentAction === 'void') {
                assertSameValue(0, (int) $opponentResult['voided']);
            } else {
                assertSameValue('ticket_not_found', $opponentResult['status']);
            }
        } else {
            assertSameValue('ticket_not_found', $manualResult['status']);
            if ($opponentAction === 'void') {
                assertSameValue(1, (int) $opponentResult['voided']);
            } else {
                assertSameValue('success', $opponentResult['status']);
            }
        }

        $terminalActivities = (int) $conn->query("
            SELECT COUNT(*) AS total
            FROM activity_logs
            WHERE ticket_id={$manualVoidTicket}
              AND action IN ('ticket_completed', 'ticket_skipped', 'ticket_voided')
        ")->fetch_assoc()['total'];
        assertSameValue(1, $terminalActivities);
        $notificationCount = (int) $conn->query("
            SELECT COUNT(*) AS total
            FROM notifications
            WHERE ticket_id={$manualVoidTicket}
        ")->fetch_assoc()['total'];
        assertTrueValue($notificationCount <= 1);
        assertSameValue('open', $conn->query("
            SELECT status FROM service_windows WHERE window_id=" . (int) $fixtures['window_id']
        )->fetch_assoc()['status']);
        cleanupServiceWindowConcurrencyTickets($conn, $fixtures);
    }

    fwrite(STDOUT, '[PASS] duplicate Call Next produced one serving ticket' . PHP_EOL);
    fwrite(STDOUT, '[PASS] Complete and timeout produced one terminal state' . PHP_EOL);
    fwrite(STDOUT, '[PASS] Skip and Complete produced one terminal state' . PHP_EOL);
    fwrite(STDOUT, '[PASS] Manual Void races produced exactly one terminal transition' . PHP_EOL);
} catch (Throwable $error) {
    fwrite(STDERR, '[FAIL] ' . get_class($error) . ': ' . $error->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    if ($fixtures) {
        cleanupServiceWindowConcurrencyFixtures($conn, $fixtures);
    }
}

exit($exitCode);
