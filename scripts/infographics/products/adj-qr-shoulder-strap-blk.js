// Two-point bungee sling kit, black (SKU ADJ-QR-SHOULDER-STRAP-BLK, product 73 on prod). Facts
// from the product page on armooutdoor.fr (1 sling, 2 QD M-Lok adapters, 2
// hooks, 2 Allen keys, two bungee sections, sliding buckle on the wide part and
// a second near the fork, also takes a classic sling ring, 190 g) and the
// supplier sheets in sources/sangle-supplier-* from the Temu listing (110,5 cm
// at most, elastic 21 to 29 cm; loop 4 cm, inside 3,2 cm, stud 0,94 cm, base
// 3,6 x 0,89 cm, key 5,1 x 1,8 cm, screw 1,2 cm with a 0,8 cm head, nut 1,1 x
// 0,4 cm; M-Lok slot 3,2 cm; its "40.65cm/16inch" for
// the loop is a typo for the 1.6"/4cm of the other sheet). Photos from the
// gallery, whitened: the kit (photos/<sku>.jpg), the buckles (-buckles.jpg) and
// the QD close-up (photos/adj-qr-shoulder-strap-qd.jpg, the same for the three
// colours, about 145 px per cm on the loop and base, 110 on the key).
const COLOUR = { fill: '#2c2d30', dark: '#121315', light: '#505256' };

// The real bungee, cut from the kit photo and straightened (-bungee.png),
// len px long and h px high centred on cy; a stub of strap at each end.
function bungee(el, V, x, cy, len, h) {
  [x - 24, x + len - 4].forEach(sx => el('rect', { x: sx, y: cy - h * .17, width: 28, height: h * .34, rx: 3, fill: COLOUR.fill, stroke: COLOUR.dark, 'stroke-width': 1.5 }));
  el('image', { href: V.bungee, x, y: cy - h / 2, width: len, height: h, preserveAspectRatio: 'none' });
}

// A black tri-glide around a strap of height h, centred on x.
function glide(el, x, cy, h) {
  el('rect', { x: x - 15, y: cy - h / 2 - 7, width: 30, height: h + 14, rx: 6, fill: 'none', stroke: '#17181a', 'stroke-width': 7 });
  el('line', { x1: x, y1: cy - h / 2 - 4, x2: x, y2: cy + h / 2 + 4, stroke: '#17181a', 'stroke-width': 6 });
}

// The mounting parts, drawn: black anodised metal, sizes from the supplier's
// sheets. metal() sets the shared gradients once per sheet.
function metal(el) {
  const defs = el('defs', {});
  const grad = (id, x2, y2, stops) => {
    const g = el('linearGradient', { id, x1: 0, y1: 0, x2, y2 }, defs);
    stops.forEach(([offset, c]) => el('stop', { offset, 'stop-color': c }, g));
  };
  grad('mSide', 1, 0, [[0, '#121315'], [.22, '#3d3f44'], [.34, '#6a6d73'], [.48, '#2b2c30'], [.8, '#17181a'], [1, '#2a2b2f']]);
  grad('mTop', 0, 1, [[0, '#4a4c51'], [.5, '#2d2e32'], [1, '#1b1c1f']]);
  grad('mWall', 0, 1, [[0, '#2f3034'], [.25, '#1c1d20'], [1, '#0e0f11']]);
}

