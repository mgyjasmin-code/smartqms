<?php
/**
 * SmartQMS test bootstrap. It deliberately never loads config/database.php.
 */

defined('SMARTQMS_ROOT') || define('SMARTQMS_ROOT', dirname(__DIR__, 2));
defined('SMARTQMS_TEST_ROOT') || define('SMARTQMS_TEST_ROOT', dirname(__DIR__));

require_once __DIR__ . '/assertions.php';
require_once SMARTQMS_ROOT . '/config/config.php';
require_once SMARTQMS_ROOT . '/modules/reports/report_utils.php';

function testDatabaseConfiguration(): array {
    static $configuration = null;
    if (is_array($configuration)) {
        return $configuration;
    }

    $localPath = SMARTQMS_TEST_ROOT . '/config.local.php';
    $local = is_file($localPath) ? require $localPath : [];
    if (!is_array($local)) {
        throw new RuntimeException('tests/config.local.php must return a configuration array.');
    }

    $environment = [
        'host' => getenv('SMARTQMS_TEST_DB_HOST') ?: null,
        'port' => getenv('SMARTQMS_TEST_DB_PORT') ?: null,
        'user' => getenv('SMARTQMS_TEST_DB_USER') ?: null,
        'password' => getenv('SMARTQMS_TEST_DB_PASSWORD'),
        'database' => getenv('SMARTQMS_TEST_DB_NAME') ?: null,
        'charset' => getenv('SMARTQMS_TEST_DB_CHARSET') ?: null,
    ];

    $defaults = [
        'host' => 'localhost',
        'port' => 3306,
        'user' => 'root',
        'password' => '',
        'database' => '',
        'charset' => 'utf8mb4',
    ];

    $resolved = $defaults;
    foreach ($local as $key => $value) {
        if (array_key_exists($key, $resolved)) {
            $resolved[$key] = $value;
        }
    }
    foreach ($environment as $key => $value) {
        if ($value !== null && $value !== false && $value !== '') {
            $resolved[$key] = $value;
        }
    }

    $database = (string) $resolved['database'];
    if (!preg_match('/_(test|testing)$/i', $database)) {
        throw new RuntimeException('Refusing database access: the test database name must end in _test or _testing.');
    }

    $resolved['port'] = (int) $resolved['port'];
    $configuration = $resolved;
    return $configuration;
}

function testDatabaseConnection(): mysqli {
    static $connection = null;
    if ($connection instanceof mysqli) {
        return $connection;
    }

    $configuration = testDatabaseConfiguration();
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $connection = new mysqli(
        (string) $configuration['host'],
        (string) $configuration['user'],
        (string) $configuration['password'],
        (string) $configuration['database'],
        (int) $configuration['port']
    );
    $connection->set_charset((string) $configuration['charset']);

    $selected = (string) ($connection->query('SELECT DATABASE() AS database_name')->fetch_assoc()['database_name'] ?? '');
    if ($selected !== (string) $configuration['database'] || !preg_match('/_(test|testing)$/i', $selected)) {
        $connection->close();
        $connection = null;
        throw new RuntimeException('Refusing database access: connected database failed the test-name verification.');
    }

    return $connection;
}

function withTestTransaction(callable $callback): mixed {
    static $transactionActive = false;
    $connection = testDatabaseConnection();
    if ($transactionActive) {
        throw new RuntimeException('Nested test transactions are not supported.');
    }

    $connection->begin_transaction();
    $transactionActive = true;
    try {
        return $callback($connection);
    } finally {
        try {
            $connection->rollback();
        } finally {
            $transactionActive = false;
        }
    }
}

function testFixturePrefix(): string {
    return 'characterization_';
}

function testNextCounterNumber(mysqli $connection): int {
    return (int) ($connection->query(
        'SELECT COALESCE(MAX(counter_number), 0) + 1 AS next_counter FROM service_windows'
    )->fetch_assoc()['next_counter'] ?? 1);
}
