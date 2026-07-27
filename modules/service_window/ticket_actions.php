<?php
/**
 * Transactional staff queue-ticket state transitions.
 */

function serviceWindowConnectionHasActiveTransaction(mysqli $conn): bool {
    $row = $conn->query('SELECT @@in_transaction AS in_transaction')->fetch_assoc();
    return (int) ($row['in_transaction'] ?? 0) === 1;
}

function beginServiceWindowTransaction(mysqli $conn): bool {
    $ownsTransaction = !serviceWindowConnectionHasActiveTransaction($conn);
    if ($ownsTransaction) {
        $conn->begin_transaction();
    }
    return $ownsTransaction;
}

function finishServiceWindowTransaction(mysqli $conn, bool $ownsTransaction, bool $commit): void {
    if (!$ownsTransaction) {
        return;
    }
    if ($commit) {
        $conn->commit();
    } else {
        $conn->rollback();
    }
}

function callNextTicketForStaff(mysqli $conn, int $staffId): array {
    $ownsTransaction = beginServiceWindowTransaction($conn);
    try {
        $window = getStaffWindowForUpdate($conn, $staffId);
        if (!$window) {
            finishServiceWindowTransaction($conn, $ownsTransaction, false);
            return ['status' => 'no_window'];
        }
        if (($window['status'] ?? 'closed') === 'closed') {
            finishServiceWindowTransaction($conn, $ownsTransaction, false);
            return ['status' => 'closed_window'];
        }

        $serviceId = (int) ($window['service_id'] ?? 0);
        if ($serviceId < 1) {
            finishServiceWindowTransaction($conn, $ownsTransaction, false);
            return ['status' => 'unassigned_service'];
        }

        $windowId = (int) $window['window_id'];
        $serving = getCurrentServingTicketForWindow($conn, $windowId);
        if ($serving) {
            finishServiceWindowTransaction($conn, $ownsTransaction, false);
            return ['status' => 'already_serving', 'ticket' => $serving];
        }

        $ticket = getNextWaitingTicketForUpdate($conn, $serviceId);
        if (!$ticket) {
            finishServiceWindowTransaction($conn, $ownsTransaction, false);
            return ['status' => 'empty_queue', 'service_id' => $serviceId];
        }

        $ticketId = (int) $ticket['ticket_id'];
        $update = $conn->prepare("
            UPDATE queue_tickets
            SET status = 'serving', called_at = NOW(), served_at = NOW(), window_id = ?
            WHERE ticket_id = ? AND status = 'waiting'
        ");
        $update->bind_param('ii', $windowId, $ticketId);
        $update->execute();
        if ($update->affected_rows !== 1) {
            throw new RuntimeException('Waiting ticket changed before it could be called.');
        }
        updateServiceWindowStatus($conn, $windowId, 'busy');
        logActivity($conn, 'ticket_called', 'Called ticket ' . $ticket['ticket_number'], $ticketId);
        finishServiceWindowTransaction($conn, $ownsTransaction, true);

        $ticket['status'] = 'serving';
        $ticket['window_id'] = $windowId;
        return [
            'status' => 'success',
            'ticket' => $ticket,
            'service_id' => $serviceId,
            'window_id' => $windowId,
        ];
    } catch (Throwable $error) {
        finishServiceWindowTransaction($conn, $ownsTransaction, false);
        throw $error;
    }
}

function skipTicketForStaff(mysqli $conn, int $staffId, int $ticketId): array {
    $ownsTransaction = beginServiceWindowTransaction($conn);
    try {
        $window = getStaffWindowForUpdate($conn, $staffId);
        if (!$window) {
            finishServiceWindowTransaction($conn, $ownsTransaction, false);
            return ['status' => 'no_window'];
        }
        $windowId = (int) $window['window_id'];
        $ticket = getServingTicketForWindowForUpdate($conn, $ticketId, $windowId);
        if (!$ticket) {
            finishServiceWindowTransaction($conn, $ownsTransaction, false);
            return ['status' => 'ticket_not_found'];
        }

        $reason = 'Client did not appear';
        $update = $conn->prepare("
            UPDATE queue_tickets
            SET status = 'skipped', voided_at = NOW(), voided_reason = ?
            WHERE ticket_id = ? AND status = 'serving'
        ");
        $update->bind_param('si', $reason, $ticketId);
        $update->execute();
        if ($update->affected_rows !== 1) {
            finishServiceWindowTransaction($conn, $ownsTransaction, false);
            return ['status' => 'ticket_not_found'];
        }
        updateServiceWindowStatus($conn, $windowId, 'open');
        logActivity($conn, 'ticket_skipped', 'Skipped ticket ' . $ticket['ticket_number'], $ticketId);
        finishServiceWindowTransaction($conn, $ownsTransaction, true);
        return [
            'status' => 'success',
            'ticket_id' => $ticketId,
            'service_id' => (int) $ticket['service_id'],
            'window_id' => $windowId,
        ];
    } catch (Throwable $error) {
        finishServiceWindowTransaction($conn, $ownsTransaction, false);
        throw $error;
    }
}

function voidTicketForStaff(mysqli $conn, int $staffId, int $ticketId): array {
    $ownsTransaction = beginServiceWindowTransaction($conn);
    try {
        $window = getStaffWindowForUpdate($conn, $staffId);
        if (!$window) {
            finishServiceWindowTransaction($conn, $ownsTransaction, false);
            return ['status' => 'no_window'];
        }

        $windowId = (int) $window['window_id'];
        $ticket = getServingTicketForWindowForUpdate($conn, $ticketId, $windowId);
        if (!$ticket) {
            finishServiceWindowTransaction($conn, $ownsTransaction, false);
            return ['status' => 'ticket_not_found'];
        }

        $reason = 'Voided manually by staff';
        $update = $conn->prepare("
            UPDATE queue_tickets
            SET status = 'voided', voided_at = NOW(), voided_reason = ?
            WHERE ticket_id = ? AND status = 'serving' AND window_id = ?
        ");
        $update->bind_param('sii', $reason, $ticketId, $windowId);
        $update->execute();
        if ($update->affected_rows !== 1) {
            finishServiceWindowTransaction($conn, $ownsTransaction, false);
            return ['status' => 'ticket_not_found'];
        }

        updateServiceWindowStatus($conn, $windowId, 'open');

        $message = 'Your SmartQMS ticket ' . $ticket['ticket_number']
            . ' was voided by staff. Please contact the service window if you need assistance.';
        $type = 'turn_void';
        $channel = 'browser';
        $pending = 'pending';
        $unread = 0;
        $userId = (int) $ticket['user_id'];
        $notification = $conn->prepare("
            INSERT INTO notifications
              (ticket_id, user_id, message, type, channel, delivery_status, is_read)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $notification->bind_param('iissssi', $ticketId, $userId, $message, $type, $channel, $pending, $unread);
        $notification->execute();

        logActivity(
            $conn,
            'ticket_voided',
            'Voided ticket ' . $ticket['ticket_number'] . ' manually',
            $ticketId
        );

        finishServiceWindowTransaction($conn, $ownsTransaction, true);
        return [
            'status' => 'success',
            'ticket_id' => $ticketId,
            'ticket_number' => (string) $ticket['ticket_number'],
            'service_id' => (int) $ticket['service_id'],
            'window_id' => $windowId,
        ];
    } catch (Throwable $error) {
        finishServiceWindowTransaction($conn, $ownsTransaction, false);
        throw $error;
    }
}

function completeTicketForStaff(mysqli $conn, int $staffId, int $ticketId): array {
    $ownsTransaction = beginServiceWindowTransaction($conn);
    try {
        $window = getStaffWindowForUpdate($conn, $staffId);
        if (!$window) {
            finishServiceWindowTransaction($conn, $ownsTransaction, false);
            return ['status' => 'no_window'];
        }
        $windowId = (int) $window['window_id'];
        $ticket = getServingTicketForWindowForUpdate($conn, $ticketId, $windowId);
        if (!$ticket) {
            finishServiceWindowTransaction($conn, $ownsTransaction, false);
            return ['status' => 'ticket_not_found'];
        }

        $update = $conn->prepare("
            UPDATE queue_tickets
            SET status = 'completed', completed_at = NOW()
            WHERE ticket_id = ? AND status = 'serving'
        ");
        $update->bind_param('i', $ticketId);
        $update->execute();
        if ($update->affected_rows !== 1) {
            finishServiceWindowTransaction($conn, $ownsTransaction, false);
            return ['status' => 'ticket_not_found'];
        }

        $calc = $conn->prepare("
            SELECT ROUND(
                       TIMESTAMPDIFF(
                           SECOND,
                           issued_at,
                           COALESCE(served_at, called_at, completed_at)
                       ) / 60,
                       2
                   ) AS wait_min,
                   GREATEST(
                       0,
                       TIMESTAMPDIFF(
                           SECOND,
                           COALESCE(served_at, called_at, completed_at),
                           completed_at
                       )
                   ) AS service_sec
            FROM queue_tickets
            WHERE ticket_id = ?
        ");
        $calc->bind_param('i', $ticketId);
        $calc->execute();
        $metrics = $calc->get_result()->fetch_assoc();
        $waitMin = (float) ($metrics['wait_min'] ?? 0);
        $serviceSec = (int) ($metrics['service_sec'] ?? 0);

        $log = $conn->prepare("UPDATE wait_time_logs SET actual_wait_min=?, actual_service_dur=?, staff_id=? WHERE ticket_id=?");
        $log->bind_param('diii', $waitMin, $serviceSec, $staffId, $ticketId);
        $log->execute();

        updateServiceWindowStatus($conn, $windowId, 'open');

        $message = 'Your service is complete. Please submit feedback when convenient.';
        $type = 'feedback_prompt';
        $channel = 'browser';
        $pending = 'pending';
        $unread = 0;
        $notif = $conn->prepare("INSERT INTO notifications (ticket_id, user_id, message, type, channel, delivery_status, is_read) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $notif->bind_param('iissssi', $ticketId, $ticket['user_id'], $message, $type, $channel, $pending, $unread);
        $notif->execute();

        logActivity($conn, 'ticket_completed', 'Completed ticket ' . $ticket['ticket_number'], $ticketId);
        finishServiceWindowTransaction($conn, $ownsTransaction, true);

        return [
            'status' => 'success',
            'ticket_id' => $ticketId,
            'actual_wait_min' => $waitMin,
            'actual_service_dur' => $serviceSec,
            'service_id' => (int) $ticket['service_id'],
            'window_id' => $windowId,
        ];
    } catch (Throwable $error) {
        finishServiceWindowTransaction($conn, $ownsTransaction, false);
        throw $error;
    }
}

function voidExpiredTicketsForStaff(
    mysqli $conn,
    int $staffId,
    int $timeoutMinutes
): array {
    $ownsTransaction = beginServiceWindowTransaction($conn);
    try {
        $window = getStaffWindowForUpdate($conn, $staffId);
        if (!$window) {
            finishServiceWindowTransaction($conn, $ownsTransaction, false);
            return ['status' => 'no_window', 'voided' => 0];
        }

        $windowId = (int) $window['window_id'];
        $tickets = getExpiredServingTicketsForUpdate(
            $conn,
            $windowId,
            max(1, $timeoutMinutes)
        );
        $count = 0;
        foreach ($tickets as $ticket) {
            $ticketId = (int) $ticket['ticket_id'];
            $reason = 'Client did not appear within timeout';
            $update = $conn->prepare("
                UPDATE queue_tickets
                SET status = 'voided', voided_at = NOW(), voided_reason = ?
                WHERE ticket_id = ? AND status = 'serving'
            ");
            $update->bind_param('si', $reason, $ticketId);
            $update->execute();
            if ($update->affected_rows !== 1) {
                continue;
            }

            $message = 'Your ticket was voided because you did not appear when called.';
            $type = 'turn_void';
            $channel = 'browser';
            $pending = 'pending';
            $unread = 0;
            $notif = $conn->prepare("
                INSERT INTO notifications
                  (ticket_id, user_id, message, type, channel, delivery_status, is_read)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $userId = (int) $ticket['user_id'];
            $notif->bind_param('iissssi', $ticketId, $userId, $message, $type, $channel, $pending, $unread);
            $notif->execute();
            logActivity($conn, 'ticket_voided', $reason, $ticketId, $userId, ROLE_CLIENT);
            $count++;
        }

        if ($count > 0) {
            updateServiceWindowStatus($conn, $windowId, 'open');
        }
        finishServiceWindowTransaction($conn, $ownsTransaction, true);
        return [
            'status' => 'success',
            'voided' => $count,
            'window_id' => $windowId,
            'service_id' => (int) ($window['service_id'] ?? 0),
        ];
    } catch (Throwable $error) {
        finishServiceWindowTransaction($conn, $ownsTransaction, false);
        throw $error;
    }
}

function setServiceWindowStatusForStaff(
    mysqli $conn,
    int $staffId,
    string $status
): array {
    $ownsTransaction = beginServiceWindowTransaction($conn);
    try {
        $window = getStaffWindowForUpdate($conn, $staffId);
        if (!$window) {
            finishServiceWindowTransaction($conn, $ownsTransaction, false);
            return ['status' => 'no_window'];
        }

        $windowId = (int) $window['window_id'];
        updateServiceWindowStatus($conn, $windowId, $status);
        logActivity(
            $conn,
            $status === 'closed' ? 'window_closed' : 'window_opened',
            'Window set to ' . $status
        );
        finishServiceWindowTransaction($conn, $ownsTransaction, true);
        return [
            'status' => 'success',
            'window_id' => $windowId,
            'window_status' => $status,
        ];
    } catch (Throwable $error) {
        finishServiceWindowTransaction($conn, $ownsTransaction, false);
        throw $error;
    }
}
