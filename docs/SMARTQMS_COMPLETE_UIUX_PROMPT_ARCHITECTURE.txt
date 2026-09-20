# SmartQMS Complete UI/UX Prompt Architecture

Status: Final design direction  
Product: SmartQMS  
Audience: Client, Staff, Administrator, and Public Queue Display  
Visual direction: clean, simple, user-friendly, modern, government-oriented healthcare  
Default theme: light, with a persistent theme toggle  
Primary typeface: Poppins  
Icon system: Lucide SVG  

## 1. How to use this document

This is the authoritative UI/UX build prompt for SmartQMS. Give it to an AI coding assistant, designer, or developer together with the implementation plan and lifecycle document. Preserve the product rules, privacy constraints, data calculations, security behavior, and queue concurrency already implemented in the repository.

Do not redesign SmartQMS into a generic SaaS dashboard. It is a civic-healthcare operations system used by clients, frontline staff, administrators, and people watching a public display. Every screen must make the current state and next safe action immediately understandable.

## 2. Product outcome

Create a responsive queue-management experience that:

- lets guest clients reserve a visit date without creating an account;
- gives walk-ins and online reservations the same FIFO position based on physical check-in;
- lets staff operate one claimed counter from a single focused workspace;
- gives administrators clear configuration and evidence-based reporting;
- lets clients track a checked-in ticket through one secure QR tracker;
- provides SMS or browser alerts without making notifications essential to queue operation;
- supports an audible and visual public queue display;
- protects client identity throughout staff tables, displays, and feedback reports.

## 3. Fixed product rules

- SmartQMS has no Client login, registration, password, or account dashboard.
- Online booking is a visit-date reservation, not a timed appointment.
- A reservation has a reference number but no queue number and no FIFO position.
- Physical check-in creates the queue ticket and assigns its FIFO queue number.
- Walk-ins and checked-in reservations enter one FIFO queue.
- Staff may claim any enabled and unoccupied counter.
- Each counter can support several configured health services.
- Call Next selects the earliest eligible checked-in ticket supported by that counter.
- Each staff member controls only the ticket assigned to their claimed counter.
- Several counters may call or serve different tickets at the same time.
- Staff operational tables expose queue number, service, source, and timestamps, not client names.
- One QR code is generated after check-in. It opens a private tokenized tracker and later the feedback form.
- A reference-only public lookup exposes a privacy-safe reservation or ticket status.
- Cancellation requires the private management link issued with the reservation.
- Feedback is available after completion, is one response per ticket, and is anonymized in reports.
- Staff workspace remains a single operational page with no sidebar.
- Admin workspace retains a responsive sidebar.
- Reports remain ten separate report pages.

## 4. Complete experience architecture

### 4.1 Client journey

    Landing page
      -> Book a Visit
          -> Choose service
          -> Choose visit date
          -> Enter client details and consent
          -> Review
          -> Reservation confirmed
          -> Save reference number
      -> Track Queue or Reservation
          -> Reference-only safe lookup
      -> Cancel Reservation
          -> Private management link
          -> Confirm cancellation

    Physical arrival
      -> Staff finds reservation or creates walk-in
      -> Staff checks client in
      -> Queue number and QR tracker are generated
      -> Client scans QR or uses reference lookup
      -> Waiting
      -> Near-turn alert
      -> Calling: proceed to counter
      -> In Service
      -> Completed
      -> Optional anonymous feedback

### 4.2 Staff journey

    Staff login
      -> Single operational workspace
      -> Claim enabled counter
      -> Review operational cards
      -> Check in reservation or walk-in through Tools
      -> Call Next
      -> Recall or Start Service
      -> Complete, Skip, or Void according to lifecycle
      -> Repeat
      -> Release counter
      -> Logout

The staff member can view a compact read-only indicator for activity at other counters, but never receives action controls for another counter's ticket.

