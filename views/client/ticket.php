<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_CLIENT);

$ticket = getActiveTicket($conn, (int) $_SESSION['user_id']);
if (!$ticket) {
    $stmt = $conn->prepare("
        SELECT qt.*, hs.service_name, sw.window_name, wl.predicted_wait_min
        FROM queue_tickets qt
        JOIN health_services hs ON hs.service_id=qt.service_id
        LEFT JOIN service_windows sw ON sw.window_id=qt.window_id
        LEFT JOIN wait_time_logs wl ON wl.ticket_id=qt.ticket_id
        WHERE qt.user_id=?
        ORDER BY qt.issued_at DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
}

$peopleAhead = $ticket && in_array($ticket['status'], ['waiting', 'serving'], true) ? peopleAhead($conn, $ticket) : 0;

if ($ticket && empty($ticket['qr_code_path'])) {
    require_once __DIR__ . '/../../modules/queue/qr_generate.php';
    try {
        $generatedQrPath = generateQR($ticket['reference_number'], (string) $ticket['ticket_id']);
        $qrUpdate = $conn->prepare("UPDATE queue_tickets SET qr_code_path = ? WHERE ticket_id = ?");
        $ticketId = (int) $ticket['ticket_id'];
        $qrUpdate->bind_param('si', $generatedQrPath, $ticketId);
        $qrUpdate->execute();
        $ticket['qr_code_path'] = $generatedQrPath;
    } catch (Throwable $e) {
        $ticket['qr_code_path'] = '';
    }
}

$qrPath = $ticket['qr_code_path'] ?? '';
$qrFile = $qrPath ? __DIR__ . '/../../' . ltrim($qrPath, '/') : '';
$hasQrImage = $qrPath && is_file($qrFile);
$estimatedWait = $ticket && $ticket['predicted_wait_min'] !== null
    ? '~' . rtrim(rtrim(number_format((float) $ticket['predicted_wait_min'], 1), '0'), '.') . ' minutes'
    : 'Calculating';
$clientTypeLabel = $ticket ? match ($ticket['client_type']) {
    'senior' => 'Senior Citizen',
    'pwd' => 'PWD',
    default => 'Regular',
} : '';
$isPriority = $ticket && in_array($ticket['client_type'], ['senior', 'pwd'], true);
$progressWidth = $ticket && in_array($ticket['status'], ['waiting', 'serving'], true)
    ? ($peopleAhead === 0 ? 100 : max(12, min(88, 100 - ($peopleAhead * 14))))
    : 100;
$issuedAt = $ticket ? date('g:i A', strtotime($ticket['issued_at'])) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Your Ticket -- SmartQMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
</head>
<body class="client-page">
  <main class="client-shell">
    <div class="client-subnav">
      <a href="index.php" class="btn btn-link px-0">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        Back to dashboard
      </a>
      <a href="queue_status.php" class="btn btn-outline-primary">
        <i class="bi bi-display" aria-hidden="true"></i>
        Queue Status
      </a>
    </div>
    <?php if (!$ticket): ?>
      <section class="client-panel empty-ticket-panel">
        <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
        <h1>No tickets yet</h1>
        <p>Join the queue from your dashboard to receive your digital ticket.</p>
        <a class="btn btn-primary" href="index.php">Get Queue Number</a>
      </section>
    <?php else: ?>
      <section class="digital-ticket-hero">
        <span class="live-update-pill"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Live Queue Updates Active</span>
        <h1>Your Digital Ticket</h1>
        <p>Please present this ticket at the counter when your number is called.</p>
      </section>

      <section class="digital-ticket-layout">
        <article class="digital-ticket-card" aria-label="Client queue ticket">
          <div class="ticket-top-line"></div>
          <?php if ($isPriority): ?>
            <span class="ticket-priority-pill">Priority - <?= htmlspecialchars($clientTypeLabel) ?></span>
          <?php else: ?>
            <span class="ticket-priority-pill ticket-priority-regular"><?= htmlspecialchars($clientTypeLabel) ?></span>
          <?php endif; ?>

          <p class="ticket-service-name"><?= htmlspecialchars($ticket['service_name']) ?></p>
          <div class="digital-ticket-number"><?= htmlspecialchars($ticket['ticket_number']) ?></div>
          <p class="digital-ticket-reference">Ref: <?= htmlspecialchars($ticket['reference_number']) ?></p>

          <div class="digital-ticket-qr">
            <?php if ($hasQrImage): ?>
              <img src="<?= htmlspecialchars(APP_URL . '/' . ltrim($qrPath, '/'), ENT_QUOTES) ?>" alt="QR code for ticket <?= htmlspecialchars($ticket['reference_number']) ?>">
            <?php else: ?>
              <div class="qr-placeholder" aria-hidden="true">
                <i class="bi bi-qr-code" aria-hidden="true"></i>
              </div>
            <?php endif; ?>
          </div>

          <div class="ticket-progress-block">
            <div>
              <span>Queue Position</span>
              <strong><?= (int) $peopleAhead ?> <?= $peopleAhead === 1 ? 'person' : 'people' ?> ahead</strong>
            </div>
            <span class="queue-status-pill queue-status-<?= htmlspecialchars($ticket['status']) ?>"><?= htmlspecialchars(ucfirst($ticket['status'])) ?></span>
          </div>
          <div class="ticket-progress-track" aria-hidden="true">
            <span style="width: <?= (int) $progressWidth ?>%"></span>
          </div>

          <div class="ticket-estimate-card">
            <span><i class="bi bi-clock" aria-hidden="true"></i></span>
            <div>
              <small>Est. Wait Time</small>
              <strong><?= htmlspecialchars($estimatedWait) ?></strong>
            </div>
          </div>

          <div class="ticket-meta-row">
            <span>Issued: <?= htmlspecialchars($issuedAt) ?></span>
            <span><?= htmlspecialchars($ticket['window_name'] ?? 'Window pending') ?></span>
          </div>

          <button class="btn btn-outline-secondary ticket-print-button" type="button" onclick="window.print()">
            <i class="bi bi-printer" aria-hidden="true"></i>
            Print Ticket
          </button>
        </article>

        <aside class="ticket-side-panel">
          <section class="ticket-update-card">
            <h2><i class="bi bi-bell" aria-hidden="true"></i> Queue Updates</h2>
            <p>You can keep this page open or scan the QR code to view this ticket's safe public status page.</p>
            <div class="ticket-update-row">
              <span><i class="bi bi-display" aria-hidden="true"></i></span>
              <div>
                <strong>Current Status</strong>
                <small><?= htmlspecialchars(ucfirst($ticket['status'])) ?><?= $ticket['window_name'] ? ' at ' . htmlspecialchars($ticket['window_name']) : '' ?></small>
              </div>
            </div>
            <div class="ticket-update-row">
              <span><i class="bi bi-hourglass-split" aria-hidden="true"></i></span>
              <div>
                <strong>Estimated Wait</strong>
                <small><?= htmlspecialchars($estimatedWait) ?></small>
              </div>
            </div>
          </section>

          <div class="ticket-action-grid">
            <a class="btn btn-primary" href="queue_status.php">
              <i class="bi bi-activity" aria-hidden="true"></i>
              Check Updates
            </a>
            <a class="btn btn-outline-primary" href="index.php">
              <i class="bi bi-house" aria-hidden="true"></i>
              Dashboard
            </a>
            <?php if ($ticket['status'] === 'completed'): ?>
              <a class="btn btn-success" href="feedback.php?ticket_id=<?= (int) $ticket['ticket_id'] ?>">
                <i class="bi bi-chat-heart" aria-hidden="true"></i>
                Submit Feedback
              </a>
            <?php endif; ?>
          </div>
        </aside>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>
