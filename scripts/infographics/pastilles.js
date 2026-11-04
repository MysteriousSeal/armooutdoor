// Drawings shared by the plain 13 mm round stickers (pastilles), one spec
// per colour (products/lab-*-013-1000.js). Loaded after kit.js by
// dimensions.html and effect.html; exposes Infographic.pastilles.
// Specs only call these from their draw functions, at render time: render.sh
// reads the specs in node, where neither kit.js nor this file exists.
(() => {
  const T = () => Infographic.targets;
  const INK = '#1b1d19';

  // One plain paper sticker of radius r: flat colour over a thin soft
  // shadow, so it reads as paper stuck down, not a ball; a white one gets a
  // thin grey edge so it reads on white.
  function dot(el, cx, cy, r, fill, parent) {
    const g = el('g', {}, parent);
    el('circle', { cx: cx + 1, cy: cy + 1.5, r, fill: '#000', opacity: 0.22, filter: 'url(#dotBlur)' }, g);
    el('circle', { cx, cy, r, fill, stroke: fill === '#fefefe' ? '#cfcfcf' : 'none', 'stroke-width': 1.5 }, g);
  }

  // A bare hole: dark centre, a pale torn rim.
  function hole(el, cx, cy, r, parent) {
    el('circle', { cx, cy, r, fill: '#141414', stroke: 'rgba(255,255,255,.75)', 'stroke-width': Math.max(1.2, r / 4) }, parent);
  }

  let defsDone = false;
  function defs(el) {
    if (defsDone) return;
    defsDone = true;
    const d = el('defs', {});
    const f = el('filter', { id: 'dotBlur', x: '-30%', y: '-30%', width: '160%', height: '160%' }, d);
    el('feGaussianBlur', { stdDeviation: 1.5 }, f);
  }

  // A classic white paper target, drawn: black rings and a black centre, at
  // diameter D on (cx, cy).
  function paperTarget(el, cx, cy, D) {
    const R = D / 2;
    el('circle', { cx: cx + 3, cy: cy + 5, r: R, fill: '#000', opacity: 0.12 });
    el('circle', { cx, cy, r: R, fill: '#fbfaf6', stroke: '#d8d4cb', 'stroke-width': 2 });
    for (const k of [0.95, 0.75, 0.55]) el('circle', { cx, cy, r: R * k, fill: 'none', stroke: INK, 'stroke-width': 2 });
    el('circle', { cx, cy, r: R * 0.35, fill: INK });
  }

  // Our green and black 5-zone target (product 154's photo, not reactive)
  // cut round at diameter D on (cx, cy).
  let clipN = 0;
  function greenTarget(el, cx, cy, D) {
    const s = D / (2 * 334), id = 'gb' + (++clipN);
    const cp = el('clipPath', { id }, el('defs', {}));
    el('circle', { cx, cy, r: D / 2 }, cp);
    el('image', { href: 'photos/tgt-fluo-gnbk-200-5z.jpg', x: cx - 493 * s, y: cy - 502 * s, width: 1000 * s, height: 1000 * s, 'clip-path': `url(#${id})` });
  }

  // Before / after a repair inside a 560 px panel whose left edge is x0:
  // two targets of 250 px, the hits as holes (or splatter on the reactive
  // one), then the stickers over the hits listed in cover.
  // kind: 'reactive' (our 76 mm target), 'paper' or 'green'.
  // r: the sticker's radius on the 250 px target (21 for 13 mm on 76 mm).
  function repair(kit, x0, { kind, fill, hits, cover, seed, r = 21 }) {
    const { el, text } = kit, D = 250, cy = 730, xs = [x0 + 125, x0 + 435];
    xs.forEach((cx, i) => {
      if (kind === 'reactive') T().usedTarget(el, cx, cy, D, Infographic.rng(seed), hits);
      else {
        if (kind === 'paper') paperTarget(el, cx, cy, D);
        else greenTarget(el, cx, cy, D);
        const g = el('g', {});
        hits.forEach(([dx, dy]) => hole(el, cx + dx, cy + dy, 5.5, g));
      }
      if (i === 1) cover.forEach(k => dot(el, cx + hits[k][0], cy + hits[k][1], r, fill));
      text(cx, 900, i ? 'APRÈS' : 'AVANT', { 'text-anchor': 'middle', 'font-size': 24, 'font-weight': 700, 'letter-spacing': '.2em', fill: 'var(--mute)' });
    });
    el('path', { d: `M${x0 + 264} ${cy} H${x0 + 296}`, stroke: 'var(--coyote)', 'stroke-width': 5 });
    el('path', { d: `M${x0 + 284} ${cy - 12} L${x0 + 298} ${cy} L${x0 + 284} ${cy + 12}`, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 5, 'stroke-linejoin': 'round' });
  }

  // Hits on our 76 mm reactive target at 250 px: two in the red bull, five
  // in the black zones, clear of the yellow lines (r 51 and 86) and cross.
  const REACTIVE_HITS = [[-7, 5], [8, -6], [44, -58], [65, 28], [-28, 70], [-77, 19], [84, -70]];
  const BLACK_ZONES = [2, 3, 4, 5, 6];

  // A Mode d'emploi sheet: two panels (left edge 130 and 810, 560 px wide),
  // each { head, title, line, draw(kit, x0) }.
  function usage(kit, panels) {
    const { el, text, section } = kit;
    defs(el);
    panels.forEach(({ head, title, line, draw }, i) => {
      const x = i ? 810 : 130;
      draw(kit, x);
      section(x, 425, head);
      text(x, 1065, title, { 'font-size': 44, 'font-weight': 700, fill: 'var(--ink)' });
      text(x, 1105, line, { style: 'font-family: Barlow', 'font-size': 27, fill: 'var(--mute)' });
    });
  }

  // The dimensions sheet: one sticker and the coin enlarged at the same
  // scale, the pack facts, then holes the page says it covers, at the same
  // scale, dashed over stickers. opts (all optional, 13 mm by default):
  // mm, count ('1 000'), K (px per mm), holes [[label, small, hole mm]].
  // The scale shrinks for bigger stickers so the row of holes still fits.
  const HOLES_13 = [['4,5 mm', 'plomb ou bille', 4.5], ['5,5 mm', 'plomb ou bille', 5.5], ['.22 LR', 'impact', 5.7]];
  function dimensions({ el, text, dim, ext, section }, fill, { mm = 13, count = '1 000', K, holes = HOLES_13 } = {}) {
    defs(el);
    // As large as the row allows: sticker, gap and coin within 610 px.
    K = K || Math.min(14, 610 / (mm + 23.25));
    const r = mm / 2 * K, cr = 23.25 / 2 * K, sx = Math.max(270, 150 + r), sy = Math.min(760, 895 - r);
    section(150, 400, 'UNE PASTILLE ET LA PIÈCE');
    dot(el, sx, sy, r, fill);
    ext(sx - r, sy - 8, sx - r, sy - r - 70); ext(sx + r, sy - 8, sx + r, sy - r - 70);
    dim(sx - r, sy - r - 55, sx + r, sy - r - 55, `${String(mm).replace('.', ',')} mm`, 44);
    const coinX = Math.max(610, sx + r + 60 + cr);
    T().coin({ el, text }, coinX, sy + r - cr, cr);
    const fx = Math.max(860, coinX + cr + 60);
    [[count, 'pastilles'], ['Rouleau', 'à dérouler au fil des séances']].forEach(([big, small], i) => {
      const fy = 640 + i * 110;
      text(fx, fy, big, { 'font-size': 50, 'font-weight': 700, fill: 'var(--ink)' });
      text(fx, fy + 32, small, { 'font-size': 26, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.04em' });
    });
    section(150, 960, 'IMPACTS COUVERTS');
    const line = fill === '#000000' ? '#e8e4dc' : INK;
    // Up to four holes across: smaller dots when there are more of them.
    // Three holes keep the 13 mm layout; more get smaller dots on a grid.
    const n = holes.length, hr = Math.min(r, n > 3 ? 52 : 91), step = n > 3 ? 1200 / Math.ceil(n / 2) : 350;
    holes.forEach(([big, small, hmm], i) => {
      const k = hr / r * K, col = n > 3 ? i % Math.ceil(n / 2) : i, row = n > 3 ? Math.floor(i / Math.ceil(n / 2)) : 0;
      const cx = (n > 3 ? 150 + hr : 245) + col * step, cy = n > 3 ? 1045 + row * 135 : 1120;
      dot(el, cx, cy, hr, fill);
      el('circle', { cx, cy, r: hmm / 2 * k, fill: 'none', stroke: line, 'stroke-width': 3, 'stroke-dasharray': '6 5' });
      text(cx + hr + 24, cy - 4, big, { 'font-size': n > 3 ? 34 : 38, 'font-weight': 700, fill: 'var(--ink)' });
      text(cx + hr + 24, cy + 28, small, { 'font-size': n > 3 ? 22 : 24, 'font-weight': 500, fill: 'var(--mute)' });
    });
  }

  // A round close-up of a surface (radius R, coyote rim) at (cx, cy): bg
  // fill, optional rings of a light paper target, one sticker of radius r
  // and, beside it, a bare hole of radius hole or a splatter (an unrepaired
  // reactive hit, to show the black sticker blends in).
  let zoomN = 0;
  function closeUp(el, cx, cy, R, { bg, ring = false, fill, r, hole: holeR = 0, splat = false }) {
    defs(el);
    const id = 'zoom' + (++zoomN), cp = el('clipPath', { id }, el('defs', {}));
    el('circle', { cx, cy, r: R }, cp);
    const g = el('g', { 'clip-path': `url(#${id})` });
    el('rect', { x: cx - R, y: cy - R, width: 2 * R, height: 2 * R, fill: bg }, g);
    if (ring) for (const k of [1.25, 1.75]) el('circle', { cx: cx - 30, cy: cy + 10, r: R * k * 0.55, fill: 'none', stroke: INK, 'stroke-width': 3 }, g);
    dot(el, cx - 40, cy + 10, r, fill, g);
    if (holeR) {
      // The hole the sticker now hides, dashed, and its torn edge.
      el('circle', { cx: cx - 40, cy: cy + 10, r: holeR * 1.6, fill: 'none', stroke: '#9a958a', 'stroke-width': 3, 'stroke-dasharray': '7 6' }, g);
    }
    if (splat) T().splat(el, Infographic.rng(345), cx + 150, cy - 120, 48, g);
    el('circle', { cx, cy, r: R, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 5 });
  }

  // Our 20 cm board (tgt-splat-200mm-20-fluo's photo, red centre r40 in
  // its photo = 9.15 mm of its 200 mm) before and after: hits in the red
  // centre, then one 25 mm sticker over it. Drawn at 250 px for 200 mm.
  function bigRepair(kit, x0, fill) {
    const { el, text } = kit, cy = 730, xs = [x0 + 125, x0 + 435], D = 250, s = D / (2 * 437);
    const hits = [[-6, 4], [7, -5], [3, 9]];
    xs.forEach((cx, i) => {
      const id = 'big' + i, cp = el('clipPath', { id }, el('defs', {}));
      el('rect', { x: cx - D / 2, y: cy - D / 2, width: D, height: D }, cp);
      el('image', { href: 'photos/tgt-splat-200mm-20-fluo.jpg', x: cx - 499 * s, y: cy - 500 * s, width: 1000 * s, height: 1000 * s, 'clip-path': `url(#${id})` });
      const g = el('g', { 'clip-path': `url(#${id})` }), rnd = Infographic.rng(109);
      hits.forEach(([dx, dy]) => T().splat(el, rnd, cx + dx, cy + dy, 7, g));
      if (i) dot(el, cx, cy, 25 / 2 * D / 200, fill);
      text(cx, 900, i ? 'APRÈS' : 'AVANT', { 'text-anchor': 'middle', 'font-size': 24, 'font-weight': 700, 'letter-spacing': '.2em', fill: 'var(--mute)' });
    });
    arrowBetween(el, x0, cy);
  }

  // A tight group on our 76 mm reactive target (at 250 px), covered by one
  // 25 mm sticker (r 41) after.
  function groupRepair(kit, x0, fill) {
    const { el, text } = kit, cy = 730, xs = [x0 + 125, x0 + 435];
    const hits = [[-60, -60], [-48, -72], [-70, -44], [-42, -50], [-58, -40]];
    xs.forEach((cx, i) => {
      T().usedTarget(el, cx, cy, 250, Infographic.rng(345), hits);
      if (i) dot(el, cx - 55, cy - 55, 41, fill);
      text(cx, 900, i ? 'APRÈS' : 'AVANT', { 'text-anchor': 'middle', 'font-size': 24, 'font-weight': 700, 'letter-spacing': '.2em', fill: 'var(--mute)' });
    });
    arrowBetween(el, x0, cy);
  }

  function arrowBetween(el, x0, cy) {
    el('path', { d: `M${x0 + 264} ${cy} H${x0 + 296}`, stroke: 'var(--coyote)', 'stroke-width': 5 });
    el('path', { d: `M${x0 + 284} ${cy - 12} L${x0 + 298} ${cy} L${x0 + 284} ${cy + 12}`, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 5, 'stroke-linejoin': 'round' });
  }

  // Small drawn props for the labelling uses. Each centred on (cx, cy).
  function ammoBox(el, cx, cy, w = 170, h = 120, body = '#4a5236') {
    el('rect', { x: cx - w / 2 + 4, y: cy - h / 2 + 6, width: w, height: h, rx: 8, fill: '#000', opacity: 0.15 });
    el('rect', { x: cx - w / 2, y: cy - h / 2, width: w, height: h, rx: 8, fill: body });
    el('rect', { x: cx - w / 2, y: cy - h / 2, width: w, height: h * 0.28, rx: 8, fill: '#000', opacity: 0.18 });
    el('rect', { x: cx - 16, y: cy - h / 2 + h * 0.2, width: 32, height: 12, rx: 3, fill: '#2a2e20' });
  }
  function magazine(el, cx, cy) {
    el('path', { d: `M${cx - 30} ${cy - 100} L${cx + 26} ${cy - 100} L${cx + 40} ${cy + 80} L${cx - 22} ${cy + 80} Z`, fill: '#2b2d30' });
    el('rect', { x: cx - 30, y: cy + 78, width: 76, height: 22, rx: 5, fill: '#1c1d1f' });
    el('rect', { x: cx - 18, y: cy - 112, width: 34, height: 14, rx: 3, fill: '#8c6a3c' });
  }
  function rifleCase(el, cx, cy) {
    el('rect', { x: cx - 210 + 4, y: cy - 46 + 6, width: 420, height: 92, rx: 22, fill: '#000', opacity: 0.15 });
    el('rect', { x: cx - 210, y: cy - 46, width: 420, height: 92, rx: 22, fill: '#3c4128' });
    el('path', { d: `M${cx - 190} ${cy} H${cx + 190}`, stroke: '#2a2e1c', 'stroke-width': 4, 'stroke-dasharray': '10 8' });
    el('rect', { x: cx - 40, y: cy - 62, width: 80, height: 18, rx: 6, fill: '#22251a' });
  }
  function stand(el, cx, cy) {
    // Two wooden stakes in the ground, a cardboard backer between them.
    el('path', { d: `M${cx - 230} ${cy + 200} H${cx + 230}`, stroke: '#9b8c6e', 'stroke-width': 4 });
    for (const sx of [cx - 150, cx + 150]) {
      el('rect', { x: sx - 14, y: cy - 220, width: 28, height: 420, fill: '#c19a63', stroke: '#8f6d3e', 'stroke-width': 2 });
    }
    T().cardboard(el, cx - 136, cy - 200, 272, 300, 251);
    el('rect', { x: cx - 164, y: cy + 110, width: 328, height: 20, fill: '#b38a52', stroke: '#8f6d3e', 'stroke-width': 2 });
  }

  // A dark board (painted plywood) for the bright-sticker aim points.
  function darkBoard(el, x, y, w, h) {
    el('rect', { x: x + 4, y: y + 6, width: w, height: h, fill: '#000', opacity: 0.15 });
    el('rect', { x, y, width: w, height: h, fill: '#2c2a26' });
    el('rect', { x, y, width: w, height: h, fill: 'none', stroke: '#1a1916', 'stroke-width': 3 });
  }

  Infographic.pastilles = {
    dot, hole, paperTarget, greenTarget, repair, closeUp, bigRepair, groupRepair, usage, dimensions, ammoBox, magazine, rifleCase, stand, darkBoard,
    REACTIVE_HITS, BLACK_ZONES,
    COLOURS: {
      rouge: '#f20d0d', blanc: '#fefefe', noir: '#000000', bleu: '#005dd9', jaune: '#fed504',
      orange: '#fe6c02', rose: '#f71c77', vert: '#2db03d', violet: '#893bb4',
    },
  };
})();
