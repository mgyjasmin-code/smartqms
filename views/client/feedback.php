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
  <title>Feedback -- SmartQMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
  <main class="container py-4">
    <a href="ticket.php" class="btn btn-link px-0">Back to ticket</a>
    <div class="card p-4" style="max-width:620px;">
      <h1 class="h4 mb-3">Submit Feedback</h1>
      <form id="feedback-form">
        <input type="hidden" name="ticket_id" value="<?= $ticketId ?>">
        <label class="form-label">Rating</label>
        <select class="form-select mb-3" name="rating" required>
          <option value="5">5 - Excellent</option>
          <option value="4">4 - Good</option>
          <option value="3">3 - Okay</option>
          <option value="2">2 - Poor</option>
          <option value="1">1 - Very poor</option>
        </select>
        <label class="form-label">Comment</label>
        <textarea class="form-control mb-3" name="comment" rows="4"></textarea>
        <button class="btn btn-primary" type="submit">Send Feedback</button>
      </form>
      <div id="feedback-result" class="mt-3"></div>
    </div>
  </main>
  <script>
    document.getElementById('feedback-form').addEventListener('submit', async (event) => {
      event.preventDefault();
      const result = document.getElementById('feedback-result');
      const response = await fetch('<?= APP_URL ?>/modules/feedback/submit_feedback.php', {
        method: 'POST',
        body: new FormData(event.target)
      });
      const data = await response.json();
      result.innerHTML = data.success
        ? '<div class="alert alert-success">Thank you for your feedback.</div>'
        : `<div class="alert alert-danger">${data.error || 'Could not submit feedback.'}</div>`;
    });
  </script>
</body>
</html>
