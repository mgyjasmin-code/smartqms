<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Settings -- SmartQMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
  <main class="container py-4">
    <a href="dashboard.php" class="btn btn-link px-0">Back to dashboard</a>
    <h1 class="h4 mb-3">System Settings</h1>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <form method="POST">
      <div class="row g-3">
        <?php foreach ($settings as $section => $rows): ?>
          <section class="col-lg-6">
            <div class="card p-4 h-100">
              <h2 class="h5 text-capitalize"><?= htmlspecialchars($section) ?></h2>
              <?php foreach ($rows as $setting): ?>
                <label class="form-label mt-3"><?= htmlspecialchars($setting['label'] ?: $setting['setting_key']) ?></label>
                <input class="form-control" name="settings[<?= htmlspecialchars($setting['setting_key']) ?>]" value="<?= htmlspecialchars($setting['setting_val']) ?>">
              <?php endforeach; ?>
            </div>
          </section>
        <?php endforeach; ?>
      </div>
      <button class="btn btn-primary mt-4" type="submit">Save Settings</button>
    </form>
  </main>
</body>
</html>
