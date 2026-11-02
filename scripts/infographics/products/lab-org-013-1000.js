// 1000 plain orange round stickers, 13 mm, on a roll (SKU LAB-ORG-013-1000,
// product 248 on prod). The family of lab-red-013-1000 (product 48): same
// roll, same 13 mm, same holes covered (4.5 and 5.5 mm, .22 LR). No supplier
// sheet: every fact comes from the product page on armooutdoor.fr, which
// gives this colour its own use (the page: marking each impact of a series so the next one stands out; orange reads on black and on white). Drawn with pastilles.js; the
// sticker colour is sampled from the page's close-up photo. Roll photo:
// gallery image 1, 1000 px, the anatomy markers mapped from product 48's.
const FILL = '#fe6c02';

window.PRODUCT = {
  eyebrow: '1 000 pastilles rondes orange 13 mm',

  variants: {
    roll: {
      photo: 'photos/lab-org-013-1000.jpg',
      points: [[[437, 255]], [[386, 571]], [[747, 306]], [[655, 510]]],
    },
  },

  anatomy: {
    title: 'Anatomie du rouleau',
    callouts: [
      { side: 'l', y: 470, title: 'Papier autocollant', text: 'Sur papier, carton, bois ou plastique' },
      { side: 'l', y: 790, title: 'Pastille de 13 mm', text: 'Ronde et orange, lisible sur noir et sur blanc' },
      { side: 'r', y: 450, title: 'Se décolle du rouleau', text: 'Pose au doigt, sans outil' },
      { side: 'r', y: 760, title: 'Rouleau de 1 000', text: 'Une réserve pour de nombreuses séances' },
    ],
    footer: {
      tag: 'Séries',
      html: 'Après une série, une pastille orange sur chaque impact : ces tirs <strong>restent repérables</strong>, et la série suivante <strong>se distingue aussitôt</strong>.',
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
        { head: 'DEUX SÉRIES', title: 'Comparer deux groupements', line: 'la première sous l’orange.',
          draw: ({ el }, x) => {
            const cx = x + 280, cy = 730;
            const first = [[-130, -90], [-40, -150], [-160, 40]], second = [[70, 40], [120, -10], [95, 100]];
            Infographic.targets.usedTarget(el, cx, cy, 500, Infographic.rng(248), [...first, ...second]);
            first.forEach(([dx, dy]) => Infographic.pastilles.dot(el, cx + dx, cy + dy, 43, FILL));
          } },
        { head: 'SUR NOIR COMME SUR BLANC', title: 'Lisible partout', line: 'surface réactive ou papier blanc.',
          draw: ({ el }, x) => {
            el('rect', { x, y: 600, width: 260, height: 260, fill: '#121211' });
            el('rect', { x: x + 300, y: 600, width: 260, height: 260, fill: '#fbfaf6', stroke: '#d8d4cb', 'stroke-width': 2 });
            Infographic.pastilles.dot(el, x + 130, 730, 60, FILL);
            Infographic.pastilles.dot(el, x + 430, 730, 60, FILL);
          } },
      ]);
    },
  },
};
