<?php

function highFidelityUiSource(string $relativePath): string {
    $source = file_get_contents(SMARTQMS_ROOT . '/' . $relativePath);
    if ($source === false) {
        throw new RuntimeException('Could not read ' . $relativePath);
    }
    return $source;
}

testCase('SmartQMS design system documents the approved civic healthcare contract', function (): void {
    foreach ([
        'design-system/smartqms/MASTER.md',
        'design-system/smartqms/pages/public.md',
        'design-system/smartqms/pages/staff.md',
        'design-system/smartqms/pages/admin.md',
    ] as $path) {
        assertTrueValue(is_file(SMARTQMS_ROOT . '/' . $path), $path . ' must exist.');
    }

    $master = highFidelityUiSource('design-system/smartqms/MASTER.md');
    foreach (['#F5F9FF', '#174A6E', '#147BFE', '#020617', '#102A43', '60/30/10', 'Poppins', '44px', 'WCAG'] as $contract) {
        assertStringContains($contract, $master, 'Master design contract');
    }
});

testCase('wireframe gallery covers public staff and admin journeys at target widths', function (): void {
    $gallery = highFidelityUiSource('docs/ui-ux/wireframes/index.html');
    foreach (['landing', 'booking', 'tracker', 'login', 'display', 'staff', 'checkin', 'admin', 'management', 'reports'] as $screen) {
        assertStringContains('data-screen="' . $screen . '"', $gallery, 'Missing wireframe screen ' . $screen . '.');
    }
    foreach (['data-gallery-width="1440"', 'data-gallery-width="768"', 'data-gallery-width="390"'] as $width) {
        assertStringContains($width, $gallery, 'Missing responsive gallery control.');
    }
    $galleryStyles = highFidelityUiSource('docs/ui-ux/wireframes/wireframes.css');
    assertStringContains('.prototype[hidden]', $galleryStyles, 'Inactive journey frames must stay out of layout.');
    assertStringContains('.state-overlay[hidden]', $galleryStyles, 'The state overlay must be absent in the default state.');
    assertFalseValue(str_contains($gallery, 'fetch('), 'Static wireframes must not call production APIs.');
});

testCase('production visual assets are local and use the shared SVG identity', function (): void {
    foreach ([
        'assets/images/brand/smartqms-mark.svg',
        'assets/vendor/fonts/poppins-regular.ttf',
        'assets/vendor/fonts/poppins-latin-500.woff2',
        'assets/vendor/fonts/poppins-latin-600.woff2',
        'assets/vendor/fonts/poppins-latin-700.woff2',
        'assets/vendor/lucide/lucide.min.js',
        'assets/vendor/chart.js/chart.umd.min.js',
    ] as $path) {
        assertTrueValue(is_file(SMARTQMS_ROOT . '/' . $path), $path . ' must be self-hosted.');
    }

    $shellHeader = highFidelityUiSource('views/shared/includes/shell_header.php');
    $shellFooter = highFidelityUiSource('views/shared/includes/shell_footer.php');
    assertStringContains("assets/images/brand/smartqms-mark.svg", $shellHeader);
    assertStringContains("assets/vendor/lucide/lucide.min.js", $shellFooter);
    assertStringContains("assets/vendor/chart.js/chart.umd.min.js", $shellFooter);

    $productionSources = '';
    foreach (['index.php', 'login/index.php', 'queue/join/index.php', 'views/public/tracker.php', 'views/shared/includes/shell_header.php', 'views/shared/includes/shell_footer.php'] as $path) {
        $productionSources .= highFidelityUiSource($path);
    }
    foreach (['fonts.googleapis.com', 'fonts.gstatic.com', 'unpkg.com/lucide', 'cdn.jsdelivr.net/npm/bootstrap-icons', 'cdn.jsdelivr.net/npm/chart.js'] as $remote) {
        assertFalseValue(str_contains($productionSources, $remote), 'Production UI must not request ' . $remote . '.');
    }
});

testCase('shared semantic tokens preserve compatibility aliases and both themes', function (): void {
    $styles = highFidelityUiSource('assets/css/style.css');
    foreach (['--sq-canvas', '--sq-surface', '--sq-navy', '--sq-primary', '--sq-focus', '--sq-font-heading', '--sq-font-body'] as $token) {
        assertStringContains($token, $styles, 'Missing semantic token ' . $token . '.');
    }
    assertStringContains('html[data-app-theme="dark"]', $styles);
    assertStringContains('--bg-page: var(--sq-canvas)', $styles);
    assertStringContains('@media (prefers-reduced-motion: reduce)', $styles);

    $admin = highFidelityUiSource('assets/css/admin.css');
    assertStringContains('--admin-bg: var(--sq-canvas)', $admin);
    assertStringContains('--admin-primary: var(--sq-primary)', $admin);
});

