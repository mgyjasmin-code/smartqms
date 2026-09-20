<?php

require_once SMARTQMS_ROOT . '/modules/queue/qr_generate.php';
require_once SMARTQMS_ROOT . '/modules/queue/ticket_service.php';
require_once SMARTQMS_ROOT . '/modules/queue/status_queries.php';
require_once SMARTQMS_ROOT . '/modules/notifications/send_alert.php';
require_once SMARTQMS_ROOT . '/modules/notifications/notification_queries.php';
require_once SMARTQMS_ROOT . '/modules/feedback/feedback_service.php';

function characterizationQueueFixtures(mysqli $connection): array {
    $password = password_hash('characterization_password', PASSWORD_BCRYPT);
    $users = [];
    foreach (['owner', 'regular_early', 'regular_late', 'senior', 'pwd'] as $label) {
        $email = testFixturePrefix() . $label . '@example.test';
        $firstName = 'Characterization';
        $lastName = ucfirst(str_replace('_', ' ', $label));
        $role = ROLE_CLIENT;
        $verified = 1;
        $statement = $connection->prepare('INSERT INTO users (first_name, last_name, email, password_hash, role, is_verified) VALUES (?, ?, ?, ?, ?, ?)');
        $statement->bind_param('sssssi', $firstName, $lastName, $email, $password, $role, $verified);
        $statement->execute();
        $users[$label] = $connection->insert_id;
    }

    $serviceCode = 'CHAR-Q';
    $serviceName = 'Characterization Queue';
    $serviceEncoded = 120;
    $priorityOnly = 1;
    $displayOrder = 120;
    $statement = $connection->prepare('INSERT INTO health_services (service_code, service_name, service_encoded, priority_only, display_order) VALUES (?, ?, ?, ?, ?)');
    $statement->bind_param('ssiii', $serviceCode, $serviceName, $serviceEncoded, $priorityOnly, $displayOrder);
    $statement->execute();

    return ['users' => $users, 'service_id' => $connection->insert_id];
}

function characterizationInsertTicket(
    mysqli $connection,
    int $userId,
    int $serviceId,
    string $suffix,
    string $clientType,
    int $priority,
    string $status,
    string $issuedAt
): int {
    $reference = 'BHC-2099-' . $suffix;
    $ticketNumber = 'C-' . $suffix;
    $lifecycle = match ($status) {
        'serving' => 'calling',
        'completed' => 'completed',
        'voided', 'skipped' => 'void',
        default => 'waiting',
    };
    $checkedInAt = in_array($status, ['waiting', 'serving', 'completed', 'skipped'], true) ? $issuedAt : null;
    $statement = $connection->prepare("INSERT INTO queue_tickets (user_id, service_id, reference_number, ticket_number, client_type, priority_level, status, lifecycle_status, issued_at, checked_in_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $statement->bind_param('iisssissss', $userId, $serviceId, $reference, $ticketNumber, $clientType, $priority, $status, $lifecycle, $issuedAt, $checkedInAt);
    $statement->execute();
    return $connection->insert_id;
}

testCase('active ticket lookup includes scheduled waiting calling and in-progress but excludes terminal states', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $fixtures = characterizationQueueFixtures($connection);
        $owner = $fixtures['users']['owner'];
        $serviceId = $fixtures['service_id'];

        characterizationInsertTicket($connection, $owner, $serviceId, '9101', 'regular', 0, 'completed', '2037-01-01 08:00:00');
        $waitingId = characterizationInsertTicket($connection, $owner, $serviceId, '9102', 'regular', 0, 'waiting', '2037-01-01 09:00:00');
        assertSameValue($waitingId, (int) getActiveTicket($connection, $owner)['ticket_id']);

        $connection->query("UPDATE queue_tickets SET status='completed', lifecycle_status='completed' WHERE ticket_id=" . $waitingId);
        assertSameValue(null, getActiveTicket($connection, $owner));

        $servingId = characterizationInsertTicket($connection, $owner, $serviceId, '9103', 'regular', 0, 'serving', '2037-01-01 10:00:00');
        assertSameValue($servingId, (int) getActiveTicket($connection, $owner)['ticket_id']);

        $connection->query("UPDATE queue_tickets SET status='completed', lifecycle_status='completed' WHERE ticket_id=" . $servingId);
        characterizationInsertTicket($connection, $owner, $serviceId, '9104', 'regular', 0, 'skipped', '2037-01-01 11:00:00');
        assertSameValue(null, getActiveTicket($connection, $owner));
    });
});

