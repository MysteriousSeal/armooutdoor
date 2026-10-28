// 200 reactive round targets, 76 mm, zoned 3-colour splatter (SKU
// CR-OV-SPLAT-076-200, product 68 on prod). No supplier sheet: every fact comes
// from the product's own characteristics and description on armooutdoor.fr,
// and a gallery photo of real hits confirms the colours (green outer ring,
// orange middle zone, yellow in the bull). Geometry measured on the flat
// gallery photo: centre 500,488; green edge r381 to r392; orange circle r265;
// red bull r129 ringed yellow to r136; cross green outside, orange inside.
window.PRODUCT = {
  eyebrow: '200 cibles réactives à gerbe zonée',

  variants: {
    tricolour: {
      photo: 'photos/cr-ov-splat-076-200.jpg',
      points: [[[270, 258]], [[180, 488], [320, 488]], [[673, 388]], [[560, 550]]],
      palette: { black: '#0a0a0a', green: '#0b7a35', orange: '#f27a1a', yellow: '#f7c81c', red: '#ea0104' },
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
    callouts: [
      { side: 'l', y: 430, title: 'Couronne extérieure', text: 'Cerclée de vert, gerbe verte à l’impact' },
      { side: 'l', y: 780, title: 'Croix de visée', text: 'Verte à l’extérieur, orange vers le centre' },
      { side: 'r', y: 520, title: 'Zone médiane', text: 'Cerclée d’orange, gerbe orange à l’impact' },
      { side: 'r', y: 820, title: 'Mouche', text: 'Rouge cerclée de jaune, gerbe jaune à l’impact' },
    ],
    footer: {
      tag: '3 couleurs',
      html: 'La <strong>couleur de la gerbe</strong> indique la zone touchée : <strong>verte</strong> sur la couronne, <strong>orange</strong> sur la zone médiane, <strong>jaune</strong> dans la mouche.',
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
      const R = 76 / 2 * K, cx = 560, cy = 800, u = R / 392;
      const line = (r1, r2, a, stroke) => el('line', { x1: cx + Math.cos(a) * r1 * u, y1: cy + Math.sin(a) * r1 * u, x2: cx + Math.cos(a) * r2 * u, y2: cy + Math.sin(a) * r2 * u, stroke, 'stroke-width': 6 * u });
      el('circle', { cx, cy, r: R, fill: P.green });
      el('circle', { cx, cy, r: 381 * u, fill: P.black });
      el('circle', { cx, cy, r: 265 * u, fill: 'none', stroke: P.orange, 'stroke-width': 7 * u });
      for (let q = 0; q < 4; q++) {
        const a = (q * Math.PI) / 2;
        line(269, 381, a, P.green);
        line(136, 262, a, P.orange);
      }
      el('circle', { cx, cy, r: 132 * u, fill: P.red, stroke: P.yellow, 'stroke-width': 8 * u });

      section(150, 350, 'UNE CIBLE');
      ext(cx - R, cy - 20, cx - R, cy - R - 70); ext(cx + R, cy - 20, cx + R, cy - R - 70);
      dim(cx - R, cy - R - 55, cx + R, cy - R - 55, '76 mm', 56);

      Infographic.targets.coinAndFacts({ el, text }, {
        K, cy, R, facts: [['200', 'cibles'], ['Rouleau', 'conditionnement'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // Before / after a series spread over the three zones, each splatter in
  // its zone's documented colour, then one close-up per zone.
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      const green = '#3fe060', orange = '#ff8f1f', yellow = '#e9f51c';
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [500, 488], radius: 392, seed: 68,
        zones: [[136, yellow], [265, orange], [392, green]],
        // Two in the bull, three in the middle zone, two in the outer ring.
        hits: [[-15, -25], [25, 20], [-120, 60], [90, -110], [140, 40], [-170, -170], [60, 235]],
        afterLabel: 'APRÈS UNE SÉRIE',
        closeUps: [
          { label: 'Mouche', colour: yellow, bg: '#ea0104' },
          { label: 'Zone médiane', colour: orange, bg: '#0a0a0a' },
          { label: 'Couronne', colour: green, bg: '#0a0a0a' },
        ],
        closeUpTitle: 'Une couleur par zone',
        caption: ['La gerbe dit où l’impact est tombé,', 'sans descendre au but.'],
      });
    },
  },
};
