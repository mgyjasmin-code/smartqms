<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/../../modules/reports/report_utils.php';
requireLogin(ROLE_ADMIN);

$today = date('Y-m-d');
$sevenDaysAgo = date('Y-m-d', strtotime('-6 days'));

$counts = [
    'total_today' => (int) (reportFetchOne($conn, "SELECT COUNT(*) AS c FROM queue_tickets WHERE DATE(issued_at)=CURDATE()")['c'] ?? 0),
    'serving' => (int) (reportFetchOne($conn, "SELECT COUNT(*) AS c FROM queue_tickets WHERE status='serving'")['c'] ?? 0),
    'voided_today' => (int) (reportFetchOne($conn, "SELECT COUNT(*) AS c FROM queue_tickets WHERE status IN ('voided','skipped') AND DATE(COALESCE(voided_at, issued_at))=CURDATE()")['c'] ?? 0),
];
$avgWait = reportFetchOne($conn, "
    SELECT ROUND(
               AVG(
                   TIMESTAMPDIFF(
                       SECOND,
                       qt.issued_at,
                       COALESCE(qt.served_at, qt.called_at, qt.completed_at)
                   ) / 60
               ),
               2
           ) AS avg_wait
    FROM wait_time_logs wl
    JOIN queue_tickets qt ON qt.ticket_id = wl.ticket_id
    WHERE qt.status = 'completed'
      AND qt.completed_at IS NOT NULL
      AND TIMESTAMPDIFF(
              SECOND,
              qt.issued_at,
              COALESCE(qt.served_at, qt.called_at, qt.completed_at)
          ) BETWEEN 0 AND 28800
      AND DATE(qt.completed_at)=CURDATE()
")['avg_wait'] ?? null;

$hourRows = reportFetchAll($conn, "
    SELECT CONCAT(LPAD(HOUR(issued_at), 2, '0'), ':00') AS hour_label, COUNT(*) AS tickets
    FROM queue_tickets
    WHERE DATE(issued_at)=CURDATE()
    GROUP BY HOUR(issued_at)
    ORDER BY HOUR(issued_at)
");
$hourMax = $hourRows ? max(array_map(static fn($row) => (int) $row['tickets'], $hourRows)) : 1;
$peakHour = null;
foreach ($hourRows as $row) {
    if (!$peakHour || (int) $row['tickets'] > (int) $peakHour['tickets']) {
        $peakHour = $row;
    }
}

$waitRows = reportFetchAll($conn, "
    SELECT observed.report_date,
           ROUND(AVG(observed.predicted_wait_min), 2) AS predicted_wait_min,
           ROUND(AVG(observed.actual_wait_min), 2) AS actual_wait_min,
           ROUND(AVG(ABS(observed.predicted_wait_min - observed.actual_wait_min)), 2) AS mae
    FROM (
        SELECT DATE(qt.completed_at) AS report_date,
               wl.predicted_wait_min,
               TIMESTAMPDIFF(
                   SECOND,
                   qt.issued_at,
                   COALESCE(qt.served_at, qt.called_at, qt.completed_at)
               ) / 60 AS actual_wait_min
        FROM wait_time_logs wl
        JOIN queue_tickets qt ON qt.ticket_id = wl.ticket_id
        WHERE qt.status = 'completed'
          AND qt.completed_at IS NOT NULL
          AND wl.predicted_wait_min BETWEEN 0 AND 480
          AND TIMESTAMPDIFF(
                  SECOND,
                  qt.issued_at,
                  COALESCE(qt.served_at, qt.called_at, qt.completed_at)
              ) BETWEEN 0 AND 28800
          AND DATE(qt.completed_at) BETWEEN ? AND ?
    ) observed
    GROUP BY report_date
    ORDER BY report_date
", 'ss', [$sevenDaysAgo, $today]);
$latestWait = $waitRows ? $waitRows[count($waitRows) - 1] : null;
$hourChart = [
    'type' => 'bar',
    'labels' => array_column($hourRows, 'hour_label'),
    'datasets' => [[
        'label' => 'Tickets',
        'data' => array_map(static fn($row) => (int) $row['tickets'], $hourRows),
    ]],
    'unit' => 'tickets',
];
$waitChart = [
    'type' => 'line',
    'labels' => array_column($waitRows, 'report_date'),
    'datasets' => [
        ['label' => 'Predicted wait', 'data' => array_map(static fn($row) => (float) $row['predicted_wait_min'], $waitRows)],
        ['label' => 'Actual wait', 'data' => array_map(static fn($row) => (float) $row['actual_wait_min'], $waitRows)],
    ],
    'unit' => 'minutes',
];

$pageTitle = 'Admin Dashboard';
$pageHeading = 'Operational Analytics';
$pageSubtitle = 'Real-time smart queue monitoring and ML predictions for ' . date('M d, Y') . '.';
$activePage = 'dashboard';
$adminBodyClass = 'admin-dashboard-page';
include __DIR__ . '/includes/header.php';
?>
<section class="admin-kpi-grid" aria-label="Operational metrics">
  <article class="admin-kpi-card">
    <h2>Total Tickets Today</h2>
    <p class="admin-kpi-value"><?= number_format($counts['total_today']) ?></p>
    <p class="admin-kpi-trend">Issued on <?= htmlspecialchars(date('M d')) ?></p>
    <span class="admin-kpi-icon is-blue"><i data-lucide="users-round" aria-hidden="true"></i></span>
  </article>
  <article class="admin-kpi-card">
    <h2>Avg Wait Time</h2>
    <p class="admin-kpi-value"><?= $avgWait !== null ? htmlspecialchars((string) $avgWait) . ' mins' : 'No data' ?></p>
    <p class="admin-kpi-trend">Completed tickets today</p>
    <span class="admin-kpi-icon is-green"><i data-lucide="clock-3" aria-hidden="true"></i></span>
  </article>
  <article class="admin-kpi-card">
    <h2>Currently Serving</h2>
    <p class="admin-kpi-value"><?= number_format($counts['serving']) ?></p>
    <p class="admin-kpi-trend">Active service windows</p>
    <span class="admin-kpi-icon is-orange"><i data-lucide="user-check" aria-hidden="true"></i></span>
  </article>
  <article class="admin-kpi-card">
    <h2>Voided/Skipped Today</h2>
    <p class="admin-kpi-value"><?= number_format($counts['voided_today']) ?></p>
    <p class="admin-kpi-trend">No-show and voided tickets</p>
    <span class="admin-kpi-icon is-slate"><i data-lucide="user-round-minus" aria-hidden="true"></i></span>
  </article>
</section>

<section class="admin-dashboard-grid">
  <article class="admin-card">
    <header class="admin-card-header">
      <h2>Hourly Ticket Volume Distribution</h2>
    </header>
    <div class="admin-chart-body">
      <?php if ($hourRows): ?>
        <div class="admin-chart-canvas-wrap">
          <canvas data-admin-chart="admin-hour-chart-data" role="img" aria-label="Hourly ticket volume for today. Exact values are available in the table after the chart."></canvas>
        </div>
        <script type="application/json" id="admin-hour-chart-data"><?= json_encode($hourChart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
        <div class="admin-table-wrap admin-chart-table-alternative">
          <table class="admin-data-table">
            <caption class="visually-hidden">Hourly ticket volume values</caption>
            <thead><tr><th>Hour</th><th>Tickets</th></tr></thead>
            <tbody><?php foreach ($hourRows as $row): ?><tr><td><?= htmlspecialchars($row['hour_label']) ?></td><td><?= (int) $row['tickets'] ?></td></tr><?php endforeach; ?></tbody>
          </table>
        </div>
        <div class="admin-insight">
          <i data-lucide="info" aria-hidden="true"></i>
          <p class="mb-0">Peak hour today is <?= htmlspecialchars($peakHour['hour_label'] ?? 'N/A') ?> with <?= (int) ($peakHour['tickets'] ?? 0) ?> ticket(s).</p>
        </div>
      <?php else: ?>
        <div class="queue-empty-state">No tickets have been issued today.</div>
      <?php endif; ?>
    </div>
  </article>

  <article class="admin-card">
    <header class="admin-card-header">
      <h2>Wait Time Performance (Predicted vs Actual)</h2>
    </header>
    <div class="admin-chart-body">
      <?php if ($waitRows): ?>
        <div class="admin-chart-canvas-wrap">
          <canvas data-admin-chart="admin-wait-chart-data" role="img" aria-label="Predicted and actual average wait times over the last seven days. Exact values are available in the table after the chart."></canvas>
        </div>
        <script type="application/json" id="admin-wait-chart-data"><?= json_encode($waitChart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
        <div class="admin-table-wrap">
          <table class="admin-data-table">
            <thead>
              <tr><th>Date</th><th>Predicted Avg</th><th>Actual Avg</th><th>MAE</th></tr>
            </thead>
            <tbody>
              <?php foreach ($waitRows as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['report_date']) ?></td>
                  <td><?= htmlspecialchars((string) $row['predicted_wait_min']) ?> min</td>
                  <td><?= htmlspecialchars((string) $row['actual_wait_min']) ?> min</td>
                  <td><?= htmlspecialchars((string) $row['mae']) ?> min</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="admin-insight">
          <i data-lucide="info" aria-hidden="true"></i>
          <p class="mb-0">Latest model error is <?= htmlspecialchars((string) ($latestWait['mae'] ?? '0')) ?> minute(s) on <?= htmlspecialchars((string) ($latestWait['report_date'] ?? $today)) ?>.</p>
        </div>
      <?php else: ?>
        <div class="queue-empty-state">No completed tickets with prediction accuracy data yet.</div>
      <?php endif; ?>
    </div>
  </article>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
