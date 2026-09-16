# CSS refactoring baseline and guardrails

SmartQMS keeps three browser-ready custom stylesheet entry points and uses
Bootstrap 5.3.8 as the generic component/layout foundation. No CSS build step
is required.

## Recorded baseline

The pre-refactor production baseline on 2026-09-08 was:

| Metric | Baseline |
| --- | ---: |
| Physical custom-CSS lines | 15,308 |
| `style.css` lines | 10,331 |
| `admin.css` lines | 4,845 |
| `display.css` lines | 132 |
| `!important` declarations | 228 |
| Non-print `!important` declarations | 111 |
| Media queries | 101 |
| Hardcoded color literals | 658 |
| Exact duplicate selector blocks | 8 |

The completed refactor records the following acceptance snapshot:

| Metric | Refactored | Change |
| --- | ---: | ---: |
| Physical custom-CSS lines | 2,770 | -82% |
| `style.css` lines | 1,929 | -81% |
| `admin.css` lines | 694 | -86% |
| `display.css` lines | 147 | +11% (display rules isolated here) |
| `!important` declarations | 135 | -41% |
| Non-print `!important` declarations | 18 | -84% |
| Media queries | 100 | -1% |
| Hardcoded color literals | 631 | -4% |
| Exact duplicate selector blocks | 0 | -100% |

Run `php scripts/css_audit.php` after CSS changes. It reports the same metrics
and fails if the three production stylesheets exceed 7,650 physical lines, if
their braces are unbalanced, or if an exact selector/declaration block is
duplicated in the same at-rule context.

## Ownership and loading

- Public, authentication, client, and staff surfaces load Bootstrap and
  `style.css`.
- Admin surfaces additionally load `admin.css`.
- The privacy-safe queue display additionally loads `display.css`.
- Shared `--sq-*` variables own color, type, space, shape, focus, and motion.
  Bootstrap variables are mapped to that semantic layer in `style.css`.
- Admin-only variables are limited to component composition such as sidebar,
  chart, and report dimensions. Existing `--admin-*` aliases remain only where
  current JavaScript or compatibility tests still consume them.

Bootstrap is installed by Composer and published locally with
`php scripts/publish_bootstrap.php`. Use `--check` in CI/deployment checks to
verify the public CSS, JavaScript bundle, license, version, and SHA-256 manifest
without rewriting them.
