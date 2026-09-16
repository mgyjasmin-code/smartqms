<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/arrival_service.php';
require_once __DIR__ . '/print_batch_service.php';
requireLogin(ROLE_STAFF);
requirePostRequest(false, 'staff/batch-printing/', 'batch_print', $_POST);
requireValidCsrf('staff/batch-printing/', 'batch_print', $_POST);

$staffId = getCurrentStaffId($conn);
if (!$staffId) {
    http_response_code(403);
    exit('An active Staff profile is required.');
}
$validated = validateTicketPrintBatchInput($_POST);
if ($validated['errors']) {
    flashFormFeedback('batch_print', $validated['errors'], $_POST, (string) reset($validated['errors']));
    header('Location: ' . APP_URL . '/staff/batch-printing/');
    exit;
}

try {
    $values = $validated['values'];
    $batch = createTicketPrintBatch(
        $conn,
        (int) $staffId,
        (int) $values['service_id'],
        (int) $values['start_number'],
        (int) $values['end_number']
    );
    $pdf = renderTicketPrintBatchPdf($batch);
    $filename = sprintf('smartqms-%s-%s-%04d-%04d.pdf',
        strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', (string) $batch['service_code'])),
        $batch['service_date'],
        $batch['start_number'],
        $batch['end_number']
    );
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, no-store');
    echo $pdf;
} catch (DomainException|InvalidArgumentException $error) {
    flashFormFeedback('batch_print', [], $_POST, $error->getMessage());
    header('Location: ' . APP_URL . '/staff/batch-printing/');
} catch (Throwable $error) {
    error_log('Ticket batch PDF failed: ' . $error->getMessage());
    flashFormFeedback('batch_print', [], $_POST, 'The ticket batch could not be generated.');
    header('Location: ' . APP_URL . '/staff/batch-printing/');
}
exit;
