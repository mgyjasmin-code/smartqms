<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireLogin(ROLE_ADMIN);

$logs = $conn->query("
    SELECT al.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name
    FROM activity_logs al
    JOIN users u ON u.user_id=al.user_id
    ORDER BY al.logged_at DESC
    LIMIT 100
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Activity Logs';
$pageHeading = 'Activity Logs';
$activePage = 'activity_logs';
$adminBodyClass = 'admin-activity-page';
include __DIR__ . '/includes/header.php';
?>
<section class="admin-content-grid">
  <div data-admin-paginated-region>
    <div class="admin-section-heading">
      <h2>Recent Activity Logs</h2>
      <span class="admin-pill">Real-time Feed</span>
    </div>

    <article class="admin-card admin-table-card">
      <div class="admin-table-wrap admin-activity-table-wrap">
        <table class="admin-data-table" data-admin-paginated-table data-page-size="10">
          <thead>
            <tr>
              <th>Ticket ID</th>
              <th>Patient Category</th>
              <th>Action Taken</th>
              <th>Station</th>
              <th>Timestamp</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $log): ?>
              <?php
                $role = strtolower((string) ($log['role'] ?? 'admin'));
                $category = $role === 'client' ? 'regular' : $role;
                $categoryClass = in_array($category, ['emergency', 'senior', 'pwd'], true) ? ' is-' . $category : '';
                $ticketLabel = !empty($log['ticket_id']) ? '#' . (int) $log['ticket_id'] : '#' . str_pad((string) (int) $log['log_id'], 4, '0', STR_PAD_LEFT);
                $stationLabel = $role === 'staff' ? 'Service Window' : ($role === 'client' ? 'Client Portal' : 'Admin Panel');
                $timeLabel = !empty($log['logged_at']) ? date('h:i A', strtotime($log['logged_at'])) : '';
              ?>
              <tr>
                <td data-label="Ticket ID"><strong><?= htmlspecialchars($ticketLabel) ?></strong></td>
                <td data-label="Patient Category"><span class="admin-pill<?= $categoryClass ?>"><?= htmlspecialchars(ucfirst($category ?: 'Regular')) ?></span></td>
                <td data-label="Action Taken"><?= htmlspecialchars($log['action']) ?><?= !empty($log['details']) ? ' - ' . htmlspecialchars($log['details']) : '' ?></td>
                <td class="admin-station-cell" data-label="Station"><?= htmlspecialchars($stationLabel) ?></td>
                <td class="admin-time-cell" data-label="Timestamp"><?= htmlspecialchars($timeLabel) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php if (!$logs): ?>
          <div class="admin-empty-state">No activity logs yet. New system events will appear here.</div>
        <?php endif; ?>
      </div>
    </article>

    <p class="admin-pagination-status" data-admin-pagination-status aria-live="polite"></p>
    <nav class="admin-pagination" aria-label="Activity log pagination" data-admin-pagination></nav>
  </div>

  <aside>
    <div class="admin-model-status">
      <h2>Model Insights</h2>
      <span>Optimized</span>
    </div>

    <article class="admin-card admin-model-card">
      <div class="admin-model-title">ML Predictive Model Summary</div>
      <div class="admin-model-body">
        <div class="admin-model-head">
          <span class="admin-model-icon"><i data-lucide="brain-circuit" aria-hidden="true"></i></span>
          <div>
            <strong>Random Forest Regressor</strong>
            <span>Version: v4.2.0 (Stable)</span>
          </div>
        </div>
        <div class="admin-model-metrics">
          <div>
            <span>Accuracy Score</span>
            <strong class="is-blue">98.2%</strong>
          </div>
          <div>
            <span>Model Drift</span>
            <strong>Low</strong>
          </div>
        </div>
        <dl class="admin-definition-list">
          <div><dt>Mean Absolute Error (MAE)</dt><dd>1.24s</dd></div>
          <div><dt>Root Mean Square Error (RMSE)</dt><dd>1.89s</dd></div>
          <div><dt>Training Cycles (24h)</dt><dd>144</dd></div>
        </dl>
        <div class="admin-note-box">
          <i data-lucide="circle-alert" aria-hidden="true"></i>
          <span>Model performance is within peak parameters. Backend metrics can replace this UI placeholder later.</span>
        </div>
      </div>
    </article>

    <article class="admin-cta-card">
      <h3>Weekly Reports Ready</h3>
      <p>The aggregated ML accuracy and ticket volume report for this week is now available for review.</p>
      <a href="reports.php">View Report Center</a>
    </article>
  </aside>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
