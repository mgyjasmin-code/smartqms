# SmartQMS Civic Healthcare Design System

Status: production source of truth  
Version: 3.2 (uniform 60/30/10 blue system)  
Applies to: public, Staff, Admin, display, print, and static wireframes

Visual source of truth: `../../docs/SMARTQMS_COMPLETE_UIUX_PROMPT_ARCHITECTURE.md`. Its healthcare palette, Poppins typography, responsive architecture, product rules, privacy boundaries, and WCAG 2.2 AA contract override earlier visual experiments.

## Product principles

SmartQMS is a civic-healthcare operations product. The interface must feel calm, trustworthy, legible, and efficient under time pressure. Visual polish may never obscure a queue state, a destructive action, or a required field.

1. **Arrival before decoration.** Current lifecycle state, queue number, counter, and next action receive the strongest hierarchy.
2. **One system, three roles.** Public, Staff, and Admin experiences share tokens, navigation behavior, controls, feedback, and accessibility while retaining role-specific information density.
3. **Accessible by default.** Normal text targets WCAG AA (4.5:1), meaningful controls and boundaries target 3:1, focus is always visible, and color is paired with text or iconography.
4. **Local and resilient.** Fonts, icons, Bootstrap, scripts, and identity assets are served from SmartQMS. Core workflows remain usable without optional animation or browser notification permission.
5. **Behavior is authoritative.** Existing routes, sessions, CSRF, native validation, strict FIFO, lifecycle transitions, ML contracts, printing, and role boundaries are not redesigned away.

## Healthcare color hierarchy (60/30/10)

- Roughly 60% of each composition is hygienic canvas and content surface: blue-white `#F5F9FF`, white `#FFFFFF`, or their dark-theme equivalents.
- Roughly 30% is institutional structure: deep text navy `#102A43`, government navy `#174A6E`, and restrained blue-gray surfaces.
- Roughly 10% is interaction emphasis. Exact action blue `#147BFE` is the brand accent for focus, selected state, data, and non-text emphasis. Filled CTAs use the accessible deep-blue shade `#075DBD`, hover at `#084784`, and white labels.
- Do not place normal-size white text directly on `#147BFE`; use the deep-blue button component token whenever a filled control needs a white label.
- The primitive blue scale is `#EAF3FF`, `#D6E8FF`, `#B8D8FF`, `#9BCBFF`, `#76B5FF`, `#3B92FF`, `#147BFE`, `#0875F5`, `#075DBD`, and `#084784`.
- Success, availability, and completed states use the same blue family plus written labels and icons. Warning and danger remain semantic amber/red exceptions used only to communicate meaning.

## Semantic token contract

Production CSS uses `--sq-*` variables. Legacy `--admin-*` and shared variables are compatibility aliases only.

| Role | Light | Dark |
| --- | --- | --- |
| Canvas | `--sq-canvas: #F5F9FF` | `#071526` |
| Surface | `--sq-surface: #FFFFFF` | `#0B2038` |
| Raised surface | `--sq-surface-raised: #FFFFFF` | `#12385F` |
| Strong text | `--sq-text: #102A43` | `#F2FAFB` |
| Muted text | `--sq-text-muted: #486581` | `#B7CCE3` |
| Structure | `--sq-secondary: #174A6E` | `#9BCBFF` |
| CTA | `--sq-primary: #147BFE` | `#147BFE` |
| CTA hover | `--sq-primary-hover: #3B92FF` | `#3B92FF` |
| CTA label | `--sq-on-primary: #020617` | `#020617` |
| Filled button | `--sq-button-primary-bg: #075DBD` | `#075DBD` |
| Filled button label | `--sq-button-primary-text: #FFFFFF` | `#FFFFFF` |
| Border | `--sq-border: #C9D9EB` | `#294A6A` |
| Focus | `--sq-focus: #147BFE` | `#3B92FF` |
| Success / available / completed | `#147BFE` | `#76B5FF` with theme-safe surface |
| Warning | `#9A6700` | `#9A6700` with theme-safe surface |
| Danger | `#B42318` | `#B42318` with theme-safe surface |

Status colors always include a written label such as Scheduled, Waiting, Calling, In Progress, Completed, or Void.

## Typography