testCase('completed ticket remains visible only until its feedback is submitted', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $fixtures = characterizationQueueFixtures($connection);
        $owner = $fixtures['users']['owner'];
        $serviceId = $fixtures['service_id'];

        characterizationInsertTicket($connection, $owner, $serviceId, '9151', 'regular', 0, 'skipped', '2037-01-01 08:00:00');
        assertSameValue(null, getCompletedTicketAwaitingFeedback($connection, $owner));

        $completedId = characterizationInsertTicket($connection, $owner, $serviceId, '9152', 'regular', 0, 'completed', '2037-01-01 09:00:00');
        assertSameValue($completedId, (int) getCompletedTicketAwaitingFeedback($connection, $owner)['ticket_id']);

        $rating = 5;
        $statement = $connection->prepare('INSERT INTO feedback (ticket_id, user_id, service_id, rating) VALUES (?, ?, ?, ?)');
        $statement->bind_param('iiii', $completedId, $owner, $serviceId, $rating);
        $statement->execute();

        assertSameValue(null, getCompletedTicketAwaitingFeedback($connection, $owner));
    });
});

testCase('completed ticket accepts exactly one feedback submission', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $fixtures = characterizationQueueFixtures($connection);
        $owner = $fixtures['users']['owner'];
        $serviceId = $fixtures['service_id'];
        $completedId = characterizationInsertTicket(
            $connection,
            $owner,
            $serviceId,
            '9153',
            'regular',
            0,
            'completed',
            '2037-01-01 09:15:00'
        );

        assertSameValue($completedId, (int) completedTicketAvailableForFeedback($connection, $owner, $completedId)['ticket_id']);

        $first = submitFeedbackForClient($connection, $owner, $completedId, 5, 'Characterization feedback');
        $second = submitFeedbackForClient($connection, $owner, $completedId, 4, 'Duplicate attempt');

        assertSameValue('submitted', $first['status']);
        assertSameValue('already_submitted', $second['status']);
        assertSameValue(null, completedTicketAvailableForFeedback($connection, $owner, $completedId));

        $feedbackCount = $connection->query(
            'SELECT COUNT(*) AS total FROM feedback WHERE ticket_id=' . $completedId
        )->fetch_assoc();
        assertSameValue(1, (int) $feedbackCount['total']);

        $activityCount = $connection->query(
            "SELECT COUNT(*) AS total FROM activity_logs WHERE ticket_id={$completedId} AND action='feedback_submitted'"
        )->fetch_assoc();
        assertSameValue(1, (int) $activityCount['total']);
    });
});

testCase('live ordering is strict FIFO and ignores legacy priority values', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $fixtures = characterizationQueueFixtures($connection);
        $users = $fixtures['users'];
        $serviceId = $fixtures['service_id'];
        characterizationInsertTicket($connection, $users['regular_early'], $serviceId, '9201', 'regular', 0, 'waiting', '2037-01-01 08:00:00');
        characterizationInsertTicket($connection, $users['regular_late'], $serviceId, '9202', 'regular', 0, 'waiting', '2037-01-01 09:00:00');
        characterizationInsertTicket($connection, $users['senior'], $serviceId, '9203', 'senior', 1, 'waiting', '2037-01-01 08:30:00');
        characterizationInsertTicket($connection, $users['pwd'], $serviceId, '9204', 'pwd', 1, 'waiting', '2037-01-01 08:45:00');

        $statement = $connection->prepare("SELECT client_type FROM queue_tickets WHERE service_id=? AND status='waiting' ORDER BY checked_in_at ASC, ticket_id ASC");
        $statement->bind_param('i', $serviceId);
        $statement->execute();
        assertSameValue(['regular', 'senior', 'pwd', 'regular'], array_column($statement->get_result()->fetch_all(MYSQLI_ASSOC), 'client_type'));
    });
});

