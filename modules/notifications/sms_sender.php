<?php
/**
 * SmartQMS SMS delivery boundary for OTP and notifications.
 *
 * Provider is selected through the SMS configuration. Disabled delivery is
 * logged as simulated so local queue flows can be exercised without credits.
 *
 * Usage:
 *   require_once 'sms_sender.php';
 *   sendSMS($conn, '09171234567', 'Your OTP is: 123456', 'otp', $userId);
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/sms_provider.php';

function sendSMS(mysqli $conn, string $phone, string $message, string $type = 'notification', int $userId = 0, ?string &$deliveryStatus = null): bool {
    // Read SMS settings from system_settings table
    $result  = $conn->query("SELECT setting_key, setting_val FROM system_settings
                              WHERE section = 'sms'");
    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_val'];
    }

    $configuration = resolveSmsConfiguration($settings);
    $status = 'simulated';
    $errorMessage = null;

    if ($configuration['enabled']) {
        try {
            $result = sendSmsWithProvider($configuration, $phone, $message);
            $status = $result['accepted'] ? 'sent' : 'failed';
            if (!$result['accepted']) {
                $errorMessage = 'Provider rejected the request (HTTP ' . $result['http_code'] . ').';
            }
        } catch (Throwable $error) {
            $status = 'failed';
            $errorMessage = substr($error->getMessage(), 0, 255);
            error_log('SmartQMS SMS delivery failed for provider ' . $configuration['provider'] . '.');
        }
    }

    // Always log SMS attempt regardless of actual sending
    $nullableUserId = $userId > 0 ? $userId : null;
    $stmt = $conn->prepare("INSERT INTO sms_logs
        (user_id, phone, message, type, status, error_msg) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isssss', $nullableUserId, $phone, $message, $type, $status, $errorMessage);
    $stmt->execute();

    $deliveryStatus = $status;

    return $status === 'sent' || $status === 'simulated';
}
?>
