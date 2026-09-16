<?php
/**
 * Shared database-state helpers used by transactional queue services.
 */

function queueConnectionHasActiveTransaction(mysqli $conn): bool {
    $row = $conn->query('SELECT @@in_transaction AS in_transaction')->fetch_assoc();
    return (int) ($row['in_transaction'] ?? 0) === 1;
}
