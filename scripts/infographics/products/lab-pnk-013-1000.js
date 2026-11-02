// 1000 plain rose round stickers, 13 mm, on a roll (SKU LAB-PNK-013-1000,
// product 249 on prod). The family of lab-red-013-1000 (product 48): same
// roll, same 13 mm, same holes covered (4.5 and 5.5 mm, .22 LR). No supplier
// sheet: every fact comes from the product page on armooutdoor.fr, which
// gives this colour its own use (the page: identifying ammunition batches and boxes; still a repair sticker). Drawn with pastilles.js; the
// sticker colour is sampled from the page's close-up photo. Roll photo:
// gallery image 1, 1000 px, the anatomy markers mapped from product 48's.
const FILL = '#f71c77';

window.PRODUCT = {
  eyebrow: '1 000 pastilles rondes roses 13 mm',

  variants: {
    roll: {
      photo: 'photos/lab-pnk-013-1000.jpg',
      points: [[[428, 253]], [[377, 565]], [[741, 304]], [[649, 505]]],
    },
  },

  anatomy: {
    title: 'Anatomie du rouleau',
    callouts: [
      { side: 'l', y: 470, title: 'Papier autocollant', text: 'Sur papier, carton, bois ou plastique' },
      { side: 'l', y: 790, title: 'Pastille de 13 mm', text: 'Ronde et rose, à part du matériel' },
      { side: 'r', y: 450, title: 'Se décolle du rouleau', text: 'Pose au doigt, sans outil' },
      { side: 'r', y: 760, title: 'Rouleau de 1 000', text: 'Une réserve pour de nombreuses séances' },
    ],
    footer: {
      tag: 'Munitions',
      html: 'Une pastille sur une boîte suffit à <strong>distinguer un lot d’un autre</strong> : une provenance, une série de rechargement ou <strong>une arme précise</strong>.',
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
        { head: 'IDENTIFIER UN LOT', title: 'Un lot d’un coup d’œil', line: 'parmi des boîtes identiques.',
          draw: ({ el }, x) => {
            [[x + 130, 620], [x + 430, 620], [x + 130, 850], [x + 430, 850]].forEach(([cx, cy], i) => {
              Infographic.pastilles.ammoBox(el, cx, cy, 230, 160);
              if (i === 1) Infographic.pastilles.dot(el, cx + 60, cy + 25, 28, FILL);
            });
          } },
        { head: 'RÉPARER SI BESOIN', title: 'Masquer un impact', line: 'de plomb, de bille ou de .22 LR.',
          draw: (k, x) => Infographic.pastilles.repair(k, x, { kind: 'reactive', fill: FILL, seed: 249, hits: Infographic.pastilles.REACTIVE_HITS, cover: Infographic.pastilles.BLACK_ZONES }) },
      ]);
    },
  },
};