### 4.3 Administrator journey

    Admin login
      -> Dashboard
      -> Staff Accounts
      -> Health Services
      -> Service Windows
      -> Reports
          -> Queue Summary
          -> Predicted vs Actual Wait
          -> Peak Hour Analysis
          -> Counter Performance
          -> Turnaround Time
          -> No-Show
          -> Staff Productivity
          -> ML Accuracy
          -> Daily and Monthly Stats
          -> Satisfaction
      -> Profile, theme, language, logout

### 4.4 Public display journey

    Full-screen display
      -> Now Calling tickets and counters
      -> Recently called tickets
      -> Waiting summary
      -> Audible chime and bilingual announcement
      -> Automatic refresh
      -> Offline or connection-recovery state

## 5. Information architecture and page inventory

### Public and Client

- Landing page
- Guided booking flow
- Reservation confirmation
- Reference-status lookup
- Manage reservation verification
- Cancel reservation confirmation
- QR queue tracker
- Completed-ticket feedback
- Client-facing error, expired, unavailable, and offline states

### Staff

- Staff login
- Single live workspace
- Arrival check-in page or full-screen dialog
- Reservation search and confirmation
- Walk-in check-in
- QR and ticket handoff
- Batch printing
- Optional read-only activity history

### Admin

- Admin login
- Dashboard
- Staff Accounts
- Health Services
- Service Windows
- Ten separate reports
- Profile and password management

### Display and print

- Public queue display
- Printed queue ticket with QR
- Batch ticket slips
- Printable report layout

## 6. Technology stack

Use the repository's server-rendered architecture:

| Layer | Technology | UI/UX responsibility |
|---|---|---|
| Backend | PHP 8.x | Routes, sessions, CSRF, validation, authorization, rendering, APIs |
| Database | MySQL or MariaDB | Reservations, tickets, events, feedback, report sources |
| UI framework | Bootstrap 5 | Grid, utilities, responsive shell, forms, dialogs, menus, feedback |
| Current dependency note | Composer currently installs Bootstrap 5.0.2 | Keep compatibility or plan a tested upgrade to Bootstrap 5.3.x before using native color-mode features |
| Client behavior | Vanilla JavaScript ES6+ | Theme, language, polling, form steps, live updates, audio, charts |
| Charts | Locally bundled Chart.js | Responsive report visualizations |
| Icons | Locally bundled Lucide | One consistent SVG icon family |
| QR | endroid/qr-code 5.1 | Secure tracker QR generation |
| Typography | Self-hosted Poppins | Prevent external font requests and preserve availability |
| SMS | Provider adapter | TextBee, Infobip, PhilSMS, or another provider without coupling UI to one vendor |
| Audio | HTML Audio and Web Speech API fallback | Chime and spoken public-display announcement |

Serve Bootstrap, icons, charts, fonts, and application scripts locally. Do not depend on a CDN for core health-center operation.

### Bootstrap color-mode decision

If the project remains on Bootstrap 5.0.2, use a SmartQMS data-theme attribute and semantic CSS-variable overrides. If upgrading to Bootstrap 5.3.x, map the same semantic tokens to Bootstrap color modes. In either case:

- light is the default;
- the toggle is available in public, staff, and admin headers where appropriate;
- save the preference in localStorage;
- authenticated users may also persist it server-side;
- apply the stored theme before first paint to prevent flashing;
- support prefers-color-scheme only as the first-visit default when no preference exists.

## 7. Visual design system

### 7.1 Brand character

The interface should feel like a capable digital public service: calm, trustworthy, hygienic, contemporary, and welcoming. Use generous whitespace, strong structure, plain language, and clear operational states. Avoid glassmorphism, neon effects, decorative gradients, excessive shadows, and visually playful patterns that weaken government credibility.

Use the shield mark as the core identity. Display the product name as SmartQMS. The shield may combine protection, health, and orderly service, but must remain legible at 24px.

### 7.2 Three-layer token architecture

Use primitive tokens for raw values, semantic tokens for meaning, and component tokens for Bootstrap and custom components. Components must never contain repeated hardcoded color values.

    Primitive values
      -> Semantic purpose
          -> Component tokens

