<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/../../modules/reports/report_utils.php';
requireLogin(ROLE_ADMIN);

$definitions = reportDefinitions();
try {
    $range = reportDateRange($_GET);
} catch (InvalidArgumentException $e) {
    $range = reportDateRange([]);
}

$activeReport = normalizeReportKey((string) ($_GET['report'] ?? 'queue_summary'));
if (!isset($definitions[$activeReport])) {
    $activeReport = 'queue_summary';
}

try {
    $report = buildReport($conn, $activeReport, $range);
    $reportError = '';
} catch (Throwable $e) {
    $report = emptyReport($activeReport, $range, [], 'Could not load this report.');
    $reportError = 'Could not load report data.';
}

$pageTitle = 'Reports';
$pageHeading = 'Reports';
$pageSubtitle = 'Database-backed analytics for SmartQMS operations.';
$activePage = 'reports';
$adminBodyClass = 'admin-reports-page';
$exportUrl = postActionUrl('modules/reports/export_csv.php')
    . '?report=' . urlencode($activeReport)
    . '&from=' . urlencode($range['from'])
    . '&to=' . urlencode($range['to']);
include __DIR__ . '/includes/header.php';
?>
<section class="admin-report-shell">
  <?php if ($reportError): ?>
    <div class="admin-alert is-danger" role="alert">
      <i data-lucide="circle-alert" aria-hidden="true"></i>
      <span><?= htmlspecialchars($reportError) ?></span>
    </div>
  <?php endif; ?>

  <div class="admin-report-toolbar">
    <div class="admin-report-title">
      <h2><?= htmlspecialchars($report['title']) ?></h2>
      <p><?= htmlspecialchars($report['description']) ?></p>
    </div>
    <form class="admin-report-actions" method="GET">
      <input type="hidden" name="report" value="<?= htmlspecialchars($activeReport, ENT_QUOTES) ?>">
      <label class="admin-action-button" for="report-from">
        <i data-lucide="calendar-days" aria-hidden="true"></i>
        <input id="report-from" type="date" name="from" value="<?= htmlspecialchars($range['from']) ?>">
      </label>
      <label class="admin-action-button" for="report-to">
        <i data-lucide="calendar-days" aria-hidden="true"></i>
        <input id="report-to" type="date" name="to" value="<?= htmlspecialchars($range['to']) ?>">
      </label>
      <button class="admin-action-button" type="submit">
        <i data-lucide="filter" aria-hidden="true"></i>
        <span>Apply</span>
      </button>
      <a class="admin-action-button is-dark" href="<?= htmlspecialchars($exportUrl, ENT_QUOTES) ?>">
        <i data-lucide="download" aria-hidden="true"></i>
        <span>Export CSV</span>
      </a>
      <button class="admin-action-button" type="button" data-admin-print>
        <i data-lucide="printer" aria-hidden="true"></i>
        <span>Print</span>
      </button>
    </form>
  </div>

  <div class="admin-report-metrics">
    <?php foreach ($report['metrics'] as $metric): ?>
      <article class="admin-metric-card">
        <span><?= htmlspecialchars($metric['label']) ?></span>
        <p class="admin-metric-value"><?= htmlspecialchars((string) $metric['value']) ?></p>
        <?php if (!empty($metric['note'])): ?>
          <p class="admin-metric-note"><?= htmlspecialchars($metric['note']) ?></p>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>

  <article class="admin-card">
    <header class="admin-report-table-title">
      <i data-lucide="<?= htmlspecialchars($definitions[$activeReport]['icon']) ?>" aria-hidden="true"></i>
      <h2><?= htmlspecialchars($report['title']) ?> Trend</h2>
    </header>
    <div class="admin-chart-body">
      <?php $series = array_slice($report['series'] ?? [], 0, 12); ?>
      <?php if ($series): ?>
        <?php $maxValue = max(array_map(static fn($point) => (float) ($point['value'] ?? $point['actual'] ?? $point['predicted'] ?? 0), $series)) ?: 1; ?>
        <div class="admin-bar-chart" role="img" aria-label="<?= htmlspecialchars($report['title']) ?> chart">
          <div class="admin-bars" aria-hidden="true">
            <?php foreach ($series as $point): ?>
              <?php
                $value = (float) ($point['value'] ?? $point['actual'] ?? $point['predicted'] ?? 0);
                $height = max(8, min(100, ($value / $maxValue) * 100));
              ?>
              <span class="admin-bar" style="--bar-height: <?= (int) $height ?>%"><span><?= htmlspecialchars((string) ($point['label'] ?? '')) ?></span></span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php else: ?>
        <div class="queue-empty-state">No chartable data for this period.</div>
      <?php endif; ?>
      <div class="admin-insight">
        <i data-lucide="info" aria-hidden="true"></i>
        <p class="mb-0"><?= htmlspecialchars($report['insight']) ?></p>
      </div>
    </div>
  </article>

  <article class="admin-card admin-report-table-card">
    <header class="admin-report-table-title">
      <i data-lucide="table" aria-hidden="true"></i>
      <h2>Report Table</h2>
    </header>
    <div class="admin-table-wrap">
      <table class="admin-data-table">
        <thead>
          <tr>
            <?php foreach ($report['table']['columns'] as $column): ?>
              <th><?= htmlspecialchars($column['label']) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($report['table']['rows'])): ?>
            <tr>
              <td colspan="<?= max(1, count($report['table']['columns'])) ?>">No records found for the selected period.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($report['table']['rows'] as $row): ?>
              <tr>
                <?php foreach ($report['table']['columns'] as $column): ?>
                  <td><?= htmlspecialchars((string) ($row[$column['key']] ?? '')) ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </article>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
