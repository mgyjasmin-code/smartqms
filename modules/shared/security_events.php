<?php
/** Best-effort, secret-redacted security event recording. */

function recordSecurityEvent(
    mysqli $conn,
    string $eventType,
    string $outcome,
    ?string $targetType = null,
    ?string $targetId = null,
    array $metadata = []
): void {
    try {
        if (!smartqmsTableExists($conn, 'security_events')) {
            return;
        }
        $allowedMetadata = array_intersect_key($metadata, array_flip(['reason', 'scope', 'provider', 'report', 'method']));
        $metadataJson = $allowedMetadata ? json_encode($allowedMetadata, JSON_THROW_ON_ERROR) : null;
        $actorUserId = (int) ($_SESSION['user_id'] ?? 0);
        $actorUserId = $actorUserId > 0 ? $actorUserId : null;
        $ip = requestIpAddress();
        $correlationId = defined('SMARTQMS_CORRELATION_ID') ? SMARTQMS_CORRELATION_ID : bin2hex(random_bytes(16));
        $stmt = $conn->prepare(
            'INSERT INTO security_events (actor_user_id, event_type, outcome, target_type, target_id, ip_address, correlation_id, metadata_json) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('isssssss', $actorUserId, $eventType, $outcome, $targetType, $targetId, $ip, $correlationId, $metadataJson);
        $stmt->execute();
    } catch (Throwable $error) {
        error_log('Security event recording failed; correlation=' . (defined('SMARTQMS_CORRELATION_ID') ? SMARTQMS_CORRELATION_ID : 'unavailable'));
    }
}
