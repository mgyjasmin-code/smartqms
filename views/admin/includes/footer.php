      </main>
    </div>
  </div>
  <button class="admin-sidebar-scrim" type="button" data-admin-sidebar-close aria-label="Close admin navigation"></button>
  <div class="admin-modal-scrim" data-admin-logout-modal hidden>
    <section class="admin-logout-modal" role="dialog" aria-modal="true" aria-labelledby="admin-logout-title" aria-describedby="admin-logout-description" tabindex="-1">
      <button class="admin-logout-modal-close" type="button" data-admin-logout-close aria-label="Close logout confirmation">
        <i data-lucide="x" aria-hidden="true"></i>
      </button>
      <div class="admin-logout-modal-icon" aria-hidden="true">
        <i data-lucide="log-out"></i>
      </div>
      <div class="admin-logout-modal-copy">
        <h2 id="admin-logout-title">Log out of admin?</h2>
        <p id="admin-logout-description">You will return to the sign-in screen and need to sign in again before managing Smart QMS.</p>
      </div>
      <form class="admin-logout-modal-actions" action="<?= postActionUrl('modules/auth/logout.php') ?>" method="POST" data-admin-logout-form>
        <?= csrfInput() ?>
        <button class="admin-modal-button" type="button" data-admin-logout-close>Cancel</button>
        <button class="admin-modal-button is-primary" type="submit" data-admin-logout-confirm>
          <span>Log out</span>
        </button>
      </form>
    </section>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
  <script src="<?= assetUrl('assets/js/main.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/admin.js') ?>"></script>
</body>
</html>
