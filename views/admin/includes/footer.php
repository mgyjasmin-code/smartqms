      </main>
    </div>
  </div>
  <button class="admin-sidebar-scrim" type="button" data-admin-sidebar-close aria-label="Close admin navigation"></button>
  <div
    class="modal fade admin-logout-bootstrap-modal"
    id="adminLogoutModal"
    tabindex="-1"
    aria-labelledby="admin-logout-title"
    aria-describedby="admin-logout-description"
    aria-hidden="true"
  >
    <div class="modal-dialog modal-dialog-centered">
      <form
        class="modal-content js-validated-form"
        action="<?= postActionUrl('modules/auth/logout.php') ?>"
        method="POST"
        data-admin-logout-form
      >
        <?= csrfInput() ?>
        <div class="modal-header">
          <h2 class="modal-title fs-5" id="admin-logout-title">Log out of admin?</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close logout confirmation"></button>
        </div>
        <div class="modal-body">
          <p id="admin-logout-description">You will return to the sign-in screen and need to sign in again before managing Smart QMS.</p>
        </div>
        <div class="modal-footer">
          <button class="btn admin-action-button" type="button" data-bs-dismiss="modal">Cancel</button>
          <button
            class="btn admin-action-button is-danger"
            type="submit"
            data-loading-text="Logging out..."
            data-admin-logout-confirm
          >
            <i data-lucide="log-out" aria-hidden="true"></i>
            <span>Log out</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <script src="<?= assetUrl('vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script>
  <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
  <script src="<?= assetUrl('assets/js/main.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/admin.js') ?>"></script>
</body>
</html>
