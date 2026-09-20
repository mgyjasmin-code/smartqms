<?php
/**
 * Account-free online preregistration and token-scoped tracking operations.
 */

require_once __DIR__ . '/ticket_service.php';
require_once __DIR__ . '/qr_generate.php';

function smartqmsPublicQueueSchemaReady(mysqli $conn): bool {
    return smartqmsTableHasColumn($conn, 'queue_tickets', 'ticket_token')
        && smartqmsTableHasColumn($conn, 'queue_tickets', 'lifecycle_status')
        && smartqmsTableHasColumn($conn, 'queue_tickets', 'entry_type')
        && smartqmsTableHasColumn($conn, 'queue_tickets', 'checked_in_at')
        && smartqmsTableHasColumn($conn, 'queue_tickets', 'scheduled_expires_at')
        && smartqmsTableHasColumn($conn, 'queue_tickets', 'manage_token_hash')
        && smartqmsTableHasColumn($conn, 'queue_tickets', 'manage_token_expires_at');
}

function normalizeQueueEntryType(string $entryType): string {
    return $entryType === 'walk-in' ? 'walk-in' : 'online';
}

function isValidPublicTicketToken(string $token): bool {
    // New public links use the blueprint's 256-bit token. Accept the earlier
    // 128-bit links so tickets issued before this compatibility update remain
    // trackable and can still submit their single feedback response.
    return preg_match('/^(?:[a-f0-9]{32}|[a-f0-9]{64})$/', $token) === 1;
}

