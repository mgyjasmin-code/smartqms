<?php
/**
 * OTP generation, persistence, and email-queue coordination.
 */

function issueOtp(mysqli $conn, int $userId, string $messagePrefix, string $type = 'otp', bool $queue = true): bool {
    ensureAuthSecuritySchema($conn);
    $user = authUserById($conn, $userId);
    if (!$user || empty($user['email'])) {
        return false;
    }

    $otp = (string) random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_BCRYPT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    storeAuthOtp($conn, $userId, $otpHash, $expiresAt);

    $subject = 'Your SmartQMS verification code';
    $message = $messagePrefix . " {$otp}.\n\nThis code expires in 10 minutes. If you did not request this code, you can ignore this email.";

    if ($queue) {
        cancelPendingEmailJobs($conn, $userId, $type);
        return queueEmail($conn, $user['email'], $subject, $message, $type, $userId);
    }

    return sendEmail($user['email'], $subject, $message, $type, $userId);
}

function otpVerificationStatus(?array $record, string $otp, ?int $now = null): string {
    if (!$record || empty($record['otp_hash']) || !password_verify($otp, (string) $record['otp_hash'])) {
        return 'wrong';
    }

    $expiresAt = strtotime((string) ($record['otp_expires_at'] ?? ''));
    if ($expiresAt < ($now ?? time())) {
        return 'expired';
    }

    return 'valid';
}

function verifyStoredOtp(mysqli $conn, int $userId, string $otp): string {
    ensureAuthSecuritySchema($conn);
    return otpVerificationStatus(authOtpRecordByUserId($conn, $userId), $otp);
}

function otpMessagePrefixForFlow(string $flow): string {
    if ($flow === 'reset') {
        return 'Your SmartQMS password reset code is';
    }
    return 'Your SmartQMS verification code is';
}

function otpResendCooldownActive(int $lastSentAt, ?int $now = null): bool {
    return $lastSentAt > 0
        && ($now ?? time()) - $lastSentAt < OTP_RESEND_COOLDOWN_SECONDS;
}
