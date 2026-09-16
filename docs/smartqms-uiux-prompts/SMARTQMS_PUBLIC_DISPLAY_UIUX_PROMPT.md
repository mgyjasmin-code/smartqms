# SmartQMS Public Queue Display UI/UX Prompt

Design and implement a full-screen SmartQMS public queue display for a government health center. Use PHP 8.x, Bootstrap 5, vanilla JavaScript, Poppins, Lucide SVG, the shared healthcare palette, and HTML Audio plus Web Speech API fallback for announcements.

## Privacy and content rules

- Show queue numbers and counters only.
- Do not show names, mobile numbers, health services, booking references, or feedback.
- The display is read-only.
- Several counters may call tickets simultaneously.
- A reconnect must not repeat an already announced event.

## Visual direction

The screen is clean, high-contrast, calm, and readable from across a waiting room. Use shield and SmartQMS identity, primary teal #006B73, government navy #174A6E, strong text #102A43, and clear semantic Calling color. Provide a complete dark mode if the facility chooses it, but light remains default.

Use Poppins. Queue numbers use 72–144px responsive type, weight 700, and tabular numerals. Counter labels use 32–64px. Supporting content never falls below 20px at 1366x768.

## Layout

Target 1920x1080 and 1366x768 landscape:

    Header: shield, SmartQMS, current date/time, connection, mute
    Main: dominant newest Now Calling ticket and counter
    Secondary: additional active calls in a responsive card grid
    Side or lower rail: Recently Called
    Footer: bilingual instruction and waiting summary

At smaller landscape widths, reduce the number of recent items before shrinking the active call. Preserve safe margins for TV overscan. Do not create page-level scrolling during normal operation.

## Announcement behavior

Because browsers restrict autoplay, begin with an Enable announcements action controlled by staff. After activation:

1. Receive a new unique call event.
2. Highlight the new call.
3. Play a brief neutral chime.
4. Speak the queue number and counter in the configured language.
5. Store the event ID as announced.
6. Do not announce it again after polling, reconnect, or refresh.

Provide Mute and Unmute with visible labels and icons. If text-to-speech is unavailable, keep the visual call and chime. Notification or audio failure never blocks queue updates.

Example English:

    Queue number A-001, please proceed to Counter 1.

Example Filipino:

    Queue number A-001, mangyaring pumunta sa Counter 1.

Review real pronunciation on the target display computer.

## Live states

- idle with no active calls;
- one active call;
- several simultaneous calls;
- new-call highlight;
- connected;
- reconnecting;
- offline with last successful update;
- audio not enabled;
- muted;
- failed audio;
- stale data;
- fullscreen unavailable.

Use aria-live appropriately, but avoid causing screen readers to announce the entire board repeatedly.

## Bootstrap components

Use container-fluid, responsive grid, cards, badges, alert, buttons, form-switch or labelled mute control, toast only for technical status, spinner, placeholder, and visually-hidden accessibility text. Do not use dropdowns, modals, or complex navigation during normal display.

## Motion

Use a 300ms crossfade, subtle scale or border highlight for the newest call, and no continuous decorative motion. Calling pulse must stop after a short interval. Respect reduced motion.

## Definition of done

The display is readable at 1366x768 and 1920x1080, privacy-safe, bilingual, resilient to reconnects, capable of simultaneous calls, protected against duplicate announcements, operable with a clear audio enable/mute control, and visually consistent with the SmartQMS client, staff, and Admin interfaces.
