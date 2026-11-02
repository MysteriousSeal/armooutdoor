// 1000 plain jaune round stickers, 13 mm, on a roll (SKU LAB-YLW-013-1000,
// product 247 on prod). The family of lab-red-013-1000 (product 48): same
// roll, same 13 mm, same holes covered (4.5 and 5.5 mm, .22 LR). No supplier
// sheet: every fact comes from the product page on armooutdoor.fr, which
// gives this colour its own use (the page: a bright aim point on a dark support, a fresh aim point on a used target, also covers impacts). Drawn with pastilles.js; the
// sticker colour is sampled from the page's close-up photo. Roll photo:
// gallery image 1, 1000 px, the anatomy markers mapped from product 48's.
const FILL = '#fed504';

window.PRODUCT = {
  eyebrow: '1 000 pastilles rondes jaunes 13 mm',

  variants: {
    roll: {
      photo: 'photos/lab-ylw-013-1000.jpg',
      points: [[[443, 254]], [[400, 573]], [[707, 306]], [[629, 511]]],
    },
  },

  anatomy: {
    title: 'Anatomie du rouleau',
    callouts: [
      { side: 'l', y: 470, title: 'Papier autocollant', text: 'Sur papier, carton, bois ou plastique' },
      { side: 'l', y: 790, title: 'Pastille de 13 mm', text: 'Ronde et jaune, la plus lumineuse' },
      { side: 'r', y: 450, title: 'Se décolle du rouleau', text: 'Pose au doigt, sans outil' },
      { side: 'r', y: 760, title: 'Rouleau de 1 000', text: 'Une réserve pour de nombreuses séances' },
    ],
    footer: {
      tag: 'Point de visée',
      html: 'Sur un carton brun ou une planche noire, une pastille jaune crée <strong>un point de visée qui ressort</strong>, et à 13 mm elle <strong>oblige à viser juste</strong>.',
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
        { head: 'SUR UN SUPPORT SOMBRE', title: 'Une série de mires', line: 'sans imprimer de cible.',
          draw: ({ el }, x) => {
            Infographic.pastilles.darkBoard(el, x, 450, 560, 560);
            const g = el('g', {}), rnd = Infographic.rng(247);
            for (let i = 0; i < 10; i++) {
              const cx = x + 80 + (i % 5) * 100, cy = 640 + Math.floor(i / 5) * 180;
              Infographic.pastilles.dot(el, cx, cy, 30, FILL);
              if (i < 4) Infographic.pastilles.hole(el, cx + (rnd() - 0.5) * 12, cy + (rnd() - 0.5) * 12, 6, g);
            }
          } },
        { head: 'SUR UNE CIBLE UTILISÉE', title: 'Un nouveau départ', line: 'sans changer de support.',
          draw: ({ el }, x) => {
            Infographic.targets.usedTarget(el, x + 280, 730, 560, Infographic.rng(2470), [[40, -30], [95, -125], [140, 60], [-60, 150], [-165, 40], [40, 175], [180, -150]]);
            Infographic.pastilles.dot(el, x + 140, 590, 48, FILL);
          } },
      ]);
    },
  },
};
