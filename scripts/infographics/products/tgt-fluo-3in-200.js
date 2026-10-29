// 200 reactive round targets, 76 mm, with four red repair patches (SKU
// TGT-FLUO-3IN-200, product 155 on prod). No supplier sheet: every fact comes
// from the product page on armooutdoor.fr. Geometry measured on the flat
// gallery photo: centre 502,500; yellow-green edge r350, black from r336;
// rings r243 and r147 (13 px); cross from r147 to the edge; red centre r70;
// red patches r82 at (236,140), (770,140), (236,860), (770,860), outside the disc.
window.PRODUCT = {
  eyebrow: '200 cibles réactives et pastilles de réparation',

  variants: {
    patches: {
      photo: 'photos/tgt-fluo-3in-200.jpg',
      points: [[[236, 140], [236, 860]], [[606, 396], [674, 328], [749, 253]], [[700, 500]], [[530, 530]]],
      palette: { black: '#1c120d', yellow: '#b4ea0a', red: '#f60100' },
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
    callouts: [
      { side: 'l', y: 640, title: 'Pastilles rouges ×4', text: 'Recouvrent les impacts déjà réalisés' },
      { side: 'r', y: 380, title: 'Trois cercles', text: 'Situent les impacts autour du centre' },
      { side: 'r', y: 620, title: 'Croix de visée', text: 'Jaune-vert fluorescent sur fond noir' },
      { side: 'r', y: 880, title: 'Mouche rouge', text: 'Un point de visée très visible' },
    ],
    footer: {
      tag: 'Réparable',
      html: 'Les <strong>quatre pastilles rouges</strong> imprimées autour de la cible masquent les anciens impacts : la même cible sert à <strong>plusieurs séries</strong>.',
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
      const R = 76 / 2 * K, cx = 560, cy = 840, u = R / 350;
      el('circle', { cx, cy, r: R, fill: P.yellow });
      el('circle', { cx, cy, r: 336 * u, fill: P.black });
      for (let q = 0; q < 4; q++) {
        const a = (q * Math.PI) / 2;
        el('line', { x1: cx + Math.cos(a) * 147 * u, y1: cy + Math.sin(a) * 147 * u, x2: cx + Math.cos(a) * 340 * u, y2: cy + Math.sin(a) * 340 * u, stroke: P.yellow, 'stroke-width': 13 * u });
      }
      for (const r of [243, 147]) el('circle', { cx, cy, r: r * u, fill: 'none', stroke: P.yellow, 'stroke-width': 13 * u });
      el('circle', { cx, cy, r: 70 * u, fill: P.red });
      // Repair patches around the disc.
      for (const [sx, sy] of [[-1, -1], [1, -1], [-1, 1], [1, 1]]) {
        el('circle', { cx: cx + sx * 267 * u, cy: cy + sy * 360 * u, r: 82 * u, fill: P.red });
      }

      // The patches reach above the disc, so the 76 mm line sits over them.
      const dy = cy - 360 * u - 82 * u - 40;
      section(150, 350, 'UNE CIBLE');
      ext(cx - R, cy - 20, cx - R, dy - 15); ext(cx + R, cy - 20, cx + R, dy - 15);
      dim(cx - R, dy, cx + R, dy, '76 mm', 56);

      Infographic.targets.coinAndFacts({ el, text }, {
        K, cy, R, facts: [['200', 'cibles en rouleau'], ['4 pastilles', 'de réparation par cible'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // Before / after a series, yellow splatter as documented, off the cross.
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [502, 500], radius: 350, seed: 155,
        hits: [[-40, -38], [42, -50], [55, 45], [-35, 60], [-150, 130]],
        afterLabel: 'APRÈS UNE SÉRIE',
        caption: ['Le contour de chaque trou vire au jaune vif sur le fond noir.', 'Une fois la cible marquée, les pastilles masquent les impacts.'],
      });
    },
  },
};
