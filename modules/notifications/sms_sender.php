<?php
/**
 * SmartQMS -- SMS Sender (Semaphore API)
 * Wraps the Semaphore SMS API for OTP and notifications.
 *
 * FREE TIER: Sign up at semaphore.co to get free SMS credits.
 * If sms_enabled = 0 in system_settings -> log as 'simulated'
 *   (show sms_logs table to panel as proof feature is built)
 *
 * Usage:
 *   require_once 'sms_sender.php';
 *   sendSMS($conn, '09171234567', 'Your OTP is: 123456', 'otp', $userId);
 */
require_once __DIR__ . '/../../config/config.php';

function sendSMS(mysqli $conn, string $phone, string $message, string $type = 'notification', int $userId = 0): bool {
    // Read SMS settings from system_settings table
    $result  = $conn->query("SELECT setting_key, setting_val FROM system_settings
                              WHERE section = 'sms'");
    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_val'];
    }

    $enabled    = $settings['sms_enabled']    ?? '0';
    $apiKey     = $settings['sms_api_key']    ?? '';
    $senderName = $settings['sms_sender_name'] ?? 'BHCQMS';

    $status = 'simulated';

    if ($enabled === '1' && !empty($apiKey)) {
        // TODO: Send via Semaphore API
        $data = [
            'apikey'     => $apiKey,
            'number'     => $phone,
            'message'    => $message,
            'sendername' => $senderName,
        ];
        $ch = curl_init('https://api.semaphore.co/api/v4/messages');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $status = ($httpCode === 200) ? 'sent' : 'failed';
    }

    // Always log SMS attempt regardless of actual sending
    $nullableUserId = $userId > 0 ? $userId : null;
    $stmt = $conn->prepare("INSERT INTO sms_logs
        (user_id, phone, message, type, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('issss', $nullableUserId, $phone, $message, $type, $status);
    $stmt->execute();

    return $status === 'sent' || $status === 'simulated';
}
?>