testCase('people ahead follows check-in FIFO and ignores legacy priority', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $fixtures = characterizationQueueFixtures($connection);
        $users = $fixtures['users'];
        $serviceId = $fixtures['service_id'];
        characterizationInsertTicket($connection, $users['regular_early'], $serviceId, '9301', 'regular', 0, 'waiting', '2037-01-01 08:00:00');
        $targetId = characterizationInsertTicket($connection, $users['owner'], $serviceId, '9302', 'regular', 0, 'waiting', '2037-01-01 09:00:00');
        characterizationInsertTicket($connection, $users['regular_late'], $serviceId, '9303', 'regular', 0, 'waiting', '2037-01-01 10:00:00');
        characterizationInsertTicket($connection, $users['senior'], $serviceId, '9304', 'senior', 1, 'waiting', '2037-01-01 09:30:00');

        $ticket = $connection->query('SELECT * FROM queue_tickets WHERE ticket_id=' . $targetId)->fetch_assoc();
        assertSameValue(1, peopleAhead($connection, $ticket));
    });
});

testCase('prediction snapshot retains features while live priority remains disabled', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $fixtures = characterizationQueueFixtures($connection);
        $snapshot = queuePredictionSnapshot($connection, $fixtures['service_id'], 'senior');
        assertTrueValue(is_array($snapshot));
        assertArrayHasKeys(['service', 'availability', 'features', 'queue_length', 'active_windows', 'avg_service_time', 'priority_level', 'fallback_wait_min'], $snapshot);
        assertArrayHasKeys(['queue_length', 'hour_of_day', 'day_of_week', 'service_type_encoded', 'client_type_encoded', 'active_windows', 'avg_service_time'], $snapshot['features']);
        assertSameValue(1, (int) $snapshot['service']['priority_only']);
        assertSameValue(0, $snapshot['priority_level']);
        assertSameValue(1, $snapshot['features']['client_type_encoded']);
    });
});

testCase('queue classification retains regular senior and PWD contracts', function (): void {
    assertSameValue('regular', normalizeQueueClientType('regular'));
    assertSameValue('senior', normalizeQueueClientType('senior'));
    assertSameValue('pwd', normalizeQueueClientType('pwd'));
    assertSameValue('regular', normalizeQueueClientType('unexpected'));
    assertSameValue(0, queuePriorityLevelForClientType('regular'));
    assertSameValue(0, queuePriorityLevelForClientType('senior'));
    assertSameValue(0, queuePriorityLevelForClientType('pwd'));
    assertTrueValue(queueServiceAllowsClientType(['priority_only' => 1], 'regular'));
    assertTrueValue(queueServiceAllowsClientType(['priority_only' => 1], 'senior'));
    assertTrueValue(queueServiceAllowsClientType(['priority_only' => 1], 'pwd'));
    assertTrueValue(queueServiceAllowsClientType(['priority_only' => 0], 'regular'));
});

testCase('ML prediction parsing rejects unavailable malformed and unreasonable values', function (): void {
    assertSameValue(null, parseMlWaitEstimate(false, 200));
    assertSameValue(null, parseMlWaitEstimate('{"predicted_wait_minutes":12}', 503));
    assertSameValue(null, parseMlWaitEstimate('not-json', 200));
    assertSameValue(null, parseMlWaitEstimate('{"predicted_wait_minutes":"unknown"}', 200));
    assertSameValue(null, parseMlWaitEstimate('{"predicted_wait_minutes":-1}', 200));
    assertSameValue(null, parseMlWaitEstimate('{"predicted_wait_minutes":481}', 200));
    assertSameValue(12.35, parseMlWaitEstimate('{"predicted_wait_minutes":12.345}', 200));
});

