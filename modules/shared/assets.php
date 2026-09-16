<?php
/**
 * Shared application and cache-busted asset URL helpers.
 */

function postActionUrl(string $path): string {
    return APP_URL . '/' . ltrim($path, '/');
}

function assetUrl(string $path): string {
    $path = ltrim($path, '/');
    // Composer's vendor directory is private. Publish only Bootstrap's two
    // browser assets, retaining compatibility with existing view references.
    $publicAssets = [
        'vendor/twbs/bootstrap/dist/css/bootstrap.min.css' => 'assets/vendor/bootstrap/bootstrap.min.css',
        'vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js' => 'assets/vendor/bootstrap/bootstrap.bundle.min.js',
    ];
    $path = $publicAssets[$path] ?? $path;
    $file = __DIR__ . '/../../' . $path;
    $version = is_file($file) ? (string) filemtime($file) : APP_VERSION;

    return APP_URL . '/' . $path . '?v=' . rawurlencode($version);
}
