<?php
/**
 * Transactional client ticket creation.
 */

function createQueueTicket(
    mysqli $conn,
    int $userId,
    int $serviceId,
    string $clientType,
    int $priorityLevel,
    array $snapshot,
    ?float $mlPrediction = null
): array {
    $year = (int) date('Y');
    $issuanceLockAcquired = false;
    $qrPath = null;
    $qrExistedBefore = false;
    $ownsTransaction = !queueConnectionHasActiveTransaction($conn);

    if ($ownsTransaction) {
        $conn->begin_transaction();
    }

    try {
        if (!lockQueueClient($conn, $userId)) {
            throw new RuntimeException('Queue client could not be found.');
        }

        $activeTicket = getActiveTicketForLockedClient($conn, $userId);
        if ($activeTicket) {
            if ($ownsTransaction) {
                $conn->rollback();
            }
            return [
                'created' => false,
                'active_ticket' => $activeTicket,
            ];
        }

        if (!acquireTicketIssuanceLock($conn, $year, 5)) {
            throw new RuntimeException('Could not acquire the ticket issuance lock.');
        }
        $issuanceLockAcquired = true;

        $referenceNumber = generateRefNumber($conn, $year);
        $ticketNumber = generateDailyTicketNumber($conn);

        $insert = $conn->prepare("INSERT INTO queue_tickets (user_id, service_id, reference_number, ticket_number, client_type, priority_level) VALUES (?, ?, ?, ?, ?, ?)");
        $insert->bind_param('iisssi', $userId, $serviceId, $referenceNumber, $ticketNumber, $clientType, $priorityLevel);
        $insert->execute();
        $ticketId = (int) $conn->insert_id;

        $qrPath = queueQrRelativePath($referenceNumber, (string) $ticketId);
        $qrAbsolutePath = queueQrAbsolutePath($qrPath);
        $qrExistedBefore = $qrAbsolutePath !== null && is_file($qrAbsolutePath);
        $qrPath = generateQR($referenceNumber, (string) $ticketId);
        $qrUpdate = $conn->prepare("UPDATE queue_tickets SET qr_code_path = ? WHERE ticket_id = ?");
        $qrUpdate->bind_param('si', $qrPath, $ticketId);
        $qrUpdate->execute();

        $payload = $snapshot['features'];
        $hour = (int) $payload['hour_of_day'];
        $day = (int) $payload['day_of_week'];
        $clientEncoded = (int) $payload['client_type_encoded'];
        $queueLength = (int) $payload['queue_length'];
        $activeWindows = (int) $payload['active_windows'];
        $avgServiceTime = (float) $payload['avg_service_time'];
        $predicted = $mlPrediction ?? (float) $snapshot['fallback_wait_min'];
        $algorithmUsed = $mlPrediction !== null ? 'Verified ML model' : 'Fallback heuristic';

        $log = $conn->prepare("
            INSERT INTO wait_time_logs
              (ticket_id, queue_length, hour_of_day, day_of_week, service_type_encoded,
               client_type_encoded, active_windows, avg_service_time, predicted_wait_min,
               algorithm_used)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $serviceEncoded = (int) $snapshot['service']['service_encoded'];
        $log->bind_param(
            'iiiiiiidds',
            $ticketId,
            $queueLength,
            $hour,
            $day,
            $serviceEncoded,
            $clientEncoded,
            $activeWindows,
            $avgServiceTime,
            $predicted,
            $algorithmUsed
        );
        $log->execute();

        logActivity($conn, 'ticket_created', 'Client joined queue', $ticketId);
        if ($ownsTransaction) {
            $conn->commit();
        }

        return [
            'created' => true,
            'ticket_id' => $ticketId,
            'reference_number' => $referenceNumber,
            'ticket_number' => $ticketNumber,
            'qr_code_path' => $qrPath,
            'predicted_wait_minutes' => $predicted,
            'prediction_source' => $mlPrediction !== null ? 'ml' : 'fallback',
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
        if ($issuanceLockAcquired) {
            releaseTicketIssuanceLock($conn, $year);
        }
    }
}

function queueConnectionHasActiveTransaction(mysqli $conn): bool {
    $row = $conn->query('SELECT @@in_transaction AS in_transaction')->fetch_assoc();
    return (int) ($row['in_transaction'] ?? 0) === 1;
}