// The base seen from above at an angle, W wide (3,6 cm), W / 3.6 high (1 cm).
function baseTop(el, x, y, W) {
  const g = el('g', { transform: `translate(${x} ${y}) scale(${W / 440})` });
  const face = 'M18 66 Q18 40 78 40 L362 40 Q422 40 422 66 Q422 92 362 92 L78 92 Q18 92 18 66 Z';
  el('path', { d: 'M18 66 L18 94 Q18 122 78 122 L362 122 Q422 122 422 94 L422 66 Z', fill: 'url(#mWall)' }, g);
  el('path', { d: face, fill: 'url(#mTop)', stroke: '#5c5f65', 'stroke-width': 1.2 }, g);
  [72, 368].forEach(cx => {
    el('ellipse', { cx, cy: 66, rx: 34, ry: 13, fill: '#0c0d0e', stroke: '#55585e', 'stroke-width': 1.5 }, g);
    el('polygon', { points: [[-13, 0], [-7, -5], [7, -5], [13, 0], [7, 5], [-7, 5]].map(([a, b]) => `${cx + a},${66 + b}`).join(' '), fill: '#2f3135' }, g);
  });
  el('path', { d: 'M159 26 L159 60 Q159 82 220 82 Q281 82 281 60 L281 26 Z', fill: 'url(#mSide)' }, g);
  el('ellipse', { cx: 220, cy: 26, rx: 61, ry: 24, fill: 'url(#mTop)', stroke: '#6a6d73', 'stroke-width': 1.5 }, g);
  el('ellipse', { cx: 220, cy: 27, rx: 40, ry: 14, fill: '#08090a', stroke: '#3d3f44', 'stroke-width': 2 }, g);
  el('path', { d: 'M184 31 Q220 46 256 31', fill: 'none', stroke: '#4a4c51', 'stroke-width': 2 }, g);
}

// The base's flat underside, W wide: the bore through it, two screw holes.
function baseUnder(el, cx, cy, W) {
  const k = W / 360, h = 135 * k;
  el('rect', { x: cx - W / 2, y: cy - h / 2, width: W, height: h, rx: h / 2, fill: 'url(#mTop)', stroke: '#0e0f11', 'stroke-width': 2 });
  el('rect', { x: cx - W / 2 + 10 * k, y: cy - h / 2 + 10 * k, width: W - 20 * k, height: h - 20 * k, rx: h / 2 - 10 * k, fill: 'none', stroke: '#55585e', 'stroke-width': 1.5 });
  el('circle', { cx, cy, r: 46 * k, fill: 'var(--paper)', stroke: '#6a6d73', 'stroke-width': 2 });
  [-1, 1].forEach(side => {
    const hx = cx + side * 104 * k;
    el('circle', { cx: hx, cy, r: 27 * k, fill: 'var(--paper)', stroke: '#6a6d73', 'stroke-width': 2 });
    el('path', { d: `M${hx + side * 52 * k} ${cy - 28 * k} Q${hx + side * 34 * k} ${cy} ${hx + side * 52 * k} ${cy + 28 * k}`, fill: 'none', stroke: '#55585e', 'stroke-width': 3 });
  });
}

// A countersunk screw, head on top, W across the head (0,8 cm), 1,5 W long.
function screw(el, cx, top, W) {
  const r = W * .31, ry = W * .17, headY = top + ry, neck = top + W * .5, end = top + W * 1.5;
  el('rect', { x: cx - r, y: neck - 2, width: r * 2, height: end - neck, rx: 3, fill: 'url(#mSide)' });
  const pitch = W * .115;
  for (let y = neck + pitch * .6; y < end - pitch * .3; y += pitch) {
    el('path', { d: `M${cx - r - 1.5} ${y + pitch * .28} L${cx + r + 1.5} ${y - pitch * .28}`, stroke: '#0a0b0c', 'stroke-width': pitch * .42, 'stroke-linecap': 'round' });
    el('path', { d: `M${cx - r} ${y + pitch * .28 - pitch * .38} L${cx + r} ${y - pitch * .28 - pitch * .38}`, stroke: '#6a6d73', 'stroke-width': Math.max(1, pitch * .12), opacity: .75 });
  }
  el('path', { d: `M${cx - W / 2} ${headY} L${cx - r} ${neck} L${cx + r} ${neck} L${cx + W / 2} ${headY} Z`, fill: 'url(#mSide)' });
  el('ellipse', { cx, cy: headY, rx: W / 2, ry, fill: 'url(#mTop)', stroke: '#6a6d73', 'stroke-width': 1.5 });
  const a = W * .17, b = W * .085;
  el('polygon', { points: [[-a, 0], [-a * .5, -b], [a * .5, -b], [a, 0], [a * .5, b], [-a * .5, b]].map(([p, q]) => `${cx + p},${headY + q}`).join(' '), fill: '#08090a', stroke: '#3d3f44', 'stroke-width': 1 });
}

