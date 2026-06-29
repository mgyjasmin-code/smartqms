<?php
/**
 * SmartQMS -- Send Turn Alert
 * Triggered when a client is 2 tickets away from being served.
 * Sends both browser notification (via JS push) and SMS.
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once 'sms_sender.php';

// TODO: Check how many tickets are ahead of target client
// TODO: If people_ahead <= 2 AND no alert sent yet for this ticket:
//   - Insert into notifications table (channel='both')
//   - Call sendSMS() with turn alert message
//   - Browser notification handled client-side via get_notifications.php
?>