Example:

    --sq-teal-700: #006B73
    --sq-primary: var(--sq-teal-700)
    --sq-button-primary-bg: var(--sq-primary)

### 7.3 Healthcare color palette

#### Primitive brand colors

| Token | Value | Purpose |
|---|---:|---|
| Teal 50 | #EAFBFA | soft health background |
| Teal 100 | #CFF4F2 | selected and informational surface |
| Teal 200 | #9FE5E2 | subtle accent border |
| Teal 300 | #70D3CF | dark-theme secondary accent |
| Teal 400 | #3CB8B6 | illustrations and non-text accent |
| Teal 500 | #168F94 | secondary interactive accent |
| Teal 600 | #087F87 | emphasized information |
| Teal 700 | #006B73 | primary action |
| Teal 800 | #00575E | primary hover |
| Teal 900 | #00464C | primary active |
| Government navy | #174A6E | institutional structure and secondary action |
| Deep text navy | #102A43 | strong light-theme text |

White text on Teal 700 has an approximate contrast ratio of 6.27:1. White text on Government Navy has an approximate ratio of 9.37:1.

#### Light semantic colors

| Semantic token | Value |
|---|---:|
| Canvas | #F4FAFA |
| Surface | #FFFFFF |
| Surface subtle | #EAFBFA |
| Raised surface | #FFFFFF |
| Strong text | #102A43 |
| Muted text | #486581 |
| Border | #C7D9DE |
| Primary | #006B73 |
| Primary hover | #00575E |
| Primary active | #00464C |
| Secondary | #174A6E |
| Focus | #087F87 |
| Link | #00575E |

#### Dark semantic colors

| Semantic token | Value |
|---|---:|
| Canvas | #0B1F26 |
| Surface | #102A33 |
| Surface subtle | #163944 |
| Raised surface | #1C424D |
| Strong text | #F2FAFB |
| Muted text | #B7CDD3 |
| Border | #31515A |
| Primary | #5ED4D1 |
| Primary hover | #86E4E1 |
| Primary active | #A7EEEC |
| Primary text on light teal | #0B1F26 |
| Secondary | #9AC7DF |
| Focus | #70D3CF |
| Link | #86E4E1 |

#### Semantic status colors

| State | Strong color | Soft background | Icon and label |
|---|---:|---:|---|
| Scheduled | #486581 | #EDF4F8 | calendar-clock |
| Waiting | #175CD3 | #EAF2FF | clock-3 |
| Calling | #9A6700 | #FFF3C4 | megaphone |
| In Service | #6941C6 | #F0EAFE | stethoscope |
| Completed | #16794A | #E7F6ED | circle-check |
| Skipped | #B54708 | #FFF0E0 | skip-forward |
| Voided or Error | #B42318 | #FEECEB | circle-x |

Always pair state color with a written label and Lucide icon. Never rely on color alone.

### 7.4 Typography

- Use Poppins for headings, body text, controls, queue numbers, charts, and print.
- Self-host weights 400, 500, 600, and 700.
- Body: 16px, weight 400, line-height 1.6.
- Supporting text: 14px minimum; use 12px only for nonessential captions.
- Labels and buttons: 14–16px, weight 500 or 600.
- Page titles: clamp from 28px to 42px, weight 600.
- Section titles: 22–28px, weight 600.
- Queue numbers: clamp from 48px to 112px, weight 700, tabular numerals.
- Metrics: 30–44px, weight 700, tabular numerals.
- Use a maximum reading measure of 70 characters for instructions.

### 7.5 Spacing and sizing

Use a 4px base scale: 4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 80, and 96px.

- Page gutters: 16px mobile, 24px tablet, 32px desktop.
- Component gap: 12–16px.
- Card padding: 20px mobile, 24px desktop.
- Section gap: 32px mobile, 48–64px desktop.
- All primary controls: minimum 48px height.
- All other interactive targets: minimum 44 by 44px.
- Table rows: 56px minimum.

### 7.6 Shape, border, and elevation

