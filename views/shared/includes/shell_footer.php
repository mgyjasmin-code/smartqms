<?php
$appRole = (string) ($appShell['role'] ?? 'user');
$appLogoutTitle = (string) ($appShell['logout_title'] ?? 'Log out of Smart QMS?');
$appLogoutDescription = (string) ($appShell['logout_description'] ?? 'You will need to sign in again to continue.');
$appScripts = is_array($appShell['scripts'] ?? null) ? $appShell['scripts'] : [];
$appActionToasts = is_array($appActionToasts ?? null) ? $appActionToasts : [];
if (isset($success) && is_string($success) && $success !== '') {
    $appActionToasts[] = ['tone' => 'success', 'message' => $success];
}
if (isset($notice) && is_string($notice) && $notice !== '') {
    $appActionToasts[] = ['tone' => 'info', 'message' => $notice];
}
?>
      </main>
    </div>
  </div>
  <button class="app-sidebar-scrim admin-sidebar-scrim" type="button" data-app-sidebar-close aria-label="Close navigation"></button>

  <div class="toast-container app-toast-container position-fixed top-0 end-0 p-3" aria-live="polite" aria-atomic="true">
    <?php foreach ($appActionToasts as $toastIndex => $toast): ?>
      <?php
      $toastTone = in_array(($toast['tone'] ?? ''), ['success', 'danger', 'warning', 'info'], true) ? $toast['tone'] : 'info';
      $toastMessage = (string) ($toast['message'] ?? '');
      ?>
      <?php if ($toastMessage !== ''): ?>
        <div class="toast app-action-toast is-<?= htmlspecialchars($toastTone) ?>" role="<?= $toastTone === 'danger' ? 'alert' : 'status' ?>"
             aria-live="<?= $toastTone === 'danger' ? 'assertive' : 'polite' ?>" aria-atomic="true" data-app-action-toast>
          <div class="toast-body">
            <i data-lucide="<?= $toastTone === 'success' ? 'circle-check' : ($toastTone === 'danger' ? 'circle-alert' : 'info') ?>" aria-hidden="true"></i>
            <span><?= htmlspecialchars($toastMessage) ?></span>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="toast" aria-label="Dismiss notification"></button>
          </div>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <div class="modal fade app-logout-modal admin-logout-bootstrap-modal" id="appLogoutModal" tabindex="-1"
       aria-labelledby="app-logout-title" aria-describedby="app-logout-description" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content js-validated-form" action="<?= postActionUrl('modules/auth/logout.php') ?>" method="POST">
        <?= csrfInput() ?>
        <div class="modal-header">
          <h2 class="modal-title fs-5" id="app-logout-title"><?= htmlspecialchars($appLogoutTitle) ?></h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close logout confirmation"></button>
        </div>
        <div class="modal-body"><p id="app-logout-description"><?= htmlspecialchars($appLogoutDescription) ?></p></div>
        <div class="modal-footer">
          <button class="btn admin-action-button" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn admin-action-button is-danger" type="submit" data-loading-text="Logging out...">Log out</button>
        </div>
      </form>
    </div>
  </div>

  <?php if ($appRole === 'staff'): ?>
    <div class="modal fade app-confirm-modal" id="staffActionConfirmationModal" tabindex="-1"
         aria-labelledby="staff-action-confirm-title" aria-describedby="staff-action-confirm-message"
         aria-hidden="true" data-staff-confirm-modal>
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h2 class="modal-title fs-5" id="staff-action-confirm-title" data-staff-confirm-modal-title>Confirm ticket action</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close confirmation"></button>
          </div>
          <div class="modal-body">
            <p id="staff-action-confirm-message" data-staff-confirm-modal-message></p>
          </div>
          <div class="modal-footer">
            <button class="btn admin-action-button" type="button" data-bs-dismiss="modal">Cancel</button>
            <button class="btn admin-action-button is-danger" type="button" data-staff-confirm-modal-submit>
              <span data-staff-confirm-modal-submit-label>Confirm</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <script src="<?= assetUrl('vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script>
  <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
  <?php if (!empty($appShell['load_chart'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
  <?php endif; ?>
  <script src="<?= assetUrl('assets/js/main.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/shell.js') ?>"></script>
  <?php foreach ($appScripts as $appScript): ?>
    <script src="<?= assetUrl((string) $appScript) ?>"></script>
  <?php endforeach; ?>
</body>
</html>
