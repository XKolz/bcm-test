# Q5 — HTML correction

Files: `original.html` (as given), `index.html` (corrected), `test.jpeg` (local image, 600×400, displayed at 300×200).

Validation: Nu Html Checker (`vnu.jar`, the engine behind validator.w3.org/nu), run locally.

- `evidence/validation-original.txt` — original: stops with a **fatal error** after two errors.
- `evidence/validation-original-pass2.txt` — original with only the doctype/`</html>` fixed, to reveal the remaining errors.
- `evidence/validation-corrected.txt` — corrected file: **"No errors found."** (no warnings either).

Re-run: `java -jar node_modules/vnu-jar/build/dist/vnu.jar --format text q05-html/index.html`

## A. Actual HTML5 validity errors (reported by the validator)

| Original | Problem | Fix |
|---|---|---|
| no `<!DOCTYPE html>` | Doctype missing: browser renders in quirks mode. | Added. |
| `</html>` right after `</head>`, then `<body>` | Body sits outside `<html>` ("Stray start tag body"; the validator gives up here). | Moved `</html>` to the end. |
| `<abbr name="lobortis">` | `name` is not allowed on `abbr`. | `title="lobortis"` (the attribute that gives the expansion). |
| `<img ... with=300>` | `with` is not an attribute (typo for `width`). | `width="300"`. |
| `<img ... height=auto>` | `height` must be a whole number of pixels; `auto` is invalid. | `height="200"` (keeps the image's 3:2 ratio). |
| `<img>` with no `alt` | `alt` is required. | Descriptive `alt`. |
| `<code><b>…</code></b>` | Misnested tags. | `<code><b>…</b></code>`. |

## B. Accessibility and style improvements (valid before, better now)

- `lang="en"` on `<html>` — screen readers choose the right pronunciation (the validator also warns about this).
- `<meta charset="utf-8">` and `<meta name="viewport">` — correct character decoding and proper scaling on phones.
- `alt` text that describes the image, so it is useful when read aloud.
- `<button type="button">` — states intent; a bare button defaults to "submit" and would submit any form it ends up in.
- Leading/trailing spaces removed from inside the link (`<a href=…> odio … posuere </a >`) so the underline does not extend over spaces; attribute values quoted consistently.
- Content wrapped in `<main>` and `<article>` landmarks so screen reader users can jump straight to the article.
- Note: the link text "odio sem nec elit. Sed posuere" says nothing about where it goes. In real content it should name the destination, e.g. "Big Cabal Media". I kept the text because the paragraphs are reused in Q6.

## C. HTML-only improvements for search discoverability / interpretation

1. **Descriptive `<title>`** ("Hello, world: a sample article | Big Cabal Media"). The title is the main headline search engines show and the tab/bookmark name. "Example" tells neither a reader nor a crawler what the page is.
2. **`<meta name="description">`** — a one-sentence summary that search engines often use as the result snippet, and social/sharing tools fall back to. Without it, they pick text themselves.
3. **`<link rel="canonical">`** — tells search engines the preferred URL so copies with tracking parameters (`?utm_source=…`) or AMP/print versions do not split ranking or appear as duplicates. *Assumption:* `https://www.example.com/hello-world/` stands in for the real article URL.
4. **`<main>` + `<article>`** — mark the primary, self-contained content so crawlers, reader modes and assistive tools can tell the article from navigation and widgets.
5. **Image `alt` + `width`/`height`** — `alt` lets image search understand the picture; explicit dimensions let the browser reserve space, avoiding layout shift (a Core Web Vitals signal).
6. **`lang="en"`** — tells search engines which language audience the page serves.
