// 200 reactive round targets, 76 mm, red reticle and numbers (SKU
// TGT-FLUO-RCTR-200-76MM, product 138 on prod). No supplier sheet: every fact
// comes from the product page on armooutdoor.fr. Geometry measured on the flat
// gallery photo: centre 497,500; yellow edge r400, black from r364; yellow
// rings r208 and r120; red centre r77 ringed yellow to r82; thick red bars
// from the edge in to r218 (26 px wide), a thin red line from there to the
// centre; "9" printed at (377,590) and "8" at (311,648).
window.PRODUCT = {
  eyebrow: '200 cibles réactives réticule et chiffres',

  variants: {
    reticle: {
      photo: 'photos/tgt-fluo-rctr-200-76mm.jpg',
      points: [[[497, 170], [170, 500]], [[420, 630], [350, 700]], [[540, 460]], [[700, 640]], [[775, 775]]],
      palette: { black: '#050505', yellow: '#e2f50a', red: '#f40201' },
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
    callouts: [
      { side: 'l', y: 430, title: 'Réticule rouge', text: 'Quatre branches qui convergent vers le centre' },
      { side: 'l', y: 780, title: 'Repères 8 et 9', text: 'Deux anneaux numérotés autour du centre' },
      { side: 'r', y: 430, title: 'Centre rouge', text: 'Le point de visée, cerclé de jaune' },
      { side: 'r', y: 660, title: 'Fond noir', text: 'Fait ressortir la gerbe jaune à l’impact' },
      { side: 'r', y: 900, title: 'Liseré jaune', text: 'Délimite le bord de la cible' },
    ],
    footer: {
      tag: 'Lecture',
      html: 'Le <strong>réticule</strong> donne les axes de référence, les <strong>anneaux 8 et 9</strong> situent chaque impact autour du centre.',
    },
  },

  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À l’échelle',
      html: 'La cible et la pièce de 1 € sont dessinées <strong>à la même échelle</strong>. À tirer de préférence entre <strong>5 et 25 m</strong>.',
    },
    draw({ el, text, dim, ext, section }, P) {
      const K = 7.9; // px per mm, shared by the target and the coin

      // The target, redrawn from the photo's proportions (photo px times u).
      const R = 76 / 2 * K, cx = 560, cy = 800, u = R / 400;
      el('circle', { cx, cy, r: R, fill: P.yellow });
      el('circle', { cx, cy, r: 364 * u, fill: P.black });
      for (const r of [208, 120]) el('circle', { cx, cy, r: r * u, fill: 'none', stroke: P.yellow, 'stroke-width': 7 * u });
      for (let q = 0; q < 4; q++) {
        const a = (q * Math.PI) / 2, n = a + Math.PI / 2;
        const at = (r, w) => [cx + Math.cos(a) * r * u + Math.cos(n) * w * u, cy + Math.sin(a) * r * u + Math.sin(n) * w * u];
        el('line', { x1: at(80, 0)[0], y1: at(80, 0)[1], x2: at(232, 0)[0], y2: at(232, 0)[1], stroke: P.red, 'stroke-width': 6 * u });
        // Thick bar from the edge, tapering to a point at r218.
        const pts = [at(393, -13), at(393, 13), at(232, 13), at(218, 3), at(218, -3), at(232, -13)];
        el('path', { d: 'M' + pts.map(p => p.join(' ')).join(' L') + ' Z', fill: P.red });
      }
      el('circle', { cx, cy, r: 79 * u, fill: P.red, stroke: P.yellow, 'stroke-width': 5 * u });
      const num = (x, y, n) => text(cx + (x - 497) * u, cy + (y - 500) * u + 16 * u, n, { 'text-anchor': 'middle', 'font-size': 46 * u, fill: P.yellow, style: 'font-family: Barlow' });
      num(377, 590, '9'); num(311, 648, '8');

      section(150, 350, 'UNE CIBLE');
      ext(cx - R, cy - 20, cx - R, cy - R - 70); ext(cx + R, cy - 20, cx + R, cy - R - 70);
      dim(cx - R, cy - R - 55, cx + R, cy - R - 55, '76 mm', 56);

      Infographic.targets.coinAndFacts({ el, text }, {
        K, cy, R, facts: [['200', 'cibles'], ['Rouleau', 'conditionnement'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // Before / after a series, yellow splatter as documented, kept off the
  // red bars and the printed numbers (which sit low left).
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [497, 500], radius: 400, seed: 138,
        hits: [[40, -42], [86, -30], [36, -96], [96, -88], [150, 110]],
        afterLabel: 'APRÈS UNE SÉRIE',
        caption: ['Le jaune ressort sur le noir et le réticule rouge :', 'chaque tir se repère depuis le pas de tir.'],
      });
    },
  },
};
