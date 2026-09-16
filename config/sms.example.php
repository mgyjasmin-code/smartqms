<?php
/**
 * Copy this file to sms.local.php and enter the values from the approved
 * FMCSMS dashboard. sms.local.php is ignored by Git and must never be shared.
 */
return [
    'enabled' => false,
    'provider' => 'fmcsms',
    'api_key' => '',
    'sender_name' => 'SmartQMS',
    'from_number' => '', // Philippine mobile number, e.g. +639171234567.
    'test_to_number' => '', // Used only by scripts/test_sms_provider.php.
];
