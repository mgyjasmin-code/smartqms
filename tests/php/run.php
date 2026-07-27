<?php
require_once __DIR__ . '/bootstrap.php';

$unitTests = glob(__DIR__ . '/unit/*_test.php') ?: [];
$integrationTests = glob(__DIR__ . '/integration/*_test.php') ?: [];
sort($unitTests, SORT_STRING);
sort($integrationTests, SORT_STRING);
$testFiles = array_merge($unitTests, $integrationTests);

foreach ($testFiles as $testFile) {
    require $testFile;
}

exit(runRegisteredTests());
