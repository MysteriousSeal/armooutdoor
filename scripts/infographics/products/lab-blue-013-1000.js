// 1000 plain bleu round stickers, 13 mm, on a roll (SKU LAB-BLUE-013-1000,
// product 246 on prod). The family of lab-red-013-1000 (product 48): same
// roll, same 13 mm, same holes covered (4.5 and 5.5 mm, .22 LR). No supplier
// sheet: every fact comes from the product page on armooutdoor.fr, which
// gives this colour its own use (the page: one colour per shooter on a shared target; also covers impacts). Drawn with pastilles.js; the
// sticker colour is sampled from the page's close-up photo. Roll photo:
// gallery image 1, 1000 px, the anatomy markers mapped from product 48's.
const FILL = '#005dd9';

window.PRODUCT = {
  eyebrow: '1 000 pastilles rondes bleues 13 mm',

  variants: {
    roll: {
      photo: 'photos/lab-blue-013-1000.jpg',
      points: [[[445, 250]], [[400, 571]], [[718, 302]], [[638, 509]]],
    },
  },

  anatomy: {
    title: 'Anatomie du rouleau',
    callouts: [
      { side: 'l', y: 470, title: 'Papier autocollant', text: 'Sur papier, carton, bois ou plastique' },
      { side: 'l', y: 790, title: 'Pastille de 13 mm', text: 'Ronde et bleue, une couleur par tireur' },
      { side: 'r', y: 450, title: 'Se décolle du rouleau', text: 'Pose au doigt, sans outil' },
      { side: 'r', y: 760, title: 'Rouleau de 1 000', text: 'Une réserve pour de nombreuses séances' },
    ],
    footer: {
      tag: 'Plusieurs tireurs',
      html: 'Une couleur par tireur : on colle une pastille <strong>à côté de ses propres trous</strong> après chaque série, et l’on sait <strong>qui a tiré où</strong>.',
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
        { head: 'DEUX TIREURS', title: 'Savoir qui a tiré où', line: 'le bleu pour l’un, une autre teinte pour l’autre.',
          draw: ({ el }, x) => {
            const cx = x + 280, cy = 730, rnd = Infographic.rng(246);
            Infographic.targets.usedTarget(el, cx, cy, 500, rnd, [[-120, -70], [-80, -120], [-150, -120], [100, 80], [140, 40], [80, 130]]);
            Infographic.pastilles.dot(el, cx - 205, cy - 30, 26, FILL);
            Infographic.pastilles.dot(el, cx + 185, cy + 150, 26, Infographic.pastilles.COLOURS.rouge);
          } },
        { head: 'ÉGALEMENT POUR RÉPARER', title: 'Masquer un impact', line: 'de plomb de 4,5 ou 5,5 mm ou de .22 LR.',
          draw: (k, x) => Infographic.pastilles.repair(k, x, { kind: 'reactive', fill: FILL, seed: 246, hits: Infographic.pastilles.REACTIVE_HITS, cover: Infographic.pastilles.BLACK_ZONES }) },
      ]);
    },
  },
};