// The nut from above, W long (1,1 cm), its threaded boss in the middle.
function nutTop(el, cx, cy, W) {
  const h = W * .64;
  el('path', { d: `M${cx - W * .36} ${cy - h / 2} Q${cx} ${cy - h * .56} ${cx + W * .36} ${cy - h / 2} Q${cx + W * .5} ${cy - h * .36} ${cx + W / 2} ${cy} Q${cx + W * .5} ${cy + h * .36} ${cx + W * .36} ${cy + h / 2} Q${cx} ${cy + h * .56} ${cx - W * .36} ${cy + h / 2} Q${cx - W * .5} ${cy + h * .36} ${cx - W / 2} ${cy} Q${cx - W * .5} ${cy - h * .36} ${cx - W * .36} ${cy - h / 2} Z`, fill: 'url(#mTop)', stroke: '#0e0f11', 'stroke-width': 1.5 });
  el('circle', { cx, cy, r: W * .25, fill: '#25262a', stroke: '#6a6d73', 'stroke-width': 1.5 });
  el('circle', { cx, cy, r: W * .19, fill: 'none', stroke: '#0c0d0e', 'stroke-width': W * .03 });
  el('circle', { cx, cy, r: W * .135, fill: 'var(--paper)', stroke: '#55585e', 'stroke-width': 1.5 });
}

// The nut from the side, W long, its height W * 0,4 / 1,1 with the boss on top.
function nutSide(el, cx, bottom, W) {
  const h = W * .4 / 1.1, foot = h * .5;
  el('path', { d: `M${cx - W * .28} ${bottom - foot} L${cx - W * .2} ${bottom - h} L${cx + W * .2} ${bottom - h} L${cx + W * .28} ${bottom - foot} Z`, fill: 'url(#mSide)' });
  el('rect', { x: cx - W / 2, y: bottom - foot, width: W, height: foot, rx: foot * .35, fill: 'url(#mSide)', stroke: '#0e0f11', 'stroke-width': 1 });
}

// The L-shaped hex key: its long arm running right from (x, y), its short arm
// running down, drawn on the bar's centre line.
function allenKey(el, x, y, long, short, t) {
  const d = `M${x + long} ${y} L${x + t * 1.2} ${y} Q${x} ${y} ${x} ${y + t * 1.2} L${x} ${y + short}`;
  el('path', { d, fill: 'none', stroke: '#1a1b1e', 'stroke-width': t });
  // A highlight along the bar, stopping short of both ends.
  el('path', { d: `M${x + long - t * .2} ${y - t * .18} L${x + t * 1.3} ${y - t * .18} Q${x + t * .14} ${y - t * .18} ${x + t * .14} ${y + t * 1.2} L${x + t * .14} ${y + short - t * .2}`, fill: 'none', stroke: '#55585e', 'stroke-width': t * .2, opacity: .9 });
}

