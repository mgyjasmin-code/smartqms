<?php
/**
 * Transactional feedback operations shared by the Client page and endpoint.
 */

function feedbackTransactionState(mysqli $conn): bool {
    $result = $conn->query('SELECT @@session.in_transaction AS transaction_active');
    return (int) ($result->fetch_assoc()['transaction_active'] ?? 0) === 1;
}

function finishFeedbackTransaction(mysqli $conn, bool $startedTransaction, string $savepoint): void {
    if ($startedTransaction) {
        $conn->commit();
        return;
    }

    $conn->query('RELEASE SAVEPOINT ' . $savepoint);
}

function rollbackFeedbackTransaction(mysqli $conn, bool $startedTransaction, string $savepoint): void {
    if ($startedTransaction) {
        $conn->rollback();
        return;
    }

    $conn->query('ROLLBACK TO SAVEPOINT ' . $savepoint);
    $conn->query('RELEASE SAVEPOINT ' . $savepoint);
}

function completedTicketAvailableForFeedback(mysqli $conn, int $userId, int $ticketId): ?array {
    $statement = $conn->prepare("
        SELECT qt.ticket_id, qt.window_id, qt.service_id, qt.reference_number, qt.ticket_number
        FROM queue_tickets qt
        WHERE qt.ticket_id = ?
          AND qt.user_id = ?
          AND qt.status = 'completed'
          AND NOT EXISTS (
              SELECT 1
              FROM feedback f
              WHERE f.ticket_id = qt.ticket_id
          )
        LIMIT 1
    ");
    $statement->bind_param('ii', $ticketId, $userId);
    $statement->execute();
    return $statement->get_result()->fetch_assoc() ?: null;
}

/**
 * @return array{status: string, feedback_id?: int}
 */
function submitFeedbackForClient(
    mysqli $conn,
    int $userId,
    int $ticketId,
    int $rating,
    string $comment
): array {
    $startedTransaction = !feedbackTransactionState($conn);
    $savepoint = 'smartqms_feedback_submission';

    if ($startedTransaction) {
        $conn->begin_transaction();
    } else {
        $conn->query('SAVEPOINT ' . $savepoint);
    }

    try {
        $ticketStatement = $conn->prepare("
            SELECT ticket_id, window_id, service_id
            FROM queue_tickets
            WHERE ticket_id = ?
              AND user_id = ?
              AND status = 'completed'
            LIMIT 1
            FOR UPDATE
        ");
        $ticketStatement->bind_param('ii', $ticketId, $userId);
        $ticketStatement->execute();
        $ticket = $ticketStatement->get_result()->fetch_assoc();

        if (!$ticket) {
            finishFeedbackTransaction($conn, $startedTransaction, $savepoint);
            return ['status' => 'ticket_not_found'];
        }

        $existingStatement = $conn->prepare('SELECT feedback_id FROM feedback WHERE ticket_id = ? LIMIT 1');
        $existingStatement->bind_param('i', $ticketId);
        $existingStatement->execute();
        if ($existingStatement->get_result()->fetch_assoc()) {
            finishFeedbackTransaction($conn, $startedTransaction, $savepoint);
            return ['status' => 'already_submitted'];
        }

        $insertStatement = $conn->prepare("
            INSERT INTO feedback (ticket_id, user_id, window_id, service_id, rating, comment)
            VALUES (?, ?, ?, ?, ?, NULLIF(?, ''))
        ");
        $windowId = $ticket['window_id'] !== null ? (int) $ticket['window_id'] : null;
        $serviceId = $ticket['service_id'] !== null ? (int) $ticket['service_id'] : null;
        $insertStatement->bind_param('iiiiis', $ticketId, $userId, $windowId, $serviceId, $rating, $comment);
        $insertStatement->execute();
        $feedbackId = (int) $conn->insert_id;

        logActivity(
            $conn,
            'feedback_submitted',
            'Rating: ' . $rating,
            $ticketId,
            $userId,
            ROLE_CLIENT
        );

        finishFeedbackTransaction($conn, $startedTransaction, $savepoint);
        return ['status' => 'submitted', 'feedback_id' => $feedbackId];
    } catch (mysqli_sql_exception $exception) {
        rollbackFeedbackTransaction($conn, $startedTransaction, $savepoint);
        if ((int) $exception->getCode() === 1062) {
            return ['status' => 'already_submitted'];
        }
        throw $exception;
    } catch (Throwable $exception) {
        rollbackFeedbackTransaction($conn, $startedTransaction, $savepoint);
        throw $exception;
    }
}
