"""Q6 checks in real Chromium: separators, shuffling, toggling, repeated clicks."""
from pathlib import Path
from playwright.sync_api import sync_playwright

root = Path(__file__).resolve().parent.parent / "q06-js"
ev = root / "evidence"
log, fails = [], []

def say(s=""):
    print(s); log.append(s)

def check(label, cond):
    say(f"  {'PASS' if cond else 'FAIL'}  {label}")
    if not cond: fails.append(label)

# Describe the article: P1..Pn (first two words identify which original), HR, (hidden).
STATE = """() => {
  const kids = [...document.querySelector('article').children].filter(e => e.tagName !== 'H1');
  return kids.map(e => e.tagName === 'HR' ? 'HR' : ({
      key: e.textContent.trim().split(/\\s+/)[0], img: !!e.querySelector('img'), hidden: e.hidden,
      shown: e.offsetParent !== null }));
}"""

def expected_pattern(n):
    out = []
    for i in range(n):
        out.append("P")
        if (i + 1) % 2 == 0 and i < n - 1:
            out.append("HR")
    return out

def verify(pg, label, hidden_expected):
    st = pg.evaluate(STATE)
    ps = [x for x in st if x != "HR"]
    pattern = ["HR" if x == "HR" else "P" for x in st]
    compact = " ".join("HR" if x == "HR" else (x["key"][:5] + ("*" if x["img"] else "") + ("(hidden)" if x["hidden"] else "")) for x in st)
    say(f"[{label}] {len(ps)} paragraphs: {compact}")
    check("separator after every pair, none at the end (hidden ones counted)", pattern == expected_pattern(len(ps)))
    img_ps = [x for x in ps if x["img"]]
    check(f"all {len(img_ps)} image paragraphs {'hidden' if hidden_expected else 'visible'}",
          all(x["hidden"] == hidden_expected and x["shown"] == (not hidden_expected) for x in img_ps))
    check("paragraphs without images always visible", all(x["shown"] for x in ps if not x["img"]))
    check("first three are still the originals in original order", [x["key"] for x in ps[:3]] == ["Cras", "Integer", "Duis"])
    batches = [ps[i:i + 3] for i in range(3, len(ps), 3)]
    check("every added batch has each original exactly once", all(sorted(x["key"] for x in b) == ["Cras", "Duis", "Integer"] for b in batches))
    return ps

with sync_playwright() as p:
    b = p.chromium.launch()
    pg = b.new_page(viewport={"width": 900, "height": 1200})
    errors = []
    pg.on("pageerror", lambda e: errors.append(str(e)))
    pg.goto((root / "index.html").as_uri())
    add = pg.get_by_role("button", name="Hit me")
    tog = pg.locator("#toggle-image-paragraphs")

    say("== 1. Initial load")
    verify(pg, "load", False)
    check("second button exists, created by JS, label 'Hide image paragraphs'", tog.inner_text() == "Hide image paragraphs" and tog.get_attribute("aria-pressed") == "false")
    check("second button is not in the HTML source", "toggle-image-paragraphs" not in (root / "index.html").read_text())
    pg.screenshot(path=str(ev / "1-initial.png"), full_page=True)

    say("\n== 2. Repeated clicks, images visible")
    add.click(); verify(pg, "after 1 click", False)
    add.click(); verify(pg, "after 2 clicks", False)
    pg.screenshot(path=str(ev / "2-after-two-clicks.png"), full_page=True)

    say("\n== 3. Toggle after adding paragraphs")
    tog.click(); verify(pg, "hidden", True)
    check("button now says 'Show image paragraphs', aria-pressed=true", tog.inner_text() == "Show image paragraphs" and tog.get_attribute("aria-pressed") == "true")
    pg.screenshot(path=str(ev / "3-images-hidden.png"), full_page=True)

    say("\n== 4. Add while hidden: new image paragraphs must stay hidden")
    add.click(); verify(pg, "hidden + 1 click", True)
    add.click(); verify(pg, "hidden + 2 clicks", True)
    pg.screenshot(path=str(ev / "4-added-while-hidden.png"), full_page=True)

    say("\n== 5. Show again")
    tog.click(); verify(pg, "shown again", False)

    say("\n== 6. Toggle BEFORE adding anything (fresh page)")
    pg.reload()
    tog.click(); verify(pg, "fresh, hidden", True)
    add.click(); verify(pg, "fresh, hidden + 1 click", True)
    tog.click(); verify(pg, "fresh, shown", False)

    say("\n== 7. Stress: 20 clicks with toggles in between; randomness")
    pg.reload()
    orders = set()
    for i in range(20):
        add.click()
        if i % 6 == 5:
            tog.click()
        st = [x for x in pg.evaluate(STATE) if x != "HR"]
        orders.add(tuple(x["key"] for x in st[-3:]))
    hidden_now = tog.get_attribute("aria-pressed") == "true"
    ps = verify(pg, "after 20 clicks", hidden_now)
    check("63 paragraphs (3 + 20×3)", len(ps) == 63)
    hr_count = pg.evaluate("document.querySelectorAll('article hr').length")
    check(f"31 separators for 63 paragraphs (got {hr_count})", hr_count == 31)
    say(f"  distinct batch orders seen in 20 clicks: {len(orders)} of 6 possible")
    check("copies are actually shuffled (more than one order seen)", len(orders) > 1)
    check("no JavaScript errors", not errors)
    b.close()

say(f"\n{'ALL CHECKS PASSED' if not fails else str(len(fails)) + ' FAILED'}")
(ev / "checks.txt").write_text("\n".join(log) + "\n")
