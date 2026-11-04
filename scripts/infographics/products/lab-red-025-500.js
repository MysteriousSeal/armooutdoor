// 500 plain red round stickers, 25 mm, on a roll (SKU
// LAB-RED-025-500, product 109 on prod). The family of the 13 mm pastilles
// (lab-red-013-1000), drawn with pastilles.js. No supplier sheet: every fact
// comes from the product page on armooutdoor.fr (the page: remaking the whole red centre of a 20 cm or larger target, covers 4.5 mm to .45 ACP, a red aim point on a light support). The sticker
// colour is sampled from the page's close-up photo. Roll photo: gallery
// image 1, 1000 px.
const FILL = '#f2030b';

window.PRODUCT = {
  eyebrow: '500 pastilles rondes rouges 25 mm',

  variants: {
    roll: {
      photo: 'photos/lab-red-025-500.jpg',
      points: [[[430, 250]], [[380, 560]], [[735, 300]], [[645, 500]]],
    },
  },

  anatomy: {
    title: 'Anatomie du rouleau',
    callouts: [
      { side: 'l', y: 470, title: 'Papier autocollant', text: 'Sur papier, carton, bois ou plastique' },
      { side: 'l', y: 790, title: 'Pastille de 25 mm', text: 'Ronde et rouge, la mouche des grandes cibles' },
      { side: 'r', y: 450, title: 'Se décolle du rouleau', text: 'Pose au doigt, sans outil' },
      { side: 'r', y: 760, title: 'Rouleau de 500', text: 'Une réserve pour de nombreuses séances' },
    ],
    footer: {
      tag: 'Grandes cibles',
      html: 'Sur une cible de <strong>20 cm ou plus</strong>, une pastille de 25 mm recouvre la mouche en entier et <strong>redonne un point de visée complet</strong>.',
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
        { head: 'REFAIRE LE CENTRE', title: 'Une mouche neuve', line: 'sur une cible de 20 cm ou plus.',
          draw: (k, x) => Infographic.pastilles.bigRepair(k, x, FILL) },
        { head: 'UN REPÈRE ROUGE', title: 'Lisible à distance', line: 'collée seule sur un support clair.',
          draw: ({ el }, x) => {
            Infographic.targets.cardboard(el, x + 80, 500, 400, 460, 109);
            Infographic.pastilles.dot(el, x + 280, 730, 90, FILL);
          } },
      ]);
    },
  },
};
