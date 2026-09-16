<div class="modal fade admin-management-confirm-modal"
     id="admin-management-confirmation"
     tabindex="-1"
     aria-labelledby="admin-management-confirmation-title"
     aria-describedby="admin-management-confirmation-message"
     aria-hidden="true"
     data-admin-management-confirm-modal>
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <p class="admin-section-kicker mb-1" data-admin-confirm-eyebrow>Review changes</p>
          <h2 class="modal-title fs-5" id="admin-management-confirmation-title" data-admin-confirm-title>Confirm changes</h2>
        </div>
        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close confirmation"></button>
      </div>
      <div class="modal-body">
        <p class="admin-confirm-message" id="admin-management-confirmation-message" data-admin-confirm-message>
          Review the information below before saving.
        </p>
        <dl class="admin-confirm-summary mb-0" data-admin-confirm-summary></dl>
      </div>
      <div class="modal-footer">
        <button class="btn admin-action-button" type="button" data-bs-dismiss="modal" data-admin-confirm-back>
          <span>Back</span>
        </button>
        <button class="btn admin-action-button is-primary" type="button" data-admin-confirm-submit>
          <span data-admin-confirm-submit-label>Confirm changes</span>
        </button>
      </div>
    </div>
  </div>
</div>