- Input and button radius: 12px.
- Card and panel radius: 16px.
- Large feature panel or modal radius: 20px.
- Pill badges: full radius.
- Borders: 1px semantic border; 2px for selected cards.
- Use small and medium shadows only for raised menus, dialogs, selected cards, and sticky controls.
- Do not make every card float. Standard content cards use a border and very soft shadow or no shadow.

### 7.7 Icon system

Use Lucide exclusively. Default size is 20px with a consistent 2px stroke. Navigation and card icons may use 22–24px. Icons must support the label, not replace it. Icon-only controls are allowed only when space is limited and must include aria-label, title or tooltip, and a 44px target.

### 7.8 Motion

Use more visible but simple transitions:

- page content: 240ms fade plus 8px upward entry;
- cards: optional 40ms stagger, maximum six items;
- hover and focus: 150ms color, border, and shadow transition;
- modal and offcanvas: Bootstrap transition, 200–300ms;
- live ticket changes: 300ms crossfade and subtle highlight;
- Calling state: restrained pulse on the badge or announcement panel, not the entire page;
- toast: slide and fade for 220ms;
- never animate width, table column dimensions, or layout-critical height.

Respect prefers-reduced-motion by disabling translation, pulse, stagger, and smooth scrolling while keeping immediate state feedback.

## 8. Responsive layout architecture

Design fluidly rather than for a few fixed screenshots.

| Range | Layout behavior |
|---|---|
| 320–575px | one column, 16px gutters, full-width primary actions, stacked cards |
| 576–767px | one or two columns when content remains readable |
| 768–991px | tablet grid, 24px gutters, admin sidebar becomes offcanvas |
| 992–1199px | desktop shell, persistent Admin sidebar, staff rail may be left column |
| 1200–1399px | comfortable desktop density and wider tables |
| 1400px+ | centered maximum content widths; never stretch forms indefinitely |

Required test widths: 320, 390, 576, 768, 992, 1280, 1440, and 1920px. Also test 200% zoom and tablet landscape.

### Cards and tables

- Information and metric cards stack into one column on narrow screens and use two or four columns as space allows.
- Operational and Admin data remains a semantic table.
- Wrap tables in Bootstrap table-responsive regions with visible horizontal-scroll affordance.
- The table region must be keyboard-focusable and labelled.
- Keep the first important column visible where practical; do not use a fragile custom sticky column unless tested at all widths.
- Do not duplicate the same dataset into unrelated card markup solely for mobile.

### Client layout

Client pages are optimized for desktop and laptop but fully usable on phones and tablets. Use a maximum 1200px landing container and a maximum 860px form flow. On small screens, the booking stepper becomes a compact progress label and progress bar.

### Staff layout

Staff has no sidebar. Use a compact top bar and one operational page:

- at 992px and above: counter control is a sticky left rail occupying approximately 280–320px, with the workspace tables in the flexible main column;
- at 768–991px: counter control becomes a full-width control strip above metrics and tables;
- below 768px: counter control is a full-width summary card; counter selection opens a Bootstrap modal or offcanvas;
- never make the whole page horizontally scroll; only table regions may scroll.

### Admin layout

- at 992px and above: persistent 264–280px sidebar and flexible main content;
- below 992px: sidebar becomes Bootstrap offcanvas, opened from the top bar;
- preserve the current page, filters, and scroll state after closing the offcanvas;
- top bar contains page search, language, theme, notifications if used, and profile menu.

### Public display

Target 1366x768 and 1920x1080 landscape screens, while supporting browser fullscreen. Use a large Now Calling area, a secondary recently-called rail, clear counter labels, high contrast, and safe margins for overscan.

## 9. Bootstrap component inventory

Use Bootstrap as the behavioral and responsive foundation, then style it through SmartQMS tokens.

