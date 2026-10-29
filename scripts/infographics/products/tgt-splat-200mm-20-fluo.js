// 20 reactive shooting boards, 20 cm, numbered zones and repair patches (SKU
// TGT-SPLAT-200MM-20-FLUO, product 346 on prod). No supplier sheet: every
// fact comes from the product page on armooutdoor.fr. Measured on the flat
// gallery photo: square centre 499,500, half side 437; the 18 cm black disc
// r368 around 500,500; rings r230, r166, r92; red centre r40; zones 7, 8, 9
// printed on the horizontal axis; 5 red and 23 black patches in the corners.
window.PRODUCT = {
  eyebrow: '20 planches de tir zones numérotées et pastilles',

  variants: {
    board: {
      photo: 'photos/tgt-splat-200mm-20-fluo.jpg',
      points: [[[115, 195]], [[200, 590]], [[115, 750]], [[716, 421]], [[500, 462]], [[868, 560]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la planche',
    callouts: [
      { side: 'l', y: 400, title: 'Pastilles rouges ×5', text: 'Pour reconstituer le centre' },
      { side: 'l', y: 650, title: 'Disque de 18 cm', text: 'Garde des zones lisibles à 30 ou 50 m' },
      { side: 'l', y: 920, title: 'Pastilles noires ×23', text: 'Masquent les impacts entre deux séries' },
      { side: 'r', y: 480, title: 'Zones 7, 8 et 9', text: 'Chiffrées de part et d’autre du centre' },
      { side: 'r', y: 700, title: 'Mouche rouge', text: 'Le centre de la cible' },
      { side: 'r', y: 900, title: 'Fond jaune fluorescent', text: 'Ressort à grande distance' },
    ],
    footer: {
      tag: 'Réutilisable',
      html: 'Les <strong>28 pastilles</strong> de 1,8 cm (23 noires, 5 rouges) masquent les impacts : la planche repart pour <strong>une nouvelle série</strong>.',
    },
  },

  // The photo cropped to the square and drawn at true size.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À l’échelle',
      html: 'La planche et la pièce de 1 € sont dessinées <strong>à la même échelle</strong>. À tirer de préférence entre <strong>10 et 50 m</strong>.',
    },
    draw(kit, P, V) {
      const K = 3;
      Infographic.targets.squareToScale(kit, V.photo, {
        centre: [499, 500], half: 437, mm: 200, K, label: '20 cm', heading: 'UNE PLANCHE',
        facts: [['20', 'planches'], ['Cible de 18 cm', 'zones 7, 8 et 9']],
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

  // On a 1 m panel (mounted.html), at true size: only the 18 cm round
  // target, as it is used once peeled off the board, not the square sheet.
  mounted: { centre: [500, 500], half: 368, cm: 18, round: true },

  // Before / after a series scored by zone, yellow splatter on the black
  // disc, kept off the numbers on the horizontal axis.
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [499, 500], radius: 437, shape: 'square', seed: 346,
        // In panel px the rings sit at r59 (centre), r106 (9), r147 (8).
        hits: [[-30, -42], [44, -32], [24, 48], [-72, 82], [104, -96]],
        afterLabel: 'APRÈS UNE SÉRIE',
        caption: ['Le jaune ressort sur le disque noir : on lit', 'la zone touchée même à grande distance.'],
      });
    },
  },
};
