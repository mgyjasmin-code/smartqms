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

function generateQR(string $referenceNumber, string $ticketId): string {
    if (!class_exists(Builder::class)) {
        throw new RuntimeException('QR code package is not installed. Run composer install.');
    }

    if (!is_dir(QR_DIR) && !mkdir(QR_DIR, 0775, true) && !is_dir(QR_DIR)) {
        throw new RuntimeException('Could not create QR directory.');
    }

    $safeReference = preg_replace('/[^A-Za-z0-9_-]/', '-', $referenceNumber);
    $safeTicketId = preg_replace('/[^0-9]/', '', $ticketId);
    $filename = $safeReference . ($safeTicketId !== '' ? '-' . $safeTicketId : '') . '.svg';
    $filepath = QR_DIR . $filename;

    $result = Builder::create()
        ->writer(new SvgWriter())
        ->writerOptions([
            SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => false,
            SvgWriter::WRITER_OPTION_EXCLUDE_SVG_WIDTH_AND_HEIGHT => false,
            SvgWriter::WRITER_OPTION_COMPACT => true,
        ])
        ->data(publicTicketUrl($referenceNumber))
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

    return 'assets/qr/' . $filename;
}
?>
