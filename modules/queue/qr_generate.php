<?php
/**
 * SmartQMS -- QR Code Generator
 * Generates a public, read-only ticket status QR as an SVG file.
 */
require_once __DIR__ . '/../../config/config.php';

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;

function publicTicketUrl(string $referenceNumber): string {
    return APP_URL . '/views/client/ticket_lookup.php?ref=' . rawurlencode($referenceNumber);
}

function publicTokenTrackingUrl(string $token): string {
    return APP_URL . '/track/?token=' . rawurlencode($token);
}

function queueQrRelativePath(string $referenceNumber, string $ticketId): string {
    $safeReference = preg_replace('/[^A-Za-z0-9_-]/', '-', $referenceNumber);
    $safeTicketId = preg_replace('/[^0-9]/', '', $ticketId);
    $filename = $safeReference . ($safeTicketId !== '' ? '-' . $safeTicketId : '') . '.svg';
    return 'assets/qr/' . $filename;
}

function queueQrAbsolutePath(string $relativePath): ?string {
    $normalized = str_replace('\\', '/', $relativePath);
    $prefix = 'assets/qr/';
    if (!str_starts_with($normalized, $prefix)) {
        return null;
    }

    $filename = substr($normalized, strlen($prefix));
    if ($filename === '' || basename($filename) !== $filename) {
        return null;
    }

    return rtrim(QR_DIR, '/\\') . DIRECTORY_SEPARATOR . $filename;
}

function removeGeneratedQueueQr(string $relativePath): bool {
    $absolutePath = queueQrAbsolutePath($relativePath);
    return $absolutePath !== null && is_file($absolutePath)
        ? unlink($absolutePath)
        : false;
}

function generateQueueQrForUrl(string $url, string $referenceNumber, string $ticketId): string {
    if (!class_exists(Builder::class)) {
        throw new RuntimeException('QR code package is not installed. Run composer install.');
    }

    if (!is_dir(QR_DIR) && !mkdir(QR_DIR, 0775, true) && !is_dir(QR_DIR)) {
        throw new RuntimeException('Could not create QR directory.');
    }

    $relativePath = queueQrRelativePath($referenceNumber, $ticketId);
    $filepath = queueQrAbsolutePath($relativePath);
    if ($filepath === null) {
        throw new RuntimeException('Could not resolve QR output path.');
    }

    $result = Builder::create()
        ->writer(new SvgWriter())
        ->writerOptions([
            SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => false,
            SvgWriter::WRITER_OPTION_EXCLUDE_SVG_WIDTH_AND_HEIGHT => false,
            SvgWriter::WRITER_OPTION_COMPACT => true,
        ])
        ->data($url)
        ->encoding(new Encoding('UTF-8'))
        ->errorCorrectionLevel(ErrorCorrectionLevel::High)
        ->size(320)
        ->margin(18)
        ->roundBlockSizeMode(RoundBlockSizeMode::None)
        ->foregroundColor(new Color(31, 37, 45))
        ->backgroundColor(new Color(255, 255, 255))
        ->validateResult(false)
        ->build();

    $result->saveToFile($filepath);

    return $relativePath;
}


function generateQR(string $referenceNumber, string $ticketId): string {
    return generateQueueQrForUrl(publicTicketUrl($referenceNumber), $referenceNumber, $ticketId);
}

function generateTokenQR(string $token, string $referenceNumber, string $ticketId): string {
    return generateQueueQrForUrl(publicTokenTrackingUrl($token), $referenceNumber, $ticketId);
}
?>