| Bootstrap component | SmartQMS use |
|---|---|
| container, container-fluid | public content and authenticated shells |
| row, col, g-* utilities | responsive cards, forms, and operational layout |
| navbar | public header and staff top bar |
| offcanvas | mobile Admin navigation and mobile counter selection |
| nav | Admin sidebar and public header links |
| dropdown | profile menu, Tools menu, row overflow actions |
| card | service cards, metric cards, counter control, tracker states |
| button and button group | primary actions, date granularity, report controls |
| form-control | names, references, phone, comments, search |
| form-select | service, visit date controls, filters, status |
| form-check and form-switch | consent, availability, enabled state, theme |
| input-group | reference search, masked mobile verification, search fields |
| invalid-feedback and valid-feedback | inline form validation |
| progress | booking step progress and optional queue progress |
| badge | lifecycle states and availability |
| alert | warnings, errors, privacy notes, unavailable state |
| modal | confirmation, destructive actions, focused short forms |
| toast | call confirmation, saved state, nonblocking notification |
| table and table-responsive | queues, management data, report data |
| list-group | How It Works steps, selected services, recent calls |
| accordion | landing FAQ and optional report methodology |
| collapse | compact secondary filters, not primary navigation |
| spinner-border | short asynchronous operations |
| placeholder | skeleton loading for cards and charts |
| pagination | long Admin tables and report data |
| tooltip | supplemental icon explanation; never required information |
| breadcrumb | deep Admin report and management context |
| visually-hidden | accessible chart summaries, live regions, icon names |
| ratio | QR and media containers where a stable aspect ratio is needed |

Do not use carousel, floating labels, popovers for required information, or nested modals. Avoid Bootstrap's color classes as the design source of truth; map variants to SmartQMS semantic tokens.

## 10. Global component specifications

### Buttons

- Primary: solid teal, one dominant primary action per section.
- Secondary: government navy outline or subtle filled surface.
- Tertiary: ghost button for low-priority actions.
- Danger: red, separated from primary actions.
- Default height 48px; large public buttons 52–56px.
- Busy actions show spinner plus stable label and prevent duplicates.
- Disabled actions include a nearby reason when the reason is not obvious.

### Forms

- Use permanent labels above fields, not placeholder-only labels.
- Show required and optional wording explicitly.
- Validate on blur and again on submit.
- Place errors under their field and focus the first invalid field.
- Preserve entered values after server validation.
- Use inputmode and autocomplete appropriately.
- Mobile number accepts Philippine formats but normalizes to E.164 server-side.
- Multi-step forms allow Back without losing values.

### Service cards

Each card includes service name, plain-language bilingual description, availability, and one selection control. Selected state uses a 2px teal border, check icon, and soft teal surface. Unavailable cards remain readable and explain why they cannot be selected.

### Status badges

Include icon, written status, and semantic background. Never show a colored dot alone.

### Tables

- Use comfortable 56px rows.
- Left-align text, right-align numbers, center status, and right-align actions.
- Use tabular numerals for queue numbers, times, and metrics.
- Use a single overflow menu for Recall, Skip, Void, Archive, or other uncommon actions.
- Keep the main safe action visible when appropriate.
- Provide empty, loading, failure, and no-results rows.

### Modals

- Use for short confirmation or focused edits, not multi-page navigation.
- On mobile, use near-full-width dialog or offcanvas.
- State the consequence in plain language.
- Return focus to the invoking control.
- Require a reason for Skip or Void.

### Toasts

- Shared top-right host on desktop and top-center full-width inset on mobile.
- Auto-dismiss routine success after 4–5 seconds.
- Important Calling state also changes persistent page content.
- Use aria-live polite; errors needing action remain until dismissed.

### Theme toggle

Use a labelled Bootstrap form-switch or button with sun and moon icons. The accessible label states the resulting action, such as Switch to dark mode. Do not use an unlabeled icon alone.

### Language control

Provide English and Filipino through a labelled compact control. Show one active language at a time to avoid clutter. Persist preference. All operational terms, errors, buttons, audio announcements, and printable instructions require reviewed translations.

### QR panel

Show the QR at high contrast with quiet white space, queue number, short instruction, reference fallback, and print-safe styling. Never encode client name, phone, or health details in the QR.

## 11. Page and mockup blueprints

### 11.1 Public landing page

