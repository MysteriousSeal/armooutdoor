// 1000 plain white round stickers, 19 mm, on a roll (SKU
// LAB-WHT-019-1000, product 107 on prod). The family of the 13 mm pastilles
// (lab-red-013-1000), drawn with pastilles.js. No supplier sheet: every fact
// comes from the product page on armooutdoor.fr (the page: the intermediate size, covers 4.5 mm pellets up to .45 ACP; white stays discreet on light targets). The sticker
// colour is sampled from the page's close-up photo. Roll photo: gallery
// image 1, 1000 px.
const FILL = '#fefefe';

window.PRODUCT = {
  eyebrow: '1 000 pastilles rondes blanches 19 mm',

  variants: {
    roll: {
      photo: 'photos/lab-wht-019-1000.jpg',
      points: [[[444, 252]], [[394, 572]], [[753, 303]], [[662, 510]]],
    },
  },

  anatomy: {
    title: 'Anatomie du rouleau',
    callouts: [
      { side: 'l', y: 470, title: 'Papier autocollant', text: 'Sur papier, carton, bois ou plastique' },
      { side: 'l', y: 790, title: 'Pastille de 19 mm', text: 'Ronde et blanche, pour les cibles claires' },
      { side: 'r', y: 450, title: 'Se décolle du rouleau', text: 'Pose au doigt, sans outil' },
      { side: 'r', y: 760, title: 'Rouleau de 1 000', text: 'Une réserve pour de nombreuses séances' },
    ],
    footer: {
      tag: 'Plomb et balle',
      html: 'Du plomb de 4,5 mm au <strong>.45 ACP</strong> : un seul rouleau <strong>répare les cibles de plusieurs armes</strong>.',
    },
  },

  // One sticker and the coin, then the impacts the page says it covers.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À l’échelle',
      html: 'La pastille, la pièce de 1 € et les trous sont agrandis <strong>à la même échelle</strong> : la pastille couvre l’impact <strong>avec de la marge</strong>.',
    },
    draw(kit) { Infographic.pastilles.dimensions(kit, FILL, { mm: 19, count: '1 000', holes: [['4,5 mm', 'plomb', 4.5], ['.22 LR', 'carabine', 5.7], ['9 mm', 'pistolet', 9], ['.223 Rem', 'carabine', 5.7], ['.308', 'carabine', 7.8], ['.45 ACP', 'pistolet', 11.5]] }); },
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
        { head: 'SUR LES ZONES CLAIRES', title: 'Une réparation discrète', line: 'sans créer de faux repère.',
          draw: (k, x) => Infographic.pastilles.repair(k, x, { kind: 'paper', fill: FILL, r: 31, seed: 107, hits: [[55, -58], [-72, 40], [30, 98], [-88, -62]], cover: [0, 1, 2, 3] }) },
        { head: 'ENTRE 13 ET 25 MM', title: 'Le bon compromis', line: 'quand on tire au plomb et à balle.',
          draw: ({ el, text }, x) => {
            // The three sizes of the range at the same scale, 19 mm ringed.
            [[13, x + 80], [19, x + 250], [25, x + 450]].forEach(([mm, cx]) => {
              const r = mm * 3.6;
              if (mm === 19) el('circle', { cx, cy: 730, r: r + 16, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 5 });
              Infographic.pastilles.dot(el, cx, 730, r, mm === 19 ? FILL : '#e6e3dc');
              text(cx, 730 + 45 * 1.0 + 75, `${mm} mm`, { 'text-anchor': 'middle', 'font-size': 32, 'font-weight': 700, fill: mm === 19 ? 'var(--ink)' : 'var(--mute)' });
            });
          } },
      ]);
    },
  },
};
