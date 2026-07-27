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
        '@media (max-width: 1199.98px) {',
        '@media (max-width: 575.98px) {',
    ] as $contract) {
        assertStringContains($contract, $css);
    }

    assertFalseValue(
        str_contains($css, '.admin-report-table-card {' . PHP_EOL . '  max-width: 786px;'),
        'Report tables must not return to the former narrow fixed-width layout.'
    );
});

testCase('admin report charts derive horizontal scroll width from data density', function (): void {
    $admin = adminReportsUiSource('assets/js/admin.js');

    foreach ([
        "canvas.closest('[data-admin-chart-scroll-content]')",
        'const longestLabelLength = labels.reduce(',
        "const basePointWidth = type === 'bar' ? 104 : 84;",
        "const maximumPointWidth = type === 'bar' ? 180 : 150;",
        'const minimumChartWidth = Math.max(320, (labels.length * pointWidth) + 96);',
        'style.setProperty(\'--admin-chart-min-width\', `${minimumChartWidth}px`)',
    ] as $contract) {
        assertStringContains($contract, $admin);
    }
});