Desktop structure:

    Accessible skip link
    Public navbar: shield, SmartQMS, Book a Visit, Track Queue, language, theme
    Hero:
      government-healthcare headline
      concise explanation
      primary Book a Visit
      secondary Track Queue
      service-hours status
    How It Works: three or four illustrated steps
    Services Offered: responsive service-card grid
    Queue tracking explanation
    Accessibility and privacy reassurance
    FAQ accordion
    Footer

Mobile keeps Book a Visit and Track Queue visible above the fold. Avoid decorative hero imagery that pushes actions below the fold.

### 11.2 Guided booking flow

Use five understandable stages:

1. Service
2. Visit Date
3. Your Details
4. Review
5. Confirmation

Desktop uses a horizontal stepper and centered form card. Mobile uses Step X of 5 plus a progress bar.

Fields: first name, last name, mobile number, service, visit date, and consent. Confirmation prominently shows reference, service, date, check-in hours, and the statement that the queue number is assigned after arrival.

### 11.3 Reservation lookup and management

Reference lookup is a short focused form. Safe result shows reservation status, visit date, service, and next instruction without identity. The private management link exposes cancellation. Place Cancel as a danger action and confirm it in a modal.

### 11.4 QR tracker

Show:

- queue number;
- lifecycle badge and progress timeline;
- service;
- people ahead;
- predicted wait with Last updated time;
- counter and large Proceed instruction when called;
- refresh or connection status;
- optional browser-notification permission prompt after the tracker is useful, never on initial load.

Completed state replaces live controls with a completion message and optional feedback form.

### 11.5 Staff workspace

Top bar:

- shield and SmartQMS;
- current date/time;
- staff identity;
- Tools dropdown containing Arrival Check-In and Batch Printing;
- language, theme, and Logout.

Workspace:

    Counter control rail or responsive counter card
    Completed Today | Serving | Waiting | Voided/Skipped
    Now Serving / Calling table
    Active Waiting Queue table
    Optional read-only other-counter activity strip

Only one ticket may be Calling or In Service at a staff member's claimed counter. Other counters can operate concurrently. The staff member's safe primary action is visible; Recall, Skip, and Void live in the row overflow menu. Complete is visible while In Service. Do not add keyboard shortcuts.

### 11.6 Arrival check-in

Use three clearly labelled modes:

- Find reservation by reference;
- Scan reservation information when supported;
- Create walk-in.

Show client identity only in this controlled check-in context. After confirmation, show the assigned queue number, QR, print action, and Close/Return to Workspace.

### 11.7 Admin shell and dashboard

Keep the sidebar. Desktop dashboard includes page title, date context, four to six summary metrics, daily queue trend, peak period summary, counter state, and recent administrative activity. Do not make the dashboard a substitute for the ten report pages.

### 11.8 Staff Accounts

List columns:

    Staff member | Email | Job title | Status | Current counter | Actions

Create/edit fields:

    First name | Last name | Email | Temporary password on create
    Job title optional | Mobile optional | Active/Inactive

No username, permanent counter assignment, or specialized-capability field.

### 11.9 Health Services

List columns:

    Service | Daily capacity | Reserved today | Supported counters
    Client availability | Actions

Create/edit fields:

    Service name | Client description | Maximum reservations per visit date
    Estimated duration | Available to clients | Display order

Codes and ML category values remain system-only.

### 11.10 Service Windows

List columns:

    Counter | Location | Services served | Current staff
    Runtime status | Configuration | Actions

Create/edit fields:

    Counter label | Location optional | Searchable service checklist
    Enabled for staff selection

Runtime state is read-only. No Shared/Specialized or Central Queue selector.

### 11.11 Reports shell

Each of the ten reports is a separate page using the same structure:

    Breadcrumb and report title
    Plain-language description and calculation note
    Filter card
    Apply filters | Reset | Export CSV | Print/PDF
    KPI cards
    Primary visualization and written insight
    Accessible exact-value table
    Empty, insufficient-data, and error states

