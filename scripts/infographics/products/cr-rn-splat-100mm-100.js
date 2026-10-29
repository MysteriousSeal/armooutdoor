// 100 reactive round targets, 10 cm, red and black, 5 numbered zones (SKU
// CR-RN-SPLAT-100MM-100, product 93 on prod). No supplier sheet: every fact
// comes from the product page on armooutdoor.fr. Geometry measured on the
// flat gallery photo: centre 500,500; ring edges r53 (10), r141 (9), r211 (8),
// r282 (7), r352 (6, the 10 cm edge); numbers on the four axes mid-ring;
// repair patches r52 at (198,198) and mirrors: black top left and bottom
// right, red "10" top right and bottom left, all inside the 100 mm square.
window.PRODUCT = {
  eyebrow: '100 cibles réactives à 5 zones numérotées',

  variants: {
    redblack: {
      photo: 'photos/cr-rn-splat-100mm-100.jpg',
      points: [[[325, 325]], [[533, 533]], [[395, 562]], [[802, 198]], [[802, 802]]],
      palette: { red: '#da422d', black: '#1e1a19', yellow: '#f2c55a' },
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
    callouts: [
      { side: 'l', y: 430, title: 'Zones 6 à 10', text: 'Cinq anneaux rouge et noir, du bord au centre' },
      { side: 'l', y: 640, title: 'Centre « 10 »', text: 'Repère précis pour la visée et le réglage' },
      { side: 'l', y: 860, title: 'Chiffres jaunes', text: 'Répétés sur les quatre axes' },
      { side: 'r', y: 430, title: 'Pastilles « 10 »', text: 'Deux, pour reconstituer le centre' },
      { side: 'r', y: 980, title: 'Pastilles noires', text: 'Deux, pour masquer les impacts sur les zones sombres' },
    ],
    footer: {
      tag: 'Réutilisable',
      html: 'Les quatre <strong>pastilles de réparation</strong> imprimées aux coins recouvrent les impacts : la cible repart sur une <strong>zone propre</strong>.',
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
      const R = 100 / 2 * K, cx = 560, cy = 800, u = R / 352;
      [[352, P.red], [282, P.black], [211, P.red], [141, P.black], [53, P.red]].forEach(([r, fill]) => {
        el('circle', { cx, cy, r: r * u, fill });
      });
      // Zone numbers on the four axes, mid-ring.
      const num = (x, y, n) => text(x, y + 11 * u, n, { 'text-anchor': 'middle', 'font-size': 34 * u, fill: P.yellow, style: 'font-family: Barlow' });
      num(cx, cy, '10');
      [[317, '6'], [247, '7'], [176, '8'], [106, '9']].forEach(([r, n]) => {
        for (const [dx, dy] of [[0, -1], [1, 0], [0, 1], [-1, 0]]) num(cx + dx * r * u, cy + dy * r * u, n);
      });
      // Repair patches in the corners of the 100 mm square.
      [[-1, -1, P.black, ''], [1, -1, P.red, '10'], [-1, 1, P.red, '10'], [1, 1, P.black, '']].forEach(([sx, sy, fill, n]) => {
        const px = cx + sx * 302 * u, py = cy + sy * 302 * u;
        el('circle', { cx: px, cy: py, r: 52 * u, fill });
        if (n) num(px, py, n);
      });

      section(150, 350, 'UNE CIBLE');
      ext(cx - R, cy - 20, cx - R, cy - R - 70); ext(cx + R, cy - 20, cx + R, cy - R - 70);
      dim(cx - R, cy - R - 55, cx + R, cy - R - 55, '10 cm', 56);

      Infographic.targets.coinAndFacts({ el, text }, {
        K, cy, R, facts: [['100', 'cibles en rouleau'], ['4 pastilles', 'de réparation par cible'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // Before / after a series spread over the zones, yellow-green splatter as
  // documented, kept clear of the printed numbers on the axes.
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [500, 500], radius: 352, seed: 93,
        splatColour: '#c8f51e',
        // Three 9, one 8, one 7, all on the diagonals, clear of the numbers.
        hits: [[-56, -58], [58, -54], [52, 60], [100, -100], [-138, 138]],
        afterLabel: 'APRÈS UNE SÉRIE',
        closeUpTitle: 'Gerbe jaune-vert fluorescent',
        caption: ['Chaque impact se repère depuis le pas de tir,', 'et le chiffre de la zone donne le score.'],
      });
    },
  },
};
