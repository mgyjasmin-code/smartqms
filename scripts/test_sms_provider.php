<?php
/**
 * Sends exactly one live SMS using the locally configured provider.
 *
 * Required environment variables:
 *   SMARTQMS_SMS_LIVE_TEST=1
 *   SMARTQMS_SMS_TEST_TO=+639XXXXXXXXX (or test_to_number in sms.local.php)
 * Provider secrets come from config/sms.local.php or SMARTQMS_SMS_* variables.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modules/notifications/sms_provider.php';

if (getenv('SMARTQMS_SMS_LIVE_TEST') !== '1') {
    fwrite(STDERR, "Refusing to send: set SMARTQMS_SMS_LIVE_TEST=1 for this command.\n");
    exit(2);
}

$localConfiguration = smsLocalConfiguration();
$toNumber = trim((string) (getenv('SMARTQMS_SMS_TEST_TO') ?: ($localConfiguration['test_to_number'] ?? '')));
if ($toNumber === '') {
    fwrite(STDERR, "Refusing to send: set SMARTQMS_SMS_TEST_TO to the approved destination.\n");
    exit(2);
}

try {
    $configuration = resolveSmsConfiguration([], $localConfiguration, smsEnvironmentConfiguration());
    if (($configuration['provider'] ?? '') !== 'fmcsms') {
        throw new RuntimeException('The smoke test requires provider=fmcsms.');
    }

    $result = sendSmsWithProvider(
        $configuration,
        $toNumber,
        'SmartQMS SMS integration test. No action is required.'
    );

    if (!$result['accepted']) {
        fwrite(STDERR, 'FMCSMS rejected the test request (HTTP ' . $result['http_code'] . ").\n");
        exit(1);
    }

    fwrite(STDOUT, 'FMCSMS accepted one test message (HTTP ' . $result['http_code'] . ").\n");
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'SMS test failed: ' . $error->getMessage() . "\n");
    exit(1);
}