- All display, body, interface, chart, print, and numeric queue text: self-hosted Poppins.
- Poppins weights 400, 500, 600, and 700 are bundled locally.
- Fallbacks: `system-ui, "Segoe UI", sans-serif`.
- Body: 16px / 1.6. Supporting text is 14px minimum; 12px is reserved for nonessential captions.
- Page title: `clamp(1.75rem, 4vw, 2.625rem)` with weight 600.
- Queue number: tabular numerals, `clamp(3rem, 9vw, 7rem)`, maximum two visual lines.
- Long content uses a readable measure of roughly 68 characters.

## Spatial system

Use the 4/8px rhythm through `--sq-space-*`: 4, 8, 12, 16, 24, 32, 48, and 64px. Component padding is normally 16 or 24px. Section separation is 32 or 48px. Small screens use 16px page gutters, tablets 24px, and desktops 32px.

## Shape, border, shadow, and motion

- Inputs and buttons use 12px radius; cards and panels use 16px; feature panels and modals use 20px; badges are pills.
- Borders use 1px semantic borders, with 2px borders for selected cards.
- Standard cards use a border and no shadow or a very soft shadow; stronger elevation is reserved for menus and dialogs.
- Focus uses a visible 2–3px action-blue outline with offset.
- Motion: 150ms interaction transitions and 240ms content entry. No layout-critical dimension animation.
- `prefers-reduced-motion: reduce` disables nonessential transitions, calling flash animation, and smooth scrolling.

## Component standards

### Buttons

- Minimum 44×44px target; visible label is retained for primary workflow actions.
- Primary controls use accessible deep blue `#075DBD` with white text while `#147BFE` remains the focus and selected accent; secondary uses government navy, and danger is solid semantic red.
- Icon-only actions are allowed only in dense tables, with `aria-label`, title/tooltip, 44px hit area, and consistent Lucide stroke.
- Busy state changes the label, sets `aria-busy`, and prevents repeated submission without resizing.

### Forms

- Visible labels, stable helper/error space where layout shift is disruptive, and native HTML validation.
- Controls share height, padding, radius, border, theme, and focus tokens.
- Server errors remain inline and connected through `aria-describedby`/`aria-invalid`.
- Optional fields are explicitly labelled optional; required state is not conveyed by color alone.

### Cards and metrics

- Cards use white or Gray 10 surface plus a 1px hairline and no shadow.
- Metrics show label, tabular value, contextual note, and a semantic vector icon.
- Empty states explain what happened and, where appropriate, expose one next action.

### Tables

- Full-width table-first layout with sticky-feeling visual headers, 56px minimum rows, and tabular numeric columns.
- Mobile preserves a real table in a horizontally scrollable, keyboard-focusable region.
- Row actions use a single inline action cluster with accessible icon buttons.

### Modal, toast, dropdown

- Bootstrap owns keyboard handling, focus trap, Escape, backdrop, and focus return.
- Dialogs are centered and scrollable with title, concise consequence, and clear primary/secondary action.
- Toasts appear in a shared top-right host and use text + icon + blue state border for success or semantic amber/red for warning and danger.
- Dropdowns remain inside viewport and provide 44px rows.

### Charts

- Charts include a title, written insight, accessible canvas label, and exact-value table.
- Dense plots sit in a horizontally scrollable region with a calculated minimum width.
- Series are distinguishable by label and shape as well as color.

## Responsive structure

- Mobile: 0–575px, single-column content, full-width primary CTAs, horizontal data overflow.
- Tablet: 576–991px, two-column forms/metrics where labels remain readable.
- Desktop: 992px+, fixed 276px role sidebar, flexible topbar search, and 1280px content measure where appropriate.
- Authenticated search expands inside its reserved flex region and never replaces the profile control.
- At 200% zoom, workflow controls remain reachable without two-dimensional page scrolling except intentional tables/charts.

## Identity and iconography

The SmartQMS mark combines a protective heart, health pulse, and queue node. Use `assets/images/brand/smartqms-mark.svg` or the equivalent shared inline partial. Lucide is the only interface icon family. Decorative icons are hidden from assistive technology; standalone icon buttons always have an accessible name.

## Verification checklist

- 1440px, 768px, and 390px in light and dark.
- Keyboard-only navigation, visible focus, modal Escape/focus return, off-canvas close, and search result navigation.
- WCAG AA text, 3:1 meaningful control boundaries/icons, status text alongside color.
- Native form validation, server error retention, busy state, and exactly-once submission.
- Reduced motion, 200% zoom, print views, horizontal tables/charts, QR legibility.
- Network panel contains no external font or icon requests.
