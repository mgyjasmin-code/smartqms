<?php

require_once SMARTQMS_ROOT . '/modules/queue/public_intake.php';
require_once SMARTQMS_ROOT . '/modules/queue/reservation_management.php';
require_once SMARTQMS_ROOT . '/modules/notifications/send_alert.php';

testCase('booking SMS is logged once per public ticket when delivery is simulated', function (): void {
    $previousEnabled = getenv('SMARTQMS_SMS_ENABLED');
    putenv('SMARTQMS_SMS_ENABLED=0');
    try {
        withTestTransaction(function (mysqli $connection): void {
            $code = 'CHAR-SMS-ONCE';
            $name = 'SMS Booking Test';
            $encoded = 115;
            $order = 115;
            $service = $connection->prepare('INSERT INTO health_services (service_code, service_name, service_encoded, display_order, priority_only) VALUES (?, ?, ?, ?, 0)');
            $service->bind_param('ssii', $code, $name, $encoded, $order);
            $service->execute();
            $ticket = createPublicQueueTicket($connection, 'SMS', 'Booking', '09171234567', (int) $connection->insert_id, 'regular', 'online', false);
            $ticketId = (int) $ticket['ticket_id'];
            $message = bookingConfirmationSms((string) $ticket['reference_number'], (string) $ticket['visit_date']);
            $connection->query("UPDATE queue_tickets SET lifecycle_status='void' WHERE ticket_id={$ticketId}");
            assertFalseValue(sendBookingConfirmationForTicket($connection, $ticketId));
            $connection->query("UPDATE queue_tickets SET lifecycle_status='scheduled' WHERE ticket_id={$ticketId}");
            assertTrueValue(sendBookingConfirmationForTicket($connection, $ticketId));
            assertFalseValue(sendBookingConfirmationForTicket($connection, $ticketId));
            $event = $connection->query("SELECT status FROM ticket_sms_events WHERE ticket_id={$ticketId} AND event_type='booking_confirmation'")->fetch_assoc();
            assertSameValue('simulated', $event['status']);
            $logs = $connection->query("SELECT COUNT(*) AS total FROM sms_logs WHERE phone='09171234567' AND message='" . $connection->real_escape_string($message) . "'")->fetch_assoc();
            assertSameValue(1, (int) $logs['total']);
        });
    } finally {
        $previousEnabled === false ? putenv('SMARTQMS_SMS_ENABLED') : putenv('SMARTQMS_SMS_ENABLED=' . $previousEnabled);
    }
});

testCase('public near-turn candidates appear only after arrival check-in', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $code = 'CHAR-SMS-ARRIVAL';
        $name = 'SMS Arrival Test';
        $encoded = 116;
        $order = 116;
        $service = $connection->prepare('INSERT INTO health_services (service_code, service_name, service_encoded, display_order, priority_only) VALUES (?, ?, ?, ?, 0)');
        $service->bind_param('ssii', $code, $name, $encoded, $order);
        $service->execute();
        $serviceId = (int) $connection->insert_id;
        $ticket = createPublicQueueTicket($connection, 'SMS', 'Candidate', '09171234567', $serviceId, 'regular', 'online', false);
        $ticketId = (int) $ticket['ticket_id'];
        assertSameValue([], nearTurnAlertCandidates($connection, $serviceId));

        $number = 'S-001';
        $update = $connection->prepare("UPDATE queue_tickets SET lifecycle_status = 'waiting', checked_in_at = NOW(), ticket_number = ? WHERE ticket_id = ?");
        $update->bind_param('si', $number, $ticketId);
        $update->execute();
        $candidates = nearTurnAlertCandidates($connection, $serviceId);
        assertSameValue(1, count($candidates));
        assertSameValue($ticketId, (int) $candidates[0]['ticket_id']);
        assertSameValue('09171234567', $candidates[0]['phone_number']);
        assertSameValue(0, (int) $candidates[0]['people_ahead']);
    });
});

testCase('online booking persists a same-day Scheduled ticket without queue or ML data', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $code = 'CHAR-ONLINE';
        $name = 'Characterization Online Booking';
        $encoded = 119;
        $order = 119;
        $service = $connection->prepare('INSERT INTO health_services (service_code, service_name, service_encoded, display_order, priority_only) VALUES (?, ?, ?, ?, 0)');
        $service->bind_param('ssii', $code, $name, $encoded, $order);
        $service->execute();
        $serviceId = (int) $connection->insert_id;

        $created = createPublicQueueTicket(
            $connection,
            'Characterization',
            'Scheduled Client',
            '09171234567',
            $serviceId,
            'pwd',
            'online',
            false
        );
        $ticketId = (int) $created['ticket_id'];
        $row = $connection->query('SELECT * FROM queue_tickets WHERE ticket_id=' . $ticketId)->fetch_assoc();

        assertSameValue('scheduled', $row['lifecycle_status']);
        assertSameValue('waiting', $row['status']);
        assertSameValue(null, $row['ticket_number']);
        assertSameValue(null, $row['checked_in_at']);
        assertSameValue('Characterization', $row['client_first_name']);
        assertSameValue('Scheduled Client', $row['client_last_name']);
        assertSameValue('regular', $row['client_type']);
        assertSameValue(0, (int) $row['priority_level']);
        assertSameValue(date('Y-m-d'), date('Y-m-d', strtotime((string) $row['scheduled_expires_at'])));
        assertSameValue(0, (int) $connection->query('SELECT COUNT(*) AS total FROM wait_time_logs WHERE ticket_id=' . $ticketId)->fetch_assoc()['total']);
        assertSameValue(null, getNextWaitingTicketForUpdate($connection, $serviceId));

        $reference = publicQueueTicketByReference($connection, (string) $created['reference_number']);
        $public = publicReferenceStatusProjection($reference ?: []);
        assertSameValue('Scheduled', $public['status_label']);
        foreach (['client_name', 'phone_number', 'ticket_token', 'ticket_id', 'ticket_number', 'counter_label', 'checked_in_at'] as $privateKey) {
            assertFalseValue(array_key_exists($privateKey, $public));
        }
        $legacyReference = 'BHC-2098-999999';
        $legacyUpdate = $connection->prepare('UPDATE queue_tickets SET reference_number = ? WHERE ticket_id = ?');
        $legacyUpdate->bind_param('si', $legacyReference, $ticketId);
        $legacyUpdate->execute();
        assertSameValue($legacyReference, publicQueueTicketByReference($connection, $legacyReference)['reference_number']);
        $earlierNumericReference = '2026000046';
        $legacyUpdate->bind_param('si', $earlierNumericReference, $ticketId);
        $legacyUpdate->execute();
        assertSameValue($earlierNumericReference, publicQueueTicketByReference($connection, $earlierNumericReference)['reference_number']);
    });
});

