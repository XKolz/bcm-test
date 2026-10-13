# BCM Developer Test — Samuel

Submission for the Big Cabal Media Software Developer screening test.

## Where each answer is

| Q | Topic | Where |
|---|-------|-------|
| 1 | PHP / WordPress listing | `q01-php/ANSWER.md`, corrected code in `q01-php/search-listing.php` + `q01-php/inc/ads.php` |
| 2 | Editor cannot upload images | `WRITTEN_ANSWERS.md` |
| 3 | Slow article page on mobile | `WRITTEN_ANSWERS.md` |
| 4 | CSS desktop/mobile layout | `q04-css/` (`index.html`, `styles.css`, `ANSWER.md`, screenshots in `evidence/`) |
| 5 | HTML correction + validation | `q05-html/` (`index.html`, `test.jpeg`, `ANSWER.md`, validator output in `evidence/`) |
| 6 | JavaScript behaviour | `q06-js/` (`index.html`, `script.js`, `ANSWER.md`, screenshots + check log in `evidence/`) |
| 7 | Stale article after a release | `WRITTEN_ANSWERS.md` |
| 8 | Editors' copy-paste workflow | `WRITTEN_ANSWERS.md` |
| 9 | Newsletter signup reliability | `q09-api/` (`subscribe.js`, `subscribe.test.js`, `ANSWER.md`) |
| 10 | Endpoint security review | `WRITTEN_ANSWERS.md` |

## Setup

Requirements: PHP 8.1+, Node 18+, Java 11+ (HTML validator), Python 3.10+ with Playwright (browser checks for Q4/Q6).

```bash
npm install                                   # installs vnu-jar (HTML validator)
pip install playwright && python -m playwright install chromium   # only for Q4/Q6 checks
```

## Running the checks

```bash
php q01-php/tests/test.php                                   # Q1: 24 checks (stubs WordPress, no install needed)
python3 tools/check_q4.py                                    # Q4: screenshots, layout order, Tab order, overflow
java -jar node_modules/vnu-jar/build/dist/vnu.jar --format text q05-html/index.html q06-js/index.html   # Q5/Q6 validation
python3 tools/check_q6.py                                    # Q6: separators, shuffling, toggling, 20-click stress test
node --test --test-reporter=spec q09-api/subscribe.test.js   # Q9: 8 tests
```

Or simply open `q04-css/index.html` and `q06-js/index.html` in a browser. Results from my runs are saved in each `evidence/` folder.

| Check | Result |
|---|---|
| Q1 PHP checks | 24 passed, 0 failed |
| Q4 layout | Desktop A B / C D E / F G H; mobile A D B C G with E F H hidden; long heading wraps, no overflow at 320–1280px |
| Q5 validation | Original: fatal error, then 6 more errors; corrected: no errors, no warnings |
| Q6 browser checks | All passed (7 scenarios, every step asserted) |
| Q9 tests | 8 passed |

## How AI was used, and how I verified it

I used an AI assistant (Claude) throughout, as the brief allows: to draft the corrected code, the test harnesses and mocks, and first drafts of the written answers. I verified its output rather than trusting it:

- **Q1:** ran the original code against WordPress stubs to confirm each claimed error actually happens (`q01-php/evidence/original-output.txt`, `original-after-fatal-fixes.txt`), then ran 24 checks against the corrected template. The checks caught a real bug in the first draft (`absint()` turned `?page=-3` into page 3 instead of page 1), which I fixed.
- **Q5:** validated with the official Nu HTML Checker instead of relying on the AI's list of errors. It showed the original stops at a fatal error, so I validated a second pass to surface the rest.
- **Q4/Q6:** checked in a real browser (Chromium via Playwright): measured element positions, real keyboard Tab order and rendered visibility, and looked at every screenshot.
- **Q9:** the tests include the exact failure in the brief (provider accepts, then the response times out). Reviewing the first draft, I found the "needs reconcile" state was not carried into the next background run, which could still create a duplicate with a provider that ignores idempotency keys, and fixed it.
- **Written answers:** reviewed and edited against my own experience deploying and running production web apps (Django/Next.js on DigitalOcean with Nginx, Docker and CI/CD); I am happy to walk through any of them.

## Time

About [X] hours, excluding setup.

## Assumptions and what I would do next

- **Q1:** the WordPress stubs simulate paging and search but not `the_content` filters (wpautop). Next: run the same requests on a local WordPress (wp-env or Docker) with Query Monitor.
- **Q1:** `[ad]` slots are numbered across the whole page; see `q01-php/ANSWER.md` for the alternatives.
- **Q5:** the canonical URL is a placeholder (`example.com`).
- **Q6:** with image paragraphs hidden, separators can sit next to each other or at the end because hidden paragraphs still count, as specified. I would confirm the preferred look with the team.
- **Q9:** concurrent workers and alerting on stuck signups are described but not implemented.
