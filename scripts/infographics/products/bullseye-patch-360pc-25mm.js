// 360 bullseye stickers, 25 mm, red and black (SKU BULLSEYE-PATCH-360PC-25MM,
// product 156 on prod): repair patches that double as aim points. NOT
// reactive: a shot leaves a plain hole, so no image shows a splatter on them
// and there is no impact image. No supplier sheet: every fact comes from the
// product page on armooutdoor.fr (sheets of 36, 6 x 6 visible on the photo).
// Sizes: the user confirmed (2026-11-02) the 25 mm stickers; on the photo
// that makes the sheet about 15.3 cm, not the page's 18 cm, so no sheet size
// is printed on any image until the page is settled.
// Sheet photo (the black sheet cropped from gallery image 4 and padded,
// 1000 px): sheet x and y 81 to 918; sticker centres at 150.75 + 139.5 i,
// red disc r68, black ring r40, diamond about r16. Close-up
// (photos/<sku>-sticker.jpg): disc centre 500,500, r483.
const SHEET = { centre: [499.5, 499.5], half: 418.5 };
const STICKER = 'photos/bullseye-patch-360pc-25mm-sticker.jpg';

window.PRODUCT = {
  eyebrow: '360 pastilles bullseye 25 mm rouge et noir',

  variants: {
    patches: {
      photo: 'photos/bullseye-patch-360pc-25mm.jpg',
      points: [[[93, 151]], [[110, 430]], [[121, 879]], [[903, 151]], [[848, 430]], [[878, 739]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la planche',
    photoFilter: 'brightness(1.03)',
    callouts: [
      { side: 'l', y: 400, title: 'Pastille de 25 mm', text: 'Disque rouge autocollant' },
      { side: 'l', y: 650, title: 'Anneau noir', text: 'Autour du point de visée' },
      { side: 'l', y: 960, title: '36 par planche', text: 'Six rangées de six, à détacher' },
      { side: 'r', y: 400, title: 'Croix de visée', text: 'Noire, interrompue au centre' },
      { side: 'r', y: 650, title: 'Losange noir', text: 'Le point de visée' },
      { side: 'r', y: 930, title: 'Non réactive', text: 'Un tir y laisse un simple trou' },
    ],
    footer: {
      tag: 'Réparer',
      html: 'Posée sur un trou, la pastille <strong>efface l’impact</strong> : une cible saturée repart pour <strong>de nouvelles séries</strong>.',
    },
  },

  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À l’échelle',
      html: 'La pastille et la pièce de 1 € sont agrandies <strong>à la même échelle</strong>. Pastilles <strong>non réactives</strong>.',
    },
    draw(kit, P, V) {
      Infographic.targets.sheetDimensions(kit, V.photo, {
        sheet: SHEET, sticker: STICKER, mm: 25, label: '25 mm',
        sheetLine: '36 pastilles, 6 × 6', heading: 'UNE PASTILLE ET LA PIÈCE', facts: [['360', 'pastilles'], ['10', 'planches de 36']],
      });
    },
  },

  // Loose stickers on a 55 x 55 cm board, the size of a C50 card, at true
  // size: a 4 x 4 grid of aim points, the page's second use.
  mounted: [
    { id: 'c50', photo: STICKER, centre: [500, 500], half: 478, cm: 2.5, round: true, panel: [55, 55], grid: [4, 4] },
  ],

  // No text (stack.html): a pile of sheets (the pack holds 10 sheets of
  // 36), and one sheet on its own.
  stack: [
    { ...SHEET, cm: 15.3, count: 7 },
    { id: 'single', ...SHEET, cm: 15.3, count: 1 },
  ],

  // The page's two uses: covering an impact on a used target, and a free
  // aim point on bare cardboard. Plain holes only: they are not reactive.
  effect: {
    title: 'Mode d’emploi',
    footer: {
      tag: 'Illustration',
      html: 'Usages reproduits <strong>à titre d’illustration</strong>, d’après la fiche produit. Pastilles <strong>non réactives</strong> : un tir y laisse un simple trou.',
    },
    draw(kit, V) {
      const { el, text, section } = kit, T = Infographic.targets, rnd = Infographic.rng(156);
      const panels = [
        ['RÉPARER', 'Masquer un impact', 'la cible retrouve une surface lisible.'],
        ['VISER', 'Créer un point de visée', 'sur un carton nu ou une planche de fond.'],
      ];
      // Left: our 76 mm target already shot; two patches (true relative
      // size) cover two of its impacts.
      const hits = [[40, -30], [95, -125], [140, 60], [-60, 150], [-165, 40], [40, 175], [180, -150]];
      T.usedTarget(el, 410, 730, 560, rnd, hits);
      T.sticker(el, STICKER, 410 + 95, 730 - 125, 92);
      T.sticker(el, STICKER, 410 - 60, 730 + 150, 92);
      // Right: five patches on bare cardboard, two already shot (plain holes).
      T.cardboard(el, 810, 450, 560, 560, 156);
      [[950, 590], [1230, 590], [1090, 730], [950, 870], [1230, 870]].forEach(([cx, cy], i) => {
        T.sticker(el, STICKER, cx, cy, 52);
        if (i === 0 || i === 2) {
          el('circle', { cx: cx + (rnd() - 0.5) * 10, cy: cy + (rnd() - 0.5) * 10, r: 5.5, fill: '#141414', stroke: 'rgba(255,255,255,.7)', 'stroke-width': 1.5 });
        }
      });
      panels.forEach(([head, title, line], i) => {
        const x = i ? 810 : 130;
        section(x, 425, head);
        text(x, 1065, title, { 'font-size': 44, 'font-weight': 700, fill: 'var(--ink)' });
        text(x, 1105, line, { style: 'font-family: Barlow', 'font-size': 27, fill: 'var(--mute)' });
      });
    },
  },
};
