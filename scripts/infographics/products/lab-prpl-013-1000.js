// 1000 plain violet round stickers, 13 mm, on a roll (SKU LAB-PRPL-013-1000,
// product 251 on prod). The family of lab-red-013-1000 (product 48): same
// roll, same 13 mm, same holes covered (4.5 and 5.5 mm, .22 LR). No supplier
// sheet: every fact comes from the product page on armooutdoor.fr, which
// gives this colour its own use (the page: marking target stands and shooting stations; repairs small impacts). Drawn with pastilles.js; the
// sticker colour is sampled from the page's close-up photo. Roll photo:
// gallery image 1, 1000 px, the anatomy markers mapped from product 48's.
const FILL = '#893bb4';

window.PRODUCT = {
  eyebrow: '1 000 pastilles rondes violettes 13 mm',

  variants: {
    roll: {
      photo: 'photos/lab-prpl-013-1000.jpg',
      points: [[[463, 263]], [[424, 550]], [[703, 309]], [[632, 495]]],
    },
  },

  anatomy: {
    title: 'Anatomie du rouleau',
    callouts: [
      { side: 'l', y: 470, title: 'Papier autocollant', text: 'Sur papier, carton, bois ou plastique' },
      { side: 'l', y: 790, title: 'Pastille de 13 mm', text: 'Ronde et violette, rare dans le décor' },
      { side: 'r', y: 450, title: 'Se décolle du rouleau', text: 'Pose au doigt, sans outil' },
      { side: 'r', y: 760, title: 'Rouleau de 1 000', text: 'Une réserve pour de nombreuses séances' },
    ],
    footer: {
      tag: 'Pas de tir',
      html: 'Une pastille sur un porte-cible, un piquet ou un poste : on <strong>retrouve la même installation</strong> d’une séance à l’autre.',
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
        { head: 'REPÉRER UN POSTE', title: 'La même installation', line: 'd’une séance à l’autre.',
          draw: ({ el }, x) => {
            Infographic.pastilles.stand(el, x + 280, 700);
            Infographic.pastilles.dot(el, x + 130, 520, 22, FILL);
          } },
        { head: 'RÉPARER', title: 'Les petits impacts', line: 'de 4,5 ou 5,5 mm et de .22 LR.',
          draw: (k, x) => Infographic.pastilles.repair(k, x, { kind: 'reactive', fill: FILL, seed: 251, hits: Infographic.pastilles.REACTIVE_HITS, cover: Infographic.pastilles.BLACK_ZONES }) },
      ]);
    },
  },
};
