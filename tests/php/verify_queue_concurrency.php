<?php
/**
 * Isolated, repeatable multi-process verification for Batch 4 issuance locks.
 */

require_once __DIR__ . '/bootstrap.php';
require_once SMARTQMS_ROOT . '/modules/queue/qr_generate.php';

function concurrencyFixtureUser(mysqli $conn, string $suffix): int {
    $email = testFixturePrefix() . 'concurrency_' . $suffix . '@example.test';
    $existing = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
    $existing->bind_param('s', $email);
    $existing->execute();
    if ($existing->get_result()->fetch_assoc()) {
        throw new RuntimeException('Stale concurrency fixture exists: ' . $email);
    }

    $firstName = 'Characterization';
    $lastName = 'Concurrency ' . strtoupper($suffix);
    $passwordHash = password_hash('characterization_password', PASSWORD_BCRYPT);
    $role = ROLE_CLIENT;
    $verified = 1;
    $stmt = $conn->prepare("
        INSERT INTO users (first_name, last_name, email, password_hash, role, is_verified)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('sssssi', $firstName, $lastName, $email, $passwordHash, $role, $verified);
    $stmt->execute();
    return (int) $conn->insert_id;
}

function concurrencyTempPath(string $label): string {
    $path = tempnam(sys_get_temp_dir(), 'smartqms_' . $label . '_');
    if ($path === false) {
        throw new RuntimeException('Could not allocate a concurrency coordination file.');
    }
    unlink($path);
    return $path;
}

function runConcurrentIssuance(array $requests): array {
    $goPath = concurrencyTempPath('go');
    $workerPath = __DIR__ . '/integration/queue_concurrency_worker.php';
    $processes = [];

    try {
        foreach ($requests as $index => $request) {
            $readyPath = concurrencyTempPath('ready');
            $command = implode(' ', array_map('escapeshellarg', [
                PHP_BINARY,
                $workerPath,
                (string) $request['user_id'],
                (string) $request['service_id'],
                (string) $request['client_type'],
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
                throw new RuntimeException('Could not start queue concurrency worker.');
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
                throw new RuntimeException('Workers did not reach the concurrency barrier.');
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
            throw new RuntimeException(
                'Concurrency worker failed: ' . implode(' | ', $failures)
                . ' successful_results=' . json_encode($results)
            );
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

function cleanupConcurrencyTickets(mysqli $conn, array $userIds): void {
    if (!$userIds) {
        return;
    }
    $ids = array_map('intval', $userIds);
    $idList = implode(',', $ids);
    $tickets = $conn->query("
        SELECT ticket_id, qr_code_path
        FROM queue_tickets
        WHERE user_id IN ({$idList})
    ")->fetch_all(MYSQLI_ASSOC);

    foreach ($tickets as $ticket) {
        if (!empty($ticket['qr_code_path'])) {
            removeGeneratedQueueQr((string) $ticket['qr_code_path']);
        }
    }

    if ($tickets) {
        $ticketIds = implode(',', array_map(
            static fn(array $ticket): int => (int) $ticket['ticket_id'],
            $tickets
        ));
        $conn->query("DELETE FROM notifications WHERE ticket_id IN ({$ticketIds})");
        $conn->query("DELETE FROM wait_time_logs WHERE ticket_id IN ({$ticketIds})");
        $conn->query("DELETE FROM activity_logs WHERE ticket_id IN ({$ticketIds})");
        $conn->query("DELETE FROM queue_tickets WHERE ticket_id IN ({$ticketIds})");
    }

    $remaining = (int) $conn->query("
        SELECT COUNT(*) AS cnt
        FROM queue_tickets
        WHERE user_id IN ({$idList})
    ")->fetch_assoc()['cnt'];
    if ($remaining !== 0) {
        throw new RuntimeException('Concurrency ticket cleanup left committed rows.');
    }
}

$conn = testDatabaseConnection();
$serviceId = (int) ($conn->query("SELECT service_id FROM health_services WHERE is_active=1 AND priority_only=0 ORDER BY service_id LIMIT 1")->fetch_assoc()['service_id'] ?? 0);
if ($serviceId < 1) {
    fwrite(STDERR, 'No active regular service is available in the test database.' . PHP_EOL);
    exit(1);
}

$userIds = [];
$exitCode = 0;
try {
    $userIds = [
        concurrencyFixtureUser($conn, 'a'),
        concurrencyFixtureUser($conn, 'b'),
    ];

    fwrite(STDOUT, '[RUN] single-client issuance warmup' . PHP_EOL);
    $singleResult = runConcurrentIssuance([
        ['user_id' => $userIds[0], 'service_id' => $serviceId, 'client_type' => 'regular'],
    ]);
    fwrite(STDOUT, '[INFO] single issuance seconds=' . $singleResult[0]['duration_seconds'] . PHP_EOL);
    cleanupConcurrencyTickets($conn, $userIds);

    fwrite(STDOUT, '[RUN] same-client concurrency' . PHP_EOL);
    $sameClientResults = runConcurrentIssuance([
        ['user_id' => $userIds[0], 'service_id' => $serviceId, 'client_type' => 'regular'],
        ['user_id' => $userIds[0], 'service_id' => $serviceId, 'client_type' => 'regular'],
    ]);
    $sameCreated = array_values(array_filter(
        $sameClientResults,
        static fn(array $result): bool => (bool) ($result['created'] ?? false)
    ));
    $sameActive = array_values(array_filter(
        $sameClientResults,
        static fn(array $result): bool => !(bool) ($result['created'] ?? false)
    ));
    assertSameValue(1, count($sameCreated), 'Same-client concurrency must create one ticket.');
    assertSameValue(1, count($sameActive), 'Same-client concurrency must return one active ticket.');
    cleanupConcurrencyTickets($conn, $userIds);
    $lockOwner = $conn->query("
        SELECT IS_USED_LOCK('" . ticketIssuanceLockName((int) date('Y')) . "') AS owner
    ")->fetch_assoc()['owner'] ?? null;
    fwrite(STDOUT, '[INFO] lock owner before different-client run=' . ($lockOwner ?? 'none') . PHP_EOL);

    fwrite(STDOUT, '[RUN] different-client concurrency' . PHP_EOL);
    $differentClientResults = runConcurrentIssuance([
        ['user_id' => $userIds[0], 'service_id' => $serviceId, 'client_type' => 'senior'],
        ['user_id' => $userIds[1], 'service_id' => $serviceId, 'client_type' => 'pwd'],
    ]);
    assertTrueValue((bool) $differentClientResults[0]['created']);
    assertTrueValue((bool) $differentClientResults[1]['created']);
    assertSameValue(
        2,
        count(array_unique(array_column($differentClientResults, 'reference_number'))),
        'Concurrent references must be unique.'
    );
    assertSameValue(
        2,
        count(array_unique(array_column($differentClientResults, 'ticket_number'))),
        'Concurrent daily ticket numbers must be unique.'
    );

    fwrite(STDOUT, '[PASS] same-client concurrent joins create exactly one active ticket' . PHP_EOL);
    fwrite(STDOUT, '[PASS] different-client concurrent joins retain unique references and daily numbers' . PHP_EOL);
} catch (Throwable $error) {
    fwrite(STDERR, '[FAIL] ' . get_class($error) . ': ' . $error->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    if ($userIds) {
        cleanupConcurrencyTickets($conn, $userIds);
        $idList = implode(',', array_map('intval', $userIds));
        $conn->query("DELETE FROM sms_logs WHERE user_id IN ({$idList})");
        $conn->query("DELETE FROM auth_attempts WHERE identifier_hash IN (
            SHA2('" . testFixturePrefix() . "concurrency_a@example.test', 256),
            SHA2('" . testFixturePrefix() . "concurrency_b@example.test', 256)
        )");
        $conn->query("DELETE FROM users WHERE user_id IN ({$idList})");
    }
}

exit($exitCode);
