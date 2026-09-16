<?php
/**
 * Arrival lifecycle, per-service numbering, and prediction persistence.
 *
 * Mutating functions in this file expect InnoDB tables and use row locks so
 * concurrent QR/manual confirmations cannot allocate two numbers or perform
 * two lifecycle transitions for one appointment.
 */

function queueArrivalSchemaReady(mysqli $conn): bool {
    foreach ([
        'client_first_name',
        'client_last_name',
        'checked_in_at',
        'scheduled_expires_at',
        'check_in_method',
        'checked_in_by',
    ] as $column) {
        if (!smartqmsTableHasColumn($conn, 'queue_tickets', $column)) {
            return false;
        }
    }
    return smartqmsTableExists($conn, 'ticket_print_batches')
        && smartqmsTableExists($conn, 'ticket_number_reservations');
}

function staffArrivalTicketProjection(array $ticket): array {
    $expiresAt = trim((string) ($ticket['scheduled_expires_at'] ?? ''));
    $expiresTimestamp = $expiresAt !== '' ? strtotime($expiresAt) : false;
    $visitDate = $expiresTimestamp !== false ? date('Y-m-d', $expiresTimestamp) : null;
    $isExpired = $expiresTimestamp !== false && $expiresTimestamp < time();
    $isForToday = $visitDate !== null && $visitDate === date('Y-m-d');
    $lifecycle = (string) ($ticket['lifecycle_status'] ?? 'scheduled');

    return [
        'ticket_id' => (int) ($ticket['ticket_id'] ?? 0),
        'reference_number' => (string) ($ticket['reference_number'] ?? ''),
        'ticket_number' => ($ticket['ticket_number'] ?? null) !== null
            ? (string) $ticket['ticket_number']
            : null,
        'client_first_name' => (string) ($ticket['client_first_name'] ?? ''),
        'client_last_name' => (string) ($ticket['client_last_name'] ?? ''),
        'client_name' => trim((string) ($ticket['client_name'] ?? '')),
        'phone_number' => (string) ($ticket['phone_number'] ?? ''),
        'service_id' => (int) ($ticket['service_id'] ?? 0),
        'service_name' => (string) ($ticket['service_name'] ?? ''),
        'entry_type' => (string) ($ticket['entry_type'] ?? 'online'),
        'lifecycle_status' => $lifecycle,
        'checked_in_at' => ($ticket['checked_in_at'] ?? null) !== null
            ? (string) $ticket['checked_in_at']
            : null,
        'scheduled_expires_at' => $expiresAt !== '' ? $expiresAt : null,
        'visit_date' => $visitDate,
        'is_for_today' => $isForToday,
        'is_expired' => $isExpired,
        'can_check_in' => $lifecycle === 'scheduled' && !$isExpired && $isForToday,
        'already_checked_in' => $lifecycle === 'waiting'
            && !empty($ticket['checked_in_at'])
            && !empty($ticket['ticket_number']),
    ];
}

function staffArrivalTicketById(mysqli $conn, int $ticketId, bool $forUpdate = false): ?array {
    if ($ticketId < 1 || !queueArrivalSchemaReady($conn)) {
        return null;
    }
    $sql = "
        SELECT qt.*, hs.service_name
        FROM queue_tickets qt
        JOIN health_services hs ON hs.service_id = qt.service_id
        WHERE qt.ticket_id = ?
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    return $ticket ? staffArrivalTicketProjection($ticket) : null;
}

/**
 * Read-only Staff lookup used before confirmation. The private token is used
 * only as a selector and is deliberately absent from the returned projection.
 */
