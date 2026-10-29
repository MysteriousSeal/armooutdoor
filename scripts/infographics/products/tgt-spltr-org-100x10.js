// 100 reactive round targets, 10 cm, fluo orange with a black centre (SKU
// TGT-SPLTR-ORG-100X10, product 94 on prod). No supplier sheet: every fact
// comes from the product's own characteristics and description on
// armooutdoor.fr. Geometry measured on the flat gallery photo: centre
// 503,502; yellow edge r445, orange from r432; black dot r92; thin circle r134;
// thick circle r287; cross from the edge in to the thin circle.
window.PRODUCT = {
  eyebrow: '100 cibles réactives orange de 10 cm',

  variants: {
    orange: {
      photo: 'photos/tgt-spltr-org-100x10.jpg',
      points: [[[503, 150], [150, 502]], [[503, 502]], [[598, 407], [706, 299]], [[700, 650], [813, 812]]],
      palette: { orange: '#fb2a0e', yellow: '#f5ec2a', black: '#1e1410' },
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
    callouts: [
      { side: 'l', y: 450, title: 'Croix de visée', text: 'Noire, du bord jusqu’au premier cercle' },
      { side: 'l', y: 800, title: 'Point noir central', text: 'Repère net pour le guidon, le point rouge ou le réticule' },
      { side: 'r', y: 470, title: 'Deux cercles', text: 'Un fin près du centre, un épais à mi-rayon' },
      { side: 'r', y: 870, title: 'Orange fluorescent', text: 'Liseré jaune, fort contraste avec le noir' },
    ],
    footer: {
      tag: 'Dispersion',
      html: 'D’un coup d’œil, on voit si un <strong>groupement</strong> tient dans le <strong>premier cercle</strong> ou déborde jusqu’au <strong>second</strong>.',
    },
  },

  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À l’échelle',
      html: 'La cible et la pièce de 1 € sont dessinées <strong>à la même échelle</strong>. À tirer de préférence entre <strong>10 et 25 m</strong>.',
    },
    draw({ el, text, dim, ext, section }, P) {
      const K = 6; // px per mm: a 10 cm target needs a smaller scale than the 76 mm ones

      // The target, redrawn from the photo's proportions (photo px times u).
      const R = 100 / 2 * K, cx = 560, cy = 800, u = R / 445;
      el('circle', { cx, cy, r: R, fill: P.yellow });
      el('circle', { cx, cy, r: 432 * u, fill: P.orange });
      for (let q = 0; q < 4; q++) {
        const a = (q * Math.PI) / 2;
        el('line', { x1: cx + Math.cos(a) * 134 * u, y1: cy + Math.sin(a) * 134 * u, x2: cx + Math.cos(a) * 432 * u, y2: cy + Math.sin(a) * 432 * u, stroke: P.black, 'stroke-width': 6 * u });
      }
      el('circle', { cx, cy, r: 287 * u, fill: 'none', stroke: P.black, 'stroke-width': 12 * u });
      el('circle', { cx, cy, r: 134 * u, fill: 'none', stroke: P.black, 'stroke-width': 7 * u });
      el('circle', { cx, cy, r: 92 * u, fill: P.black });

      section(150, 350, 'UNE CIBLE');
      ext(cx - R, cy - 20, cx - R, cy - R - 70); ext(cx + R, cy - 20, cx + R, cy - R - 70);
      dim(cx - R, cy - R - 55, cx + R, cy - R - 55, '10 cm', 56);

      Infographic.targets.coinAndFacts({ el, text }, {
        K, cy, R, facts: [['100', 'cibles'], ['Rouleau', 'conditionnement'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // Before / after a series: a group on the centre that holds the first
  // circle, two shots out to the second. Yellow splatter on the orange.
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [503, 502], radius: 445, seed: 94,
        splatColour: '#ffec1f', closeUpBg: '#fb2a0e',
        hits: [[-40, -40], [35, -30], [10, 45], [-50, 25], [120, -70], [-100, 125]],
        afterLabel: 'APRÈS UNE SÉRIE',
        caption: ['Le jaune ressort sur l’orange : chaque impact', 'se repère depuis le pas de tir.'],
      });
    },
  },
};
