<?php
/**
 * Copy this file to config.local.php and adjust only for a disposable test
 * database. The test bootstrap refuses database names that do not end in
 * _test or _testing.
 */
return [
    'host' => 'localhost',
    'port' => 3306,
    'user' => 'root',
    'password' => '',
    'database' => 'smartqms_test',
    'charset' => 'utf8mb4',
];
