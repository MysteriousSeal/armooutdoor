// 100 square reactive targets, 10.16 cm, sighting-in grid (SKU TGT-REACT-SQ-100-4IN-GRID, product 145 on prod).
// No supplier sheet: every fact comes from the product page on armooutdoor.fr.
// Square measured on the flat gallery photo: centre 500,500, half side 374.
window.PRODUCT = {
  eyebrow: '100 cibles carrées réactives grille de réglage',

  variants: {
    grid: {
      photo: 'photos/tgt-react-sq-100-4in-grid.jpg',
      points: [[[152, 152]], [[330, 670]], [[700, 498], [500, 300]], [[530, 520]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
    callouts: [
      { side: 'l', y: 400, title: 'Légende de correction', text: '4R à 4L, 4D à 4U, C au centre' },
      { side: 'l', y: 760, title: 'Quadrillage fin', text: 'Jaune-vert fluorescent sur fond noir' },
      { side: 'r', y: 450, title: 'Axes rouges', text: 'Se croisent sur le disque de visée' },
      { side: 'r', y: 800, title: 'Disque de visée', text: 'Rouge, au croisement des axes' },
    ],
    footer: {
      tag: 'Correction',
      html: 'Le repère le plus proche du groupement indique le <strong>sens</strong> de la correction, le chiffre le nombre de <strong>cases</strong> à rattraper.',
    },
  },

  // The photo cropped to the square and drawn at true size: the printed
  // legends stay exactly as they are on the target.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À l’échelle',
      html: 'La cible et la pièce de 1 € sont dessinées <strong>à la même échelle</strong>. À tirer de préférence entre <strong>10 et 25 m</strong>.',
    },
    draw(kit, P, V) {
      Infographic.targets.squareToScale(kit, V.photo, {
        centre: [500, 500], half: 374, mm: 101.6, K: 6,
        facts: [['100', 'cibles'], ['Rouleau', 'conditionnement'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // The page’s own example: a group high and to the left, read as D and R.
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [500, 500], radius: 374, shape: 'square', seed: 145,
        hits: [[-80, -70], [-145, -115], [-75, -140], [-135, -50], [-190, -95]],
        afterLabel: 'GROUPÉ EN HAUT À GAUCHE',
        caption: ['La bordure se lit D et R : la correction se fait', 'vers le bas et vers la droite.'],
      });
    },
  },
};
