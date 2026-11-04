// Zipped case for 40 rifle rounds, .22-250 to 7.62x39 (SKU
// 40RD-RFL-AMMO-BOX-BLK, product 60 on prod). No supplier sheet: every fact
// comes from the product page on armooutdoor.fr (15,75 x 11,43 x 7,62 cm,
// 40 cells cut in foam, rounds standing bullet up, rigid shell in black
// cloth, zip on three sides opening flat, strap handle on the top edge).
// The page's size image is kept in sources/mallette-40-cartouches-size.png.
// Photo: the closed case (gallery image 1, whitened); case silhouette x 153
// to 904, y 225 to 842 under the handle, front face right edge (the zip) at
// x 830. The gallery has no open view, so the foam is only drawn, on the
// third sheet.
window.PRODUCT = {
  eyebrow: 'Mallette 40 cartouches carabine',

  variants: {
    case: {
      photo: 'photos/40rd-rfl-ammo-box-blk.jpg',
      points: [[[470, 135]], [[300, 213]], [[330, 620]], [[855, 450]], [[640, 520]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la mallette',
    callouts: [
      { side: 'l', y: 420, title: 'Poignée sanglée', text: 'Cousue sur le chant supérieur' },
      { side: 'l', y: 690, title: 'Curseur de fermeture', text: 'Le couvercle bascule en arrière' },
      { side: 'l', y: 960, title: 'Coque rigide', text: 'Les ogives restent à l’abri des chocs' },
      { side: 'r', y: 450, title: 'Fermeture éclair', text: 'Sur trois côtés, ouverture à plat' },
      { side: 'r', y: 750, title: 'Habillage textile', text: 'Toile noire sur toute la coque' },
    ],
    footer: {
      tag: 'Intérieur',
      html: 'Dedans, <strong>40 logements découpés dans la mousse</strong>, et une garniture textile noire <strong>sous le couvercle</strong>.',
    },
  },

  // Measured as the page's own size image does on this view: height on the
  // left, length under the base, depth across the zipped side. No coin: a
  // three-quarter view is not a true-scale drawing.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'Compacte',
      html: 'Avec <strong>moins de 16 cm de long</strong>, elle se glisse dans une caisse de matériel ou une poche de sac de tir.',
    },
    draw({ el, text, dim, ext, arrow }, P, V) {
      const X = x => 190 + x * 0.86, Y = y => 250 + y * 0.86;
      el('image', { href: V.photo, x: X(0), y: Y(0), width: 860, height: 860, style: 'mix-blend-mode: multiply' });
      const vx = X(153) - 55, t = Y(250), b = Y(842);
      ext(X(240), t, vx - 14, t); ext(X(700), b, vx - 14, b);
      arrow(vx, t, vx, b);
      text(vx - 18, (t + b) / 2 + 18, '11,43 cm', { 'text-anchor': 'end', 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
      const l = X(153), r = X(830), hy = b + 60;
      ext(l, Y(560), l, hy + 14); ext(r, Y(828), r, hy + 14);
      dim(l, hy, r, hy, '15,75 cm', 52);
      const dl = X(830), dr = X(904), dy = Y(190);
      ext(dl, Y(278), dl, dy - 14); ext(dr, Y(470), dr, dy - 14);
      arrow(dl, dy, dr, dy);
      text((dl + dr) / 2, dy - 30, '7,62 cm', { 'text-anchor': 'middle', 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
      [['40', 'cartouches de carabine'], ['150 g', 'poids de la mallette']].forEach(([big, small], i) => {
        const fy = 560 + i * 120;
        text(1150, fy, big, { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
        text(1150, fy + 34, small, { 'font-size': 25, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.02em' });
      });
    },
  },

  // The page's point: holes cut in foam rather than a moulded tray, so the
  // same case takes four common calibres of its .22-250 to 7.62x39 range,
  // picked with the user (.222 Rem, .223 Rem, .300 Blackout, 7.62x39). A section through one row, the cartridges drawn to their own relative scale (CIP profiles, mm).
  effect: {
    title: 'Plusieurs calibres',
    footer: {
      tag: 'Schéma',
      html: 'Vue en coupe simplifiée. La mousse <strong>se resserre autour de la douille</strong> : un bac moulé ne prendrait qu’un calibre.',
    },
    draw({ el, text }) {
      const s = 9, base = 1040, foamTop = base - 22 * s;
      const rnd = Infographic.rng(60);
      // Half-profiles [height, radius] from the case head up, then the bullet
      // from the case mouth to its tip.
      const ROUNDS = {
        '.223 Rem': { caseLen: 44.7, profile: [[0, 4.8], [1.1, 4.8], [1.1, 4.2], [2.1, 4.2], [2.9, 4.8], [36.1, 4.5], [39.3, 3.2], [44.7, 3.2]], bullet: [2.85, 49.0, 57.4] },
        '.222 Rem': { caseLen: 43.2, profile: [[0, 4.8], [1.1, 4.8], [1.1, 4.2], [2.1, 4.2], [2.9, 4.8], [33.9, 4.52], [35.4, 3.2], [43.2, 3.2]], bullet: [2.85, 46.5, 54.5] },
        '.300 BLK': { caseLen: 34.7, profile: [[0, 4.8], [1.1, 4.8], [1.1, 4.2], [2.1, 4.2], [2.9, 4.8], [30.4, 4.5], [31.6, 4.2], [34.7, 4.2]], bullet: [3.9, 41.0, 56.0] },
        '7.62x39': { caseLen: 38.7, profile: [[0, 5.65], [1.5, 5.65], [1.5, 4.8], [2.6, 4.8], [3.5, 5.65], [30.5, 5.03], [33.0, 4.3], [38.7, 4.3]], bullet: [3.95, 43.0, 56.0] },
      };
      const cartridge = (cx, R) => {
        const P = ([h, r], side) => `${cx + side * r * s} ${base - h * s}`;
        const right = R.profile.map(p => P(p, 1)), left = R.profile.slice().reverse().map(p => P(p, -1));
        const [br, hb, tip] = R.bullet, mouth = base - R.caseLen * s, sh = base - hb * s, ty = base - tip * s;
        el('path', {
          d: `M${cx - br * s} ${mouth + 2} L${cx - br * s} ${sh} Q${cx - br * s} ${ty + (sh - ty) * 0.25} ${cx - 0.5 * s} ${ty} L${cx + 0.5 * s} ${ty} Q${cx + br * s} ${ty + (sh - ty) * 0.25} ${cx + br * s} ${sh} L${cx + br * s} ${mouth + 2} Z`,
          fill: '#b8683a', stroke: '#7a4220', 'stroke-width': 2.5, 'stroke-linejoin': 'round',
        });
        el('path', { d: `M${right.join(' L')} L${left.join(' L')} Z`, fill: '#c9a24b', stroke: '#8a6a2c', 'stroke-width': 2.5, 'stroke-linejoin': 'round' });
        // Highlight down the body.
        el('path', { d: `M${cx - 2.6 * s} ${base - 4 * s} L${cx - 2.4 * s} ${base - (R.profile[5][0] - 1) * s}`, stroke: '#e6c97c', 'stroke-width': 5, 'stroke-linecap': 'round', opacity: .8 });
      };

      // The foam block, cut open on one row.
      const x0 = 230, x1 = 1330;
      el('rect', { x: x0, y: foamTop, width: x1 - x0, height: base - foamTop + 30, rx: 10, fill: '#2b2c30' });
      for (let i = 0; i < 900; i++) {
        el('circle', { cx: x0 + 8 + rnd() * (x1 - x0 - 16), cy: foamTop + 8 + rnd() * (base - foamTop + 14), r: 1 + rnd() * 2, fill: rnd() < 0.5 ? '#3a3b40' : '#1d1e21' });
      }
      // The foam's top face, then the holes cut through it.
      el('rect', { x: x0, y: foamTop - 6, width: x1 - x0, height: 12, rx: 6, fill: '#3a3b40' });
      const order = ['.222 Rem', '.223 Rem', '.300 BLK', '7.62x39'];
      const xs = [355, 575, 795, 1015, 1225];
      xs.forEach((cx, i) => {
        const name = order[i], R = name && ROUNDS[name];
        // An empty hole sits narrower: the foam closes up without a case in it.
        const w = R ? R.profile[0][1] * 2 * s : 8 * s;
        el('rect', { x: cx - w / 2, y: foamTop - 6, width: w, height: base - foamTop + 6, fill: '#141517' });
        if (R) cartridge(cx, R);
        text(cx, base + 80, name || 'logement vide', { 'text-anchor': 'middle', 'font-size': name ? 36 : 28, 'font-weight': 700, fill: name ? 'var(--ink)' : 'var(--mute)' });
      });
      text(780, base + 135, 'Debout, ogive en l’air, espacées : elles ne s’entrechoquent pas.', { 'text-anchor': 'middle', style: 'font-family: Barlow', 'font-size': 30, fill: 'var(--mute)' });
    },
  },
};
