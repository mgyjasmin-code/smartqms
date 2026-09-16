<?php

testCase('database bootstrap catches connection failures without exposing a stack trace', function (): void {
    $source = file_get_contents(SMARTQMS_ROOT . '/config/database.php') ?: '';

    assertStringContains('mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT)', $source);
    assertStringContains('catch (mysqli_sql_exception $exception)', $source);
    assertStringContains('http_response_code(503)', $source);
    assertStringContains("header('Retry-After: 10')", $source);
    assertStringContains("'success' => false", $source);
    assertStringContains('The database service is temporarily unavailable.', $source);
    assertFalseValue(str_contains($source, 'die(json_encode'));
    assertFalseValue(str_contains($source, 'echo $exception'));
});
