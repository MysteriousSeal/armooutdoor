// 300 plain fluorescent orange round stickers, 38 mm, on a roll (SKU
// LAB-ORGF-038-300, product 363 on prod). The family of the 13 mm pastilles
// (lab-red-013-1000), drawn with pastilles.js. No supplier sheet: every fact
// comes from the product page on armooutdoor.fr (the page: the largest size, swallows a whole group or a spray of small shot, an aim point on bare cardboard read without optics, a safety signal on cases and stations). The sticker
// colour is sampled from the page's close-up photo. Roll photo: gallery
// image 1, 1000 px.
const FILL = '#fd5006';

window.PRODUCT = {
  eyebrow: '300 pastilles rondes orange fluo 38 mm',

  variants: {
    roll: {
      photo: 'photos/lab-orgf-038-300.jpg',
      points: [[[300, 170]], [[420, 480]], [[790, 250]], [[740, 470]]],
    },
  },

  anatomy: {
    title: 'Anatomie du rouleau',
    callouts: [
      { side: 'l', y: 470, title: 'Papier autocollant', text: 'Sur papier, carton, bois ou plastique' },
      { side: 'l', y: 790, title: 'Pastille de 38 mm', text: 'Orange fluorescent, le plus grand format' },
      { side: 'r', y: 450, title: 'Se décolle du rouleau', text: 'Pose au doigt, sans outil' },
      { side: 'r', y: 760, title: 'Rouleau de 300', text: 'Une réserve pour de nombreuses séances' },
    ],
    footer: {
      tag: 'Signalement',
      html: 'L’orange des repères de sécurité, <strong>poussé d’un cran par le fluo</strong> : sur une mallette ou un poste, il se lit comme <strong>un signalement</strong>.',
    },
  },

  // One sticker and the coin, then the impacts the page says it covers.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À l’échelle',
      html: 'La pastille, la pièce de 1 € et les trous sont agrandis <strong>à la même échelle</strong> : la pastille couvre l’impact <strong>avec de la marge</strong>.',
    },
    draw(kit) { Infographic.pastilles.dimensions(kit, FILL, { mm: 38, count: '300', holes: [['4,5 mm', 'plomb', 4.5], ['.22 LR', 'carabine', 5.7], ['9 mm', 'pistolet', 9], ['.223 Rem', 'carabine', 5.7], ['.308', 'carabine', 7.8], ['.45 ACP', 'pistolet', 11.5]] }); },
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
        { head: 'POINT DE VISÉE', title: 'Repérée sans optique', line: 'sur un carton nu, à moyenne distance.',
          draw: ({ el }, x) => {
            Infographic.targets.cardboard(el, x + 80, 500, 400, 460, 363);
            Infographic.pastilles.dot(el, x + 280, 730, 110, FILL);
          } },
        { head: 'SIGNALER', title: 'Un repère d’attention', line: 'sur une caisse, une mallette ou un poste.',
          draw: ({ el }, x) => {
            Infographic.pastilles.ammoBox(el, x + 280, 730, 460, 320, '#3f4530');
            Infographic.pastilles.dot(el, x + 400, 790, 54, FILL);
          } },
      ]);
    },
  },
};
