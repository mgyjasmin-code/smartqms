<?php
$reportsOpen = $activePage === 'reports';
$reportItems = [
    'queue_summary' => ['label' => 'Queue Summary', 'icon' => 'chart-column'],
    'predicted_vs_actual' => ['label' => 'Predicted vs Actual', 'icon' => 'line-chart'],
    'peak_hour' => ['label' => 'Peak Hour Analysis', 'icon' => 'trending-up'],
    'counter_performance' => ['label' => 'Counter Performance', 'icon' => 'gauge'],
    'turnaround_time' => ['label' => 'Turnaround Time', 'icon' => 'timer'],
    'no_show' => ['label' => 'No-Show Report', 'icon' => 'user-x'],
    'staff_productivity' => ['label' => 'Staff Productivity', 'icon' => 'users'],
    'ml_accuracy' => ['label' => 'ML Accuracy', 'icon' => 'brain-circuit'],
    'daily_monthly_stats' => ['label' => 'Daily/Monthly Stats', 'icon' => 'calendar-days'],
    'satisfaction' => ['label' => 'Satisfaction', 'icon' => 'star'],
];
?>
<aside class="admin-sidebar" id="admin-sidebar" aria-label="Admin navigation">
  <div class="admin-sidebar-header">
    <div class="admin-brand">
      <span>Smart QMS</span>
    </div>
    <button class="admin-sidebar-close" type="button" data-admin-sidebar-close aria-label="Close admin navigation">
      <i data-lucide="x" aria-hidden="true"></i>
    </button>
  </div>

  <nav class="admin-nav">
    <a class="admin-nav-link<?= $activePage === 'dashboard' ? ' is-active' : '' ?>" href="dashboard.php"<?= $activePage === 'dashboard' ? ' aria-current="page"' : '' ?>>
      <i data-lucide="layout-dashboard" aria-hidden="true"></i>
      <span>Dashboard</span>
    </a>
    <a class="admin-nav-link<?= $activePage === 'activity_logs' ? ' is-active' : '' ?>" href="activity_log.php"<?= $activePage === 'activity_logs' ? ' aria-current="page"' : '' ?>>
      <i data-lucide="activity" aria-hidden="true"></i>
      <span>Activity Logs</span>
    </a>

    <button class="admin-nav-link admin-nav-toggle<?= $reportsOpen ? ' is-active' : '' ?>" type="button" data-admin-submenu-toggle aria-expanded="<?= $reportsOpen ? 'true' : 'false' ?>" aria-controls="admin-report-submenu">
      <i data-lucide="file-text" aria-hidden="true"></i>
      <span>Reports</span>
      <i class="admin-nav-chevron" data-lucide="chevron-down" aria-hidden="true"></i>
    </button>
    <div class="admin-subnav<?= $reportsOpen ? ' is-open' : '' ?>" id="admin-report-submenu" aria-hidden="<?= $reportsOpen ? 'false' : 'true' ?>">
      <?php foreach ($reportItems as $key => $item): ?>
        <a class="admin-subnav-link<?= $activeReport === $key ? ' is-active' : '' ?>" href="reports.php?report=<?= urlencode($key) ?>"<?= $activeReport === $key ? ' aria-current="page"' : '' ?>>
          <i class="admin-subnav-icon" data-lucide="<?= htmlspecialchars($item['icon']) ?>" aria-hidden="true"></i>
          <span><?= htmlspecialchars($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <a class="admin-nav-link<?= $activePage === 'settings' ? ' is-active' : '' ?>" href="settings.php"<?= $activePage === 'settings' ? ' aria-current="page"' : '' ?>>
      <i data-lucide="settings" aria-hidden="true"></i>
      <span>Settings</span>
    </a>
  </nav>

  <div class="admin-sidebar-footer">
    <button class="admin-logout-button" type="button" data-admin-logout-open>
      <i data-lucide="log-out" aria-hidden="true"></i>
      <span>Logout</span>
    </button>
  </div>
</aside>
