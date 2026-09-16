<?php
/**
 * SmartQMS -- Email Sender
 * Uses PHPMailer SMTP for real email, or a local outbox log for development.
 */
require_once __DIR__ . '/../../config/config.php';

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

$localEmailConfig = __DIR__ . '/../../config/email.local.php';
if (APP_ENV !== 'production' && is_file($localEmailConfig)) {
    require_once $localEmailConfig;
}

function smartqmsEmailEnvironmentValue(string $key, string $fallback): string {
    $value = getenv($key);
    return $value === false ? $fallback : trim((string) $value);
}

defined('EMAIL_DELIVERY_MODE') || define('EMAIL_DELIVERY_MODE', smartqmsEmailEnvironmentValue('EMAIL_DELIVERY_MODE', 'log'));
defined('EMAIL_SMTP_HOST') || define('EMAIL_SMTP_HOST', smartqmsEmailEnvironmentValue('EMAIL_SMTP_HOST', 'smtp.gmail.com'));
defined('EMAIL_SMTP_PORT') || define('EMAIL_SMTP_PORT', (int) smartqmsEmailEnvironmentValue('EMAIL_SMTP_PORT', '587'));
defined('EMAIL_SMTP_SECURE') || define('EMAIL_SMTP_SECURE', smartqmsEmailEnvironmentValue('EMAIL_SMTP_SECURE', 'tls'));
defined('EMAIL_SMTP_USERNAME') || define('EMAIL_SMTP_USERNAME', smartqmsEmailEnvironmentValue('EMAIL_SMTP_USERNAME', ''));
defined('EMAIL_SMTP_PASSWORD') || define('EMAIL_SMTP_PASSWORD', smartqmsEmailEnvironmentValue('EMAIL_SMTP_PASSWORD', ''));
defined('EMAIL_FROM') || define('EMAIL_FROM', smartqmsEmailEnvironmentValue('EMAIL_FROM', 'no-reply@smartqms.local'));
defined('EMAIL_FROM_NAME') || define('EMAIL_FROM_NAME', smartqmsEmailEnvironmentValue('EMAIL_FROM_NAME', 'SmartQMS'));

if (APP_ENV === 'production' && EMAIL_DELIVERY_MODE !== 'smtp') {
    throw new RuntimeException('Production requires EMAIL_DELIVERY_MODE=smtp.');
}

function emailOutboxPath(): string {
    $dir = defined('LOG_DIR') ? LOG_DIR : __DIR__ . '/../../storage/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir . '/email_outbox.log';
}

function emailErrorLogPath(): string {
    $dir = defined('LOG_DIR') ? LOG_DIR : __DIR__ . '/../../storage/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir . '/email_errors.log';
}

function logEmailError(string $message): void {
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents(emailErrorLogPath(), $entry, FILE_APPEND | LOCK_EX);
}

