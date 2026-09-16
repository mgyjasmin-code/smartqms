<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/public_intake.php';
requireLogin(ROLE_STAFF);
requirePostRequest(true);
requireValidCsrf('', '', [], 'Security check failed. Refresh the page and try again.', true);

$staffId = getCurrentStaffId($conn);
if (!$staffId) {
    jsonResponse(false, ['error' => 'An active Staff profile is required.'], 403);
}
$staffWindow = getStaffWindow($conn, (int) $staffId);
if (!$staffWindow) {
    jsonResponse(false, ['error' => 'Claim a service counter before registering a walk-in client.'], 409);
}

$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$phone = normalizePhone((string) ($_POST['phone_number'] ?? ''));
$serviceId = filter_var($_POST['service_id'] ?? null, FILTER_VALIDATE_INT);
if ($firstName === '' || strlen($firstName) > 50 || $lastName === '' || strlen($lastName) > 50) {
    jsonResponse(false, ['error' => 'Enter the walk-in client’s first and last name.'], 422);
}
if (!isValidPhMobile($phone)) {
    jsonResponse(false, ['error' => 'Enter an 11-digit mobile number beginning with 09.'], 422);
}
$allowedServiceIds = getWindowServiceIds($conn, $staffWindow);
if ($serviceId === false || !in_array((int) $serviceId, $allowedServiceIds, true)) {
    jsonResponse(false, ['error' => 'Choose a health service assigned to your active counter.'], 422);
}

try {
    $ticket = createWalkInTicketForStaff(
        $conn,
        (int) $staffId,
        $firstName,
        $lastName,
        $phone,
        (int) $serviceId
    );
    jsonResponse(true, ['data' => $ticket]);
} catch (DomainException $error) {
    jsonResponse(false, ['error' => $error->getMessage()], 422);
} catch (Throwable $error) {
    error_log('Staff walk-in intake failed: ' . $error->getMessage());
    jsonResponse(false, ['error' => 'The walk-in ticket could not be created.'], 500);
}
