<?php
/**
 * Minimal dependency-free test registration and assertion helpers.
 */

$GLOBALS['smartqms_tests'] = [];

function testCase(string $name, callable $callback): void {
    $GLOBALS['smartqms_tests'][] = [
        'name' => $name,
        'callback' => $callback,
    ];
}

function failTest(string $message): never {
    throw new RuntimeException($message);
}

function assertTrueValue(bool $condition, string $message = 'Expected condition to be true.'): void {
    if (!$condition) {
        failTest($message);
    }
}

function assertFalseValue(bool $condition, string $message = 'Expected condition to be false.'): void {
    if ($condition) {
        failTest($message);
    }
}

function assertSameValue(mixed $expected, mixed $actual, string $message = ''): void {
    if ($expected !== $actual) {
        $detail = $message !== '' ? $message . ' ' : '';
        failTest($detail . 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.');
    }
}

function assertEqualsValue(mixed $expected, mixed $actual, string $message = ''): void {
    if ($expected != $actual) {
        $detail = $message !== '' ? $message . ' ' : '';
        failTest($detail . 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.');
    }
}

function assertArrayHasKeys(array $keys, array $actual, string $message = ''): void {
    foreach ($keys as $key) {
        if (!array_key_exists($key, $actual)) {
            $detail = $message !== '' ? $message . ' ' : '';
            failTest($detail . 'Missing array key: ' . $key . '.');
        }
    }
}

function assertContainsValue(mixed $needle, array $haystack, string $message = ''): void {
    if (!in_array($needle, $haystack, true)) {
        $detail = $message !== '' ? $message . ' ' : '';
        failTest($detail . 'Array does not contain ' . var_export($needle, true) . '.');
    }
}

function assertStringContains(string $needle, string $haystack, string $message = ''): void {
    if (!str_contains($haystack, $needle)) {
        $detail = $message !== '' ? $message . ' ' : '';
        failTest($detail . 'String does not contain ' . var_export($needle, true) . '.');
    }
}

function assertThrowsException(callable $callback, string $exceptionClass, string $messageContains = ''): void {
    try {
        $callback();
    } catch (Throwable $error) {
        if (!$error instanceof $exceptionClass) {
            failTest('Expected ' . $exceptionClass . ', got ' . get_class($error) . ': ' . $error->getMessage());
        }
        if ($messageContains !== '' && !str_contains($error->getMessage(), $messageContains)) {
            failTest('Exception message did not contain ' . var_export($messageContains, true) . '.');
        }
        return;
    }

    failTest('Expected exception ' . $exceptionClass . ' was not thrown.');
}

function runRegisteredTests(): int {
    $passed = 0;
    $failed = 0;

    foreach ($GLOBALS['smartqms_tests'] as $test) {
        try {
            $test['callback']();
            $passed++;
            fwrite(STDOUT, '[PASS] ' . $test['name'] . PHP_EOL);
        } catch (Throwable $error) {
            $failed++;
            fwrite(STDERR, '[FAIL] ' . $test['name'] . PHP_EOL);
            fwrite(STDERR, '       ' . get_class($error) . ': ' . $error->getMessage() . PHP_EOL);
        }
    }

    fwrite(STDOUT, PHP_EOL . sprintf('Tests: %d passed, %d failed, %d total', $passed, $failed, $passed + $failed) . PHP_EOL);
    return $failed === 0 ? 0 : 1;
}
