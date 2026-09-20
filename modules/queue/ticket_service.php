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
    ?float $mlPrediction = null,
    ?array $customerProfile = null,
    ?array $predictionMetadata = null
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

        if ($customerProfile !== null) {
            smartqmsUpdateCustomerBookingProfile($conn, $userId, $customerProfile);
        }

        if (!acquireTicketIssuanceLock($conn, $year, 5)) {
            throw new RuntimeException('Could not acquire the ticket issuance lock.');
        }
        $issuanceLockAcquired = true;

        $referenceNumber = generateRefNumber($conn);
        $ticketNumber = generateDailyTicketNumber($conn);

        $queueMode = normalizeQueueMode((string) ($snapshot['service']['queue_mode'] ?? 'central'));
        $insert = $conn->prepare("
            INSERT INTO queue_tickets
              (user_id, service_id, queue_mode, window_id, reference_number,
               ticket_number, client_type, priority_level)
            VALUES (?, ?, ?, NULL, ?, ?, ?, ?)
        ");
        $insert->bind_param(
            'iissssi',
            $userId,
            $serviceId,
            $queueMode,
            $referenceNumber,
            $ticketNumber,
            $clientType,
            $priorityLevel
        );
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
        $predictionConfidence = $mlPrediction !== null && is_numeric($predictionMetadata['confidence'] ?? null)
            ? (float) $predictionMetadata['confidence']
            : null;
        $modelVersion = $mlPrediction !== null
            ? trim((string) ($predictionMetadata['model_version'] ?? ''))
            : 'fallback-v1';

        $hasPredictionMetadata = smartqmsTableHasColumn($conn, 'wait_time_logs', 'prediction_confidence')
            && smartqmsTableHasColumn($conn, 'wait_time_logs', 'model_version');
        $log = $conn->prepare($hasPredictionMetadata ? "
            INSERT INTO wait_time_logs
              (ticket_id, queue_length, hour_of_day, day_of_week, service_type_encoded,
               client_type_encoded, active_windows, avg_service_time, predicted_wait_min,
               prediction_confidence, model_version, algorithm_used)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        " : "
            INSERT INTO wait_time_logs
              (ticket_id, queue_length, hour_of_day, day_of_week, service_type_encoded,
               client_type_encoded, active_windows, avg_service_time, predicted_wait_min,
               algorithm_used)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $serviceEncoded = (int) $snapshot['service']['service_encoded'];
        if ($hasPredictionMetadata) {
            $log->bind_param(
                'iiiiiiidddss',
                $ticketId,
                $queueLength,
                $hour,
                $day,
                $serviceEncoded,
                $clientEncoded,
                $activeWindows,
                $avgServiceTime,
                $predicted,
                $predictionConfidence,
                $modelVersion,
                $algorithmUsed
            );
        } else {
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
        }
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
            'prediction_confidence' => $predictionConfidence,
            'model_version' => $modelVersion,
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
