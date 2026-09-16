<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once __DIR__ . '/../../modules/reports/report_utils.php';
requireLogin(ROLE_ADMIN);

$definitions = reportDefinitions();
try {
    $range = reportDateRange($_GET);
    $rangeError = '';
} catch (InvalidArgumentException $e) {
    $range = reportDateRange([]);
    $rangeError = $e->getMessage() . ' The default date range has been restored.';
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
$chartHeadingSuffix = match ($report['chart']['type'] ?? 'bar') {
    'line' => 'Trend',
    'doughnut' => 'Distribution',
    'horizontalBar' => 'Comparison',
    default => !empty($report['chart']['stacked']) ? 'Status by Day' : 'Overview',
};
include __DIR__ . '/includes/header.php';
?>
<section class="admin-report-shell container-fluid" aria-labelledby="active-report-title" data-admin-print-region>
  <?php if ($reportError): ?>
    <div class="admin-alert is-danger" role="alert">
      <i data-lucide="circle-alert" aria-hidden="true"></i>
      <span><?= htmlspecialchars($reportError) ?></span>
    </div>
  <?php endif; ?>
  <?php if ($rangeError): ?>
    <div class="admin-alert is-danger" role="alert">
      <i data-lucide="calendar-x" aria-hidden="true"></i>
      <span><?= htmlspecialchars($rangeError) ?></span>
    </div>
  <?php endif; ?>

  <header class="admin-report-toolbar">
    <div class="admin-report-title">
      <h2 id="active-report-title"><?= htmlspecialchars($report['title']) ?></h2>
      <p><?= htmlspecialchars($report['description']) ?></p>
      <p class="admin-report-print-period">Reporting period: <?= htmlspecialchars($range['from']) ?> to <?= htmlspecialchars($range['to']) ?></p>
    </div>
    <form class="admin-report-actions" method="GET" aria-label="<?= htmlspecialchars($report['title'], ENT_QUOTES) ?> report controls">
      <input type="hidden" name="report" value="<?= htmlspecialchars($activeReport, ENT_QUOTES) ?>">
      <label class="admin-report-date-field" for="report-from">
        <span>From</span>
        <input id="report-from" type="date" name="from" value="<?= htmlspecialchars($range['from']) ?>">
      </label>
      <label class="admin-report-date-field" for="report-to">
        <span>To</span>
        <input id="report-to" type="date" name="to" value="<?= htmlspecialchars($range['to']) ?>">
      </label>
      <button class="admin-action-button is-primary" type="submit">
        <i data-lucide="filter" aria-hidden="true"></i>
        <span>Apply</span>
      </button>
      <a class="admin-action-button is-dark" href="<?= htmlspecialchars($exportUrl, ENT_QUOTES) ?>">
        <i data-lucide="download" aria-hidden="true"></i>
        <span>Export CSV</span>
      </a>
      <button class="admin-action-button" type="button" data-admin-print aria-label="Print <?= htmlspecialchars($report['title'], ENT_QUOTES) ?> report or save it as PDF">
        <i data-lucide="printer" aria-hidden="true"></i>
        <span>Print / Save PDF</span>
      </button>
    </form>
  </header>

  <div class="admin-report-content">
  <div class="admin-report-metrics" aria-label="<?= htmlspecialchars($report['title'], ENT_QUOTES) ?> key metrics">
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

  <article class="admin-card admin-report-chart-card">
    <header class="admin-report-table-title">
      <i data-lucide="<?= htmlspecialchars($definitions[$activeReport]['icon']) ?>" aria-hidden="true"></i>
      <h2><?= htmlspecialchars($report['title']) ?> <?= htmlspecialchars($chartHeadingSuffix) ?></h2>
    </header>
    <div class="admin-chart-body">
      <?php if (!empty($report['chart']['labels']) && !empty($report['chart']['datasets'])): ?>
        <div
          class="admin-chart-canvas-wrap"
          role="region"
          aria-label="<?= htmlspecialchars($report['title'], ENT_QUOTES) ?> chart; scroll horizontally to view all data points"
          tabindex="0"
          data-admin-chart-scroll
        >
          <div class="admin-chart-scroll-content" data-admin-chart-scroll-content>
            <canvas data-admin-chart="admin-report-chart-data" role="img" aria-label="<?= htmlspecialchars($report['chart']['summary'] ?: $report['title'] . ' chart') ?> Exact values are available in the report table."></canvas>
          </div>
        </div>
        <script nonce="<?= htmlspecialchars(SMARTQMS_CSP_NONCE, ENT_QUOTES) ?>" type="application/json" id="admin-report-chart-data"><?= json_encode($report['chart'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
      <?php else: ?>
        <div class="queue-empty-state">No chartable data for this period.</div>
      <?php endif; ?>
      <div class="admin-insight">
        <i data-lucide="info" aria-hidden="true"></i>
        <p class="mb-0"><?= htmlspecialchars($report['insight']) ?></p>
      </div>
    </div>
  </article>

  <?php if ($activeReport === 'ml_accuracy' && !empty($report['table']['rows'])): ?>
    <?php $bestModel = current(array_filter($report['table']['rows'], static fn($row) => (int) ($row['is_best'] ?? 0) === 1)) ?: $report['table']['rows'][0]; ?>
    <article class="admin-card admin-model-summary" aria-labelledby="best-model-title">
      <header class="admin-report-table-title">
        <i data-lucide="badge-check" aria-hidden="true"></i>
        <h2 id="best-model-title">Best Model: <?= htmlspecialchars($bestModel['algorithm']) ?></h2>
      </header>
      <dl class="admin-model-metrics">
        <div><dt>MAE</dt><dd><?= htmlspecialchars((string) $bestModel['mae']) ?> min</dd></div>
        <div><dt>RMSE</dt><dd><?= htmlspecialchars((string) $bestModel['rmse']) ?> min</dd></div>
        <div><dt>R²</dt><dd><?= htmlspecialchars((string) $bestModel['r2']) ?></dd></div>
        <div><dt>MAPE</dt><dd><?= htmlspecialchars((string) $bestModel['mape']) ?>%</dd></div>
        <div><dt>Dataset</dt><dd><?= htmlspecialchars((string) $bestModel['dataset_used']) ?></dd></div>
        <div><dt>Samples</dt><dd><?= number_format((int) $bestModel['sample_size']) ?></dd></div>
      </dl>
    </article>
  <?php endif; ?>

  <article class="admin-card admin-report-table-card">
    <header class="admin-report-table-title">
      <i data-lucide="table" aria-hidden="true"></i>
      <h2>Report Table</h2>
    </header>
    <div class="admin-table-wrap" role="region" aria-label="<?= htmlspecialchars($report['title'], ENT_QUOTES) ?> data table; scroll horizontally to view all columns" tabindex="0">
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
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