testCase('reference allocation uses booking date and daily sequence while daily queue numbering remains sequential', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $fixtures = characterizationQueueFixtures($connection);
        $owner = $fixtures['users']['owner'];
        $serviceId = $fixtures['service_id'];
        $year = (int) date('Y');
        $issuedAt = date('Y-m-d H:i:s');

        foreach ([2, 7] as $suffix) {
            $reference = REF_PREFIX . '-' . $year . '-' . str_pad($suffix, 4, '0', STR_PAD_LEFT);
            $ticketNumber = 'Z-' . str_pad($suffix, 3, '0', STR_PAD_LEFT);
            $status = 'completed';
            $clientType = 'regular';
            $priority = 0;
            $stmt = $connection->prepare("
                INSERT INTO queue_tickets
                  (user_id, service_id, reference_number, ticket_number, client_type, priority_level, status, issued_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('iisssiss', $owner, $serviceId, $reference, $ticketNumber, $clientType, $priority, $status, $issuedAt);
            $stmt->execute();
        }

        $newReference = sprintf('%04d%06d', $year, 13);
        $stmt = $connection->prepare("INSERT INTO queue_tickets (user_id, service_id, reference_number, ticket_number, status, issued_at) VALUES (?, ?, ?, 'Z-013', 'completed', ?)");
        $stmt->bind_param('iiss', $owner, $serviceId, $newReference, $issuedAt);
        $stmt->execute();

        $datedReference = '2037010200000045';
        $datedIssuedAt = '2037-01-02 08:00:00';
        $dated = $connection->prepare("INSERT INTO queue_tickets (user_id, service_id, reference_number, ticket_number, status, issued_at) VALUES (?, ?, ?, 'Z-045', 'completed', ?)");
        $dated->bind_param('iiss', $owner, $serviceId, $datedReference, $datedIssuedAt);
        $dated->execute();
        assertSameValue('2037010200000046', generateRefNumber($connection, '20370102'));
        assertSameValue('2037010300000001', generateRefNumber($connection, '20370103'));

        $dailyCount = (int) $connection->query("SELECT COUNT(*) AS cnt FROM queue_tickets WHERE DATE(issued_at) = CURDATE()")->fetch_assoc()['cnt'];
        assertSameValue(
            'A-' . str_pad($dailyCount + 1, 3, '0', STR_PAD_LEFT),
            generateDailyTicketNumber($connection)
        );
    });
});

testCase('ticket issuance stores QR prediction and activity inside the caller transaction', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $fixtures = characterizationQueueFixtures($connection);
        $userId = (int) $fixtures['users']['senior'];
        $serviceId = (int) $fixtures['service_id'];
        $snapshot = queuePredictionSnapshot($connection, $serviceId, 'senior');
        $originalSession = $_SESSION;
        $result = null;

        try {
            $_SESSION['user_id'] = $userId;
            $_SESSION['role'] = ROLE_CLIENT;
            $result = createQueueTicket(
                $connection,
                $userId,
                $serviceId,
                'senior',
                1,
                $snapshot,
                12.34
            );

            assertTrueValue($result['created']);
            assertTrueValue((bool) preg_match('/^' . date('Ymd') . '[0-9]{8}$/', $result['reference_number']));
            assertTrueValue((bool) preg_match('/^A-\d{3,}$/', $result['ticket_number']));
            assertSameValue('ml', $result['prediction_source']);
            assertSameValue(12.34, $result['predicted_wait_minutes']);

            $ticket = $connection->query('SELECT * FROM queue_tickets WHERE ticket_id=' . (int) $result['ticket_id'])->fetch_assoc();
            assertSameValue('senior', $ticket['client_type']);
            assertSameValue(1, (int) $ticket['priority_level']);
            assertSameValue($result['qr_code_path'], $ticket['qr_code_path']);

            $qrAbsolutePath = queueQrAbsolutePath($result['qr_code_path']);
            assertTrueValue($qrAbsolutePath !== null && is_file($qrAbsolutePath));

            $log = $connection->query('SELECT * FROM wait_time_logs WHERE ticket_id=' . (int) $result['ticket_id'])->fetch_assoc();
            assertSameValue(12.34, (float) $log['predicted_wait_min']);
            assertArrayHasKeys([
                'queue_length',
                'hour_of_day',
                'day_of_week',
                'service_type_encoded',
                'client_type_encoded',
                'active_windows',
                'avg_service_time',
            ], $log);

            $activity = $connection->query("
                SELECT action, details
                FROM activity_logs
                WHERE ticket_id = " . (int) $result['ticket_id'] . "
                LIMIT 1
            ")->fetch_assoc();
            assertSameValue('ticket_created', $activity['action']);
            assertSameValue('Client joined queue', $activity['details']);
        } finally {
            if (is_array($result) && !empty($result['qr_code_path'])) {
                removeGeneratedQueueQr($result['qr_code_path']);
            }
            $_SESSION = $originalSession;
        }
    });
});

