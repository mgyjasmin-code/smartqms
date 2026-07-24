<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_CLIENT);

$ticketId = (int) ($_GET['ticket_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= csrfMetaTag() ?>
  <title>Feedback -- SmartQMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body data-client-root
      data-client-notification-url="<?= htmlspecialchars(postActionUrl('modules/notifications/get_notifications.php'), ENT_QUOTES) ?>">
  <main class="container py-4">
    <a href="ticket.php" class="btn btn-link px-0">Back to ticket</a>
    <div class="card p-4" style="max-width:620px;">
      <h1 class="h4 mb-3">Submit Feedback</h1>
      <form id="feedback-form" data-feedback-form
            data-feedback-url="<?= htmlspecialchars(postActionUrl('modules/feedback/submit_feedback.php'), ENT_QUOTES) ?>">
        <?= csrfInput() ?>
        <input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
        <label class="form-label" for="rating">Rating</label>
        <select id="rating" class="form-select" name="rating" required>
          <option value="5">5 - Excellent</option>
          <option value="4">4 - Good</option>
          <option value="3">3 - Okay</option>
          <option value="2">2 - Poor</option>
          <option value="1">1 - Very poor</option>
        </select>
        <div class="field-error mb-3" data-field-error-for="rating" aria-live="polite"></div>
        <label class="form-label" for="comment">Comment</label>
        <textarea class="form-control mb-3" name="comment" rows="4"></textarea>
        <button class="btn btn-primary" type="submit">Send Feedback</button>
      </form>
      <div id="feedback-result" class="mt-3" data-feedback-result aria-live="polite"></div>
    </div>
  </main>
  <script src="<?= assetUrl('assets/js/main.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/client.js') ?>"></script>
</body>
</html>
