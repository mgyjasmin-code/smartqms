<?php
$adminActionToasts = is_array($adminActionToasts ?? null) ? $adminActionToasts : [];
?>
<?php if ($adminActionToasts): ?>
  <div class="toast-container position-fixed p-3 admin-toast-container" aria-label="Admin action notifications">
    <?php foreach ($adminActionToasts as $toast): ?>
      <?php
      $tone = ($toast['tone'] ?? '') === 'info' ? 'info' : 'success';
      $title = $tone === 'info' ? 'Action notice' : 'Action completed';
      $icon = $tone === 'info' ? 'info' : 'check-circle-2';
      ?>
      <div
        class="toast admin-action-toast is-<?= $tone ?>"
        role="status"
        aria-live="polite"
        aria-atomic="true"
        data-bs-autohide="true"
        data-bs-delay="5000"
        data-admin-action-toast
      >
        <div class="toast-header">
          <span class="admin-toast-icon" aria-hidden="true">
            <i data-lucide="<?= $icon ?>"></i>
          </span>
          <strong class="me-auto"><?= htmlspecialchars($title) ?></strong>
          <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Dismiss notification"></button>
        </div>
        <div class="toast-body"><?= htmlspecialchars((string) ($toast['message'] ?? '')) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
