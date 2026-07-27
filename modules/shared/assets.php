<?php
/**
 * Shared application and cache-busted asset URL helpers.
 */

function postActionUrl(string $path): string {
    return APP_URL . '/' . ltrim($path, '/');
}

function assetUrl(string $path): string {
    $path = ltrim($path, '/');
    $file = __DIR__ . '/../../' . $path;
    $version = is_file($file) ? (string) filemtime($file) : APP_VERSION;

    return APP_URL . '/' . $path . '?v=' . rawurlencode($version);
}