testCase('online booking stores the selected future visit date without assigning FIFO position', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $code = 'CHAR-FUTURE';
        $name = 'Future Visit Booking';
        $encoded = 118;
        $order = 118;
        $service = $connection->prepare('INSERT INTO health_services (service_code, service_name, service_encoded, display_order, priority_only) VALUES (?, ?, ?, ?, 0)');
        $service->bind_param('ssii', $code, $name, $encoded, $order);
        $service->execute();
        $serviceId = (int) $connection->insert_id;
        $visitDate = date('Y-m-d', strtotime('+7 days'));

        $created = createPublicQueueTicket(
            $connection,
            'Future',
            'Reservation',
            '09171234567',
            $serviceId,
            'regular',
            'online',
            false,
            $visitDate
        );
        $row = $connection->query('SELECT * FROM queue_tickets WHERE ticket_id=' . (int) $created['ticket_id'])->fetch_assoc();

        assertSameValue($visitDate, date('Y-m-d', strtotime((string) $row['scheduled_expires_at'])));
        assertSameValue($visitDate, $created['visit_date']);
        assertSameValue('scheduled', $row['lifecycle_status']);
        assertSameValue(null, $row['ticket_number']);
        assertSameValue(null, $row['checked_in_at']);
        assertSameValue(0, (int) $connection->query('SELECT COUNT(*) AS total FROM wait_time_logs WHERE ticket_id=' . (int) $created['ticket_id'])->fetch_assoc()['total']);
    });
});

testCase('token-authorized reservation cancellation is isolated and revokes access', function (): void {
    withTestTransaction(function (mysqli $connection): void {
        $code = 'CHAR-MANAGE';
        $name = 'Managed Visit Booking';
        $encoded = 117;
        $order = 117;
        $service = $connection->prepare('INSERT INTO health_services (service_code, service_name, service_encoded, display_order, priority_only) VALUES (?, ?, ?, ?, 0)');
        $service->bind_param('ssii', $code, $name, $encoded, $order);
        $service->execute();
        $serviceId = (int) $connection->insert_id;

        $created = createPublicQueueTicket(
            $connection,
            'Managed',
            'Reservation',
            '09171234567',
            $serviceId,
            'regular',
            'online',
            false,
            date('Y-m-d', strtotime('+4 days'))
        );
        $managementToken = (string) $created['management_token'];
        assertTrueValue(preg_match('/^[a-f0-9]{64}$/', $managementToken) === 1);
        $stored = $connection->query('SELECT manage_token_hash FROM queue_tickets WHERE ticket_id=' . (int) $created['ticket_id'])->fetch_assoc();
        assertSameValue(hash('sha256', $managementToken), $stored['manage_token_hash']);
        assertFalseValue(hash_equals((string) $stored['manage_token_hash'], $managementToken));
        assertSameValue(null, publicManagedReservationByToken($connection, str_repeat('0', 64)));
        $authorized = publicManagedReservationByToken($connection, $managementToken);
        assertSameValue((int) $created['ticket_id'], (int) $authorized['ticket_id']);
        $cancelled = cancelSessionReservation($connection, (int) $created['ticket_id']);
        assertSameValue('void', $cancelled['status']);
        assertFalseValue($cancelled['can_manage']);
        assertSameValue(null, publicManagedReservationByToken($connection, $managementToken));
        $row = $connection->query('SELECT lifecycle_status, status, scheduled_expires_at, voided_reason, manage_token_revoked_at FROM queue_tickets WHERE ticket_id=' . (int) $created['ticket_id'])->fetch_assoc();
        assertSameValue('void', $row['lifecycle_status']);
        assertSameValue('voided', $row['status']);
        assertSameValue($created['visit_date'], date('Y-m-d', strtotime((string) $row['scheduled_expires_at'])));
        assertStringContains('token-authorized', (string) $row['voided_reason']);
        assertTrueValue(!empty($row['manage_token_revoked_at']));
    });
});
