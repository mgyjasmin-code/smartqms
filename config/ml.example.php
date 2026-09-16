<?php
/**
 * Copy to config/ml.local.php for local development, or set the equivalent
 * environment variables. Never commit a production ML bearer token.
 */
return [
    'url' => 'http://127.0.0.1:5000',
    'token' => '',
    'timeout_seconds' => 2,
];
