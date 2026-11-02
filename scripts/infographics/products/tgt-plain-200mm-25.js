// 25 non-reactive shooting boards, 20 cm, numbered zones and repair patches
// (SKU TGT-PLAIN-200MM-25, product 351 on prod). NOT reactive: the page says
// the shot leaves a plain hole, so the third sheet counts a series with plain
// holes and never shows a splatter. No supplier sheet: every fact comes from
// the product page.
// Measured on its flat gallery photo (the sharper single board the user
// uploaded on 2026-11-02), same layout as tgt-splat-200mm-20-fluo (product
// 346), so its markers and hits carry over: square centre 499,500, half side
// 438; the 18 cm black disc r368 around 500,500; rings r228, r166, r92; red
// centre r40; zones 7, 8, 9 on the horizontal axis; 5 red and 23 black
// patches in the corners.
window.PRODUCT = {
  eyebrow: '25 planches de tir zones numérotées non réactives',

  variants: {
    board: {
      photo: 'photos/tgt-plain-200mm-25.jpg',
      points: [[[115, 195]], [[200, 590]], [[115, 750]], [[716, 421]], [[500, 462]], [[868, 560]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la planche',
    photoFilter: 'brightness(1.03)',
    callouts: [
      { side: 'l', y: 400, title: 'Pastilles rouges ×5', text: 'Pour reconstituer le centre' },
      { side: 'l', y: 650, title: 'Disque de 18 cm', text: 'Trois anneaux pour compter les points' },
      { side: 'l', y: 920, title: 'Pastilles noires ×23', text: 'Masquent les impacts entre deux séries' },
      { side: 'r', y: 480, title: 'Zones 7, 8 et 9', text: 'Chiffrées de part et d’autre du centre' },
      { side: 'r', y: 700, title: 'Mouche rouge', text: 'Le centre de la cible' },
      { side: 'r', y: 900, title: 'Fond jaune fluorescent', text: 'Le visuel de la version réactive' },
    ],
    footer: {
      tag: 'Non réactive',
      html: 'Imprimée <strong>sans couche réactive</strong> : le tir laisse un simple trou, visible à l’œil nu ou à la longue-vue <strong>à 10 ou 15 m</strong>.',
    },
  },

  // The photo cropped to the square and drawn at true size.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À l’échelle',
      html: 'La planche et la pièce de 1 € sont dessinées <strong>à la même échelle</strong>. À tirer de préférence entre <strong>10 et 25 m</strong>.',
    },
    draw(kit, P, V) {
      const K = 3;
      Infographic.targets.squareToScale(kit, V.photo, {
        centre: [499, 500], half: 437, mm: 200, K, label: '20 cm', heading: 'UNE PLANCHE',
        facts: [['25', 'planches'], ['Cible de 18 cm', 'zones 7, 8 et 9']],
      });

      // The repair patches, at the same scale as the board and the coin:
      // 1.8 cm, 5 red and 23 black on each board.
      const { el, text, section } = kit;
      const r = 18 / 2 * K, y = 790, xs = [930, 1080];
      section(900, 730, 'PASTILLES');
      [['#ec2a24', '#b51d18', 'rouges', '×5'], ['#231f1d', '#000', 'noires', '×23']].forEach(([fill, edge, name, n], i) => {
        el('circle', { cx: xs[i], cy: y, r, fill, stroke: edge, 'stroke-width': 1.5 });
        el('circle', { cx: xs[i] - r * 0.3, cy: y - r * 0.3, r: r * 0.35, fill: '#fff', opacity: 0.12 });
        text(xs[i] + r + 12, y - 2, n, { 'font-size': 34, 'font-weight': 700, fill: 'var(--ink)' });
        text(xs[i] + r + 12, y + 24, name, { 'font-size': 24, 'font-weight': 500, fill: 'var(--mute)' });
      });
      text(900, y + r + 46, 'Ø 1,8 cm, 28 par planche', { 'font-size': 26, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.04em' });
    },
  },

  // At true size (mounted.html): only the 18 cm round target, as it is used
  // once peeled off the board, not the square sheet. Five on the default 1 m
  // panel, and four in a 2 x 2 grid on a 55 x 55 cm board, the size of a C50
  // card (five 18 cm discs would overlap on it).
  mounted: [
    { centre: [500, 500], half: 368, cm: 18, round: true },
    { id: 'c50', centre: [500, 500], half: 368, cm: 18, round: true, panel: [55, 55], count: 4 },
  ],

  // A pile of boards, no text (stack.html): the pack holds 25.
  stack: { centre: [499, 500], half: 437, cm: 20, count: 7 },

  // The scored series both the third sheet and the text-free impact.html
  // draw, plain holes on the diagonals, clear of the printed numbers and of
  // the centre zone, whose value the page doesn't give. In panel px the zone
  // edges are r59 (centre), r106 (9), r147 (8), r236 (7, the disc edge):
  // 9 + 9 + 8 + 8 + 7 = 41.
  impact: {
    centre: [499, 500], radius: 437, shape: 'square', seed: 351, holes: 'plain',
    hits: [[55, -55], [-60, 55], [90, 85], [-88, -82], [125, -125]],
  },

  // Before / after a series counted by zone, plain holes.
  effect: {
    title: 'Compter les points',
    footer: {
      tag: 'Illustration',
      html: 'Série reproduite <strong>à titre d’illustration</strong>. Planche <strong>non réactive</strong> : les impacts restent de simples trous.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        ...window.PRODUCT.impact,
        afterLabel: 'APRÈS UNE SÉRIE',
        closeUp: false,
        closeUpTitle: 'Le score de la série',
        caption: [
          'Chaque trou vaut le chiffre de sa zone : 9 + 9 + 8 + 8 + 7 = 41 points.',
          'Comparez le total d’une série à l’autre.',
        ],
      });
    },
  },
};