function findStaffArrivalTicket(mysqli $conn, string $lookupType, string $lookupValue): ?array {
    if (!queueArrivalSchemaReady($conn)) {
        throw new RuntimeException('The arrival check-in database upgrade has not been applied.');
    }
    if (!in_array($lookupType, ['token', 'reference'], true)) {
        throw new InvalidArgumentException('Lookup type must be token or reference.');
    }

    $lookupValue = trim($lookupValue);
    if ($lookupValue === '') {
        throw new InvalidArgumentException('Enter a ticket reference or scan a QR code.');
    }
    if ($lookupType === 'token') {
        $lookupValue = strtolower($lookupValue);
        if (preg_match('/^(?:[a-f0-9]{32}|[a-f0-9]{64})$/', $lookupValue) !== 1) {
            throw new InvalidArgumentException('The scanned QR ticket is not valid.');
        }
        $where = 'qt.ticket_token = ?';
    } else {
        $lookupValue = strtoupper($lookupValue);
        if (strlen($lookupValue) > 40) {
            throw new InvalidArgumentException('The reference number is not valid.');
        }
        $where = 'qt.reference_number = ?';
    }

    $stmt = $conn->prepare("
        SELECT qt.*, hs.service_name
        FROM queue_tickets qt
        JOIN health_services hs ON hs.service_id = qt.service_id
        WHERE {$where}
        ORDER BY qt.ticket_id DESC
        LIMIT 1
    ");
    $stmt->bind_param('s', $lookupValue);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    if (!$ticket) {
        throw new DomainException('The scheduled ticket could not be found.');
    }
    return staffArrivalTicketProjection($ticket);
}

function queueTicketNumberPrefix(array $service): string {
    $source = strtoupper((string) ($service['service_name'] ?? $service['service_code'] ?? 'Q'));
    return preg_match('/[A-Z0-9]/', $source, $match) === 1 ? $match[0] : 'Q';
}

function queueTicketNumberFromSequence(array $service, int $sequence): string {
    return queueTicketNumberPrefix($service) . '-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
}

/**
 * Allocate the next number for a ticket already locked by its caller.
 * The service row is locked to serialize allocations for that service.
 */
function allocateQueueNumberForLockedTicket(
    mysqli $conn,
    array $ticket,
    ?string $serviceDate = null
): array {
    $existing = trim((string) ($ticket['ticket_number'] ?? ''));
    if ($existing !== '') {
        return ['ticket_number' => $existing, 'sequence_number' => (int) substr(strrchr($existing, '-'), 1), 'reserved' => false];
    }

    $ticketId = (int) ($ticket['ticket_id'] ?? 0);
    $serviceId = (int) ($ticket['service_id'] ?? 0);
    if ($ticketId < 1 || $serviceId < 1) {
        throw new InvalidArgumentException('A locked ticket and service are required for number allocation.');
    }

    $serviceStmt = $conn->prepare('SELECT * FROM health_services WHERE service_id = ? LIMIT 1 FOR UPDATE');
    $serviceStmt->bind_param('i', $serviceId);
    $serviceStmt->execute();
    $service = $serviceStmt->get_result()->fetch_assoc();
    if (!$service) {
        throw new RuntimeException('The ticket service could not be found.');
    }

    $serviceDate = $serviceDate ?: date('Y-m-d');
    $reservation = $conn->prepare("
        SELECT reservation_id, sequence_number
        FROM ticket_number_reservations
        WHERE service_id = ? AND service_date = ? AND ticket_id IS NULL
        ORDER BY sequence_number ASC
        LIMIT 1
        FOR UPDATE
    ");
    $reservation->bind_param('is', $serviceId, $serviceDate);
    $reservation->execute();
    $reservedRow = $reservation->get_result()->fetch_assoc();

    if ($reservedRow) {
        $sequence = (int) $reservedRow['sequence_number'];
        $reservationId = (int) $reservedRow['reservation_id'];
        $assign = $conn->prepare("
            UPDATE ticket_number_reservations
            SET ticket_id = ?, assigned_at = NOW()
            WHERE reservation_id = ? AND ticket_id IS NULL
        ");
        $assign->bind_param('ii', $ticketId, $reservationId);
        $assign->execute();
        if ($assign->affected_rows !== 1) {
            throw new RuntimeException('The reserved number changed before it could be allocated.');
        }
        $reserved = true;
    } else {
        $sequenceStmt = $conn->prepare("
            SELECT GREATEST(
              COALESCE((
                SELECT MAX(CAST(SUBSTRING_INDEX(qt.ticket_number, '-', -1) AS UNSIGNED))
                FROM queue_tickets qt
                WHERE qt.service_id = ?
                  AND DATE(COALESCE(qt.checked_in_at, qt.issued_at)) = ?
                  AND qt.ticket_number IS NOT NULL
              ), 0),
              COALESCE((
                SELECT MAX(r.sequence_number)
                FROM ticket_number_reservations r
                WHERE r.service_id = ? AND r.service_date = ?
              ), 0)
            ) + 1 AS next_sequence
        ");
        $sequenceStmt->bind_param('isis', $serviceId, $serviceDate, $serviceId, $serviceDate);
        $sequenceStmt->execute();
        $sequence = max(1, (int) ($sequenceStmt->get_result()->fetch_assoc()['next_sequence'] ?? 1));
        $reserved = false;
    }

    $ticketNumber = queueTicketNumberFromSequence($service, $sequence);
    $update = $conn->prepare('UPDATE queue_tickets SET ticket_number = ? WHERE ticket_id = ? AND ticket_number IS NULL');
    $update->bind_param('si', $ticketNumber, $ticketId);
    $update->execute();
    if ($update->affected_rows !== 1) {
        throw new RuntimeException('The ticket number changed before it could be allocated.');
    }

    return ['ticket_number' => $ticketNumber, 'sequence_number' => $sequence, 'reserved' => $reserved];
}

function queueStoreArrivalPrediction(mysqli $conn, int $ticketId, int $serviceId): array {
    $existing = $conn->prepare('SELECT predicted_wait_min, algorithm_used FROM wait_time_logs WHERE ticket_id = ? LIMIT 1');
    $existing->bind_param('i', $ticketId);
    $existing->execute();
    $existingRow = $existing->get_result()->fetch_assoc();
    if ($existingRow) {
        return [
            'predicted_wait_minutes' => (float) ($existingRow['predicted_wait_min'] ?? 0),
            'prediction_source' => str_contains(strtolower((string) ($existingRow['algorithm_used'] ?? '')), 'fallback') ? 'fallback' : 'ml',
        ];
    }

    $snapshot = queuePredictionSnapshot($conn, $serviceId, 'regular');
    if (!$snapshot) {
        throw new RuntimeException('Prediction inputs could not be created for the checked-in ticket.');
    }
    // The ticket has already entered Waiting; feature queue_length represents
    // people ahead, so exclude the ticket itself.
    $snapshot['features']['queue_length'] = max(0, (int) $snapshot['features']['queue_length'] - 1);
    $snapshot['queue_length'] = $snapshot['features']['queue_length'];
    $snapshot['priority_level'] = 0;
    $snapshot['fallback_wait_min'] = fallbackWaitEstimate(
        $snapshot['queue_length'],
        (int) $snapshot['active_windows'],
        (float) $snapshot['avg_service_time'],
        0
    );

    $prediction = requestMlPrediction($snapshot['features']);
    $predicted = $prediction !== null
        ? (float) $prediction['estimated_wait_minutes']
        : (float) $snapshot['fallback_wait_min'];
    $confidence = $prediction !== null ? (float) $prediction['confidence'] : null;
    $modelVersion = $prediction !== null ? (string) $prediction['model_version'] : 'fallback-v1';
    $algorithm = $prediction !== null ? 'Verified ML model' : 'Fallback heuristic';
    $features = $snapshot['features'];

    $hasPredictionMetadata = smartqmsTableHasColumn($conn, 'wait_time_logs', 'prediction_confidence')
        && smartqmsTableHasColumn($conn, 'wait_time_logs', 'model_version');
    $log = $conn->prepare($hasPredictionMetadata ? "
        INSERT INTO wait_time_logs
          (ticket_id, queue_length, hour_of_day, day_of_week, service_type_encoded,
           client_type_encoded, active_windows, avg_service_time, predicted_wait_min,
           prediction_confidence, model_version, algorithm_used)
        VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?)
    " : "
        INSERT INTO wait_time_logs
          (ticket_id, queue_length, hour_of_day, day_of_week, service_type_encoded,
           client_type_encoded, active_windows, avg_service_time, predicted_wait_min,
           algorithm_used)
        VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?)
    ");
    $queueLength = (int) $features['queue_length'];
    $hour = (int) $features['hour_of_day'];
    $day = (int) $features['day_of_week'];
    $serviceEncoded = (int) $features['service_type_encoded'];
    $activeWindows = (int) $features['active_windows'];
    $avgService = (float) $features['avg_service_time'];
    if ($hasPredictionMetadata) {
        $log->bind_param(
            'iiiiiidddss',
            $ticketId,
            $queueLength,
            $hour,
            $day,
            $serviceEncoded,
            $activeWindows,
            $avgService,
            $predicted,
            $confidence,
            $modelVersion,
            $algorithm
        );
    } else {
        $log->bind_param(
            'iiiiiidds',
            $ticketId,
            $queueLength,
            $hour,
            $day,
            $serviceEncoded,
            $activeWindows,
            $avgService,
            $predicted,
            $algorithm
        );
    }
    $log->execute();

    return [
        'predicted_wait_minutes' => $predicted,
        'prediction_source' => $prediction !== null ? 'ml' : 'fallback',
    ];
}

function checkInScheduledQueueTicket(
    mysqli $conn,
    int $staffId,
    int $ticketId,
    string $method
): array {
    if (!queueArrivalSchemaReady($conn)) {
        throw new RuntimeException('The arrival check-in database upgrade has not been applied.');
    }
    if (!in_array($method, ['qr', 'reference'], true)) {
        throw new InvalidArgumentException('The check-in method must be qr or reference.');
    }

    $ownsTransaction = !queueConnectionHasActiveTransaction($conn);
    if ($ownsTransaction) {
        $conn->begin_transaction();
    }
    try {
        $staffStmt = $conn->prepare('SELECT staff_id FROM staff WHERE staff_id = ? LIMIT 1 FOR UPDATE');
        $staffStmt->bind_param('i', $staffId);
        $staffStmt->execute();
        if (!$staffStmt->get_result()->fetch_assoc()) {
            throw new DomainException('The staff account could not be found.');
        }

        $ticketStmt = $conn->prepare('SELECT * FROM queue_tickets WHERE ticket_id = ? LIMIT 1 FOR UPDATE');
        $ticketStmt->bind_param('i', $ticketId);
        $ticketStmt->execute();
        $ticket = $ticketStmt->get_result()->fetch_assoc();
        if (!$ticket) {
            throw new DomainException('The scheduled ticket could not be found.');
        }

        $lifecycle = (string) $ticket['lifecycle_status'];
        if ($lifecycle === 'waiting' && !empty($ticket['checked_in_at']) && !empty($ticket['ticket_number'])) {
            $existing = staffArrivalTicketById($conn, $ticketId) ?? [];
            if ($ownsTransaction) {
                $conn->commit();
            }
            return array_merge($existing, [
                'status' => 'already_checked_in',
                'ticket_id' => $ticketId,
                'ticket_number' => (string) $ticket['ticket_number'],
                'reference_number' => (string) $ticket['reference_number'],
                'checked_in_at' => (string) $ticket['checked_in_at'],
            ]);
        }
        if ($lifecycle !== 'scheduled') {
            throw new DomainException('Only a Scheduled ticket can be checked in.');
        }
        if (empty($ticket['scheduled_expires_at']) || strtotime((string) $ticket['scheduled_expires_at']) < time()) {
            $expire = $conn->prepare("
                UPDATE queue_tickets
                SET lifecycle_status = 'void', status = 'voided', voided_at = NOW(),
                    voided_reason = 'Scheduled ticket expired before check-in', manage_token_revoked_at = NOW()
                WHERE ticket_id = ? AND lifecycle_status = 'scheduled'
            ");
            $expire->bind_param('i', $ticketId);
            $expire->execute();
            if ($ownsTransaction) {
                $conn->commit();
            }
            return ['status' => 'expired', 'ticket_id' => $ticketId];
        }
        if (date('Y-m-d', strtotime((string) $ticket['scheduled_expires_at'])) !== date('Y-m-d')) {
            throw new DomainException('This reservation is for another visit date. Check it in on the reserved date.');
        }

        $number = allocateQueueNumberForLockedTicket($conn, $ticket);
        $update = $conn->prepare("
            UPDATE queue_tickets
            SET status = 'waiting', lifecycle_status = 'waiting', checked_in_at = NOW(),
                check_in_method = ?, checked_in_by = ?, priority_level = 0,
                manage_token_revoked_at = NOW()
            WHERE ticket_id = ? AND lifecycle_status = 'scheduled'
        ");
        $update->bind_param('sii', $method, $staffId, $ticketId);
        $update->execute();
        if ($update->affected_rows !== 1) {
            throw new RuntimeException('The appointment changed before check-in could finish.');
        }

        $prediction = queueStoreArrivalPrediction($conn, $ticketId, (int) $ticket['service_id']);
        logActivity($conn, 'ticket_checked_in', 'Checked in by ' . $method, $ticketId);
        $checkedInTicket = staffArrivalTicketById($conn, $ticketId) ?? [];
        if ($ownsTransaction) {
            $conn->commit();
        }
        return array_merge($checkedInTicket, [
            'status' => 'checked_in',
            'ticket_id' => $ticketId,
            'ticket_number' => $number['ticket_number'],
            'reference_number' => (string) $ticket['reference_number'],
            'checked_in_at' => date('Y-m-d H:i:s'),
            'used_reserved_number' => (bool) $number['reserved'],
        ], $prediction);
    } catch (Throwable $error) {
        if ($ownsTransaction) {
            $conn->rollback();
        }
        throw $error;
    }
}

/**
 * Create a physically present walk-in while retaining the same allocator and
 * prediction path used by confirmed online arrivals.
 */
function createWalkInTicketForStaff(
    mysqli $conn,
    int $staffId,
    string $firstName,
    string $lastName,
    string $phoneNumber,
    int $serviceId
): array {
    if (!function_exists('createPublicQueueTicket')) {
        throw new RuntimeException('The public intake service is unavailable.');
    }

    $ownsTransaction = !queueConnectionHasActiveTransaction($conn);
    if ($ownsTransaction) {
        $conn->begin_transaction();
    }
    try {
        $staffStmt = $conn->prepare("
            SELECT s.staff_id
            FROM staff s
            JOIN users u ON u.user_id = s.user_id
            WHERE s.staff_id = ? AND u.is_active = 1
            LIMIT 1
            FOR UPDATE
        ");
        $staffStmt->bind_param('i', $staffId);
        $staffStmt->execute();
        if (!$staffStmt->get_result()->fetch_assoc()) {
            throw new DomainException('The staff account could not be found.');
        }

        $window = getStaffWindowForUpdate($conn, $staffId);
        if (!$window) {
            throw new DomainException('Claim a service counter before registering a walk-in client.');
        }
        if (!in_array($serviceId, getWindowServiceIds($conn, $window), true)) {
            throw new DomainException('Choose a health service assigned to your active counter.');
        }

        // Walk-in slips need a queue number, not a private tracker QR file.
        $created = createPublicQueueTicket(
            $conn,
            $firstName,
            $lastName,
            $phoneNumber,
            $serviceId,
            'regular',
            'walk-in',
            false
        );
        $ticketId = (int) $created['ticket_id'];
        $assignStaff = $conn->prepare('UPDATE queue_tickets SET checked_in_by = ? WHERE ticket_id = ?');
        $assignStaff->bind_param('ii', $staffId, $ticketId);
        $assignStaff->execute();
        if ($assignStaff->affected_rows !== 1) {
            throw new RuntimeException('The walk-in ticket could not be assigned to Staff.');
        }

        logActivity(
            $conn,
            'walk_in_registered',
            'Registered walk-in ticket ' . (string) $created['ticket_number'],
            $ticketId
        );
        $projection = staffArrivalTicketById($conn, $ticketId) ?? [];
        if ($ownsTransaction) {
            $conn->commit();
        }
        return array_merge($projection, [
            'status' => 'waiting',
            'lifecycle_status' => 'waiting',
            'check_in_method' => 'walk-in',
            'predicted_wait_minutes' => $created['predicted_wait_minutes'] ?? null,
        ]);
    } catch (Throwable $error) {
        if ($ownsTransaction) {
            $conn->rollback();
        }
        throw $error;
    }
}

function expireScheduledQueueTickets(mysqli $conn, ?string $now = null): int {
    $now = $now ?: date('Y-m-d H:i:s');
    $stmt = $conn->prepare("
        UPDATE queue_tickets
        SET lifecycle_status = 'void', status = 'voided', voided_at = ?,
            voided_reason = 'Scheduled ticket expired before check-in', manage_token_revoked_at = ?
        WHERE lifecycle_status = 'scheduled'
          AND scheduled_expires_at IS NOT NULL
          AND scheduled_expires_at < ?
    ");
    $stmt->bind_param('sss', $now, $now, $now);
    $stmt->execute();
    return $stmt->affected_rows;
}
