// 200 round targets, 76 mm, green and black, 5 numbered zones (SKU
// TGT-FLUO-GNBK-200-5Z, product 154 on prod). NOT reactive, whatever "fluo"
// in the SKU suggests: the page says the impact leaves no colour, so the
// third sheet shows a score count with plain holes. No supplier sheet: every
// fact comes from the product page on armooutdoor.fr. Geometry measured on
// the flat gallery photo: centre 493,502; zone edges r65 (5), r132 (4),
// r198 (3), r266 (2), r334 (1, the 76 mm edge); numbers on the four axes.
window.PRODUCT = {
  eyebrow: '200 cibles de comptage à 5 zones',

  variants: {
    greenblack: {
      photo: 'photos/tgt-fluo-gnbk-200-5z.jpg',
      points: [[[329, 338]], [[532, 545]], [[660, 445]], [[715, 724]]],
      palette: { green: '#74f507', black: '#0a0a0a' },
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
    callouts: [
      { side: 'l', y: 450, title: 'Zones 1 à 5', text: 'Bandes alternées vert et noir, du bord au centre' },
      { side: 'l', y: 780, title: 'Mouche « 5 »', text: 'La zone centrale, la plus haute' },
      { side: 'r', y: 560, title: 'Chiffres tout autour', text: 'Score lisible quelle que soit l’orientation' },
      { side: 'r', y: 880, title: 'Vert fluorescent', text: 'Contraste franc avec le noir, à distance' },
    ],
    footer: {
      tag: 'Non réactive',
      html: 'Cible de <strong>comptage</strong> : pas de gerbe à l’impact, le <strong>score</strong> se lit à la zone atteinte, de 1 à 5.',
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
      const R = 76 / 2 * K, cx = 560, cy = 800, u = R / 334;
      [[334, P.green], [266, P.black], [198, P.green], [132, P.black], [65, P.green]].forEach(([r, fill]) => {
        el('circle', { cx, cy, r: r * u, fill });
      });
      // Numbers on the four axes, black on green, green on black.
      const num = (x, y, n, fill) => text(x, y + 14 * u, n, { 'text-anchor': 'middle', 'font-size': 42 * u, fill, style: 'font-family: Barlow' });
      num(cx, cy, '5', P.black);
      [[98, '4', P.green], [165, '3', P.black], [232, '2', P.green], [300, '1', P.black]].forEach(([r, n, fill]) => {
        for (const [dx, dy] of [[0, -1], [1, 0], [0, 1], [-1, 0]]) num(cx + dx * r * u, cy + dy * r * u, n, fill);
      });

      section(150, 350, 'UNE CIBLE');
      ext(cx - R, cy - 20, cx - R, cy - R - 70); ext(cx + R, cy - 20, cx + R, cy - R - 70);
      dim(cx - R, cy - R - 55, cx + R, cy - R - 55, '76 mm', 56);

      Infographic.targets.coinAndFacts({ el, text }, {
        K, cy, R, facts: [['200', 'cibles'], ['Rouleau', 'conditionnement'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // A series scored zone by zone: plain holes on the diagonals, clear of the
  // numbers, adding up to the total in the caption.
  effect: {
    title: 'Compter les points',
    footer: {
      tag: 'Illustration',
      html: 'Série reproduite <strong>à titre d’illustration</strong>. Cible <strong>non réactive</strong> : les impacts restent de simples trous.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [493, 502], radius: 334, seed: 154, holes: 'plain',
        // In panel px the zone edges are r54 (5), r111 (4), r166 (3), r223 (2), r280 (1).
        hits: [[20, -25], [60, 60], [-62, 58], [100, -100], [-140, -140]],
        afterLabel: 'APRÈS UNE SÉRIE',
        closeUp: false,
        closeUpTitle: 'Le score de la série',
        caption: [
          'Chaque trou vaut le chiffre de sa zone : 5 + 4 + 4 + 3 + 2 = 18 points.',
          'Comparez le total d’une série à l’autre.',
        ],
      });
    },
  },
};
