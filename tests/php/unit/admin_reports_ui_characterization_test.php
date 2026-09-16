<?php

function adminReportsUiSource(string $relativePath): string {
    $source = file_get_contents(SMARTQMS_ROOT . '/' . $relativePath);
    if ($source === false) {
        throw new RuntimeException('Could not read ' . $relativePath);
    }
    return $source;
}

testCase('all admin reports share one structured responsive layout', function (): void {
    $source = adminReportsUiSource('views/admin/reports.php');

    foreach ([
        'admin-report-shell container-fluid',
        'aria-labelledby="active-report-title"',
        '<header class="admin-report-toolbar">',
        'class="admin-report-actions"',
        'class="admin-report-content"',
        'class="admin-report-metrics"',
        'class="admin-card admin-report-chart-card"',
        'class="admin-card admin-report-table-card"',
        'data-admin-chart-scroll',
        'data-admin-chart-scroll-content',
        'chart; scroll horizontally to view all data points',
        'role="region"',
        'scroll horizontally to view all columns',
        'tabindex="0"',
    ] as $contract) {
        assertStringContains($contract, $source);
    }

    assertSameValue(
        1,
        substr_count($source, '<section class="admin-report-shell'),
        'Every report key must render through the one shared report shell.'
    );
    assertSameValue(
        1,
        substr_count($source, 'class="admin-report-content"'),
        'Chart and table spacing must be owned by one shared content stack.'
    );
});

testCase('admin report controls preserve existing request and action contracts', function (): void {
    $source = adminReportsUiSource('views/admin/reports.php');

    foreach ([
        'method="GET"',
        'name="report"',
        'name="from"',
        'name="to"',
        'type="submit"',
        'modules/reports/export_csv.php',
        'data-admin-print',
        'data-admin-print-region',
        'Print <?= htmlspecialchars($report[\'title\']',
        '<span>Print / Save PDF</span>',
        'admin-report-print-period',
    ] as $contract) {
        assertStringContains($contract, $source);
    }
});

testCase('admin report styling keeps controls cards chart and table consistent', function (): void {
    $css = adminReportsUiSource('assets/css/admin.css');

    foreach ([
        '.admin-report-actions {',
        'grid-template-columns: repeat(12, minmax(0, 1fr));',
        '.admin-report-actions .admin-report-date-field {',
        'grid-column: span 3;',
        '.admin-report-actions > .admin-action-button {',
        'min-height: 44px;',
        'white-space: nowrap;',
        '.admin-report-actions > .admin-action-button svg {',
        'flex: 0 0 18px;',
        'grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));',
        '.admin-report-content {',
        'gap: 24px;',
        '.admin-report-chart-card,',
        '.admin-report-table-card {',
        'max-width: none;',
        '.admin-chart-canvas-wrap:focus-visible {',
        '.admin-chart-scroll-content {',
        'overflow-x: auto;',
        'overscroll-behavior-inline: contain;',
        'scrollbar-gutter: stable;',
        'width: max(100%, var(--admin-chart-min-width, 100%));',
        '.admin-reports-page .admin-table-wrap:focus-visible {',
        'grid-template-columns: repeat(6, minmax(0, 1fr));',
        'grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));',
        '@media (max-width: 1499.98px) {',
        '@media (max-width: 1230px) {',
        'grid-template-columns: repeat(2, minmax(0, 1fr));',
        '@media (max-width: 575.98px) {',
    ] as $contract) {
        assertStringContains($contract, $css);
    }

    assertFalseValue(
        str_contains($css, '.admin-report-table-card {' . PHP_EOL . '  max-width: 786px;'),
        'Report tables must not return to the former narrow fixed-width layout.'
    );
});

testCase('admin report toolbar has no nested divider and report hovers stay in the blue system', function (): void {
    $css = adminReportsUiSource('assets/css/admin.css');

    foreach ([
        '.admin-reports-page .admin-report-toolbar {',
        'border: 0;',
        'background: transparent;',
        'box-shadow: none;',
        '.admin-reports-page .admin-report-actions .admin-action-button.is-primary:hover,',
        'background: var(--sq-button-primary-hover);',
        '.admin-reports-page .admin-report-actions .admin-action-button:not(.is-primary):not(.is-danger):hover,',
        'background: var(--sq-primary-soft);',
        'border-color: var(--sq-primary);',
    ] as $contract) {
        assertStringContains($contract, $css);
    }
});

testCase('admin report charts derive horizontal scroll width from data density', function (): void {
    $admin = adminReportsUiSource('assets/js/admin.js');

    foreach ([
        "canvas.closest('[data-admin-chart-scroll-content]')",
        'const longestLabelLength = labels.reduce(',
        "const basePointWidth = type === 'bar' && !isHorizontal ? 104 : 84;",
        "const maximumPointWidth = type === 'bar' && !isHorizontal ? 180 : 150;",
        'const minimumChartWidth = isHorizontal || isCircular',
        'style.setProperty(\'--admin-chart-min-width\', `${minimumChartWidth}px`)',
    ] as $contract) {
        assertStringContains($contract, $admin);
    }
});

