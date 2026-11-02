// 1000 plain blanc round stickers, 13 mm, on a roll (SKU LAB-WHT-013-1000,
// product 49 on prod). The family of lab-red-013-1000 (product 48): same
// roll, same 13 mm, same holes covered (4.5 and 5.5 mm, .22 LR). No supplier
// sheet: every fact comes from the product page on armooutdoor.fr, which
// gives this colour its own use (the page: masks impacts on the light parts of a target, a neutral marker off the range). Drawn with pastilles.js; the
// sticker colour is sampled from the page's close-up photo. Roll photo:
// gallery image 1, 1000 px, the anatomy markers mapped from product 48's.
const FILL = '#fefefe';

window.PRODUCT = {
  eyebrow: '1 000 pastilles rondes blanches 13 mm',

  variants: {
    roll: {
      photo: 'photos/lab-wht-013-1000.jpg',
      points: [[[444, 252]], [[394, 572]], [[753, 303]], [[662, 510]]],
    },
  },

  anatomy: {
    title: 'Anatomie du rouleau',
    callouts: [
      { side: 'l', y: 470, title: 'Papier autocollant', text: 'Sur papier, carton, bois ou plastique' },
      { side: 'l', y: 790, title: 'Pastille de 13 mm', text: 'Ronde et blanche, sans ajouter de couleur' },
      { side: 'r', y: 450, title: 'Se décolle du rouleau', text: 'Pose au doigt, sans outil' },
      { side: 'r', y: 760, title: 'Rouleau de 1 000', text: 'Une réserve pour de nombreuses séances' },
    ],
    footer: {
      tag: 'Zones claires',
      html: 'En blanc, la pastille <strong>se fond dans les anneaux clairs</strong> et les marges : la réparation <strong>passe inaperçue</strong>.',
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
        { head: 'RÉPARER', title: 'Sur les zones claires', line: 'la réparation passe inaperçue.',
          draw: (k, x) => Infographic.pastilles.repair(k, x, { kind: 'paper', fill: FILL, seed: 49, hits: [[55, -58], [-72, 40], [30, 98], [-88, -62]], cover: [0, 1, 2, 3] }) },
        { head: 'REPÉRER', title: 'Une marque discrète', line: 'sans ajouter une couleur au tri.',
          draw: ({ el }, x) => { Infographic.pastilles.ammoBox(el, x + 280, 730, 440, 290, '#3f4530'); Infographic.pastilles.dot(el, x + 410, 790, 32, FILL); } },
      ]);
    },
  },
};
