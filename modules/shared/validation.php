<?php
/**
 * Shared input normalization and validation helpers.
 */

function normalizePhone(string $phone): string {
    return preg_replace('/\D+/', '', trim($phone));
}

function isValidPhMobile(string $phone): bool {
    return (bool) preg_match('/^09\d{9}$/', $phone);
}

function normalizeEmail(string $email): string {
    return strtolower(trim($email));
}

function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function hasRequiredText(string $value, bool $trim = true): bool {
    return $trim ? trim($value) !== '' : $value !== '';
}

function hasMinimumLength(string $value, int $minimum): bool {
    return strlen($value) >= $minimum;
}

function passwordsMatch(string $password, string $confirmation): bool {
    return $password === $confirmation;
}

function isSixDigitOtp(string $otp): bool {
    return (bool) preg_match('/^\d{6}$/', $otp);
}

function isRatingInRange(int $rating, int $minimum = 1, int $maximum = 5): bool {
    return $rating >= $minimum && $rating <= $maximum;
}

function isPositiveIdentifier(int $identifier): bool {
    return $identifier > 0;
}

function isIntegerInRange(mixed $value, int $minimum, int $maximum): bool {
    $integer = filter_var($value, FILTER_VALIDATE_INT);
    return $integer !== false && $integer >= $minimum && $integer <= $maximum;
}

function isServiceEncodedValue(mixed $value): bool {
    return isIntegerInRange($value, 1, 127);
}

function isServiceDisplayOrder(mixed $value): bool {
    return isIntegerInRange($value, 0, 127);
}

function isAllowedWindowStatus(string $status): bool {
    return in_array($status, ['open', 'busy', 'closed'], true);
}

function isValidReportDate(string $date): bool {
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}
