// 500 plain yellow round stickers, 25 mm, on a roll (SKU
// LAB-YLW-025-500, product 108 on prod). The family of the 13 mm pastilles
// (lab-red-013-1000), drawn with pastilles.js. No supplier sheet: every fact
// comes from the product page on armooutdoor.fr (the page: big enough to be a target on its own, several make a drill; also covers impacts from 4.5 mm to .45 ACP). The sticker
// colour is sampled from the page's close-up photo. Roll photo: gallery
// image 1, 1000 px.
const FILL = '#fed504';

window.PRODUCT = {
  eyebrow: '500 pastilles rondes jaunes 25 mm',

  variants: {
    roll: {
      photo: 'photos/lab-ylw-025-500.jpg',
      points: [[[431, 254]], [[381, 568]], [[735, 305]], [[645, 507]]],
    },
  },

  anatomy: {
    title: 'Anatomie du rouleau',
    callouts: [
      { side: 'l', y: 470, title: 'Papier autocollant', text: 'Sur papier, carton, bois ou plastique' },
      { side: 'l', y: 790, title: 'Pastille de 25 mm', text: 'Ronde et jaune, une cible à part entière' },
      { side: 'r', y: 450, title: 'Se décolle du rouleau', text: 'Pose au doigt, sans outil' },
      { side: 'r', y: 760, title: 'Rouleau de 500', text: 'Une réserve pour de nombreuses séances' },
    ],
    footer: {
      tag: 'Point de visée',
      html: 'Collée sur un carton ou une planche, la pastille de 25 mm devient <strong>une mire complète</strong>, visible <strong>à distance</strong> sur un fond sombre.',
    },
  },

  // One sticker and the coin, then the impacts the page says it covers.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À l’échelle',
      html: 'La pastille, la pièce de 1 € et les trous sont agrandis <strong>à la même échelle</strong> : la pastille couvre l’impact <strong>avec de la marge</strong>.',
    },
    draw(kit) { Infographic.pastilles.dimensions(kit, FILL, { mm: 25, count: '500', holes: [['4,5 mm', 'plomb', 4.5], ['.22 LR', 'carabine', 5.7], ['9 mm', 'pistolet', 9], ['.223 Rem', 'carabine', 5.7], ['.308', 'carabine', 7.8], ['.45 ACP', 'pistolet', 11.5]] }); },
  },

  // The uses this product's page describes, two panels.
  effect: {
    title: 'Mode d’emploi',
    footer: {
      tag: 'Illustration',
      html: 'Usages reproduits <strong>à titre d’illustration</strong>, d’après la fiche produit. Pastilles en <strong>papier autocollant</strong>.',
    },
    draw(kit) {
      Infographic.pastilles.usage(kit, [
        { head: 'UNE CIBLE EN UN GESTE', title: 'Une mire complète', line: 'sur un carton ou une planche sombre.',
          draw: ({ el }, x) => {
            Infographic.pastilles.darkBoard(el, x + 80, 500, 400, 460);
            Infographic.pastilles.dot(el, x + 280, 730, 90, FILL);
            Infographic.pastilles.hole(el, x + 268, 718, 8);
          } },
        { head: 'UN EXERCICE SUR MESURE', title: 'En ligne, en carré, en hauteur', line: 'des transitions sans cible imprimée.',
          draw: ({ el }, x) => {
            Infographic.pastilles.darkBoard(el, x, 450, 560, 560);
            [[90, 120], [280, 120], [470, 120], [90, 440], [470, 440], [280, 300], [185, 300], [375, 300]].forEach(([dx, dy]) => Infographic.pastilles.dot(el, x + dx, 450 + dy, 38, FILL));
          } },
      ]);
    },
  },
};
