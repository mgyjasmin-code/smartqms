<?php

require_once SMARTQMS_ROOT . '/modules/notifications/sms_provider.php';

testCase('SMS configuration ignores database secrets and gives environment highest precedence', function (): void {
    $configuration = resolveSmsConfiguration(
        ['sms_enabled' => '0', 'sms_api_key' => 'legacy', 'sms_sender_name' => 'Legacy'],
        ['enabled' => true, 'provider' => 'fmcsms', 'api_key' => 'local', 'from_number' => '09171234567'],
        ['api_key' => 'environment', 'sender_name' => 'SmartQMS']
    );

    assertTrueValue($configuration['enabled']);
    assertSameValue('fmcsms', $configuration['provider']);
    assertSameValue('environment', $configuration['api_key']);
    assertSameValue('SmartQMS', $configuration['sender_name']);
    assertSameValue('09171234567', $configuration['from_number']);

    $databaseOnly = resolveSmsConfiguration(
        ['sms_enabled' => '1', 'sms_api_key' => 'must-not-load', 'sms_sender_name' => 'Legacy'],
        [],
        []
    );
    assertSameValue('', $databaseOnly['api_key']);
});

testCase('FMCSMS request matches the approved JSON contract and normalizes Philippine numbers', function (): void {
    $request = fmcsmsRequest([
        'api_key' => 'fmcsms_test_key',
        'sender_name' => 'SmartQMS',
        'from_number' => '09181234567',
    ], '09171234567', 'Characterization message');

    assertSameValue(SMARTQMS_FMCSMS_ENDPOINT, $request['url']);
    assertContainsValue('Content-Type: application/json', $request['headers']);
    assertContainsValue('X-API-Key: fmcsms_test_key', $request['headers']);
    $payload = json_decode($request['body'], true, 512, JSON_THROW_ON_ERROR);
    assertSameValue([
        'SenderName' => 'SmartQMS',
        'ToNumber' => '+639171234567',
        'MessageBody' => 'Characterization message',
        'FromNumber' => '+639181234567',
    ], $payload);
});

testCase('SMS provider rejects missing secrets, invalid numbers, and empty messages before networking', function (): void {
    assertThrowsException(
        fn() => fmcsmsRequest(['sender_name' => 'SmartQMS', 'from_number' => '09181234567'], '09171234567', 'Message'),
        RuntimeException::class,
        'API key'
    );
    assertThrowsException(fn() => normalizeSmsNumber('12345'), InvalidArgumentException::class, 'Philippine');
    assertThrowsException(fn() => validateSmsMessage('   '), InvalidArgumentException::class, 'cannot be empty');
});

testCase('SMS provider transport is injectable and explicit provider failures are not accepted', function (): void {
    $configuration = [
        'provider' => 'fmcsms',
        'api_key' => 'fmcsms_test_key',
        'sender_name' => 'SmartQMS',
        'from_number' => '+639181234567',
    ];
    $calls = 0;
    $accepted = sendSmsWithProvider(
        $configuration,
        '+639171234567',
        'Characterization message',
        function (array $request) use (&$calls): array {
            $calls++;
            assertSameValue(SMARTQMS_FMCSMS_ENDPOINT, $request['url']);
            return ['http_code' => 200, 'body' => '{"success":true,"status":"queued"}'];
        }
    );
    assertSameValue(1, $calls);
    assertTrueValue($accepted['accepted']);

    $rejected = sendSmsWithProvider(
        $configuration,
        '+639171234567',
        'Characterization message',
        fn(array $request): array => ['http_code' => 200, 'body' => '{"success":false,"status":"rejected"}']
    );
    assertFalseValue($rejected['accepted']);
});
