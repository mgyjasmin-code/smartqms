<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_CLIENT);

$activeTicket = getActiveTicket($conn, (int) $_SESSION['user_id']);
$feedbackTicket = $activeTicket ? null : getCompletedTicketAwaitingFeedback($conn, (int) $_SESSION['user_id']);
$ticket = $activeTicket ?? $feedbackTicket;
$feedbackPending = $feedbackTicket !== null;
$autoOpenFeedback = $feedbackPending && ($_GET['feedback'] ?? '') === '1';

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
$pageTitle = 'Your Ticket';
$pageHeading = 'Your Ticket';
$activePage = 'ticket';
$showClientPageHeader = false;
$msg = $_GET['msg'] ?? '';
$appActionToasts = match ($msg) {
    'feedback_submitted' => [['tone' => 'success', 'message' => 'Thank you for your feedback.']],
    'feedback_unavailable' => [['tone' => 'info', 'message' => 'Feedback has already been submitted or is unavailable for this ticket.']],
    default => [],
};
$publicTicketUrl = $ticket
    ? APP_URL . '/views/client/ticket_lookup.php?ref=' . rawurlencode((string) $ticket['reference_number'])
    : '';
include __DIR__ . '/includes/header.php';
?>
    <?php if ($ticket): ?>
      <section class="digital-ticket-hero">
        <span class="live-update-pill"><i class="bi bi-shield-check" aria-hidden="true"></i> Official Digital Ticket</span>
        <h1>Your Digital Ticket</h1>
        <p>Keep this page or a printed copy ready, and present the QR code at the counter when your number is called.</p>
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
            <span class="status-badge badge-<?= htmlspecialchars($ticket['status']) ?>"><?= htmlspecialchars($ticket['status']) ?></span>
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

          <p class="ticket-qr-instruction">
            <i class="bi bi-qr-code-scan" aria-hidden="true"></i>
            Scan the QR code to open the ticket's public status page, or show this screen to health-center staff.
          </p>

          <p class="ticket-print-public-url">
            Public ticket status: <?= htmlspecialchars($publicTicketUrl) ?>
          </p>

          <button class="btn btn-primary ticket-print-button" type="button" data-ticket-print>
            <i class="bi bi-printer" aria-hidden="true"></i>
            Print or Save Ticket
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
            <?php if ($feedbackPending): ?>
              <button class="btn btn-success" type="button"
                      data-bs-toggle="modal" data-bs-target="#clientFeedbackModal">
                <i class="bi bi-chat-heart" aria-hidden="true"></i>
                Submit Feedback
              </button>
            <?php endif; ?>
          </div>
        </aside>
      </section>

      <?php if ($feedbackPending): ?>
        <div class="modal fade app-feedback-modal" id="clientFeedbackModal" tabindex="-1"
             aria-labelledby="client-feedback-title" aria-describedby="client-feedback-description"
             aria-hidden="true" data-client-feedback-modal<?= $autoOpenFeedback ? ' data-auto-open="true"' : '' ?>>
          <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <div>
                  <p class="text-uppercase small fw-semibold text-primary mb-1">Service complete</p>
                  <h2 class="modal-title fs-5" id="client-feedback-title">Submit Feedback</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close feedback form"></button>
              </div>
              <form data-feedback-form
                    data-feedback-url="<?= htmlspecialchars(postActionUrl('modules/feedback/submit_feedback.php'), ENT_QUOTES) ?>"
                    data-feedback-success-url="ticket.php?msg=feedback_submitted">
                <?= csrfInput() ?>
                <input type="hidden" name="ticket_id" value="<?= (int) $ticket['ticket_id'] ?>">
                <div class="modal-body">
                  <p id="client-feedback-description" class="text-body-secondary">
                    Rate your experience for ticket <?= htmlspecialchars($ticket['ticket_number']) ?>.
                    One response is allowed for this completed service.
                  </p>
                  <div class="mb-3">
                    <label class="form-label" for="feedback-rating">Rating</label>
                    <select id="feedback-rating" class="form-select" name="rating" required>
                      <option value="">Choose a rating</option>
                      <option value="5">5 - Excellent</option>
                      <option value="4">4 - Good</option>
                      <option value="3">3 - Okay</option>
                      <option value="2">2 - Poor</option>
                      <option value="1">1 - Very poor</option>
                    </select>
                    <div class="field-error" data-field-error-for="rating" aria-live="polite"></div>
                  </div>
                  <div>
                    <label class="form-label" for="feedback-comment">Comment <span class="text-body-secondary">(optional)</span></label>
                    <textarea id="feedback-comment" class="form-control" name="comment" rows="4"
                              maxlength="2000" placeholder="Tell us what went well or what we can improve."></textarea>
                  </div>
                  <div class="mt-3" data-feedback-result aria-live="polite"></div>
                </div>
                <div class="modal-footer">
                  <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                  <button class="btn btn-primary" type="submit" data-loading-text="Sending...">Send Feedback</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
