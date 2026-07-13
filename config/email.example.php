<?php
/**
 * SmartQMS -- Email example config.
 * Copy these constants into config/email.local.php for local development.
 *
 * EMAIL_DELIVERY_MODE:
 * - log: write OTP messages to storage/logs/email_outbox.log for XAMPP/demo testing.
 * - smtp: send real email through Gmail SMTP with a Gmail App Password.
 */
defined('EMAIL_DELIVERY_MODE') || define('EMAIL_DELIVERY_MODE', 'smtp');
defined('EMAIL_SMTP_HOST') || define('EMAIL_SMTP_HOST', 'smtp.gmail.com');
defined('EMAIL_SMTP_PORT') || define('EMAIL_SMTP_PORT', 587);
defined('EMAIL_SMTP_SECURE') || define('EMAIL_SMTP_SECURE', 'tls');
defined('EMAIL_SMTP_USERNAME') || define('EMAIL_SMTP_USERNAME', 'your-email@gmail.com');
defined('EMAIL_SMTP_PASSWORD') || define('EMAIL_SMTP_PASSWORD', 'your-gmail-app-password');
defined('EMAIL_FROM') || define('EMAIL_FROM', 'your-email@gmail.com');
defined('EMAIL_FROM_NAME') || define('EMAIL_FROM_NAME', 'SmartQMS');
?>
