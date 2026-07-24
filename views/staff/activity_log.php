<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_STAFF);

$stmt = $conn->prepare("SELECT action, details, logged_at FROM activity_logs WHERE user_id=? ORDER BY logged_at DESC LIMIT 50");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function staffActivityIcon(string $action): string {
    $action = strtolower($action);
    if (str_contains($action, 'complete')) return 'check2-circle';
    if (str_contains($action, 'skip')) return 'skip-forward';
    if (str_contains($action, 'void')) return 'x-circle';
    if (str_contains($action, 'window')) return 'door-open';
    if (str_contains($action, 'call')) return 'telephone-outbound';
    return 'activity';
}

function staffActivityLabel(string $action): string {
    return ucwords(str_replace('_', ' ', $action));
}

$pageTitle = 'Activity Log';
$pageHeading = 'Activity Log';
$pageSubtitle = 'Review your latest queue and service-window actions.';
$activePage = 'activity';
include __DIR__ . '/includes/header.php';
?>

<section class="staff-activity-card">
  <div class="staff-section-heading">
    <div>
      <p class="staff-section-kicker">Latest 50 Records</p>
      <h2>My Activity</h2>
    </div>
    <a class="btn btn-outline-primary" href="dashboard.php"><i class="bi bi-arrow-left" aria-hidden="true"></i> Dashboard</a>
  </div>

  <?php if ($logs): ?>
    <ol class="staff-timeline">
      <?php foreach ($logs as $log): ?>
        <li class="staff-timeline-item">
          <span class="staff-timeline-icon"><i class="bi bi-<?= htmlspecialchars(staffActivityIcon((string) $log['action'])) ?>" aria-hidden="true"></i></span>
          <div class="staff-timeline-content">
            <div>
              <h3><?= htmlspecialchars(staffActivityLabel((string) $log['action'])) ?></h3>
              <time datetime="<?= htmlspecialchars(date(DATE_ATOM, strtotime((string) $log['logged_at']))) ?>">
                <?= htmlspecialchars(date('M j, Y · g:i A', strtotime((string) $log['logged_at']))) ?>
              </time>
            </div>
            <p><?= htmlspecialchars($log['details'] ?: 'No additional details were recorded.') ?></p>
          </div>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php else: ?>
    <div class="staff-empty-inline">
      <span><i class="bi bi-clock-history" aria-hidden="true"></i></span>
      <div><h3>No activity recorded yet</h3><p>Your queue and window actions will appear here after you start serving clients.</p></div>
    </div>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