Filters may include date range, service, counter, staff, status, ticket source, and granularity only when relevant. Do not display irrelevant filters.

### 11.12 Public display

Layout:

    SmartQMS shield and current time
    Large Now Calling region with queue number and counter
    Additional current calls in a responsive grid
    Recently Called rail
    Waiting summary
    Bilingual instruction
    Connection indicator

On a new call:

- play a short chime;
- speak the queue number and counter using the selected announcement language;
- visually highlight the new call;
- avoid repeated speech after reconnect or refresh by storing the last announced event ID;
- provide a visible Mute/Unmute control;
- respect browser autoplay limitations through an initial Enable announcements action.

## 12. Report visualization architecture

Do not use bar graphs for all reports.

| Report | Primary visual | Core KPIs |
|---|---|---|
| Queue Summary | stacked column by outcome and date | checked in, completed, waiting, skipped/voided |
| Predicted vs Actual Wait | two-line trend or paired scatter | predicted average, actual average, absolute error |
| Peak Hour Analysis | accessible day-by-hour heatmap | busiest hour, busiest day, peak arrivals |
| Counter Performance | ranked horizontal bar plus table | completed, average service time, utilization |
| Turnaround Time | line trend with wait and service breakdown | median, average, 90th percentile |
| No-Show | stacked column by no-show type | expired reservations, no response, staff void |
| Staff Productivity | ranked horizontal bar plus detailed table | completed, average duration, outcome counts |
| ML Accuracy | MAE/RMSE line trend and model cards | MAE, RMSE, sample size, model version |
| Daily/Monthly Stats | line for daily and columns for monthly | arrivals, completions, walk-in/online share |
| Satisfaction | 100% stacked rating distribution and service ranking | average rating, responses, response rate |

Every chart requires a title, unit-labelled axes, readable legend, exact-value tooltips, written summary, responsive behavior, reduced-motion support, and an accessible table. Use at most five categorical colors in one chart and never encode meaning through color alone.

## 13. Content and bilingual UX

- Use short sentences, common health-center vocabulary, and direct action verbs.
- Avoid technical terms such as ML category, lifecycle status, queue mode, or service encoding in client-facing pages.
- Translate meaning, not word-for-word syntax.
- Use English as fallback when a Filipino string is missing.
- Dates and times use the user's chosen language and Philippine timezone.
- Queue numbers and references remain unchanged across languages.
- Audio announcements must be independently reviewed because text-to-speech pronunciation varies.

Example:

    English: Your number will be called soon. Please remain nearby.
    Filipino: Malapit nang tawagin ang iyong numero. Mangyaring manatili sa malapit.

## 14. Privacy and security UX

- Never expose client names on the Staff workspace or public display.
- Show identity only during controlled staff check-in and authorized Admin investigation.
- Never put PII inside QR payloads.
- Avoid health-service names in SMS when that disclosure is unnecessary.
- Reference-only lookup returns safe status.
- Use neutral verification errors to prevent reservation discovery.
- Mask mobile numbers except where staff confirmation is authorized.
- Explain why consent is needed and link to a plain-language privacy notice.
- Confirm destructive actions and log actor, time, ticket, counter, and reason.
- Do not place API keys, SMS credentials, or tracker tokens in client-side source or logs.

## 15. Accessibility requirements

Meet WCAG 2.2 AA as the target:

- normal text contrast at least 4.5:1;
- meaningful non-text controls and boundaries at least 3:1;
- visible 2–3px focus indicator with offset;
- logical heading order and landmark regions;
- skip link on public and authenticated shells;
- all features keyboard operable;
- minimum 44px targets and 8px separation;
- labels connected to fields;
- errors connected with aria-describedby and aria-invalid;
- live queue and toast updates announced through appropriate aria-live regions;
- status never conveyed by color alone;
- dialogs trap focus, close with Escape, and restore focus;
- tables include captions or accessible names and sortable headers use aria-sort;
- charts have summaries and data tables;
- 200% zoom does not hide controls;
- reduced motion produces no loss of meaning;
- QR and printed content remain legible in grayscale.

