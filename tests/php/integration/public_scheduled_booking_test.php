<?php

require_once SMARTQMS_ROOT . '/modules/queue/public_intake.php';
require_once SMARTQMS_ROOT . '/modules/queue/reservation_management.php';

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

testCase('token-authorized reservation management is isolated, reschedules, and revokes on cancellation', function (): void {
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
        $newVisitDate = date('Y-m-d', strtotime('+9 days'));

        $rescheduled = rescheduleSessionReservation($connection, (int) $created['ticket_id'], $newVisitDate);
        assertSameValue($newVisitDate, $rescheduled['visit_date']);
        assertTrueValue($rescheduled['can_manage']);

        $cancelled = cancelSessionReservation($connection, (int) $created['ticket_id']);
        assertSameValue('void', $cancelled['status']);
        assertFalseValue($cancelled['can_manage']);
        assertSameValue(null, publicManagedReservationByToken($connection, $managementToken));
        $row = $connection->query('SELECT lifecycle_status, status, voided_reason, manage_token_revoked_at FROM queue_tickets WHERE ticket_id=' . (int) $created['ticket_id'])->fetch_assoc();
        assertSameValue('void', $row['lifecycle_status']);
        assertSameValue('voided', $row['status']);
        assertStringContains('token-authorized', (string) $row['voided_reason']);
        assertTrueValue(!empty($row['manage_token_revoked_at']));
    });
});