window.PRODUCT = {
  eyebrow: 'Sangle 2 points bungee noire',

  variants: {
    kit: {
      photo: 'photos/adj-qr-shoulder-strap-blk.jpg',
      buckles: 'photos/adj-qr-shoulder-strap-blk-buckles.jpg',
      qd: 'photos/adj-qr-shoulder-strap-qd.jpg',
      bungee: 'photos/adj-qr-shoulder-strap-blk-bungee.png',
      // A hook on the kit photo: its box, centre, and direction to its tip.
      hook: { box: [85, 760, 300, 910], c: [195, 835], angle: 152 },
      points: [[[75, 130]], [[75, 330]], [[130, 860]], [[600, 130]], [[715, 370]], [[700, 540]], [[760, 878]]],
    },
  },

  anatomy: {
    title: 'Anatomie du kit',
    callouts: [
      { side: 'l', y: 380, title: 'Émerillons QD', text: 'Deux, à bouton central et billes' },
      { side: 'l', y: 640, title: 'Embases M-Lok', text: 'Deux, vissées dans une fente' },
      { side: 'l', y: 950, title: 'Mousquetons', text: 'Deux, métal noir, à doigt' },
      { side: 'r', y: 370, title: 'Bande large', text: 'Nylon tissé noir, réglable' },
      { side: 'r', y: 590, title: 'Boucles de réglage', text: 'Longueur générale, tension des branches' },
      { side: 'r', y: 800, title: 'Bungee', text: 'Deux tronçons élastiques froncés' },
      { side: 'r', y: 1010, title: 'Clés Allen', text: 'Deux clés coudées pour le serrage' },
    ],
    footer: {
      tag: 'Noir mat',
      html: 'Sangle, bungee, quincaillerie : <strong>tout est noir mat</strong>, pour s’accorder à n’importe quel équipement.',
    },
  },

  // The sling drawn straight, to scale on its maximum length (10,4 px per
  // cm), the real hooks from the kit photo at its ends.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'Schéma',
      html: 'Sangle dessinée <strong>à sa longueur maximale</strong>, bungee tendu. Mesures du fabricant.',
    },
    draw({ el, text, dim, ext, arrow }, P, V) {
      const k = 10.4, x0 = 175, cy = 660, bandEnd = x0 + 69 * k, bx = bandEnd + 36, len = 29 * k;
      // The two branches, then the band over their start.
      [[-44, 'dimA'], [44, 'dimB']].forEach(([dy, id]) => {
        const y = cy + dy;
        el('path', { d: `M${bandEnd - 8} ${cy + dy * .2} L${bx} ${y} L${bx + len + 16} ${y}`, fill: 'none', stroke: COLOUR.fill, 'stroke-width': 22, 'stroke-linejoin': 'round' });
        bungee(el, V, bx, y, len, 74);
        // The hook, turned to point along the sling.
        const { box: [l, t, r, b], c, angle } = V.hook, clip = id + 'Hook';
        const g = el('g', { transform: `translate(${bx + len + 62} ${y}) rotate(${-angle}) scale(.46) translate(${-c[0]} ${-c[1]})` });
        el('rect', { x: l, y: t, width: r - l, height: b - t }, el('clipPath', { id: clip }, el('defs', {})));
        el('image', { href: V.photo, x: 0, y: 0, width: 1000, height: 1000, 'clip-path': `url(#${clip})`, style: 'mix-blend-mode: multiply' }, g);
      });
      el('rect', { x: x0, y: cy - 23, width: bandEnd - x0, height: 46, rx: 3, fill: COLOUR.fill, stroke: COLOUR.dark, 'stroke-width': 2 });
      el('line', { x1: x0 + 6, y1: cy - 15, x2: bandEnd - 6, y2: cy - 15, stroke: COLOUR.light, 'stroke-width': 2, opacity: .7 });
      el('line', { x1: x0 + 6, y1: cy + 15, x2: bandEnd - 6, y2: cy + 15, stroke: COLOUR.dark, 'stroke-width': 2, 'stroke-dasharray': '8 5' });
      glide(el, x0 + 120, cy, 46); glide(el, bandEnd - 60, cy, 46);
      el('path', { d: `M${bandEnd - 4} ${cy - 24} L${bandEnd + 14} ${cy - 34} L${bandEnd + 14} ${cy + 34} L${bandEnd - 4} ${cy + 24} Z`, fill: 'none', stroke: '#17181a', 'stroke-width': 6, 'stroke-linejoin': 'round' });
      // Bungee above, overall length below.
      const ty = cy - 44 - 52;
      ext(bx, cy - 66, bx, ty - 12); ext(bx + len, cy - 66, bx + len, ty - 12);
      arrow(bx, ty, bx + len, ty);
      text(bx + len / 2, ty - 24, '21 à 29 cm', { 'text-anchor': 'middle', 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
      const ly = cy + 130, x1 = x0 + 110.5 * k;
      ext(x0, cy + 32, x0, ly + 14); ext(x1, cy + 70, x1, ly + 14);
      dim(x0, ly, x1, ly, '110,5 cm au maximum', 52);
      [['190 g', 'poids']].forEach(([big, small]) => {
        const fx = 175;
        text(fx, 975, big, { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
        text(fx, 1009, small, { 'font-size': 25, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.02em' });
      });
    },
  },

  effect: [
    // Every part once, at one scale (100 px per cm), so the coin holds for all
    // of them: the QD from its close-up, the rest drawn in SVG. How many of each
    // comes with the kit is in each heading.
    {
      id: 'pieces',
      title: 'Pièces de montage',
      footer: {
        tag: 'À l’échelle',
        html: 'Mesures du fabricant. Chaque embase se serre par <strong>deux vis et deux écrous</strong>. Alliage d’aluminium et acier.',
      },
      draw({ el, text, section, dim, ext, arrow }, V) {
        metal(el);
        const K = 100;
        const big = { 'font-size': 44, 'font-weight': 700, fill: 'var(--ink)' };
        const label = (x, y, t, anchor = 'start') => text(x, y, t, { 'text-anchor': anchor, ...big });
        const across = (a, b, y, from, t, above = true) => {
          ext(a, from, a, y + (above ? -12 : 12)); ext(b, from, b, y + (above ? -12 : 12));
          arrow(a, y, b, y);
          label((a + b) / 2, above ? y - 18 : y + 52, t, 'middle');
        };
        const down = (x, a, b, from, t, right = true) => {
          ext(from, a, x + (right ? 12 : -12), a); ext(from, b, x + (right ? 12 : -12), b);
          arrow(x, a, x, b);
          label(x + (right ? 18 : -18), (a + b) / 2 + 15, t, right ? 'start' : 'end');
        };
        // The QD, its close-up at 147 px per cm brought to scale, without the
        // base and key below it.
        section(130, 345, 'ÉMERILLON × 2');
        const s = K / 147, X = x => 150 + (x - 110) * s, Y = y => 450 + (y - 140) * s;
        const cp = el('clipPath', { id: 'qdAlone' }, el('defs', {}));
        el('rect', { x: X(100), y: Y(130), width: 610 * s, height: 468 * s }, cp);
        el('image', { href: V.qd, x: X(0), y: Y(0), width: 1000 * s, height: 1000 * s, 'clip-path': 'url(#qdAlone)', style: 'mix-blend-mode: multiply' });
        across(X(110), X(698), Y(140) - 20, Y(270), '4 cm');
        dim(X(170), Y(245), X(640), Y(245), '3,2 cm', 36);
        across(X(345), X(478), Y(598) + 18, Y(598) + 2, '0,94 cm', false);
        // The base, from above and from below.
        section(660, 345, 'EMBASE × 2');
        const bx = 660, by = 465, bw = 3.6 * K, nx = bx + bw / 2;
        baseTop(el, bx, by, bw);
        across(nx - K / 2, nx + K / 2, by - 26, by + 10, '1 cm');
        down(bx + bw + 28, by, by + K, bx + bw + 6, '1 cm');
        across(bx, bx + bw, by + K + 30, by + K + 6, '3,6 cm', false);
        baseUnder(el, nx, 735, bw);
        text(bx + bw + 20, 744, 'dessous', { 'font-size': 25, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.02em' });
        // The coin, its own column.
        text(1190, 420, 'Pièce de 1 €', { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
        text(1190, 454, '23,25 mm, même échelle', { 'font-size': 25, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.02em' });
        const cr = 23.25 / 2 / 10 * K;
        Infographic.targets.coin({ el, text }, 1190 + cr, 475 + cr, cr, { caption: false });
        // The screw.
        section(130, 925, 'VIS × 4');
        const sx = 190, st = 1035, sw = .8 * K;
        screw(el, sx, st, sw);
        across(sx - sw / 2, sx + sw / 2, st - 28, st + 10, '0,8 cm');
        down(sx + sw / 2 + 28, st, st + 1.2 * K, sx + 10, '1,2 cm');
        // The nut, from above and from the side.
        section(480, 925, 'ÉCROU × 4');
        const nw = 1.1 * K, n = 540, ny = 1000;
        nutTop(el, n, ny, nw);
        across(n - nw / 2, n + nw / 2, ny + 58, ny + 40, '1,1 cm', false);
        const nb = 1190;
        nutSide(el, n, nb, nw);
        // Too short for a double arrow: one arrow outside each line, pointing in.
        const ax = n + nw / 2 + 28, top = nb - .4 * K;
        ext(n + nw / 2 + 6, top, ax + 12, top); ext(n + nw / 2 + 6, nb, ax + 12, nb);
        [[top, -1], [nb, 1]].forEach(([y, out]) => {
          el('line', { x1: ax, y1: y + out * 20, x2: ax, y2: y + out * 40, stroke: 'var(--ink)', 'stroke-width': 3 });
          el('path', { d: `M${ax} ${y} l-11 ${out * 22} l22 0 Z`, fill: 'var(--ink)' });
        });
        label(ax + 18, (top + nb) / 2 + 15, '0,4 cm');
        // The key.
        section(760, 925, 'CLÉ ALLEN × 2');
        const kx = 880, ky = 1035, kl = 5.1 * K, ks = 1.8 * K;
        allenKey(el, kx, ky, kl, ks, 22);
        across(kx - 11, kx + kl, ky - 34, ky - 14, '5,1 cm');
        down(kx - 40, ky - 11, ky + ks, kx - 15, '1,8 cm', false);
      },
    },
    // Three steps, a real sequence: the nut seen from above in the supplier's
    // slot (100 px per cm), then the QD close-up.
    {
      id: 'montage',
      title: 'Montage M-Lok',
      footer: {
        tag: 'Sans M-Lok',
        html: 'Les mousquetons se referment aussi sur un <strong>anneau de sangle classique</strong> : les embases ne servent que sur un garde-main M-Lok.',
      },
      draw({ el, text, section, dim, ext, arrow }, V) {
        const xs = [130, 530, 930];
        const rail = (x, cy) => {
          el('rect', { x, y: cy - 110, width: 360, height: 220, rx: 14, fill: '#232427' });
          el('rect', { x: x + 20, y: cy - 35, width: 320, height: 70, rx: 30, fill: 'var(--paper)' });
        };
        const nut = (cx, cy, turn) => {
          const g = el('g', { transform: `translate(${cx} ${cy}) rotate(${turn})` });
          el('rect', { x: -55, y: -30, width: 110, height: 60, rx: 22, fill: 'var(--coyote)', stroke: '#6e5430', 'stroke-width': 3 }, g);
          el('circle', { cx: 0, cy: 0, r: 15, fill: '#3a2c18' }, g);
        };
        const cy = 740;
        rail(xs[0], cy); nut(xs[0] + 180, cy, 0);
        const ay = cy - 150;
        ext(xs[0] + 20, cy - 40, xs[0] + 20, ay - 12); ext(xs[0] + 340, cy - 40, xs[0] + 340, ay - 12);
        dim(xs[0] + 20, ay, xs[0] + 340, ay, '3,2 cm', 44);
        rail(xs[1], cy); nut(xs[1] + 180, cy, 90);
        // The quarter-turn arrow, around the nut.
        const c = xs[1] + 180, R = 84;
        el('path', { d: `M${c - R * .94} ${cy - R * .34} A${R} ${R} 0 0 1 ${c + R * .34} ${cy - R * .94}`, fill: 'none', stroke: 'var(--paper)', 'stroke-width': 7 });
        el('path', { d: `M${c + R * .34 + 24} ${cy - R * .94 + 12} l-30 -22 l0 36 Z`, fill: 'var(--paper)' });
        // The QD lowered onto its base.
        const s = 0.52, X = x => xs[2] - 22 + x * s, Y = y => 490 + y * s;
        const cp = el('clipPath', { id: 'qdOnly' }, el('defs', {}));
        // Everything but the key.
        el('polygon', { points: [[90, 120], [710, 120], [710, 800], [650, 800], [650, 860], [90, 860]].map(([px, py]) => `${X(px)},${Y(py)}`).join(' ') }, cp);
        el('image', { href: V.qd, x: X(0), y: Y(0), width: 1000 * s, height: 1000 * s, 'clip-path': 'url(#qdOnly)', style: 'mix-blend-mode: multiply' });
        const ax = X(590);
        el('line', { x1: ax, y1: Y(470), x2: ax, y2: Y(590), stroke: 'var(--coyote)', 'stroke-width': 7 });
        el('path', { d: `M${ax} ${Y(625)} l-15 -26 l30 0 Z`, fill: 'var(--coyote)' });
        [['ÉTAPE 1', 'Écrou en long', 'il entre dans la fente,', 'sous l’embase.'],
         ['ÉTAPE 2', 'Quart de tour', 'la vis, serrée à la clé Allen,', 'bloque l’écrou sous le rail.'],
         ['ÉTAPE 3', 'Clipser', 'l’émerillon se bloque sur ses', 'billes, le bouton le libère.']].forEach(([head, title, a, b], i) => {
          section(xs[i], 425, head);
          text(xs[i], 1065, title, { 'font-size': 44, 'font-weight': 700, fill: 'var(--ink)' });
          [a, b].forEach((line, j) => text(xs[i], 1105 + j * 34, line, { style: 'font-family: Barlow', 'font-size': 27, fill: 'var(--mute)' }));
        });
      },
    },
    {
      id: 'bungee',
      title: 'Bungee et réglage',
      footer: {
        tag: 'Amorti',
        html: 'L’élastique amortit le moment où la sangle se tend : <strong>pas d’à-coup sur l’épaule</strong> en sautant un fossé ou en descendant d’un talus.',
      },
      draw(kit, V) {
        const { el, text, ext, arrow } = kit;
        Infographic.pastilles.usage(kit, [
          { head: 'LE BUNGEE', title: 'De 21 à 29 cm', line: 'il s’allonge à l’épaule, puis ramène l’arme.',
            draw: (k, x) => {
              // Both lengths at the same scale, 17 px per cm.
              const K = 17;
              [[610, 21, 150, 'Au repos'], [850, 29, 112, 'Tendu']].forEach(([cy, cm, h, label]) => {
                const len = cm * K;
                text(x, cy - 72, label, { 'font-size': 32, 'font-weight': 700, fill: 'var(--mute)' });
                bungee(el, V, x + 24, cy, len, h);
                const ay = cy + 70;
                ext(x + 24, cy + 44, x + 24, ay + 12); ext(x + 24 + len, cy + 44, x + 24 + len, ay + 12);
                arrow(x + 24, ay, x + 24 + len, ay);
                text(x + len + 44, ay + 15, `${cm} cm`, { 'font-size': 44, 'font-weight': 700, fill: 'var(--ink)' });
              });
            } },
          { head: 'LE RÉGLAGE', title: 'Deux boucles', line: 'une pour la longueur, une pour la tension.',
            draw: (k, x) => {
              const cx = x + 280, cy = 730, R = 250, sc = 0.62;
              const cp = el('clipPath', { id: 'buckleZoom' }, el('defs', {}));
              el('circle', { cx, cy, r: R }, cp);
              el('image', { href: V.buckles, x: cx - 500 * sc, y: cy - 500 * sc, width: 1000 * sc, height: 1000 * sc, 'clip-path': 'url(#buckleZoom)' });
              el('circle', { cx, cy, r: R, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 5 });
            } },
        ]);
      },
    },
  ],
};
