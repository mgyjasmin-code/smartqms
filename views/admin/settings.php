<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);

$success = '';
$feedback = consumeFormFeedback('admin_settings');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf('views/admin/settings.php', 'admin_settings');
    foreach ($_POST['settings'] ?? [] as $key => $value) {
        $stmt = $conn->prepare("UPDATE system_settings SET setting_val=?, updated_by=? WHERE setting_key=?");
        $stmt->bind_param('sis', $value, $_SESSION['user_id'], $key);
        $stmt->execute();
    }
    logActivity($conn, 'settings_updated', 'Updated system settings');
    $success = 'Settings saved.';
}

$settings = [];
$result = $conn->query("SELECT * FROM system_settings ORDER BY section, setting_id");
while ($row = $result->fetch_assoc()) {
    $settings[$row['section'] ?? 'other'][] = $row;
}

$pageTitle = 'Settings';
$pageHeading = 'System Settings';
$pageSubtitle = 'Configure queue behavior and Smart QMS system preferences.';
$activePage = 'settings';
$adminBodyClass = 'admin-settings-page';
include __DIR__ . '/includes/header.php';
?>
<section class="admin-settings-shell">
  <?php if ($feedback['form_error']): ?>
    <div class="admin-alert is-danger" role="alert">
      <i data-lucide="circle-alert" aria-hidden="true"></i>
      <span><?= htmlspecialchars($feedback['form_error']) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="admin-alert is-success" role="status">
      <i data-lucide="check-circle-2" aria-hidden="true"></i>
      <span><?= htmlspecialchars($success) ?></span>
    </div>
  <?php endif; ?>

  <form class="admin-settings-form" method="POST">
    <?= csrfInput() ?>
    <div class="admin-settings-grid">
      <?php foreach ($settings as $section => $rows): ?>
        <section class="admin-card admin-settings-card">
          <div class="admin-settings-card-header">
            <i data-lucide="sliders-horizontal" aria-hidden="true"></i>
            <h2><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) $section))) ?></h2>
          </div>
          <div class="admin-settings-fields">
            <?php foreach ($rows as $setting): ?>
              <?php $inputId = 'setting-' . preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) $setting['setting_key']); ?>
              <div class="admin-field">
                <label for="<?= htmlspecialchars($inputId) ?>"><?= htmlspecialchars($setting['label'] ?: $setting['setting_key']) ?></label>
                <input id="<?= htmlspecialchars($inputId) ?>" class="admin-input" name="settings[<?= htmlspecialchars($setting['setting_key']) ?>]" value="<?= htmlspecialchars($setting['setting_val']) ?>">
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
    </div>
    <div class="admin-settings-actions">
      <button class="admin-action-button is-dark" type="submit">
        <i data-lucide="save" aria-hidden="true"></i>
        <span>Save Settings</span>
      </button>
    </div>
  </form>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