function publicQueueServices(mysqli $conn): array {
    $visibility = smartqmsTableHasColumn($conn, 'health_services', 'is_hidden')
        ? 'AND is_hidden = 0'
        : '';
    $durationProjection = smartqmsTableHasColumn($conn, 'health_services', 'fallback_duration_mins')
        ? 'COALESCE(NULLIF(fallback_duration_mins, 0), 15) AS fallback_duration_mins'
        : '15 AS fallback_duration_mins';
    $result = $conn->query("
        SELECT service_id, service_code, service_name, description, priority_only,
               {$durationProjection}
        FROM health_services
        WHERE is_active = 1 {$visibility}
        ORDER BY display_order, service_name
    ");
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function publicQueueServiceForUpdate(mysqli $conn, int $serviceId): ?array {
    $visibility = smartqmsTableHasColumn($conn, 'health_services', 'is_hidden')
        ? 'AND is_hidden = 0'
        : '';
    $stmt = $conn->prepare("
        SELECT *
        FROM health_services
        WHERE service_id = ? AND is_active = 1 {$visibility}
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->bind_param('i', $serviceId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function publicTicketPrefix(array $service): string {
    $source = strtoupper((string) ($service['service_name'] ?? $service['service_code'] ?? 'Q'));
    if (preg_match('/[A-Z0-9]/', $source, $match)) {
        return $match[0];
    }
    return 'Q';
}

function generatePublicServiceTicketNumber(mysqli $conn, array $service): string {
    $serviceId = (int) $service['service_id'];
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM queue_tickets
        WHERE service_id = ? AND DATE(issued_at) = CURDATE()
        FOR UPDATE
    ");
    $stmt->bind_param('i', $serviceId);
    $stmt->execute();
    $count = (int) ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    return publicTicketPrefix($service) . '-' . str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
}

function createPublicQueueTicket(
    mysqli $conn,
    string $firstName,
    string $lastName,
    string $phoneNumber,
    int $serviceId,
    string $classification = 'regular',
    string $entryType = 'online',
    bool $generateQrCode = true,
    ?string $visitDate = null
): array {
    if (!smartqmsPublicQueueSchemaReady($conn)) {
        throw new RuntimeException('The public queue upgrade has not been applied yet.');
    }

    $year = (int) date('Y');
    $lockAcquired = false;
    $qrPath = null;
    $qrExistedBefore = false;
    $ownsTransaction = !queueConnectionHasActiveTransaction($conn);
    if ($ownsTransaction) {
        $conn->begin_transaction();
    }

    try {
        $service = publicQueueServiceForUpdate($conn, $serviceId);
        if (!$service) {
            throw new DomainException('The selected health service is not available.');
        }

        // Classification is retained in the database for historical
        // compatibility only. New arrivals always participate in strict FIFO.
        $classification = 'regular';

        if (!acquireTicketIssuanceLock($conn, $year, 5)) {
            throw new RuntimeException('Could not acquire the ticket issuance lock.');
        }
        $lockAcquired = true;

        $entryType = normalizeQueueEntryType($entryType);
        $lifecycleStatus = $entryType === 'walk-in' ? 'waiting' : 'scheduled';
        $clientName = trim($firstName . ' ' . $lastName);
        $token = bin2hex(random_bytes(32));
        $managementToken = $entryType === 'online' ? bin2hex(random_bytes(32)) : null;
        $managementTokenHash = $managementToken !== null ? hash('sha256', $managementToken) : null;
        $managementTokenIssuedAt = $managementToken !== null ? date('Y-m-d H:i:s') : null;
        $referenceNumber = generateRefNumber($conn);
        $queueMode = normalizeQueueMode((string) ($service['queue_mode'] ?? 'central'));
        $scheduledDate = date('Y-m-d');
        if ($entryType === 'online' && $visitDate !== null) {
            $visitDate = trim($visitDate);
            $parsedVisitDate = DateTimeImmutable::createFromFormat('!Y-m-d', $visitDate);
            $today = new DateTimeImmutable('today');
            $latestVisitDate = $today->modify('+30 days');
            if (!$parsedVisitDate || $parsedVisitDate->format('Y-m-d') !== $visitDate
                || $parsedVisitDate < $today || $parsedVisitDate > $latestVisitDate) {
                throw new DomainException('Choose a visit date within the next 30 days.');
            }
            $scheduledDate = $visitDate;
        }
        $scheduledExpiresAt = $scheduledDate . ' 23:59:59';
        if ($entryType === 'online') {
            $insert = $conn->prepare("
                INSERT INTO queue_tickets
                  (user_id, ticket_token, manage_token_hash, manage_token_issued_at, manage_token_expires_at,
                   client_first_name, client_last_name, client_name,
                   phone_number, service_id, queue_mode, window_id, reference_number,
                   ticket_number, entry_type, client_type, priority_level, status,
                   lifecycle_status, scheduled_expires_at)
                VALUES
                  (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, NULL, ?, 'regular', 0,
                   'waiting', 'scheduled', ?)
            ");
            $insert->bind_param(
                'ssssssssissss',
                $token,
                $managementTokenHash,
                $managementTokenIssuedAt,
                $scheduledExpiresAt,
                $firstName,
                $lastName,
                $clientName,
                $phoneNumber,
                $serviceId,
                $queueMode,
                $referenceNumber,
                $entryType,
                $scheduledExpiresAt
            );
        } else {
            $insert = $conn->prepare("
                INSERT INTO queue_tickets
                  (user_id, ticket_token, client_first_name, client_last_name, client_name,
                   phone_number, service_id, queue_mode, window_id, reference_number,
                   ticket_number, entry_type, client_type, priority_level, status,
                   lifecycle_status, checked_in_at, check_in_method)
                VALUES
                  (NULL, ?, ?, ?, ?, ?, ?, ?, NULL, ?, NULL, ?, 'regular', 0,
                   'waiting', 'waiting', NOW(), 'walk-in')
            ");
            $insert->bind_param(
                'sssssisss',
                $token,
                $firstName,
                $lastName,
                $clientName,
                $phoneNumber,
                $serviceId,
                $queueMode,
                $referenceNumber,
                $entryType
            );
        }
        $insert->execute();
        $ticketId = (int) $conn->insert_id;

        $ticketNumber = null;
        $prediction = null;
        if ($entryType === 'walk-in') {
            $number = allocateQueueNumberForLockedTicket($conn, [
                'ticket_id' => $ticketId,
                'ticket_number' => null,
                'service_id' => $serviceId,
                'issued_at' => date('Y-m-d H:i:s'),
            ]);
            $ticketNumber = $number['ticket_number'];
            $prediction = queueStoreArrivalPrediction($conn, $ticketId, $serviceId);
        }

        if ($generateQrCode) {
            $qrPath = queueQrRelativePath($referenceNumber, (string) $ticketId);
            $qrAbsolutePath = queueQrAbsolutePath($qrPath);
            $qrExistedBefore = $qrAbsolutePath !== null && is_file($qrAbsolutePath);
            $qrPath = generateTokenQR($token, $referenceNumber, (string) $ticketId);
            $qrUpdate = $conn->prepare('UPDATE queue_tickets SET qr_code_path = ? WHERE ticket_id = ?');
            $qrUpdate->bind_param('si', $qrPath, $ticketId);
            $qrUpdate->execute();
        }

        if ($ownsTransaction) {
            $conn->commit();
        }
        return [
            'ticket_id' => $ticketId,
            'ticket_token' => $token,
            'management_token' => $managementToken,
            'reference_number' => $referenceNumber,
            'ticket_number' => $ticketNumber,
            'qr_code_path' => $qrPath,
            'lifecycle_status' => $lifecycleStatus,
            'scheduled_expires_at' => $entryType === 'online' ? $scheduledExpiresAt : null,
            'visit_date' => $entryType === 'online' ? $scheduledDate : null,
            'predicted_wait_minutes' => $prediction['predicted_wait_minutes'] ?? null,
        ];
    } catch (Throwable $error) {
        if ($ownsTransaction) {
            $conn->rollback();
        }
        if ($qrPath !== null && !$qrExistedBefore) {
            removeGeneratedQueueQr($qrPath);
        }
        throw $error;
    } finally {
        if ($lockAcquired) {
            releaseTicketIssuanceLock($conn, $year);
        }
    }
}

function publicQueueTicketByToken(mysqli $conn, string $token, bool $forUpdate = false): ?array {
    if (!smartqmsPublicQueueSchemaReady($conn) || !isValidPublicTicketToken($token)) {
        return null;
    }
    $sql = "
        SELECT qt.*, hs.service_name, hs.service_code, sw.window_name,
               COALESCE(NULLIF(qt.client_name, ''), CONCAT_WS(' ', u.first_name, u.last_name)) AS display_client_name,
               wl.predicted_wait_min,
               EXISTS(SELECT 1 FROM feedback f WHERE f.ticket_id = qt.ticket_id) AS has_feedback
        FROM queue_tickets qt
        JOIN health_services hs ON hs.service_id = qt.service_id
        LEFT JOIN service_windows sw ON sw.window_id = qt.window_id
        LEFT JOIN users u ON u.user_id = qt.user_id
        LEFT JOIN wait_time_logs wl ON wl.ticket_id = qt.ticket_id
        WHERE qt.ticket_token = ?
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $token);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function publicQueueTicketByReference(mysqli $conn, string $reference): ?array {
    $reference = strtoupper(trim($reference));
    if (!smartqmsPublicQueueSchemaReady($conn)
        || preg_match('/^(?:\d{16}|\d{10}|' . preg_quote(REF_PREFIX, '/') . '-\d{4}-\d{4,})$/', $reference) !== 1) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT qt.reference_number, qt.lifecycle_status, hs.service_name
        FROM queue_tickets qt
        JOIN health_services hs ON hs.service_id = qt.service_id
        WHERE qt.reference_number = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $reference);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function publicReferenceStatusProjection(array $ticket): array {
    $lifecycle = (string) ($ticket['lifecycle_status'] ?? 'scheduled');
    $labels = [
        'scheduled' => 'Scheduled',
        'waiting' => 'Waiting in Line',
        'calling' => 'Your Turn!',
        'in-progress' => 'Being Served',
        'completed' => 'Service Completed',
        'void' => 'Ticket Expired / Void',
    ];
    return [
        'reference_number' => (string) $ticket['reference_number'],
        'service_name' => (string) $ticket['service_name'],
        'status' => $lifecycle,
        'status_label' => $labels[$lifecycle] ?? 'Status unavailable',
    ];
}

function publicQueueTicketProjection(mysqli $conn, array $ticket): array {
    $lifecycle = (string) ($ticket['lifecycle_status'] ?? 'waiting');
    $ahead = $lifecycle === 'waiting' ? peopleAhead($conn, $ticket) : 0;
    return [
        'ticket_number' => $ticket['ticket_number'] !== null ? (string) $ticket['ticket_number'] : '',
        'reference_number' => (string) $ticket['reference_number'],
        'client_name' => (string) ($ticket['display_client_name'] ?? 'Queue client'),
        'classification' => (string) ($ticket['client_type'] ?? 'regular'),
        'entry_type' => (string) ($ticket['entry_type'] ?? 'online'),
        'status' => $lifecycle,
        'service_name' => (string) $ticket['service_name'],
        'counter_label' => (string) ($ticket['window_name'] ?? ''),
        'people_ahead' => $ahead,
        'predicted_wait_minutes' => round((float) ($ticket['predicted_wait_min'] ?? 0), 1),
        'issued_at' => (string) $ticket['issued_at'],
        'scheduled_expires_at' => $ticket['scheduled_expires_at'] ?: null,
        'visit_date' => !empty($ticket['scheduled_expires_at'])
            ? date('Y-m-d', strtotime((string) $ticket['scheduled_expires_at']))
            : date('Y-m-d', strtotime((string) $ticket['issued_at'])),
        'has_feedback' => (bool) ($ticket['has_feedback'] ?? false),
    ];
}
