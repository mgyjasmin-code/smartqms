<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../modules/queue/public_intake.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$token = strtolower(trim((string) ($_GET['token'] ?? '')));
$ticket = publicQueueTicketByToken($conn, $token);
if (!$ticket) { 
    http_response_code(404); 
    exit; 
}
$projection = publicQueueTicketProjection($conn, $ticket);

// Prepare variables
$refNumber = htmlspecialchars((string) $projection['reference_number'], ENT_QUOTES, 'UTF-8');
$serviceName = htmlspecialchars((string) $projection['service_name'], ENT_QUOTES, 'UTF-8');
$clientName = htmlspecialchars((string) $projection['client_name'], ENT_QUOTES, 'UTF-8');
$appointmentDate = htmlspecialchars(date('F j, Y', strtotime((string) $projection['visit_date'])), ENT_QUOTES, 'UTF-8');

// Load QR code as base64
$qrPath = __DIR__ . '/../../' . ltrim($ticket['qr_code_path'], '/');
$qrBase64 = '';
if (!empty($ticket['qr_code_path']) && file_exists($qrPath)) {
    $qrData = file_get_contents($qrPath);
    $mime = mime_content_type($qrPath);
    if (!$mime) {
        $mime = 'image/png';
    }
    $qrBase64 = 'data:' . $mime . ';base64,' . base64_encode($qrData);
}

// Build HTML for Dompdf
$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Appointment Confirmation</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333333;
            line-height: 1.5;
            margin: 0;
            padding: 40px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
        }
        .header {
            margin-bottom: 30px;
        }
        .brand {
            font-size: 28px;
            font-weight: bold;
            color: #075DBD;
            margin-bottom: 10px;
        }
        h1 {
            font-size: 24px;
            color: #1a1a1a;
            margin: 0 0 20px 0;
        }
        .ref-box {
            background-color: #f8f9fa;
            border: 1px dashed #075DBD;
            padding: 20px;
            margin-bottom: 30px;
        }
        .ref-label {
            font-size: 14px;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 5px;
        }
        .ref-number {
            font-size: 36px;
            font-weight: bold;
            color: #075DBD;
            letter-spacing: 2px;
        }
        .details {
            text-align: left;
            margin-bottom: 30px;
            width: 100%;
        }
        .details th {
            text-align: left;
            padding: 8px 0;
            color: #666;
            width: 150px;
            font-weight: normal;
        }
        .details td {
            font-weight: bold;
            padding: 8px 0;
        }
        .qr-section {
            margin-bottom: 20px;
        }
        .qr-code {
            max-width: 200px;
            height: auto;
            margin-bottom: 10px;
        }
        .instructions {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
        }
        .notice {
            background-color: #eaf3ff;
            border: 1px solid #c9d9eb;
            color: #174a6e;
            padding: 15px;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 30px;
            text-align: center;
        }
        .footer {
            font-size: 12px;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="brand">SmartQMS</div>
        </div>
        
        <h1>Appointment Confirmed</h1>
        
        <div class="ref-box">
            <div class="ref-label">Reference Number</div>
            <div class="ref-number">{$refNumber}</div>
        </div>
        
        <div class="qr-section">
HTML;

if ($qrBase64) {
    $html .= '<img src="' . $qrBase64 . '" class="qr-code" alt="QR Code">';
}

$html .= <<<HTML
            <div class="instructions">Show this QR to staff at check-in</div>
        </div>

        <table class="details">
            <tr>
                <th>Full Name:</th>
                <td>{$clientName}</td>
            </tr>
            <tr>
                <th>Service:</th>
                <td>{$serviceName}</td>
            </tr>
            <tr>
                <th>Date:</th>
                <td>{$appointmentDate}</td>
            </tr>
            <tr>
                <th>Check-in Period:</th>
                <td>8:00 AM &ndash; 3:30 PM</td>
            </tr>
        </table>
        
        <div class="notice">
            Check in during the period shown above. Staff will assign your queue number after you arrive.
        </div>
        
        <div class="footer">
            SmartQMS &middot; Barangay health-center queue service
        </div>
    </div>
</body>
</html>
HTML;

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'smartqms-confirmation-' . strtolower($refNumber) . '.pdf';
$dompdf->stream($filename, ["Attachment" => true]);