## 16. Required UI states

Every data-driven page or component must design:

- initial;
- loading or skeleton;
- populated;
- empty;
- no search results;
- validation error;
- server error with recovery;
- offline or connection lost;
- stale data;
- permission denied where relevant;
- disabled with reason;
- success confirmation;
- concurrent-action conflict;
- session expired.

Queue actions must handle conflicts such as another staff member already claiming a counter or calling the same ticket. Show a plain-language explanation, refresh the affected data, and preserve the rest of the workspace.

## 17. Mockup deliverables

Create high-fidelity layouts for:

- landing page at 1440, 768, and 390px;
- booking flow at service, details, review, and confirmation states;
- reservation lookup, manage, tracker waiting, tracker calling, and feedback;
- staff workspace at 1440, 768, and 390px;
- staff arrival check-in and walk-in;
- Admin dashboard;
- Staff Accounts list and form;
- Health Services list and form;
- Service Windows list and form;
- one shared report shell plus all ten report content variants;
- public display at 1920x1080 and 1366x768;
- light and dark theme samples;
- loading, empty, error, destructive confirmation, and offline states.

Mockups must use realistic queue content but no real personal data.

## 18. Implementation sequence

1. Establish primitive, semantic, and component tokens.
2. Load Poppins locally and configure Lucide.
3. Build shared buttons, fields, cards, badges, tables, modals, toasts, theme, and language controls.
4. Build public/client shell and booking flow.
5. Build QR tracker and feedback.
6. Build staff single-page workspace and check-in tools.
7. Build Admin responsive sidebar and configuration pages.
8. Build report shell and ten visualizations.
9. Build public display and audio enablement.
10. Verify accessibility, responsiveness, privacy, and concurrency states.

## 19. Definition of done

- All agreed client, staff, admin, report, and display flows are represented.
- Light theme is polished and complete; dark theme has equivalent contrast and state clarity.
- New healthcare palette replaces the older navy-and-cyan visual identity.
- Shield identity and SmartQMS generic name are used consistently.
- Poppins and Lucide are local and consistent.
- Staff workspace remains one page with no sidebar.
- Admin sidebar works persistently on desktop and as offcanvas below 992px.
- Client and staff layouts remain usable from 320px through 1920px without broken structure.
- Cards stack responsibly and tables use controlled horizontal scrolling.
- All primary controls are at least 48px high and all targets at least 44px.
- Booking is guided, bilingual, and account-free.
- Staff operational pages do not expose client names.
- Destructive and uncommon actions use overflow menus and confirmation.
- Reports remain separate and use the correct visualization for their data.
- Display announcements work only after audio is enabled and do not repeat.
- Every form, table, chart, dialog, toast, and live update meets the accessibility contract.
- No implementation invents new product rules or changes FIFO, privacy, reporting calculations, or security behavior.

## 20. Master AI build instruction

Act as a senior civic-healthcare product designer and PHP/Bootstrap frontend architect. Redesign SmartQMS using this document as the authoritative UI/UX specification. Work within PHP 8.x, MySQL/MariaDB, Bootstrap 5, vanilla JavaScript, locally bundled Chart.js and Lucide, endroid/qr-code, and self-hosted Poppins. Preserve routes, server-side validation, CSRF, sessions, role permissions, strict FIFO, transactions, reporting formulas, and audit history.

Begin by auditing existing shared shells and design tokens. Implement a three-layer token system and the new healthcare palette. Build reusable Bootstrap-based components before page-specific CSS. Keep the Client experience account-free and guided, the Staff workspace single-page and action-focused, the Admin workspace sidebar-based and responsive, reports accessible and visually appropriate, and the public display readable with opt-in audible announcements.

Do not remove working backend behavior to simplify the UI. Do not expose personal information. Do not use placeholder-only fields, color-only status, tiny icon controls, external runtime assets, nested dialogs, or fixed layouts that break between common widths. Verify every deliverable at the required breakpoints, at 200% zoom, by keyboard, with reduced motion, and in light and dark themes.
