// 500 plain black round stickers, 25 mm, on a roll (SKU
// LAB-BLK-025-500, product 345 on prod). The family of the 13 mm pastilles
// (lab-red-013-1000), drawn with pastilles.js. No supplier sheet: every fact
// comes from the product page on armooutdoor.fr (the page: one sticker covers a whole tight group on black reactive targets, where several 13 mm ones would be needed; 4.5 mm to .45 ACP). The sticker
// colour is sampled from the page's close-up photo. Roll photo: gallery
// image 1, 1000 px.
const FILL = '#000000';

window.PRODUCT = {
  eyebrow: '500 pastilles rondes noires 25 mm',

  variants: {
    roll: {
      photo: 'photos/lab-blk-025-500.jpg',
      points: [[[431, 253]], [[381, 568]], [[737, 304]], [[647, 507]]],
    },
  },

  anatomy: {
    title: 'Anatomie du rouleau',
    callouts: [
      { side: 'l', y: 470, title: 'Papier autocollant', text: 'Sur papier, carton, bois ou plastique' },
      { side: 'l', y: 790, title: 'Pastille de 25 mm', text: 'Ronde et noire, pour les fonds noirs' },
      { side: 'r', y: 450, title: 'Se décolle du rouleau', text: 'Pose au doigt, sans outil' },
      { side: 'r', y: 760, title: 'Rouleau de 500', text: 'Une réserve pour de nombreuses séances' },
    ],
    footer: {
      tag: 'Groupement',
      html: 'Un groupement serré tient souvent dans 25 mm : <strong>une seule pastille</strong> le recouvre et la cible <strong>repart vierge</strong>.',
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
        { head: 'UN GROUPEMENT EFFACÉ', title: 'En un seul geste', line: 'là où il faudrait plusieurs 13 mm.',
          draw: (k, x) => Infographic.pastilles.groupRepair(k, x, FILL) },
        { head: 'LE COMPLÉMENT DE LA 13 MM', title: 'Deux diamètres noirs', line: 'la 25 mm pour les groupements.',
          draw: ({ el, text }, x) => {
            // Our 76 mm target at 500 px: 13 mm is r 43 on it, 25 mm r 82.
            const cx = x + 280, cy = 730;
            const group = [[-95, -95], [-75, -115], [-115, -75], [-70, -85], [-100, -65]], lone = [[120, 110]];
            Infographic.targets.usedTarget(el, cx, cy, 500, Infographic.rng(3450), [...group, ...lone]);
            Infographic.pastilles.dot(el, cx - 90, cy - 90, 82, FILL);
            Infographic.pastilles.dot(el, cx + 120, cy + 110, 43, FILL);
            text(cx - 90, cy - 190, '25 mm', { 'text-anchor': 'middle', 'font-size': 30, 'font-weight': 700, fill: 'var(--ink)', stroke: '#fff', 'stroke-width': 7, 'paint-order': 'stroke' });
            text(cx + 120, cy + 190, '13 mm', { 'text-anchor': 'middle', 'font-size': 30, 'font-weight': 700, fill: 'var(--ink)', stroke: '#fff', 'stroke-width': 7, 'paint-order': 'stroke' });
          } },
      ]);
    },
  },
};
