<?php
/**
 * Manual Winro smoke test. Run only from CLI after setting SMARTQMS_SMS_*.
 * Example: php winro-test/send.php 09171234567
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../modules/notifications/sms_provider.php';

$phone = (string) ($argv[1] ?? '');
if ($phone === '') {
    fwrite(STDERR, "Usage: php winro-test/send.php 09XXXXXXXXX\n");
    exit(1);
}

try {
    $configuration = resolveSmsConfiguration([], [], smsEnvironmentConfiguration());
    if (!$configuration['enabled'] || $configuration['provider'] !== 'winro') {
        throw new RuntimeException('Set SMARTQMS_SMS_ENABLED=1 and SMARTQMS_SMS_PROVIDER=winro.');
    }
    $result = sendSmsWithProvider($configuration, $phone, 'This is a SmartQMS test SMS.');
    fwrite(STDOUT, $result['accepted'] ? "Provider accepted the SMS.\n" : "Provider rejected the SMS.\n");
    exit($result['accepted'] ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, "SMS test failed: " . $error->getMessage() . "\n");
    exit(1);
}