testCase('action blue and dimensional admin navigation follow the 60 30 10 contract', function (): void {
    $styles = highFidelityUiSource('assets/css/style.css');
    foreach ([
        '--sq-action-blue: #147BFE',
        '--sq-primary: var(--sq-action-blue)',
        '--sq-on-primary: #020617',
        '--sq-focus: var(--sq-action-blue)',
        '--sq-success: var(--sq-primary)',
        '--sq-status-completed: var(--sq-primary)',
        '--sq-button-primary-bg: var(--sq-blue-800)',
        '--sq-button-primary-text: #FFFFFF',
        '--sq-canvas: #F5F9FF',
        '--sq-surface: #0B2038',
    ] as $contract) {
        assertStringContains($contract, $styles, 'Missing action-blue contract.');
    }

    $productionColorSources = $styles
        . highFidelityUiSource('assets/css/admin.css')
        . highFidelityUiSource('assets/css/display.css')
        . highFidelityUiSource('assets/js/admin.js')
        . highFidelityUiSource('views/admin/dashboard.php');
    foreach ([
        '#13795B', '#1D9E75', '#34D399', '#1FA37A', '#10B981', '#198038',
        '#16794A', '#70D3CF', '#006B73', '#00575E', '#00464C', '#16A37B',
        '#078765', '#126B50', '#3B6D11', '#86EFAC',
    ] as $retiredGreen) {
        assertFalseValue(
            str_contains(strtoupper($productionColorSources), $retiredGreen),
            'Retired green/teal color must not be present: ' . $retiredGreen . '.',
        );
    }
    assertFalseValue(str_contains($productionColorSources, 'is-green'), 'Green visual variants must be retired.');

    $adminScripts = highFidelityUiSource('assets/js/admin.js');
    foreach (['secondaryBlue', 'deepBlue', 'lightBlue', "'#147BFE'"] as $chartContract) {
        assertStringContains($chartContract, $adminScripts, 'Admin charts must use the blue series family.');
    }

    foreach ([
        '.admin-page .admin-action-button.is-primary',
        'color: var(--sq-button-primary-text)',
        'border-color: var(--sq-button-primary-bg)',
    ] as $buttonContract) {
        assertStringContains($buttonContract, highFidelityUiSource('assets/css/admin.css'), 'Admin primary buttons need accessible labels.');
    }

    $admin = highFidelityUiSource('assets/css/admin.css');
    foreach ([
        '.admin-page .admin-nav-label',
        '.admin-page .admin-nav-link.is-active',
        '--admin-sidebar-bg: #075DBD',
        'inset 3px 0 #FFFFFF',
        'background-image: linear-gradient(180deg',
        '.admin-page .admin-dashboard-grid > *',
        'overflow-x: auto',
        '--admin-topbar-height: 80px',
        'height: var(--admin-topbar-height)',
        'max-height: var(--admin-topbar-height)',
    ] as $contract) {
        assertStringContains($contract, $admin, 'Missing dimensional navigation contract.');
    }

    $shell = highFidelityUiSource('views/shared/includes/shell_header.php');
    assertStringContains('app-nav-label admin-nav-label', $shell);
    assertStringContains("'nav_label' => 'Admin workspace'", highFidelityUiSource('views/admin/includes/header.php'));
});

testCase('public theme controls are shared without changing queue form contracts', function (): void {
    $theme = highFidelityUiSource('assets/js/theme.js');
    assertStringContains("smartqms-theme", $theme);
    assertStringContains('data-sq-theme-toggle', $theme);

    $booking = highFidelityUiSource('queue/join/index.php');
    foreach (['name="first_name"', 'name="last_name"', 'name="phone_number"', 'name="service_id"', '<?= csrfInput() ?>'] as $contract) {
        assertStringContains($contract, $booking, 'Booking contract changed.');
    }
    assertStringContains('assets/js/theme.js', $booking);

    $login = highFidelityUiSource('login/index.php');
    foreach (['name="login_id"', 'name="password"', 'modules/auth/login.php'] as $contract) {
        assertStringContains($contract, $login, 'Authentication contract changed.');
    }
});
