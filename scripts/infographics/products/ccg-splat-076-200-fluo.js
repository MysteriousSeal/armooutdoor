// 200 square reactive targets, 76 mm, inch grid (SKU CCG-SPLAT-076-200-FLUO, product 52 on prod).
// No supplier sheet: every fact comes from the product page on armooutdoor.fr.
// Square measured on the flat gallery photo: centre 500,500, half side 410.
window.PRODUCT = {
  eyebrow: '200 cibles carrées réactives grille en pouces',

  variants: {
    grid: {
      photo: 'photos/ccg-splat-076-200-fluo.jpg',
      points: [[[150, 330], [430, 150]], [[338, 665]], [[530, 470]], [[750, 500], [500, 750]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
    callouts: [
      { side: 'l', y: 400, title: 'Graduations en pouces', text: '1/3", 2/3", 1" et 1-1/3" sur les quatre bords' },
      { side: 'l', y: 760, title: 'Quadrillage', text: 'Chaque case mesure un tiers de pouce' },
      { side: 'r', y: 430, title: 'Point de visée', text: 'Disque rouge cerné d’un anneau noir' },
      { side: 'r', y: 700, title: 'Axes centraux', text: 'Partagent la cible en quatre' },
    ],
    footer: {
      tag: 'Réglage',
      html: 'Comptez les cases entre le groupement et le centre : chacune vaut <strong>1/3 de pouce</strong>, à reporter en <strong>clics</strong> selon la notice de l’optique.',
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
        centre: [500, 500], half: 410, mm: 76, K: 7.9,
        facts: [['200', 'cibles'], ['Rouleau', 'conditionnement'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // A group off to the upper right, to be measured in squares from the centre.
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [500, 500], radius: 410, shape: 'square', seed: 52,
        hits: [[55, -45], [115, -60], [80, -5], [130, -110], [60, -115]],
        afterLabel: 'APRÈS UNE SÉRIE',
        caption: ['Le groupement se lit sur la grille : comptez les cases', 'jusqu’au centre, chacune vaut un tiers de pouce.'],
      });
    },
  },
};
