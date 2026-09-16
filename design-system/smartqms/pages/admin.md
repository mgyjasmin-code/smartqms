# Admin workspace override

Inherits `../MASTER.md`.

- Density: balanced analytics. Prefer comparable rows of metrics and aligned card edges over oversized empty canvas.
- Dashboard metrics use four equal cards. Charts form a two-column row and stack below 992px.
- Dashboard order is overview and update time, operational metrics, then charts with expandable exact-value tables. Background updates wait while those tables or dialogs are open or content has keyboard focus.
- Management pages use full-width table-first cards. Add actions live in the toolbar; forms use shared Bootstrap modals.
- Icon-only row actions have tooltips/accessibility labels and a single-line action cluster.
- Reports share one responsive toolbar: title, dates, Apply, Export CSV, and Print. Buttons never wrap their label.
- Metric cards wrap predictably; chart and table cards have 24px vertical separation and horizontal overflow only inside their data region.
- Admin-specific CSS may define analytics, management, report, and chart layout; shell and base components come from shared tokens.
- Sidebar hierarchy uses institutional navy depth, a labelled navigation group, separated icon tiles, and restrained elevation. Reserve `#147BFE` for the active indicator, active icon tile, and keyboard focus; inactive links remain neutral so the accent stays near the 10% allocation.
