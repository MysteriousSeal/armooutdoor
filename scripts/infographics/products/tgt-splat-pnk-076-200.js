// 200 round sighting-in targets, 76 mm, fluo pink (SKU TGT-SPLAT-PNK-076-200,
// product 63 on prod). No supplier sheet: every fact comes from the product's
// own characteristics and description on armooutdoor.fr. NOT reactive,
// whatever "splatter" in the image file names says: never draw a splatter.
// Geometry measured on the flat gallery photo: centre 500,500; pink disc
// r438; black dot r72; four points from r417 (66 px wide) to a tip at r116;
// broken arcs at r222 (10 to 80 deg per quadrant) and r335 (12 to 78 deg).
window.PRODUCT = {
  eyebrow: '200 cibles de réglage rose fluorescent',

  variants: {
    pink: {
      photo: 'photos/tgt-splat-pnk-076-200.jpg',
      points: [[[500, 200], [200, 500]], [[500, 500]], [[657, 343], [737, 263]], [[742, 640]]],
      palette: { pink: '#f70776', black: '#050505' },
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
    photoFilter: 'brightness(1.04)',
    callouts: [
      { side: 'l', y: 520, title: 'Pointes de visée ×4', text: 'Forment les axes horizontal et vertical' },
      { side: 'l', y: 820, title: 'Mouche noire', text: 'Repère net pour une visée régulière' },
      { side: 'r', y: 480, title: 'Arcs concentriques', text: 'Situent les impacts autour du centre' },
      { side: 'r', y: 860, title: 'Rose fluorescent', text: 'Ressort sur la verdure ou au stand' },
    ],
    footer: {
      tag: 'Réglage',
      html: 'Cible <strong>non réactive</strong> : elle ne change pas d’aspect à l’impact. Les <strong>axes</strong> servent à lire la correction de <strong>hauteur</strong> et de <strong>dérive</strong>.',
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
      const R = 76 / 2 * K, cx = 560, cy = 800, u = R / 438;
      el('circle', { cx, cy, r: R, fill: P.pink });
      // Two broken rings, one arc per quadrant, clear of the axes.
      const arc = (r, from, to, q) => {
        const a1 = ((q * 90 + from) * Math.PI) / 180, a2 = ((q * 90 + to) * Math.PI) / 180;
        el('path', { d: `M${cx + Math.cos(a1) * r * u} ${cy + Math.sin(a1) * r * u} A${r * u} ${r * u} 0 0 1 ${cx + Math.cos(a2) * r * u} ${cy + Math.sin(a2) * r * u}`, fill: 'none', stroke: P.black, 'stroke-width': 11 * u, 'stroke-linecap': 'butt' });
      };
      for (let q = 0; q < 4; q++) { arc(222, 10, 80, q); arc(335, 12, 78, q); }
      // Four tapered points on the axes, aimed at the centre.
      for (let q = 0; q < 4; q++) {
        const a = (q * Math.PI) / 2, n = a + Math.PI / 2;
        const o = [cx + Math.cos(a) * 417 * u, cy + Math.sin(a) * 417 * u];
        const t = [cx + Math.cos(a) * 116 * u, cy + Math.sin(a) * 116 * u];
        const w = 33 * u;
        el('path', { d: `M${o[0] + Math.cos(n) * w} ${o[1] + Math.sin(n) * w} L${o[0] - Math.cos(n) * w} ${o[1] - Math.sin(n) * w} L${t[0]} ${t[1]} Z`, fill: P.black });
      }
      el('circle', { cx, cy, r: 72 * u, fill: P.black });

      section(150, 350, 'UNE CIBLE');
      ext(cx - R, cy - 20, cx - R, cy - R - 70); ext(cx + R, cy - 20, cx + R, cy - R - 70);
      dim(cx - R, cy - R - 55, cx + R, cy - R - 55, '76 mm', 56);

      Infographic.targets.coinAndFacts({ el, text }, {
        K, cy, R, facts: [['200', 'cibles'], ['Rouleau', 'conditionnement'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // What the axes are for: a group off to the low left, the height and
  // windage it calls for, and the group centred after the correction. Plain
  // holes, since the target does not react.
  // On a 55 x 55 cm plywood board, the size of a C50 card (mounted.html),
  // at true size: five 76 mm discs, the corner ones 10 cm from the edges.
  mounted: [
    { id: 'c50', centre: [500, 500], half: 438, cm: 7.6, round: true, panel: [55, 55], inset: [10, 10] },
  ],

  // The corrected group both the effect sheet's after panel and the
  // text-free impact.html draw. Plain holes: this target is not reactive.
  impact: {
    centre: [500, 500], radius: 438, seed: 63, holes: 'plain',
    hits: [[-26, -20], [24, -16], [-14, 30], [30, 26], [0, 2]],
  },

  effect: {
    title: 'Le réglage de visée',
    footer: {
      tag: 'Illustration',
      html: 'Groupements reproduits <strong>à titre d’illustration</strong>. Cible <strong>non réactive</strong> : les impacts restent de simples trous.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        ...window.PRODUCT.impact,
        beforeLabel: 'AVANT CORRECTION', afterLabel: 'APRÈS CORRECTION',
        beforeHits: [[-172, 130], [-130, 138], [-166, 176], [-124, 182], [-150, 157]],
        correction: [-148, 156],
        closeUp: false,
        closeUpTitle: 'Lire la correction',
        caption: [
          'Les quatre pointes forment les axes : mesurez l’écart du groupement en hauteur',
          'et en dérive, corrigez votre visée, puis comparez la série suivante.',
        ],
      });
    },
  },
};