testCase('admin reports select chart types that match the underlying comparison', function (): void {
    $base = [
        'title' => 'Report',
        'insight' => 'Summary.',
        'series' => [],
        'table' => ['rows' => []],
    ];

    $queue = reportChartConfig(array_replace($base, [
        'key' => 'queue_summary',
        'table' => ['rows' => [
            ['report_date' => '2026-08-24', 'status' => 'waiting', 'total' => 2],
            ['report_date' => '2026-08-24', 'status' => 'completed', 'total' => 3],
            ['report_date' => '2026-08-25', 'status' => 'completed', 'total' => 4],
        ]],
    ]));
    assertSameValue('bar', $queue['type']);
    assertSameValue(true, $queue['stacked']);
    assertSameValue(['2026-08-24', '2026-08-25'], $queue['labels']);
    assertSameValue(['waiting', 'completed'], array_column($queue['datasets'], 'style_key'));

    $prediction = reportChartConfig(array_replace($base, [
        'key' => 'predicted_vs_actual',
        'series' => [['label' => '2026-08-25', 'predicted' => 12, 'actual' => 15]],
    ]));
    assertSameValue(['predicted', 'actual'], array_column($prediction['datasets'], 'style_key'));

    $noShow = reportChartConfig(array_replace($base, [
        'key' => 'no_show',
        'table' => ['rows' => [
            ['reason' => 'No response', 'total' => 2],
            ['reason' => 'No response', 'total' => 1],
            ['reason' => 'Client left', 'total' => 1],
        ]],
    ]));
    assertSameValue('doughnut', $noShow['type']);
    assertSameValue([3, 1], $noShow['datasets'][0]['data']);
    assertSameValue('reason_breakdown', $noShow['datasets'][0]['style_key']);

    $satisfaction = reportChartConfig(array_replace($base, [
        'key' => 'satisfaction',
        'table' => ['rows' => [
            ['service_name' => 'Dental', 'avg_rating' => 5, 'feedback_count' => 1],
            ['service_name' => 'Dental', 'avg_rating' => 3, 'feedback_count' => 3],
        ]],
    ]));
    assertSameValue('horizontalBar', $satisfaction['type']);
    assertSameValue([3.5], $satisfaction['datasets'][0]['data']);
    assertSameValue('rating', $satisfaction['datasets'][0]['style_key']);
    assertSameValue(5, $satisfaction['suggestedMax']);

    foreach (['counter_performance', 'staff_productivity'] as $key) {
        $comparison = reportChartConfig(array_replace($base, [
            'key' => $key,
            'series' => [['label' => 'Window 1', 'value' => 4]],
        ]));
        assertSameValue('horizontalBar', $comparison['type']);
    }

    foreach (['turnaround_time', 'daily_monthly_stats'] as $key) {
        $trend = reportChartConfig(array_replace($base, [
            'key' => $key,
            'series' => [['label' => '2026-08-25', 'value' => 4]],
        ]));
        assertSameValue('line', $trend['type']);
    }
});

testCase('admin report print layout is a dedicated PDF-safe composition', function (): void {
    $css = adminReportsUiSource('assets/css/admin.css');
    $admin = adminReportsUiSource('assets/js/admin.js');

    foreach ([
        '@page {',
        'size: A4 landscape;',
        '.admin-reports-page .admin-report-shell',
        'print-color-adjust: exact;',
        'break-before: page;',
        '.admin-report-print-period {',
        'display: table-header-group;',
        'page-break-inside: avoid;',
        'table-layout: fixed;',
    ] as $contract) {
        assertStringContains($contract, $css);
    }

    foreach ([
        'function prepareAdminPrint()',
        'function restoreAdminPrint()',
        'function scheduleAdminPrintRestore(',
        'function openAdminPrintDialog(button)',
        "window.addEventListener('beforeprint', prepareAdminPrint)",
        "window.addEventListener('afterprint', restoreAdminPrint)",
        "window.addEventListener('focus', () => scheduleAdminPrintRestore(250))",
        "window.matchMedia?.('print').addEventListener?.('change'",
        "document.body.classList.contains('admin-print-mode')",
        'activeCharts.forEach((chart) => chart.resize())',
    ] as $contract) {
        assertStringContains($contract, $admin);
    }

    assertSameValue(1, substr_count($css, '@media print {'), 'Admin reports must have one authoritative print ruleset.');
});

testCase('admin chart presentation uses semantic non-green series and accessible line styles', function (): void {
    $admin = adminReportsUiSource('assets/js/admin.js');
    $css = adminReportsUiSource('assets/css/admin.css');

    foreach ([
        "actual: { color: amber, dash: [8, 5], pointStyle: 'triangle' }",
        "predicted: { color: primaryColor, dash: [], pointStyle: 'circle' }",
        'const seriesStyle = semanticSeriesStyles[dataset.style_key]',
        "borderDash: type === 'line' ? seriesStyle.dash : []",
        'pointStyle: seriesStyle.pointStyle',
        "--admin-chart-series-amber: #F59E0B;",
        "--admin-chart-series-violet: #7C3AED;",
        "--admin-chart-series-cyan: #0891B2;",
        "--admin-chart-series-rose: #E11D48;",
    ] as $contract) {
        assertStringContains($contract, $admin . $css);
    }
});

testCase('shared interaction polish is pointer-aware and motion-safe', function (): void {
    $shared = adminReportsUiSource('assets/css/style.css');
    $admin = adminReportsUiSource('assets/css/admin.css');

    foreach ([
        '@media (hover: hover) and (pointer: fine) {',
        'transform: translateY(-1px);',
        '@media (prefers-reduced-motion: reduce) {',
        'transition: none !important;',
        '--sq-motion-ease: cubic-bezier(.2, 0, 0, 1);',
        '--admin-motion-ease: cubic-bezier(.2, 0, 0, 1);',
    ] as $contract) {
        assertStringContains($contract, $shared . $admin);
    }
});
