# Q6 — JavaScript behaviour

Files: `index.html` (the corrected Q5 file plus one `<script src="script.js" defer>` line), `script.js`, `test.jpeg`.

Open `index.html` directly in a browser; no build step or server needed.

## How it works

- **Copies always come from the originals.** On load the script clones the three original paragraphs once and keeps them in memory. Every click clones that snapshot, so copies never drift even after earlier copies were hidden or changed.
- **Random order:** Fisher–Yates shuffle, so all six orders are equally likely. Copies are inserted after the current last paragraph.
- **Separators:** after every change the script removes its own `<hr class="pair-separator">` elements and rebuilds them: an `<hr>` after paragraph 2, 4, 6… only when another paragraph follows. Rebuilding from scratch is simpler and cannot accumulate mistakes across clicks. Hidden paragraphs are counted because the count uses all `<p>` elements, regardless of visibility.
- **Second button:** created with `document.createElement`, not present in the HTML. It flips one `imagesHidden` flag and sets the `hidden` attribute on every paragraph containing an `<img>`. New copies get the same flag applied *before* they are inserted, so image paragraphs added while hidden never flash on screen.
- **Accessibility:** both buttons are `type="button"`; the toggle has `aria-pressed` and a label that says what it will do next; a visually hidden `role="status"` region announces "Added 3 paragraphs…" / "Image paragraphs hidden." It is a `<div>` outside the article, so it is never counted as a paragraph.

## How I checked it

`python3 tools/check_q6.py` drives the page in real Chromium (Playwright) and writes `evidence/checks.txt`. After **every** step it asserts:

1. The sequence matches `P P HR P P HR … P` for the current paragraph count (hidden ones included), with no HR at the end.
2. Every image paragraph is hidden or visible as expected (checks both the `hidden` attribute and actual rendering), and non-image paragraphs are always visible.
3. The first three paragraphs are still the originals, in order.
4. Every added batch of three contains each original exactly once.

Scenarios covered:

| # | Scenario | Result |
|---|----------|--------|
| 1 | Initial load | `P1 P2 HR P3`; toggle button created by JS (`1-initial.png`) |
| 2 | Two clicks | 9 paragraphs, 4 separators (`2-after-two-clicks.png`) |
| 3 | Hide images *after* adding | all 3 image paragraphs hidden, separators unchanged (`3-images-hidden.png`) |
| 4 | Two more clicks while hidden | new image paragraphs arrive hidden (`4-added-while-hidden.png`) |
| 5 | Show again | everything visible |
| 6 | Hide *before* adding anything, then add, then show | correct at each step |
| 7 | 20 clicks with toggles in between | 63 paragraphs, 31 separators, all 6 possible orders seen, no JS errors |

All checks pass. The Q6 HTML also validates with no errors.

## A side-effect worth flagging

Because hidden paragraphs still count for separator placement (as the brief requires), hiding image paragraphs can leave a separator at the very bottom (see `3-images-hidden.png`) or two separators with nothing visible between them. That is the specified behaviour. If editors find it odd, the fix is a one-line CSS rule or to hide an `<hr>` whose neighbouring paragraph is hidden; I would confirm the preferred behaviour before changing it.
