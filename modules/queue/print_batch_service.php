<?php
/**
 * Transactional preprinted-number reservations and PDF rendering.
 */

function validateTicketPrintBatchInput(array $source): array {
    $serviceId = filter_var($source['service_id'] ?? null, FILTER_VALIDATE_INT);
    $start = filter_var($source['start_number'] ?? null, FILTER_VALIDATE_INT);
    $end = filter_var($source['end_number'] ?? null, FILTER_VALIDATE_INT);
    $errors = [];
    if ($serviceId === false || $serviceId < 1) {
        $errors['service_id'] = 'Choose an active health service.';
    }
    if ($start === false || $start < 1 || $start > 9999) {
        $errors['start_number'] = 'Enter a starting number from 1 to 9999.';
    }
    if ($end === false || $end < 1 || $end > 9999) {
        $errors['end_number'] = 'Enter an ending number from 1 to 9999.';
    }
    if (!$errors && $end < $start) {
        $errors['end_number'] = 'The ending number must not be lower than the starting number.';
    }
    if (!$errors && (($end - $start) + 1) > 200) {
        $errors['end_number'] = 'A print batch is limited to 200 tickets.';
    }
    return [
        'values' => [
            'service_id' => $serviceId === false ? 0 : (int) $serviceId,
            'start_number' => $start === false ? 0 : (int) $start,
            'end_number' => $end === false ? 0 : (int) $end,
        ],
        'errors' => $errors,
    ];
}

