// 250 reactive round targets, 76 mm, yellow fluo on anti-tear paper (SKU
// CR-REACT-076-250-FLUO, product 71 on prod). Same design as the other
// yellow fluo targets, measured on its own flat gallery photo: centre
// 500,490; yellow edge r399, black from r384; rings r165 and r276 (15 px);
// cross from r165 to the edge; red dot r75. No supplier sheet: every fact
// comes from the product page on armooutdoor.fr.
window.PRODUCT = {
  eyebrow: '250 cibles réactives papier anti-déchirure',

  variants: {
    fluo: {
      photo: 'photos/cr-react-076-250-fluo.jpg',
      points: [[[500, 140]], [[500, 490]], [[617, 373], [695, 295], [776, 214]], [[620, 680]]],
      palette: { black: '#0d0d0d', yellow: '#e6f50a', red: '#f80000' },
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
    callouts: [
      { side: 'l', y: 400, title: 'Croix de visée', text: 'Jaune fluorescent sur fond noir' },
      { side: 'l', y: 700, title: 'Pastille rouge', text: 'Le point de visée, au centre' },
      { side: 'r', y: 450, title: 'Trois cercles', text: 'Situent les impacts autour du centre' },
      { side: 'r', y: 860, title: 'Papier renforcé', text: 'Multicouche, résiste à la déchirure' },
    ],
    footer: {
      tag: 'Anti-déchirure',
      html: 'Le papier renforcé <strong>limite les déchirures</strong> autour des impacts : la cible reste lisible même avec des <strong>tirs rapprochés</strong>.',
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
      const R = 76 / 2 * K, cx = 560, cy = 800, u = R / 399;
      el('circle', { cx, cy, r: R, fill: P.yellow });
      el('circle', { cx, cy, r: 384 * u, fill: P.black });
      for (let q = 0; q < 4; q++) {
        const a = (q * Math.PI) / 2;
        el('line', { x1: cx + Math.cos(a) * 165 * u, y1: cy + Math.sin(a) * 165 * u, x2: cx + Math.cos(a) * 384 * u, y2: cy + Math.sin(a) * 384 * u, stroke: P.yellow, 'stroke-width': 15 * u });
      }
      for (const r of [276, 165]) el('circle', { cx, cy, r: r * u, fill: 'none', stroke: P.yellow, 'stroke-width': 15 * u });
      el('circle', { cx, cy, r: 75 * u, fill: P.red });

      section(150, 350, 'UNE CIBLE');
      ext(cx - R, cy - 20, cx - R, cy - R - 70); ext(cx + R, cy - 20, cx + R, cy - R - 70);
      dim(cx - R, cy - R - 55, cx + R, cy - R - 55, '76 mm', 56);

      Infographic.targets.coinAndFacts({ el, text }, {
        K, cy, R, facts: [['250', 'cibles'], ['Rouleau', 'conditionnement'], ['Anti-déchirure', 'papier multicouche renforcé']],
      });
    },
  },

  // Before / after a tight series, the point of this paper: close hits that
  // stay readable. Yellow splatter as documented.
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [500, 490], radius: 399, seed: 71,
        afterLabel: 'APRÈS DES TIRS RAPPROCHÉS',
        hits: [[-42, -40], [24, -52], [52, 6], [-6, 40], [-58, 18], [36, 58]],
        caption: ['Une gerbe jaune autour de chaque trou,', 'sans que le papier se déchire entre les tirs.'],
      });
    },
  },
};
