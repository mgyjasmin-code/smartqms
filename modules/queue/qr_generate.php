<?php
/**
 * SmartQMS -- QR Code Generator
 * Generates a QR code image for a ticket reference number.
 * Saves to /assets/qr/{reference_number}.png
 *
 * OPTION A (recommended): Use Google Chart API -- free, no library needed
 *   $url = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=" . urlencode($data);
 *   file_put_contents(QR_DIR . $filename, file_get_contents($url));
 *
 * OPTION B: Install endroid/qr-code via Composer
 *   composer require endroid/qr-code
 */
require_once '../../config/config.php';

function generateQR(string $referenceNumber, string $ticketId): string {
    $filename = $referenceNumber . '.png';
    $filepath = QR_DIR . $filename;
    $data     = APP_URL . '/modules/queue/status.php?ref=' . $referenceNumber;

    // TODO: Generate QR using Google Chart API or endroid/qr-code
    // TODO: Save file to QR_DIR
    // TODO: Return relative path for database storage

    return 'assets/qr/' . $filename;
}
?>
