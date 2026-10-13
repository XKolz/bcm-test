"""Q4 checks: screenshots, grid positions, hidden sections, tab order, overflow."""
from pathlib import Path
from playwright.sync_api import sync_playwright

root = Path(__file__).resolve().parent.parent / "q04-css"
url = (root / "index.html").as_uri()
ev = root / "evidence"
log = []

def say(s):
    print(s); log.append(s)

with sync_playwright() as p:
    b = p.chromium.launch()
    for name, w, h in [("desktop", 1280, 900), ("tablet-768", 768, 1000), ("mobile", 375, 1100), ("mobile-320", 320, 1400)]:
        pg = b.new_page(viewport={"width": w, "height": h})
        pg.goto(url)
        pg.screenshot(path=str(ev / f"{name}.png"), full_page=True)
        info = pg.evaluate("""() => [...document.querySelectorAll('.box')].map(el => {
            const r = el.getBoundingClientRect();
            return {id: el.querySelector('h2').id.slice(2).toUpperCase(), visible: getComputedStyle(el).display !== 'none',
                    x: Math.round(r.x), y: Math.round(r.y), w: Math.round(r.width)};
        })""")
        vis = [i for i in info if i["visible"]]
        order = [i["id"] for i in sorted(vis, key=lambda i: (i["y"], i["x"]))]
        overflow = pg.evaluate("document.documentElement.scrollWidth > document.documentElement.clientWidth")
        tabs = []
        for _ in range(len(vis)):
            pg.keyboard.press("Tab")
            tabs.append(pg.evaluate("document.activeElement.textContent.replace('Read ','')"))
        say(f"== {name} ({w}px)")
        say(f"  visual order (top-left to bottom-right): {' '.join(order)}")
        say(f"  hidden: {' '.join(i['id'] for i in info if not i['visible']) or 'none'}")
        say(f"  widths: " + ", ".join(f"{i['id']}={i['w']}" for i in vis))
        say(f"  keyboard Tab order: {' '.join(tabs)}")
        say(f"  horizontal overflow: {overflow}")
        hc = pg.evaluate("(() => { const h = document.getElementById('h-c'); return h.scrollWidth <= h.clientWidth })()")
        say(f"  long heading C fits its box (no overflow): {hc}")
        pg.close()
    b.close()
(ev / "checks.txt").write_text("\n".join(log) + "\n")
