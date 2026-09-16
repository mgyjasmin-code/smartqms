<?php
/**
 * Provider adapters and secret-safe SMS configuration.
 *
 * Secrets are loaded only from config/sms.local.php or SMARTQMS_SMS_*
 * environment variables. Database settings may control non-secret behavior.
 */

const SMARTQMS_FMCSMS_ENDPOINT = 'https://www.fortmed.org/web/FMCSMS/api/messages.php';
const SMARTQMS_SEMAPHORE_ENDPOINT = 'https://api.semaphore.co/api/v4/messages';

function smsBooleanValue(mixed $value): bool {
    if (is_bool($value)) {
        return $value;
    }

    return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
}

function smsLocalConfiguration(): array {
    static $configuration = null;
    if (is_array($configuration)) {
        return $configuration;
    }

    $path = __DIR__ . '/../../config/sms.local.php';
    if ((defined('APP_ENV') && APP_ENV === 'production') || !is_file($path)) {
        $configuration = [];
        return $configuration;
    }

    $loaded = require $path;
    if (!is_array($loaded)) {
        throw new RuntimeException('config/sms.local.php must return a configuration array.');
    }

    $configuration = $loaded;
    return $configuration;
}

function smsEnvironmentConfiguration(): array {
    $mapping = [
        'enabled' => 'SMARTQMS_SMS_ENABLED',
        'provider' => 'SMARTQMS_SMS_PROVIDER',
        'api_key' => 'SMARTQMS_SMS_API_KEY',
        'sender_name' => 'SMARTQMS_SMS_SENDER_NAME',
        'from_number' => 'SMARTQMS_SMS_FROM_NUMBER',
    ];

    $configuration = [];
    foreach ($mapping as $key => $name) {
        $value = getenv($name);
        if ($value !== false && trim((string) $value) !== '') {
            $configuration[$key] = trim((string) $value);
        }
    }

    return $configuration;
}

function resolveSmsConfiguration(
    array $databaseSettings = [],
    ?array $localConfiguration = null,
    ?array $environmentConfiguration = null
): array {
    $configuration = [
        'enabled' => smsBooleanValue($databaseSettings['sms_enabled'] ?? false),
        'provider' => 'semaphore',
        'api_key' => '',
        'sender_name' => trim((string) ($databaseSettings['sms_sender_name'] ?? 'BHCQMS')),
        'from_number' => '',
    ];

    foreach ($localConfiguration ?? smsLocalConfiguration() as $key => $value) {
        if (array_key_exists($key, $configuration)) {
            $configuration[$key] = $value;
        }
    }
    foreach ($environmentConfiguration ?? smsEnvironmentConfiguration() as $key => $value) {
        if (array_key_exists($key, $configuration)) {
            $configuration[$key] = $value;
        }
    }

    $configuration['enabled'] = smsBooleanValue($configuration['enabled']);
    $configuration['provider'] = strtolower(trim((string) $configuration['provider']));
    $configuration['api_key'] = trim((string) $configuration['api_key']);
    $configuration['sender_name'] = trim((string) $configuration['sender_name']);
    $configuration['from_number'] = trim((string) $configuration['from_number']);

    return $configuration;
}

function normalizeSmsNumber(string $phone): string {
    $digits = preg_replace('/\D+/', '', trim($phone));
    if (preg_match('/^09\d{9}$/', $digits)) {
        return '+63' . substr($digits, 1);
    }
    if (preg_match('/^639\d{9}$/', $digits)) {
        return '+' . $digits;
    }

    throw new InvalidArgumentException('SMS numbers must be Philippine mobile numbers in 09XXXXXXXXX or +639XXXXXXXXX format.');
}

function validateSmsMessage(string $message): string {
    $message = trim($message);
    if ($message === '') {
        throw new InvalidArgumentException('SMS message cannot be empty.');
    }
    if (strlen($message) > 1000) {
        throw new InvalidArgumentException('SMS message is too long.');
    }

    return $message;
}

