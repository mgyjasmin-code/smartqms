<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$reference = strtoupper(trim((string) ($_GET['ref'] ?? '')));
$ticket = null;

if ($reference !== '' && preg_match('/^[A-Z0-9-]{6,32}$/', $reference)) {
    $stmt = $conn->prepare("
        SELECT qt.ticket_id, qt.service_id, qt.reference_number, qt.ticket_number,
               qt.priority_level, qt.status, qt.issued_at,
               hs.service_name, sw.window_name, wl.predicted_wait_min
        FROM queue_tickets qt
        JOIN health_services hs ON hs.service_id = qt.service_id
        LEFT JOIN service_windows sw ON sw.window_id = qt.window_id
        LEFT JOIN wait_time_logs wl ON wl.ticket_id = qt.ticket_id
        WHERE qt.reference_number = ?
        ORDER BY wl.logged_at DESC
        LIMIT 1
    ");
    $stmt->bind_param('s', $reference);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc() ?: null;
}

$peopleAhead = $ticket && in_array($ticket['status'], ['waiting', 'serving'], true)
    ? peopleAhead($conn, $ticket)
    : 0;
$estimatedWait = $ticket && $ticket['predicted_wait_min'] !== null
    ? '~' . rtrim(rtrim(number_format((float) $ticket['predicted_wait_min'], 1), '0'), '.') . ' minutes'
    : 'Calculating';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ticket Status -- SmartQMS</title>
  <link href="<?= assetUrl('vendor/twbs/bootstrap/dist/css/bootstrap.min.css') ?>" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
</head>
<body class="client-page public-ticket-page">
  <main class="client-shell public-ticket-shell">
    <section class="public-ticket-card">
      <div class="public-ticket-brand">
        <span>Smart QMS</span>
        <small>Barangay Health Center</small>
      </div>

      <?php if (!$ticket): ?>
        <div class="public-ticket-empty">
          <i class="bi bi-qr-code-scan" aria-hidden="true"></i>
          <h1>Ticket not found</h1>
          <p>Please check the QR code or ask the service desk to verify your reference number.</p>
        </div>
      <?php else: ?>
        <div class="public-ticket-status">
          <span class="queue-status-pill queue-status-<?= htmlspecialchars($ticket['status']) ?>"><?= htmlspecialchars(strtoupper($ticket['status'])) ?></span>
          <h1><?= htmlspecialchars($ticket['ticket_number']) ?></h1>
          <p><?= htmlspecialchars($ticket['service_name']) ?></p>
        </div>

        <div class="public-ticket-grid">
          <div>
            <span>Reference</span>
            <strong><?= htmlspecialchars($ticket['reference_number']) ?></strong>
          </div>
          <div>
            <span>People Ahead</span>
            <strong><?= (int) $peopleAhead ?></strong>
          </div>
          <div>
            <span>Estimated Wait</span>
            <strong><?= htmlspecialchars($estimatedWait) ?></strong>
          </div>
          <div>
            <span>Window</span>
            <strong><?= htmlspecialchars($ticket['window_name'] ?? 'Not called yet') ?></strong>
          </div>
        </div>

        <p class="public-ticket-note">
          This page only shows ticket status details. It does not expose personal client information.
        </p>

        <button class="btn btn-primary ticket-print-button" type="button" data-ticket-print>
          <i class="bi bi-printer" aria-hidden="true"></i>
          Print or Save Ticket
        </button>
      <?php endif; ?>
    </section>
  </main>
  <script src="<?= assetUrl('assets/js/client.js') ?>"></script>
</body>
</html>