function listBatchPrintableServices(mysqli $conn): array {
    return $conn->query("
        SELECT service_id, service_code, service_name, is_active, is_hidden
        FROM health_services
        WHERE is_active = 1
        ORDER BY display_order, service_name
    ")->fetch_all(MYSQLI_ASSOC);
}

function createTicketPrintBatch(
    mysqli $conn,
    int $staffId,
    int $serviceId,
    int $startNumber,
    int $endNumber,
    ?string $serviceDate = null
): array {
    $validated = validateTicketPrintBatchInput([
        'service_id' => $serviceId,
        'start_number' => $startNumber,
        'end_number' => $endNumber,
    ]);
    if ($validated['errors']) {
        throw new InvalidArgumentException((string) reset($validated['errors']));
    }
    $serviceDate = $serviceDate ?: date('Y-m-d');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $serviceDate);
    if (!$date || $date->format('Y-m-d') !== $serviceDate || $serviceDate !== date('Y-m-d')) {
        throw new InvalidArgumentException('Preprinted numbers may be reserved only for the current local date.');
    }

    $activeTransaction = (int) ($conn->query('SELECT @@session.in_transaction AS active')->fetch_assoc()['active'] ?? 0) === 1;
    $savepoint = 'smartqms_print_batch';
    if ($activeTransaction) {
        $conn->query('SAVEPOINT ' . $savepoint);
    } else {
        $conn->begin_transaction();
    }
    try {
        $staff = $conn->prepare('SELECT staff_id FROM staff WHERE staff_id = ? LIMIT 1 FOR UPDATE');
        $staff->bind_param('i', $staffId);
        $staff->execute();
        if (!$staff->get_result()->fetch_assoc()) {
            throw new DomainException('An active Staff profile is required.');
        }
        $serviceStmt = $conn->prepare("
            SELECT service_id, service_code, service_name, is_active
            FROM health_services
            WHERE service_id = ?
            LIMIT 1 FOR UPDATE
        ");
        $serviceStmt->bind_param('i', $serviceId);
        $serviceStmt->execute();
        $service = $serviceStmt->get_result()->fetch_assoc();
        if (!$service || (int) $service['is_active'] !== 1) {
            throw new DomainException('Choose an active health service.');
        }

        $overlap = $conn->prepare("
            SELECT reservation_id
            FROM ticket_number_reservations
            WHERE service_id = ? AND service_date = ?
              AND sequence_number BETWEEN ? AND ?
            LIMIT 1 FOR UPDATE
        ");
        $overlap->bind_param('isii', $serviceId, $serviceDate, $startNumber, $endNumber);
        $overlap->execute();
        if ($overlap->get_result()->fetch_assoc()) {
            throw new DomainException('One or more numbers in this range are already reserved.');
        }

        $assigned = $conn->prepare("
            SELECT ticket_id
            FROM queue_tickets
            WHERE service_id = ?
              AND DATE(COALESCE(checked_in_at, issued_at)) = ?
              AND ticket_number IS NOT NULL
              AND CAST(SUBSTRING_INDEX(ticket_number, '-', -1) AS UNSIGNED) BETWEEN ? AND ?
            LIMIT 1 FOR UPDATE
        ");
        $assigned->bind_param('isii', $serviceId, $serviceDate, $startNumber, $endNumber);
        $assigned->execute();
        if ($assigned->get_result()->fetch_assoc()) {
            throw new DomainException('One or more numbers in this range have already been assigned.');
        }

        $insertBatch = $conn->prepare("
            INSERT INTO ticket_print_batches
              (service_id, service_date, start_number, end_number, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $insertBatch->bind_param('isiii', $serviceId, $serviceDate, $startNumber, $endNumber, $staffId);
        $insertBatch->execute();
        $batchId = (int) $conn->insert_id;

        $insertReservation = $conn->prepare("
            INSERT INTO ticket_number_reservations
              (batch_id, service_id, service_date, sequence_number)
            VALUES (?, ?, ?, ?)
        ");
        $numbers = [];
        for ($sequence = $startNumber; $sequence <= $endNumber; $sequence++) {
            $insertReservation->bind_param('iisi', $batchId, $serviceId, $serviceDate, $sequence);
            $insertReservation->execute();
            $numbers[] = queueTicketNumberFromSequence($service, $sequence);
        }
        logActivity($conn, 'ticket_batch_created', sprintf(
            'Reserved %d-%d for %s on %s',
            $startNumber,
            $endNumber,
            $service['service_name'],
            $serviceDate
        ));
        if ($activeTransaction) {
            $conn->query('RELEASE SAVEPOINT ' . $savepoint);
        } else {
            $conn->commit();
        }
        return [
            'batch_id' => $batchId,
            'service_id' => $serviceId,
            'service_code' => (string) $service['service_code'],
            'service_name' => (string) $service['service_name'],
            'service_date' => $serviceDate,
            'start_number' => $startNumber,
            'end_number' => $endNumber,
            'numbers' => $numbers,
        ];
    } catch (Throwable $error) {
        if ($activeTransaction) {
            $conn->query('ROLLBACK TO SAVEPOINT ' . $savepoint);
        } else {
            $conn->rollback();
        }
        if ($error instanceof mysqli_sql_exception && (int) $error->getCode() === 1062) {
            throw new DomainException('One or more numbers in this range are already reserved.', 0, $error);
        }
        throw $error;
    }
}

function renderTicketPrintBatchPdf(array $batch): string {
    $autoload = __DIR__ . '/../../vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('The PDF renderer is not installed.');
    }
    require_once $autoload;
    if (!class_exists(\Dompdf\Dompdf::class)) {
        throw new RuntimeException('The PDF renderer is unavailable.');
    }
    $service = htmlspecialchars((string) $batch['service_name'], ENT_QUOTES, 'UTF-8');
    $date = htmlspecialchars(date('F j, Y', strtotime((string) $batch['service_date'])), ENT_QUOTES, 'UTF-8');
    $tickets = '';
    foreach ((array) $batch['numbers'] as $number) {
        $tickets .= '<section class="ticket"><div class="brand">Barangay Health Center</div>'
            . '<div class="service">' . $service . '</div>'
            . '<div class="number">' . htmlspecialchars((string) $number, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div class="date">' . $date . '</div><div class="note">Please wait for your number to be called.</div></section>';
    }
    $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
        . '@page{size:A4 portrait;margin:10mm}body{font-family:DejaVu Sans,sans-serif;color:#172033;margin:0}'
        . '.sheet{font-size:0}.ticket{box-sizing:border-box;display:inline-block;vertical-align:top;width:50%;height:67mm;'
        . 'border:1px dashed #77839a;text-align:center;padding:8mm 5mm;font-size:12pt;page-break-inside:avoid}'
        . '.brand{font-size:10pt;text-transform:uppercase;letter-spacing:1px;color:#516079}.service{font-weight:700;margin-top:7mm}'
        . '.number{font-size:35pt;font-weight:800;color:#087fce;margin:6mm 0 4mm}.date{font-size:10pt}.note{font-size:8pt;color:#65748b;margin-top:5mm}'
        . '</style></head><body><main class="sheet">' . $tickets . '</main></body></html>';
    $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    return $dompdf->output();
}
