<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/counter_claim.php';

requireLogin(ROLE_STAFF);
requirePostRequest(false, 'staff/select-counter/', 'counter_claim');
requireValidCsrf('staff/select-counter/', 'counter_claim');

$staffId = getCurrentStaffId($conn);
$counterId = filter_var($_POST['counter_id'] ?? null, FILTER_VALIDATE_INT);
if (!$staffId || $counterId === false || (int) $counterId < 1) {
    redirectWithFormFeedback('staff/select-counter/', 'counter_claim', [], [], 'Choose an available service counter.');
}

try {
    $result = claimCounterForStaff($conn, (int) $staffId, (int) $counterId);
    if (($result['status'] ?? '') === 'success') {
        redirectTo('views/staff/dashboard.php');
    }
    $messages = [
        'active_ticket' => 'Complete or close the current ticket before changing counters.',
        'unavailable' => 'That counter was claimed by another staff member.',
        'not_eligible' => 'Your staff profile is not authorized for that specialized counter.',
        'not_found' => 'The selected counter is no longer available.',
    ];
    redirectWithFormFeedback(
        'staff/select-counter/',
        'counter_claim',
        [],
        [],
        $messages[$result['status'] ?? ''] ?? 'The counter could not be claimed.'
    );
} catch (Throwable $error) {
    error_log('Counter claim failed: ' . $error->getMessage());
    redirectWithFormFeedback('staff/select-counter/', 'counter_claim', [], [], 'The counter could not be claimed.');
}
