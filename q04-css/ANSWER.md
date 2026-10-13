# Q4 — CSS layout

Files: `index.html`, `styles.css`. No JavaScript, each section appears once in the HTML.

Screenshots (`evidence/`): `desktop.png` (1280px), `tablet-768.png` (exactly at the breakpoint), `mobile.png` (375px), `mobile-320.png` (smallest common phone). Automated checks: `evidence/checks.txt` (run with `python3 tools/check_q4.py`).

## How it works

- **Mobile first** (default): one-column grid. E, F and H get `display: none`. A, D, B, C, G are placed with `order`.
- **Desktop** (`min-width: 48rem`, i.e. 768px): three equal columns with named areas:

  ```
  "a a b"
  "c d e"
  "f g h"
  ```

  A spans the first two columns; `order` is reset so the visual order equals the source order.
- `minmax(0, 1fr)` columns, `min-width: 0` on the boxes and `overflow-wrap: anywhere` on headings let a long heading wrap inside its column instead of widening it.

## Breakpoint choice

48rem (768px). Below that, three columns would be under ~220px each, too narrow for article headlines. At 768px each column is 227px, which still reads acceptably (`tablet-768.png`). Using `rem` means the breakpoint moves with the reader's font size: someone who has increased their default text size gets the one-column layout sooner, which is what they need.

## Long heading check

Section C carries a 20-word headline plus a long unbroken word, in the narrowest column. Results at every width: the heading wraps inside its box, nothing overflows horizontally, and the page has no sideways scroll (`checks.txt`). On desktop, the long heading makes row 2 taller, and D and E stretch to the same height. That is normal grid behaviour and keeps the row tidy; `align-items: start` would make each box only as tall as its content if the design prefers that.

## HTML order, keyboard and screen readers

The HTML is in desktop order: A B C D E F G H. Keyboard focus and screen readers follow the **HTML order, not the visual order** that CSS `order` or grid placement produce.

- **Desktop:** visual order = source order, so Tab and screen readers go A → H exactly as seen (checked: Tab order A B C D E F G H).
- **Mobile:** visually A, D, B, C, G but focus and reading go A, B, C, D, G. D is the one item that is out of step: a keyboard user sees focus jump from A down past D to B, then back up to D later. This is a known accessibility trade-off (WCAG 2.4.3 focus order, 1.3.2 meaningful sequence). I accepted it because it affects one item on one layout. Putting the HTML in mobile order instead would make desktop worse (several jumps).
- If D is promoted on mobile because it is editorially more important, the better fix is to make it come second in the HTML everywhere and adjust the desktop grid, rather than relying on `order`.
- E, F and H use `display: none`, which removes them from the Tab order and from the accessibility tree too, so mobile screen reader users do not hear content that sighted users cannot see (checked: Tab order skips them).
- Each section is a `<section>` labelled by its `<h2>`, so screen reader users can jump between sections by heading or landmark.
