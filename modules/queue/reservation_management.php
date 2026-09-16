<?php
/**
 * Privacy-safe public reservation verification and management.
 */

require_once __DIR__ . '/ticket_service.php';

function isValidReservationManagementToken(string $token): bool {
    return preg_match('/^[a-f0-9]{64}$/', $token) === 1;
}

function publicManagedReservationByToken(mysqli $conn, string $token): ?array {
    $token = strtolower(trim($token));
    if (!isValidReservationManagementToken($token)) {
        return null;
    }
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare("
        SELECT qt.ticket_id, qt.reference_number, qt.lifecycle_status,
               qt.scheduled_expires_at, qt.manage_token_expires_at,
               qt.manage_token_revoked_at, hs.service_name
        FROM queue_tickets qt
        JOIN health_services hs ON hs.service_id = qt.service_id
        WHERE qt.manage_token_hash = ?
          AND qt.lifecycle_status = 'scheduled'
          AND qt.manage_token_revoked_at IS NULL
          AND qt.manage_token_expires_at >= NOW()
        LIMIT 1
    ");
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function publicManagedReservationBySession(mysqli $conn, int $ticketId, bool $forUpdate = false): ?array {
    if ($ticketId < 1) {
        return null;
    }
    $sql = "
        SELECT qt.ticket_id, qt.reference_number, qt.lifecycle_status,
               qt.scheduled_expires_at, qt.manage_token_expires_at,
               qt.manage_token_revoked_at, hs.service_name
        FROM queue_tickets qt
        JOIN health_services hs ON hs.service_id = qt.service_id
        WHERE qt.ticket_id = ?
          AND qt.manage_token_hash IS NOT NULL
          AND qt.manage_token_revoked_at IS NULL
          AND qt.manage_token_expires_at >= NOW()
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function reservationManagementProjection(array $reservation): array {
    $lifecycle = (string) ($reservation['lifecycle_status'] ?? 'scheduled');
    return [
        'reference_number' => (string) ($reservation['reference_number'] ?? ''),
        'service_name' => (string) ($reservation['service_name'] ?? ''),
        'visit_date' => !empty($reservation['scheduled_expires_at'])
            ? date('Y-m-d', strtotime((string) $reservation['scheduled_expires_at']))
            : '',
        'status' => $lifecycle,
        'can_manage' => $lifecycle === 'scheduled',
    ];
}

function validateReservationVisitDate(string $visitDate): string {
    $visitDate = trim($visitDate);
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $visitDate);
    $today = new DateTimeImmutable('today');
    if (!$parsed || $parsed->format('Y-m-d') !== $visitDate
        || $parsed < $today || $parsed > $today->modify('+30 days')) {
        throw new DomainException('Choose a visit date within the next 30 days.');
    }
    return $visitDate;
}

function rescheduleSessionReservation(mysqli $conn, int $ticketId, string $visitDate): array {
    $visitDate = validateReservationVisitDate($visitDate);
    $ownsTransaction = !queueConnectionHasActiveTransaction($conn);
    if ($ownsTransaction) {
        $conn->begin_transaction();
    }
    try {
        $reservation = publicManagedReservationBySession($conn, $ticketId, true);
        if (!$reservation || (string) $reservation['lifecycle_status'] !== 'scheduled') {
            throw new DomainException('This reservation can no longer be changed.');
        }
        $expiresAt = $visitDate . ' 23:59:59';
        $stmt = $conn->prepare("
            UPDATE queue_tickets
            SET scheduled_expires_at = ?, manage_token_expires_at = ?
            WHERE ticket_id = ? AND lifecycle_status = 'scheduled'
        ");
        $stmt->bind_param('ssi', $expiresAt, $expiresAt, $ticketId);
        $stmt->execute();
        if ($stmt->affected_rows < 0) {
            throw new RuntimeException('The reservation could not be rescheduled.');
        }
        logActivity($conn, 'reservation_rescheduled', 'Visit date rescheduled through token-authorized public management.', $ticketId);
        if ($ownsTransaction) {
            $conn->commit();
        }
        $reservation['scheduled_expires_at'] = $expiresAt;
        return reservationManagementProjection($reservation);
    } catch (Throwable $error) {
        if ($ownsTransaction) {
            $conn->rollback();
        }
        throw $error;
    }
}

function cancelSessionReservation(mysqli $conn, int $ticketId): array {
    $ownsTransaction = !queueConnectionHasActiveTransaction($conn);
    if ($ownsTransaction) {
        $conn->begin_transaction();
    }
    try {
        $reservation = publicManagedReservationBySession($conn, $ticketId, true);
        if (!$reservation || (string) $reservation['lifecycle_status'] !== 'scheduled') {
            throw new DomainException('This reservation can no longer be changed.');
        }
        $reason = 'Cancelled by client through token-authorized reservation management';
        $stmt = $conn->prepare("
            UPDATE queue_tickets
            SET lifecycle_status = 'void', status = 'voided', voided_at = NOW(),
                voided_reason = ?, manage_token_revoked_at = NOW()
            WHERE ticket_id = ? AND lifecycle_status = 'scheduled'
        ");
        $stmt->bind_param('si', $reason, $ticketId);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            throw new RuntimeException('The reservation changed before cancellation could finish.');
        }
        logActivity($conn, 'reservation_cancelled', $reason, $ticketId);
        if ($ownsTransaction) {
            $conn->commit();
        }
        $reservation['lifecycle_status'] = 'void';
        return reservationManagementProjection($reservation);
    } catch (Throwable $error) {
        if ($ownsTransaction) {
            $conn->rollback();
        }
        throw $error;
    }
}
