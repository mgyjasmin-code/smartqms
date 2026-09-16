# Staff workspace override

Inherits `../MASTER.md`.

- Density: operational but calm. The next permitted action and currently active ticket must be identifiable in under two seconds.
- KPI layout: four equal desktop cards, two-up tablet wrap, and a single-column grid on small screens.
- Dashboard order: claimed-counter status, last successful update, current ticket and permitted actions, four KPI cards, then Active Waiting Queue.
- The shared staff header is preserved; dashboard reference treatments apply only inside the staff main-content region.
- Counter status changes remain available from the counter strip disclosure, while the visible status always uses a written label plus a semantic dot.
- Current-ticket actions use visible labels and 44px touch targets in a wrapping footer outside the scrollable table; waiting rows expose Call only for the first eligible FIFO ticket and visibly disable later rows.
- Counter identity appears before actions and states Open, Busy, or Closed using text plus icon/dot.
- Terminal actions use the shared confirmation modal. Complete is positive; Void is danger; Recall and Start remain secondary/primary according to lifecycle.
- The FIFO queue stays a semantic table and scrolls horizontally below 992px.
- Arrival Check-In tabs retain labels beside icons. Camera unsupported/error states always link users to Manual Input.
- Printable results omit shell chrome and preserve a high-contrast queue number.
