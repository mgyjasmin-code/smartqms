<?php
/**
 * SmartQMS -- Live Queue Status (AJAX endpoint)
 * Called every 10 seconds by main.js to update the queue display.
 * Returns JSON -- used by both client pages and display board.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
header('Content-Type: application/json');

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

if (!isLoggedIn() && !hasValidDisplayStatusToken($conn)) {
    jsonResponse(false, ['error' => 'Queue status is not available for this request.'], 403);
}

$windows = $conn->query("
    SELECT sw.window_id, sw.window_name, sw.status, hs.service_name,
           qt.ticket_id, qt.ticket_number, qt.reference_number, qt.client_type
    FROM service_windows sw
    LEFT JOIN health_services hs ON hs.service_id=sw.service_id
    LEFT JOIN queue_tickets qt ON qt.window_id=sw.window_id AND qt.status='serving'
    WHERE sw.is_active=1
    ORDER BY sw.window_id
")->fetch_all(MYSQLI_ASSOC);

$next = $conn->query("
    SELECT qt.ticket_number, qt.client_type, hs.service_name
    FROM queue_tickets qt
    JOIN health_services hs ON hs.service_id=qt.service_id
    WHERE qt.status='waiting'
    ORDER BY qt.priority_level DESC, qt.issued_at ASC
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

jsonResponse(true, ['data' => ['windows' => $windows, 'next' => $next]]);
?>
