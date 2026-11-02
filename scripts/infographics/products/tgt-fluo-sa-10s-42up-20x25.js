// 10 sheets of 42 peel-off reactive targets, 25 mm (SKU
// TGT-FLUO-SA-10S-42UP-20X25, product 144 on prod), the same sheet as
// tgt-fluo-sa-25s-42up-20x20 in a smaller pack. No supplier sheet: every fact
// comes from the product page on armooutdoor.fr (7 columns by 6 rows). The
// page also says the whole sheet can be stuck as is; the user corrected that
// (2026-11-02): only the stickers peel off, so the sheets show 70's uses. The "20X25" in the SKU is not a
// size: the user confirmed the page's sheet size, and the SKU stays as is.
// Sizes: the user confirmed (2026-11-02) that the stickers are 2.54 cm, the
// figure printed on the sheet ("diameter of 1 inch"). On the photo that makes
// the sheet about 22.3 cm, not the page's 20 x 20 cm, so no sheet size is
// printed on any image until the page is settled.
// Sheet photo (gallery image 1, 1000 px): sheet x 101 to 899, y 102 to 898;
// sticker centres at x 166 + 110.4 i, y 272 + 110.4 j, radius 45.5 (black
// disc), red bull about r15. Close-up (photos/<sku>-sticker.jpg): disc centre
// 500,500, r483.
const SHEET = { centre: [500, 500], half: 399 };
const AT = (i, j) => [166.3 + 110.4 * i, 272 + 110.4 * j];
const STICKER = 'photos/tgt-fluo-sa-10s-42up-20x25-sticker.jpg';

window.PRODUCT = {
  eyebrow: '10 planches de 42 cibles autocollantes 25 mm',

  variants: {
    sheet: {
      photo: 'photos/tgt-fluo-sa-10s-42up-20x25.jpg',
      points: [[[121, 272]], [[130, 603]], [[166, 800]], [[829, 272]], [[868, 493]], [[872, 855]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la planche',
    photoFilter: 'brightness(1.03)',
    callouts: [
      { side: 'l', y: 470, title: '42 cibles par planche', text: 'Sept colonnes, six rangées' },
      { side: 'l', y: 760, title: 'Disque de 2,54 cm', text: 'Noir, cerné d’un liseré jaune' },
      { side: 'l', y: 1010, title: 'Croix jaune', text: 'Partage la cible en quatre quadrants' },
      { side: 'r', y: 480, title: 'Mouche rouge', text: 'Le point de visée' },
      { side: 'r', y: 720, title: 'Gerbe jaune', text: 'Le noir s’arrache autour du trou' },
      { side: 'r', y: 990, title: 'À détacher', text: 'Chaque cible se décolle une à une' },
    ],
    footer: {
      tag: 'Où l’on veut',
      html: 'Chaque cible se décolle et se pose <strong>où l’on veut</strong> : au centre d’un carton, sur une cible déjà utilisée ou <strong>en rangée</strong>.',
    },
  },

  // The sheet on the left; on the right one sticker and the 1 euro coin,
  // both enlarged at the same scale so their sizes compare.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À l’échelle',
      html: 'La cible et la pièce de 1 € sont agrandies <strong>à la même échelle</strong>. À tirer de préférence entre <strong>5 et 25 m</strong>.',
    },
    draw(kit, P, V) {
      Infographic.targets.sheetDimensions(kit, V.photo, {
        sheet: SHEET, sticker: STICKER, mm: 25.4, label: '2,54 cm',
        sheetLine: '42 cibles, 7 × 6', facts: [['10', 'planches'], ['420', 'cibles au total']],
      });
    },
  },

  // Loose stickers on a 55 x 55 cm board, the size of a C50 card, at true
  // size: a 4 x 4 grid of aim points.
  mounted: [
    { id: 'c50', photo: STICKER, centre: [500, 500], half: 478, cm: 2.54, round: true, panel: [55, 55], grid: [4, 4] },
  ],

  // A pile of sheets, no text (stack.html): the pack holds 10.
  stack: { ...SHEET, cm: 22.3, count: 7 },

  // The whole sheet after a session, no text: one hit per sticker, row by
  // row, the first three rows and two more. Panel scale 0.70.
  impact: {
    ...SHEET, radius: 399, shape: 'square', seed: 144, size: 0.42,
    hits: [...Array(23)].map((_, n) => {
      const [x, y] = AT(n % 7, Math.floor(n / 7));
      const a = n * 2.4, d = 4 + (n * 7) % 9;
      return [(x - 500) * 0.7018 + Math.cos(a) * d, (y - 500) * 0.7018 + Math.sin(a) * d];
    }),
  },

  // How the page says to use them: placed in a row on cardboard, or stuck on
  // a used target as a fresh aim point.
  effect: {
    title: 'Mode d’emploi',
    footer: {
      tag: 'Illustration',
      html: 'Usages reproduits <strong>à titre d’illustration</strong>, d’après la fiche produit. Chaque impact laisse une <strong>gerbe jaune</strong>.',
    },
    draw(kit, V) {
      const { el, text, section } = kit, T = Infographic.targets, rnd = Infographic.rng(144);
      const panels = [
        ['SUR UN CARTON', 'En rangée', 'pour organiser une série.'],
        ['SUR UNE CIBLE UTILISÉE', 'Un point de visée neuf', 'sans changer de cible.'],
      ];
      // Left: a row of five stickers on cardboard, the first three shot.
      T.cardboard(el, 130, 450, 560, 560, 144);
      for (let i = 0; i < 5; i++) {
        const cx = 194 + i * 108, cy = 730;
        T.sticker(el, STICKER, cx, cy, 46);
        if (i < 3) T.splat(el, rnd, cx + (rnd() - 0.5) * 12, cy + (rnd() - 0.5) * 12, 16, el('g', {}));
      }
      // Right: our 76 mm target already shot, a fresh sticker (true relative
      // size) on a clear black zone of it.
      T.usedTarget(el, 1090, 730, 560, rnd, [[40, -30], [95, -125], [140, 60], [-60, 150], [-165, 40], [40, 175], [180, -150]]);
      T.sticker(el, STICKER, 1090 - 140, 730 - 140, 94);
      panels.forEach(([head, title, line], i) => {
        const x = i ? 810 : 130;
        section(x, 425, head);
        text(x, 1065, title, { 'font-size': 44, 'font-weight': 700, fill: 'var(--ink)' });
        text(x, 1105, line, { style: 'font-family: Barlow', 'font-size': 27, fill: 'var(--mute)' });
      });
    },
  },
};
