<?php
/**
 * Copy to sms.local.php for local development. Production reads SMARTQMS_SMS_*
 * environment variables. Never commit a real token.
 */
return [
    'enabled' => false,
    'provider' => 'winro', // winro, fmcsms, or semaphore
    'api_key' => '',
    'sender_name' => 'SmartQMS',
    'from_number' => '', // Required only for FMCSMS.
    'test_to_number' => '', // Used only by scripts/test_sms_provider.php.
];
