# Q1 — PHP / WordPress listing

Files: `search-listing.php` (corrected template), `inc/ads.php` (ad-marker helper), `original.php` (brief's code, unchanged), `tests/` (WordPress stubs + checks), `evidence/` (rendered output of each check).

Run the checks: `php tests/test.php` (PHP 8.1+, no WordPress needed). Result: **24 passed, 0 failed** (`evidence/test-results.txt`).

## Errors in the original and their effects

| # | Code | Problem | Effect |
|---|------|---------|--------|
| 1 | `$q = WP_Query([...])` | Missing `new`. | **Fatal error**: "Call to undefined function WP_Query()". Nothing renders (`evidence/original-output.txt`). |
| 2 | `preg_replace_callback('#[ad]#', function() {...})` | Subject (`$content`) never passed. | **Fatal** `ArgumentCountError` (2 arguments given, 3 needed) on the first post. |
| 3 | `'#[ad]#'` | `[ad]` is a regex *character class*: it matches any single `a` or `d`. | Every letter a/d in every post is replaced, the real `[ad]` marker is not. "Body" became "Boy" in my run (`evidence/original-after-fatal-fixes.txt`). Needs `\[ad\]`. |
| 4 | `echo '<div ...>'` in the callback | The callback must `return` the replacement. | Ad divs are echoed immediately, *above* the post heading, and the markers are replaced with an empty string. |
| 5 | `function() { ... $m[1] }` | The match array is never received as a parameter, and the pattern has no capture group, so `$m[1]` cannot exist. | "Undefined variable $m" warnings and `class="ad ad-"` with no number. |
| 6 | `'page' => $page` | `WP_Query` paginates with **`paged`**. `page` is only for static front pages / multi-page posts. | Always shows page 1 whatever `?page=` says. |
| 7 | `$_GET['page'] ?? 1` echoed in `<?= $page ?>` | Raw user input printed into HTML, never cast to an integer. | **Reflected XSS** (`?page=<script>...`), and non-numeric/negative values reach the query. |
| 8 | `'s' => $_GET['s']`, `esc_html($_GET['s'])` | No check that `s` exists; not unslashed or sanitised. | "Undefined array key" warning and `esc_html(null)` when `s` is missing; "Search for" text is shown with nothing after it. |
| 9 | `<?= $pages ?>` | `$pages` is never defined. | "Undefined variable $pages" warning, total page count blank. Should be `$q->max_num_pages`. |
| 10 | `<h1>...` | `</h1>` missing. | Invalid HTML; the browser keeps everything after it inside the heading, including the posts. |
| 11 | `esc_html(the_title())` | `the_title()` *echoes* (unescaped) and returns null. | Title printed without escaping, then `esc_html(null)` prints nothing. Use `esc_html( get_the_title() )`. |
| 12 | `get_the_content()` then `echo` | Raw content skips the `the_content` filters. | No paragraphs (wpautop), shortcodes, embeds or plugin filters, unlike the rest of the site. |
| 13 | `<p><?php echo $content ?></p>` | Post content contains block elements (paragraphs, the ad `<div>`). | Invalid nesting; browsers close the `<p>` early and layout breaks. Wrap in a `<div>`. |
| 14 | Loop end | No `wp_reset_postdata()`. | Global `$post` is left pointing at the last listed post, which breaks sidebars, footers, analytics tags, etc. after the loop. |
| 15 | No `else` branch / pagination | — | Empty page with no message when nothing matches; readers cannot reach page 2. |

## Corrected version

See `search-listing.php` and `inc/ads.php`. Key points:

- `$page = max(1, (int) $_GET['page'])` with a default of 1; `$search` is unslashed and passed through `sanitize_text_field`, defaulting to `''` (no filter).
- `new WP_Query` with `posts_per_page => 10`, `paged => $page`, `s => $search`, published posts only, sticky posts ignored so every page really has 10.
- `$pages = max(1, $q->max_num_pages)`; every printed value is escaped.
- A "no results" message, pagination links that keep `s`, and `wp_reset_postdata()`.
- Ads: `preg_replace_callback('/\[ad\]/', ...)` returns the div and numbers each slot.

## Checks (all in `evidence/`)

| Check | Request | Result |
|---|---|---|
| Missing parameters | (none) | "Page 1 of 3", 10 posts, no warnings (`missing-params.html`) |
| Page 2 | `?page=2` | "Page 2 of 3", posts 11–20, page 2 marked current (`page-2.html`) |
| Search | `?s=lagos` | Only the 5 matching posts (`page-2-search.html`) |
| No results | `?s=zzz-nothing` | "No posts found for “zzz-nothing”", no warnings (`no-results.html`) |
| Multiple `[ad]` | 2 posts, 5 markers | 5 divs numbered `ad-1`…`ad-5`, letters a/d untouched, `[AD]` and `[ad 3]` left alone (`multiple-ads.html`) |
| Extra: hostile input | `?page=-3<script>&s=<script>...` | No script in output, page clamped to 1 (`hostile-input.html`) |
| Extra: page past the end | `?page=99` | Friendly message, no error (`page-too-high.html`) |

The stubs in `tests/wp-stubs.php` are not WordPress: they reproduce paging and search just well enough to exercise the template. `the_content` filters (wpautop) are not simulated. On a real install I would repeat the same five requests in the browser and with Query Monitor open to confirm the SQL `LIMIT 10, 10` for page 2.

## Assumptions about the ad placeholders

- The marker is exactly `[ad]`, case-sensitive. `[AD]`, `[ad 3]` and similar are left as text.
- Each marker becomes `<div class="ad ad-N" data-ad-slot="N"></div>`, where N counts markers **across the whole page** (not per post), so every slot on the page is unique for the JavaScript loader. If the ad team instead needs a slot name from the marker (e.g. `[ad top]`), the pattern would become `/\[ad(?:\s+([a-z0-9-]+))?\]/` and use the capture group — which is what `$m[1]` in the original suggests someone intended.
- Markers are replaced *before* the `the_content` filters run, so wpautop sees a block-level `<div>` and does not wrap it in a `<p>`. Editors are expected to put `[ad]` on its own line.
- The empty div is intentional; the JavaScript handler fills it.

## Other notes

- WordPress reserves `page` as a public query var. On some templates `redirect_canonical()` may strip or redirect `?page=N`. The brief asks for `page`, so I kept it; on a real site I would confirm this on the target template, or use the standard `/page/2/` (`paged`) URLs.
- If this is the site's search results page, the main query already handles `s` and `paged`, and the template should use the main loop instead of a second `WP_Query`, avoiding a duplicate database query.
