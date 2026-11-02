// 1000 plain vert round stickers, 13 mm, on a roll (SKU LAB-GRN-013-1000,
// product 250 on prod). The family of lab-red-013-1000 (product 48): same
// roll, same 13 mm, same holes covered (4.5 and 5.5 mm, .22 LR). No supplier
// sheet: every fact comes from the product page on armooutdoor.fr, which
// gives this colour its own use (the page: marking gear as checked and ready, with another colour for what is left to do; also repairs green zones). Drawn with pastilles.js; the
// sticker colour is sampled from the page's close-up photo. Roll photo:
// gallery image 1, 1000 px, the anatomy markers mapped from product 48's.
const FILL = '#2db03d';

window.PRODUCT = {
  eyebrow: '1 000 pastilles rondes vertes 13 mm',

  variants: {
    roll: {
      photo: 'photos/lab-grn-013-1000.jpg',
      points: [[[437, 262]], [[390, 575]], [[720, 312]], [[636, 514]]],
    },
  },

  anatomy: {
    title: 'Anatomie du rouleau',
    callouts: [
      { side: 'l', y: 470, title: 'Papier autocollant', text: 'Sur papier, carton, bois ou plastique' },
      { side: 'l', y: 790, title: 'Pastille de 13 mm', text: 'Ronde et verte, comme un feu favorable' },
      { side: 'r', y: 450, title: 'Se décolle du rouleau', text: 'Pose au doigt, sans outil' },
      { side: 'r', y: 760, title: 'Rouleau de 1 000', text: 'Une réserve pour de nombreuses séances' },
    ],
    footer: {
      tag: 'Prêt',
      html: 'Une pastille verte sur une boîte, un chargeur ou une housse : <strong>vérifié, prêt à partir</strong>. Une autre couleur pour <strong>ce qui reste à faire</strong>.',
    },
  },

  // One sticker and the coin, then the holes a 13 mm sticker covers.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À l’échelle',
      html: 'La pastille, la pièce de 1 € et les trous sont agrandis <strong>à la même échelle</strong> : la pastille couvre l’impact <strong>avec une petite marge</strong>.',
    },
    draw(kit) { Infographic.pastilles.dimensions(kit, FILL); },
  },

  // The uses this colour's page describes, two panels.
  effect: {
    title: 'Mode d’emploi',
    footer: {
      tag: 'Illustration',
      html: 'Usages reproduits <strong>à titre d’illustration</strong>, d’après la fiche produit. Pastilles en <strong>papier autocollant</strong>.',
    },
    draw(kit) {
      Infographic.pastilles.usage(kit, [
        { head: 'UN REPÈRE D’ÉTAT', title: 'Ce qui est prêt', line: 'une autre couleur pour le reste.',
          draw: ({ el, text }, x) => {
            Infographic.pastilles.ammoBox(el, x + 120, 600, 200, 140);
            Infographic.pastilles.dot(el, x + 160, 625, 26, FILL);
            Infographic.pastilles.magazine(el, x + 420, 610);
            Infographic.pastilles.dot(el, x + 425, 640, 22, FILL);
            Infographic.pastilles.rifleCase(el, x + 280, 880);
            Infographic.pastilles.dot(el, x + 400, 880, 26, Infographic.pastilles.COLOURS.rouge);
            [['prêt', x + 120, 715], ['prêt', x + 425, 740], ['à faire', x + 280, 975]].forEach(([t, tx, ty]) =>
              text(tx, ty, t, { 'text-anchor': 'middle', 'font-size': 26, 'font-weight': 700, fill: 'var(--mute)', 'letter-spacing': '.12em' }));
          } },
        { head: 'AUSSI POUR LES CIBLES', title: 'Sur une zone verte', line: 'elle masque un trou de plomb ou de .22 LR.',
          draw: (k, x) => Infographic.pastilles.repair(k, x, { kind: 'green', fill: FILL, seed: 250, hits: [[43, -43], [-50, 30], [80, -78], [-100, 50], [22, 110]], cover: [0, 1, 2, 3, 4] }) },
      ]);
    },
  },
};