function cancelPendingEmailJobs(mysqli $conn, int $userId, string $type = 'otp'): void {
    if (!ensureEmailJobsTable($conn)) {
        return;
    }
    $stmt = $conn->prepare("
        UPDATE email_jobs
        SET status = 'cancelled',
            message = '[redacted]',
            error_msg = 'Superseded by a newer email.'
        WHERE user_id = ? AND type = ? AND status = 'pending'
    ");
    if (!$stmt) {
        logEmailError('Could not prepare email job cancellation: ' . $conn->error);
        return;
    }
    $stmt->bind_param('is', $userId, $type);
    $stmt->execute();
}

function queueEmail(mysqli $conn, string $to, string $subject, string $message, string $type = 'notification', int $userId = 0): bool {
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        logEmailError('Invalid queued recipient email: ' . $to);
        return false;
    }

    if (!ensureEmailJobsTable($conn)) {
        return false;
    }
    $stmt = $conn->prepare("
        INSERT INTO email_jobs (user_id, recipient_email, subject, message, type)
        VALUES (NULLIF(?, 0), ?, ?, ?, ?)
    ");
    if (!$stmt) {
        logEmailError('Could not prepare email job insert: ' . $conn->error);
        return false;
    }
    $stmt->bind_param('issss', $userId, $to, $subject, $message, $type);
    return $stmt->execute();
}

function dispatchQueuedEmails(mysqli $conn, ?int $userId = null, int $limit = 5): array {
    if (!ensureEmailJobsTable($conn)) {
        return ['processed' => 0, 'sent' => 0, 'failed' => 1, 'skipped' => 0];
    }
    $limit = max(1, min(20, $limit));

    if ($userId) {
        $stmt = $conn->prepare("
            SELECT job_id, user_id, recipient_email, subject, message, type
            FROM email_jobs
            WHERE status = 'pending' AND user_id = ? AND available_at <= NOW()
            ORDER BY created_at ASC
            LIMIT {$limit}
        ");
        $stmt->bind_param('i', $userId);
    } else {
        $stmt = $conn->prepare("
            SELECT job_id, user_id, recipient_email, subject, message, type
            FROM email_jobs
            WHERE status = 'pending' AND available_at <= NOW()
            ORDER BY created_at ASC
            LIMIT {$limit}
        ");
    }

    if (!$stmt) {
        logEmailError('Could not prepare email job dispatch: ' . $conn->error);
        return ['processed' => 0, 'sent' => 0, 'failed' => 1, 'skipped' => 0];
    }

    $stmt->execute();
    $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $result = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];

    foreach ($jobs as $job) {
        $jobId = (int) $job['job_id'];
        $claim = $conn->prepare("UPDATE email_jobs SET status = 'processing', attempts = attempts + 1 WHERE job_id = ? AND status = 'pending'");
        $claim->bind_param('i', $jobId);
        $claim->execute();

        if ($claim->affected_rows < 1) {
            $result['skipped']++;
            continue;
        }

        $result['processed']++;
        $sent = sendEmail(
            $job['recipient_email'],
            $job['subject'],
            $job['message'],
            $job['type'],
            (int) ($job['user_id'] ?? 0)
        );

        if ($sent) {
            $done = $conn->prepare("UPDATE email_jobs SET status = 'sent', message = '[redacted]', sent_at = NOW(), error_msg = NULL WHERE job_id = ?");
            $done->bind_param('i', $jobId);
            $done->execute();
            $result['sent']++;
        } else {
            $error = 'Email send failed. Please check SMTP settings.';
            $failed = $conn->prepare("UPDATE email_jobs SET status = 'failed', message = '[redacted]', error_msg = ? WHERE job_id = ?");
            $failed->bind_param('si', $error, $jobId);
            $failed->execute();
            $result['failed']++;
        }
    }

    if ($result['failed'] > 0) {
        $result['message'] = 'Could not send OTP email. Please check the SMTP app password and try again.';
    }

    return $result;
}

function sendEmail(string $to, string $subject, string $message, string $type = 'notification', int $userId = 0): bool {
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        logEmailError('Invalid recipient email: ' . $to);
        return false;
    }

    if (EMAIL_DELIVERY_MODE === 'smtp') {
        if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            logEmailError('PHPMailer is not installed. Run composer install.');
            return false;
        }

        if (EMAIL_SMTP_USERNAME === '' || EMAIL_SMTP_PASSWORD === '' || EMAIL_SMTP_PASSWORD === 'your-gmail-app-password') {
            logEmailError('Missing Gmail SMTP username or App Password.');
            return false;
        }

        try {
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = EMAIL_SMTP_HOST;
            $mailer->SMTPAuth = true;
            $smtpPassword = EMAIL_SMTP_PASSWORD;
            if (strtolower((string) EMAIL_SMTP_HOST) === 'smtp.gmail.com') {
                $smtpPassword = preg_replace('/\s+/', '', $smtpPassword);
            }

            $mailer->Username = EMAIL_SMTP_USERNAME;
            $mailer->Password = $smtpPassword;
            $mailer->Port = (int) EMAIL_SMTP_PORT;
            $mailer->CharSet = 'UTF-8';

            if (EMAIL_SMTP_SECURE === 'tls') {
                $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif (EMAIL_SMTP_SECURE === 'ssl') {
                $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            }

            $from = (EMAIL_FROM !== '' && EMAIL_FROM !== 'no-reply@smartqms.local')
                ? EMAIL_FROM
                : EMAIL_SMTP_USERNAME;
            $mailer->setFrom($from, EMAIL_FROM_NAME);
            $mailer->addAddress($to);
            $mailer->Subject = $subject;
            $mailer->Body = $message;
            $mailer->AltBody = $message;

            return $mailer->send();
        } catch (Throwable $e) {
            $hint = str_contains($e->getMessage(), 'Could not authenticate')
                ? ' Check the Gmail address and use a current 16-character App Password without spaces.'
                : '';
            logEmailError('SMTP send failed for ' . $to . ': ' . $e->getMessage() . $hint);
            return false;
        }
    }

    // Log mode keeps local testing free and avoids sending real OTP messages.
    $entry = sprintf(
        "[%s] type=%s user_id=%d to=%s subject=%s message=%s%s",
        date('Y-m-d H:i:s'),
        $type,
        $userId,
        $to,
        str_replace(["\r", "\n"], ' ', $subject),
        str_replace(["\r", "\n"], ' ', $message),
        PHP_EOL
    );

    return file_put_contents(emailOutboxPath(), $entry, FILE_APPEND | LOCK_EX) !== false;
}
?>
