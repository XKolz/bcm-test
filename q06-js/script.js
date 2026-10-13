/*
 * Q6 behaviour for the corrected Q5 HTML.
 *
 * - "Hit me" appends copies of the three ORIGINAL paragraphs, shuffled, after
 *   the last paragraph.
 * - An <hr> goes after every second paragraph when another paragraph follows
 *   (P1 P2 HR P3 P4 HR P5). Hidden paragraphs count. Applied on load and after
 *   every click.
 * - A second button, created here, hides/shows paragraphs that contain an
 *   image. While hidden, newly added image paragraphs are hidden too.
 */
(() => {
  'use strict';

  const article = document.querySelector('main article');
  const addButton = document.querySelector('main > button');
  if (!article || !addButton) return;

  // Snapshot the originals once, before anything changes, so copies are always
  // made from the original three paragraphs (never from earlier copies).
  const originals = Array.from(article.querySelectorAll('p'), (p) => p.cloneNode(true));

  let imagesHidden = false;

  const paragraphs = () => Array.from(article.querySelectorAll('p'));
  const hasImage = (p) => p.querySelector('img') !== null;

  // Fisher–Yates: every order of the three copies is equally likely.
  function shuffle(items) {
    const a = items.slice();
    for (let i = a.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
  }

  // Rebuild separators from scratch each time: simpler and always correct,
  // whatever happened before. Hidden paragraphs are included in the count.
  function placeSeparators() {
    article.querySelectorAll('hr.pair-separator').forEach((hr) => hr.remove());
    const ps = paragraphs();
    ps.forEach((p, i) => {
      const isSecondOfPair = (i + 1) % 2 === 0;
      const anotherFollows = i < ps.length - 1;
      if (isSecondOfPair && anotherFollows) {
        const hr = document.createElement('hr');
        hr.className = 'pair-separator';
        p.after(hr);
      }
    });
  }

  function applyImageVisibility(ps) {
    ps.filter(hasImage).forEach((p) => { p.hidden = imagesHidden; });
  }

  // Polite live region so screen reader users hear what each button did.
  // A <div>, not a <p>, so it is never counted as an article paragraph.
  const status = document.createElement('div');
  status.setAttribute('role', 'status');
  status.className = 'visually-hidden';
  status.style.cssText = 'position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap;';

  function addCopies() {
    const copies = shuffle(originals).map((p) => p.cloneNode(true));
    applyImageVisibility(copies);
    paragraphs().at(-1).after(...copies);
    placeSeparators();
    status.textContent = `Added ${copies.length} paragraphs. ${paragraphs().length} in total.`;
  }

  // Second button, created only in JavaScript.
  const toggleButton = document.createElement('button');
  toggleButton.type = 'button';
  toggleButton.id = 'toggle-image-paragraphs';

  function renderToggle() {
    toggleButton.textContent = imagesHidden ? 'Show image paragraphs' : 'Hide image paragraphs';
    toggleButton.setAttribute('aria-pressed', String(imagesHidden));
  }

  function toggleImages() {
    imagesHidden = !imagesHidden;
    applyImageVisibility(paragraphs());
    renderToggle();
    status.textContent = imagesHidden ? 'Image paragraphs hidden.' : 'Image paragraphs shown.';
  }

  renderToggle();
  addButton.after(' ', toggleButton);
  toggleButton.after(status);
  addButton.addEventListener('click', addCopies);
  toggleButton.addEventListener('click', toggleImages);

  placeSeparators(); // Initial load: P1 P2 HR P3.
})();
