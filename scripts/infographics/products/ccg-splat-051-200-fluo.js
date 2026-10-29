// 200 square reactive targets, 51 mm, 5 aim points (SKU CCG-SPLAT-051-200-FLUO, product 51 on prod).
// No supplier sheet: every fact comes from the product page on armooutdoor.fr.
// Square measured on the flat gallery photo: centre 498,498, half side 435.
window.PRODUCT = {
  eyebrow: '200 cibles carrées réactives 5 points de visée',

  variants: {
    five: {
      photo: 'photos/ccg-splat-051-200-fluo.jpg',
      points: [[[545, 450]], [[172, 620]], [[770, 220], [770, 770]], [[924, 600]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
    callouts: [
      { side: 'l', y: 430, title: 'Disque central', text: 'Barré d’une croix noire, visée principale' },
      { side: 'l', y: 760, title: 'Trame en pointillés', text: 'Sans chiffres, pour juger la dispersion' },
      { side: 'r', y: 500, title: 'Quatre pastilles', text: 'Une série sur chacune' },
      { side: 'r', y: 900, title: 'Liseré jaune', text: 'Délimite le bord de la cible' },
    ],
    footer: {
      tag: '5 visées',
      html: 'Une série sur le <strong>disque central</strong>, puis une sur <strong>chaque pastille</strong> : cinq groupements par cible, sans qu’ils se brouillent.',
    },
  },

  // The photo cropped to the square and drawn at true size: the printed
  // legends stay exactly as they are on the target.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À l’échelle',
      html: 'La cible et la pièce de 1 € sont dessinées <strong>à la même échelle</strong>. À tirer de préférence entre <strong>5 et 25 m</strong>.',
    },
    draw(kit, P, V) {
      Infographic.targets.squareToScale(kit, V.photo, {
        centre: [498, 498], half: 435, mm: 51, K: 7.9,
        facts: [['200', 'cibles'], ['51 mm', 'le plus petit format carré'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // One shot on each of the five aim points, as the page describes a series per point.
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [498, 498], radius: 435, shape: 'square', seed: 51,
        hits: [[-170, -176], [176, -170], [-178, 180], [168, 174], [10, -12]],
        afterLabel: 'APRÈS CINQ SÉRIES',
        caption: ['Une gerbe jaune autour de chaque trou : sur un repère', 'aussi petit, on voit tout de suite un tir au centre.'],
      });
    },
  },
};