function fmcsmsRequest(array $configuration, string $phone, string $message): array {
    $apiKey = trim((string) ($configuration['api_key'] ?? ''));
    $senderName = trim((string) ($configuration['sender_name'] ?? ''));
    $fromNumber = trim((string) ($configuration['from_number'] ?? ''));

    if ($apiKey === '') {
        throw new RuntimeException('FMCSMS API key is not configured.');
    }
    if ($senderName === '') {
        throw new RuntimeException('FMCSMS sender name is not configured.');
    }
    if ($fromNumber === '') {
        throw new RuntimeException('FMCSMS from number is not configured.');
    }

    $payload = [
        'SenderName' => $senderName,
        'ToNumber' => normalizeSmsNumber($phone),
        'MessageBody' => validateSmsMessage($message),
        'FromNumber' => normalizeSmsNumber($fromNumber),
    ];

    return [
        'url' => SMARTQMS_FMCSMS_ENDPOINT,
        'headers' => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-API-Key: ' . $apiKey,
        ],
        'body' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
    ];
}

function semaphoreRequest(array $configuration, string $phone, string $message): array {
    $apiKey = trim((string) ($configuration['api_key'] ?? ''));
    if ($apiKey === '') {
        throw new RuntimeException('Semaphore API key is not configured.');
    }

    normalizeSmsNumber($phone);

    return [
        'url' => SMARTQMS_SEMAPHORE_ENDPOINT,
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
        'body' => http_build_query([
            'apikey' => $apiKey,
            'number' => trim($phone),
            'message' => validateSmsMessage($message),
            'sendername' => trim((string) ($configuration['sender_name'] ?? 'BHCQMS')),
        ]),
    ];
}

function executeSmsRequest(array $request): array {
    if (!extension_loaded('curl')) {
        throw new RuntimeException('The PHP cURL extension is required for live SMS delivery.');
    }

    $responseBody = '';
    $responseLimit = 65536;
    $handle = curl_init((string) $request['url']);
    $options = [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_HTTPHEADER => $request['headers'],
        CURLOPT_POSTFIELDS => $request['body'],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'SmartQMS/' . (defined('APP_VERSION') ? APP_VERSION : '1.0'),
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$responseBody, $responseLimit): int {
            if (strlen($responseBody) + strlen($chunk) > $responseLimit) {
                return 0;
            }
            $responseBody .= $chunk;
            return strlen($chunk);
        },
    ];
    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
        $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
    }
    curl_setopt_array($handle, $options);

    $executed = curl_exec($handle);
    $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $curlError = $executed === false ? curl_error($handle) : '';
    curl_close($handle);

    if ($executed === false) {
        throw new RuntimeException('SMS provider request failed: ' . ($curlError !== '' ? $curlError : 'transport error'));
    }

    return ['http_code' => $httpCode, 'body' => $responseBody];
}

function smsProviderAccepted(array $response): bool {
    $httpCode = (int) ($response['http_code'] ?? 0);
    if ($httpCode < 200 || $httpCode >= 300) {
        return false;
    }

    $decoded = json_decode((string) ($response['body'] ?? ''), true);
    if (!is_array($decoded)) {
        return true;
    }
    if (array_key_exists('success', $decoded) && $decoded['success'] === false) {
        return false;
    }
    $status = strtolower(trim((string) ($decoded['status'] ?? '')));
    return !in_array($status, ['failed', 'error', 'rejected'], true);
}

function sendSmsWithProvider(
    array $configuration,
    string $phone,
    string $message,
    ?callable $transport = null
): array {
    $provider = strtolower(trim((string) ($configuration['provider'] ?? '')));
    $request = match ($provider) {
        'fmcsms' => fmcsmsRequest($configuration, $phone, $message),
        'semaphore' => semaphoreRequest($configuration, $phone, $message),
        default => throw new RuntimeException('Unsupported SMS provider configuration.'),
    };

    $response = $transport !== null ? $transport($request) : executeSmsRequest($request);
    if (!is_array($response)) {
        throw new RuntimeException('SMS transport returned an invalid response.');
    }

    return [
        'accepted' => smsProviderAccepted($response),
        'http_code' => (int) ($response['http_code'] ?? 0),
        'provider' => $provider,
    ];
}
