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
<body>
  <main class="container py-4">
    <a href="ticket.php" class="btn btn-link px-0">Back to ticket</a>
    <div class="card p-4" style="max-width:620px;">
      <h1 class="h4 mb-3">Submit Feedback</h1>
      <form id="feedback-form">
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
      <div id="feedback-result" class="mt-3"></div>
    </div>
  </main>
  <script>
    const feedbackForm = document.getElementById('feedback-form');
    const clearFeedbackErrors = () => {
      feedbackForm.querySelectorAll('.is-invalid').forEach(field => {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
      });
      feedbackForm.querySelectorAll('.field-error').forEach(error => {
        error.textContent = '';
      });
    };

    feedbackForm.querySelectorAll('select, textarea').forEach(field => {
      field.addEventListener('input', () => {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
        const error = feedbackForm.querySelector(`[data-field-error-for="${field.name}"]`);
        if (error) error.textContent = '';
      });
    });

    feedbackForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const result = document.getElementById('feedback-result');
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      clearFeedbackErrors();
      const response = await fetch('<?= APP_URL ?>/modules/feedback/submit_feedback.php', {
        method: 'POST',
        body: new FormData(event.target),
        credentials: 'same-origin',
        headers: csrfToken ? { 'X-CSRF-Token': csrfToken } : {}
      });
      const data = await response.json();
      if (data.success) {
        result.innerHTML = '<div class="alert alert-success">Thank you for your feedback.</div>';
        feedbackForm.reset();
        return;
      }

      Object.entries(data.field_errors || {}).forEach(([fieldName, message]) => {
        const field = feedbackForm.elements[fieldName];
        const error = feedbackForm.querySelector(`[data-field-error-for="${fieldName}"]`);
        if (field) {
          field.classList.add('is-invalid');
          field.setAttribute('aria-invalid', 'true');
        }
        if (error) error.textContent = message;
      });

      const firstInvalid = feedbackForm.querySelector('.is-invalid');
      if (firstInvalid) firstInvalid.focus();
      result.innerHTML = data.field_errors
        ? ''
        : `<div class="alert alert-danger">${data.error || 'Could not submit feedback.'}</div>`;
    });
  </script>
</body>
</html>