testCase('ticket issuance rechecks active tickets while the client row is locked', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $fixtures = characterizationQueueFixtures($connection);
        $userId = (int) $fixtures['users']['owner'];
        $serviceId = (int) $fixtures['service_id'];
        $existingId = characterizationInsertTicket(
            $connection,
            $userId,
            $serviceId,
            '9401',
            'regular',
            0,
            'waiting',
            '2037-01-01 08:00:00'
        );
        $snapshot = queuePredictionSnapshot($connection, $serviceId, 'regular');

        $result = createQueueTicket(
            $connection,
            $userId,
            $serviceId,
            'regular',
            0,
            $snapshot,
            null
        );

        assertFalseValue($result['created']);
        assertSameValue($existingId, (int) $result['active_ticket']['ticket_id']);
        $count = (int) $connection->query("SELECT COUNT(*) AS cnt FROM queue_tickets WHERE user_id={$userId}")->fetch_assoc()['cnt'];
        assertSameValue(1, $count);
    });
});

testCase('ticket issuance rollback removes only the QR created by the failed attempt', function (): void {
    $connection = testDatabaseConnection();
    $userId = 1;
    $serviceId = 1;
    $snapshot = queuePredictionSnapshot($connection, $serviceId, 'regular');
    $originalSession = $_SESSION;
    $beforeTicketCount = (int) $connection->query("SELECT COUNT(*) AS cnt FROM queue_tickets WHERE user_id={$userId}")->fetch_assoc()['cnt'];
    $beforeQrFiles = glob(rtrim(QR_DIR, '/\\') . DIRECTORY_SEPARATOR . '*.svg') ?: [];
    sort($beforeQrFiles);

    try {
        $_SESSION['user_id'] = 999999999;
        $_SESSION['role'] = ROLE_CLIENT;
        assertThrowsException(
            static function () use ($connection, $userId, $serviceId, $snapshot): void {
                createQueueTicket(
                    $connection,
                    $userId,
                    $serviceId,
                    'regular',
                    0,
                    $snapshot,
                    null
                );
            },
            mysqli_sql_exception::class
        );
    } finally {
        $_SESSION = $originalSession;
    }

    $afterTicketCount = (int) $connection->query("SELECT COUNT(*) AS cnt FROM queue_tickets WHERE user_id={$userId}")->fetch_assoc()['cnt'];
    $afterQrFiles = glob(rtrim(QR_DIR, '/\\') . DIRECTORY_SEPARATOR . '*.svg') ?: [];
    sort($afterQrFiles);
    assertSameValue($beforeTicketCount, $afterTicketCount);
    assertSameValue($beforeQrFiles, $afterQrFiles);
});

testCase('near-turn alerts preserve people-ahead semantics and one alert per ticket', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $fixtures = characterizationQueueFixtures($connection);
        $userId = (int) $fixtures['users']['owner'];
        $serviceId = (int) $fixtures['service_id'];
        $ticketId = characterizationInsertTicket(
            $connection,
            $userId,
            $serviceId,
            '9501',
            'regular',
            0,
            'waiting',
            '2037-01-01 08:00:00'
        );

        $candidates = nearTurnAlertCandidates($connection, $serviceId);
        assertSameValue(1, count($candidates));
        assertSameValue(0, (int) $candidates[0]['people_ahead']);
        assertSameValue(1, processNearTurnAlerts($connection, $serviceId));
        assertSameValue(0, processNearTurnAlerts($connection, $serviceId));

        $notification = $connection->query("SELECT * FROM notifications WHERE ticket_id={$ticketId}")->fetch_assoc();
        assertSameValue('turn_alert', $notification['type']);
        assertSameValue('browser', $notification['channel']);
        assertSameValue('sent', $notification['delivery_status']);

        $recent = recentNotificationsForUser($connection, $userId, 10);
        assertSameValue(1, count($recent));
        assertSameValue(0, (int) $recent[0]['is_read']);
        assertSameValue(1, unreadNotificationCountForUser($connection, $userId));
        assertSameValue(
            0,
            markNotificationIdsReadForUser(
                $connection,
                (int) $fixtures['users']['regular_early'],
                [(int) $notification['notif_id']]
            ),
            'A different client cannot acknowledge the owner notification.'
        );
        assertSameValue(1, markNotificationIdsReadForUser($connection, $userId, [(int) $notification['notif_id']]));
        assertSameValue(0, unreadNotificationCountForUser($connection, $userId));
        assertSameValue(1, count(recentNotificationsForUser($connection, $userId, 10)), 'Read notifications remain in history.');

        $connection->query("UPDATE notifications SET is_read=0 WHERE notif_id=" . (int) $notification['notif_id']);
        $unread = consumeUnreadNotifications($connection, $userId);
        assertSameValue(1, count($unread));
        assertSameValue($ticketId, (int) $unread[0]['ticket_id']);
        assertSameValue([], consumeUnreadNotifications($connection, $userId));
    });
});

