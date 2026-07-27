<?php
/**
 * Read models used by the live queue-status endpoint.
 */

function hasValidDisplayStatusToken(mysqli $conn): bool {
    $token = trim((string) ($_GET['token'] ?? ''));
    if ($token === '') {
        return false;
    }

    $stmt = $conn->prepare("SELECT setting_val FROM system_settings WHERE setting_key = 'display_board_token' LIMIT 1");
    $stmt->execute();
    $savedToken = (string) ($stmt->get_result()->fetch_assoc()['setting_val'] ?? '');

    return $savedToken !== '' && hash_equals($savedToken, $token);
}

function queueStatusWindows(mysqli $conn): array {
    return $conn->query("
        SELECT sw.window_id, sw.window_name, sw.status, hs.service_name,
               qt.ticket_id, qt.ticket_number, qt.reference_number, qt.client_type
        FROM service_windows sw
        LEFT JOIN health_services hs ON hs.service_id=sw.service_id
        LEFT JOIN queue_tickets qt ON qt.window_id=sw.window_id AND qt.status='serving'
        WHERE sw.is_active=1
        ORDER BY sw.window_id
    ")->fetch_all(MYSQLI_ASSOC);
}

function queueStatusNextTickets(mysqli $conn): array {
    return $conn->query("
        SELECT qt.ticket_number, qt.client_type, hs.service_name
        FROM queue_tickets qt
        JOIN health_services hs ON hs.service_id=qt.service_id
        WHERE qt.status='waiting'
        ORDER BY qt.priority_level DESC, qt.issued_at ASC
        LIMIT 8
    ")->fetch_all(MYSQLI_ASSOC);
}

function queueStatusViewerTicket(mysqli $conn): ?array {
    if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== ROLE_CLIENT) {
        return null;
    }

    $ticket = getActiveTicket($conn, (int) $_SESSION['user_id']);
    if (!$ticket) {
        return null;
    }

    return [
        'ticket_number' => (string) $ticket['ticket_number'],
        'service_name' => (string) $ticket['service_name'],
        'status' => (string) $ticket['status'],
        'people_ahead' => peopleAhead($conn, $ticket),
        'predicted_wait_min' => $ticket['predicted_wait_min'] !== null
            ? (float) $ticket['predicted_wait_min']
            : null,
        'window_name' => $ticket['window_name'] !== null
            ? (string) $ticket['window_name']
            : null,
    ];
}
