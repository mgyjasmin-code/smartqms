<?php
declare(strict_types=1);

/**
 * Publish the Composer-managed Bootstrap browser assets into the public tree.
 *
 * Usage: php scripts/publish_bootstrap.php [--check]
 */

$root = dirname(__DIR__);
$checkOnly = in_array('--check', $argv, true);
$packageFile = $root . '/vendor/twbs/bootstrap/package.json';

if (!is_file($packageFile)) {
    fwrite(STDERR, "Bootstrap is not installed. Run composer install first.\n");
    exit(1);
}

$package = json_decode((string) file_get_contents($packageFile), true);
$version = is_array($package) ? (string) ($package['version'] ?? '') : '';
if ($version !== '5.3.8') {
    fwrite(STDERR, "Expected Bootstrap 5.3.8, found " . ($version ?: 'an unknown version') . ".\n");
    exit(1);
}

$assets = [
    'bootstrap.min.css' => 'dist/css/bootstrap.min.css',
    'bootstrap.bundle.min.js' => 'dist/js/bootstrap.bundle.min.js',
    'LICENSE' => 'LICENSE',
];
$destinationDirectory = $root . '/assets/vendor/bootstrap';

if (!$checkOnly && !is_dir($destinationDirectory) && !mkdir($destinationDirectory, 0775, true) && !is_dir($destinationDirectory)) {
    fwrite(STDERR, "Unable to create {$destinationDirectory}.\n");
    exit(1);
}

$manifest = [
    'package' => 'twbs/bootstrap',
    'version' => $version,
    'algorithm' => 'sha256',
    'files' => [],
];
$failed = false;

foreach ($assets as $publicName => $vendorPath) {
    $source = $root . '/vendor/twbs/bootstrap/' . $vendorPath;
    $destination = $destinationDirectory . '/' . $publicName;

    if (!is_file($source)) {
        fwrite(STDERR, "Missing Composer asset: {$source}\n");
        $failed = true;
        continue;
    }

    $sourceHash = hash_file('sha256', $source);
    if (!$checkOnly) {
        $contents = file_get_contents($source);
        if ($contents === false || file_put_contents($destination, $contents, LOCK_EX) === false) {
            fwrite(STDERR, "Unable to publish {$publicName}.\n");
            $failed = true;
            continue;
        }
    }

    $publishedHash = is_file($destination) ? hash_file('sha256', $destination) : false;
    if ($publishedHash !== $sourceHash) {
        fwrite(STDERR, "Hash mismatch for {$publicName}.\n");
        $failed = true;
        continue;
    }

    $manifest['files'][$publicName] = $sourceHash;
    fwrite(STDOUT, ($checkOnly ? 'Verified ' : 'Published ') . "{$publicName} {$sourceHash}\n");
}

if ($failed) {
    exit(1);
}

$manifestPath = $destinationDirectory . '/manifest.json';
$manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
if ($checkOnly) {
    $existingManifest = is_file($manifestPath) ? file_get_contents($manifestPath) : false;
    if ($existingManifest !== $manifestJson) {
        fwrite(STDERR, "Bootstrap manifest is missing or stale. Publish the assets again.\n");
        exit(1);
    }
} elseif (file_put_contents($manifestPath, $manifestJson, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to write the Bootstrap manifest.\n");
    exit(1);
}

fwrite(STDOUT, "Bootstrap {$version} public assets are synchronized.\n");