testCase('SMS simulation uses the explicit connection and retains delivery logging', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $fixtures = characterizationQueueFixtures($connection);
        $userId = (int) $fixtures['users']['owner'];
        $connection->query("UPDATE system_settings SET setting_val='0' WHERE setting_key='sms_enabled'");

        assertTrueValue(sendSMS($connection, '09171234567', 'Characterization SMS', 'test', $userId));
        $row = $connection->query("
            SELECT phone, message, type, status
            FROM sms_logs
            WHERE user_id={$userId}
            ORDER BY log_id DESC
            LIMIT 1
        ")->fetch_assoc();
        assertSameValue('09171234567', $row['phone']);
        assertSameValue('Characterization SMS', $row['message']);
        assertSameValue('test', $row['type']);
        assertSameValue('simulated', $row['status']);
    });
});

testCase('queue and display status read models retain their response projections', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $fixtures = characterizationQueueFixtures($connection);
        $users = $fixtures['users'];
        $serviceId = (int) $fixtures['service_id'];
        $windowName = 'Characterization Window';
        $counterNumber = testNextCounterNumber($connection);
        $status = 'open';
        $isActive = 1;
        $stmt = $connection->prepare("
            INSERT INTO service_windows (counter_number, window_name, service_id, status, is_active)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('isisi', $counterNumber, $windowName, $serviceId, $status, $isActive);
        $stmt->execute();

        characterizationInsertTicket($connection, $users['regular_early'], $serviceId, '9601', 'regular', 0, 'waiting', '2037-01-01 08:00:00');
        characterizationInsertTicket($connection, $users['senior'], $serviceId, '9602', 'senior', 1, 'waiting', '2037-01-01 09:00:00');
        $viewerTicketId = characterizationInsertTicket($connection, $users['owner'], $serviceId, '9603', 'regular', 0, 'waiting', '2037-01-01 10:00:00');
        $predicted = 8.5;
        $stmt = $connection->prepare('INSERT INTO wait_time_logs (ticket_id, predicted_wait_min) VALUES (?, ?)');
        $stmt->bind_param('id', $viewerTicketId, $predicted);
        $stmt->execute();

        $windows = queueStatusWindows($connection);
        $matchingWindows = array_values(array_filter(
            $windows,
            static fn(array $window): bool => $window['window_name'] === 'Characterization Window'
        ));
        assertSameValue(1, count($matchingWindows));

        $next = queueStatusNextTickets($connection);
        $characterizationNext = array_values(array_filter(
            $next,
            static fn(array $ticket): bool => $ticket['service_name'] === 'Characterization Queue'
        ));
        assertSameValue('regular', $characterizationNext[0]['client_type']);

        $originalSession = $_SESSION;
        $originalGet = $_GET;
        try {
            $_SESSION['user_id'] = (int) $users['owner'];
            $_SESSION['role'] = ROLE_CLIENT;
            $viewer = queueStatusViewerTicket($connection);
            assertArrayHasKeys([
                'ticket_number',
                'service_name',
                'status',
                'people_ahead',
                'predicted_wait_min',
                'window_name',
            ], $viewer);
            assertSameValue(8.5, $viewer['predicted_wait_min']);

            $displayToken = 'characterization_display_token';
            $stmt = $connection->prepare("UPDATE system_settings SET setting_val=? WHERE setting_key='display_board_token'");
            $stmt->bind_param('s', $displayToken);
            $stmt->execute();
            $_GET['token'] = $displayToken;
            assertTrueValue(hasValidDisplayStatusToken($connection));
            $_GET['token'] = 'wrong-token';
            assertFalseValue(hasValidDisplayStatusToken($connection));
        } finally {
            $_SESSION = $originalSession;
            $_GET = $originalGet;
        }
    });
});
