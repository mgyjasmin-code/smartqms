<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $active = (int) ($_POST['is_active'] ?? 0);
    if ($userId > 0 && $userId !== (int) $_SESSION['user_id']) {
        $stmt = $conn->prepare("UPDATE users SET is_active=? WHERE user_id=?");
        $stmt->bind_param('ii', $active, $userId);
        $stmt->execute();
        logActivity($conn, 'user_status_updated', 'user_id=' . $userId);
    }
}

$users = $conn->query("SELECT user_id, first_name, last_name, phone_number, email, role, is_verified, is_active, created_at FROM users ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Users -- SmartQMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
  <main class="container py-4">
    <a href="dashboard.php" class="btn btn-link px-0">Back to dashboard</a>
    <div class="card p-4">
      <h1 class="h4">Users</h1>
      <div class="table-responsive">
        <table class="table">
          <thead><tr><th>Name</th><th>Phone</th><th>Role</th><th>Verified</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($users as $user): ?>
              <tr>
                <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                <td><?= htmlspecialchars($user['phone_number']) ?></td>
                <td><?= htmlspecialchars($user['role']) ?></td>
                <td><?= (int) $user['is_verified'] ? 'Yes' : 'No' ?></td>
                <td><?= (int) $user['is_active'] ? 'Active' : 'Inactive' ?></td>
                <td>
                  <form method="POST">
                    <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                    <input type="hidden" name="is_active" value="<?= (int) $user['is_active'] ? 0 : 1 ?>">
                    <button class="btn btn-sm btn-outline-secondary" type="submit"><?= (int) $user['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</body>
</html>
