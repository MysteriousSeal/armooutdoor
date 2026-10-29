// 25 reactive shooting boards, 15 cm, sighting-in grid and protractor (SKU
// CGL-SPLAT-150-25-FLUO, product 67 on prod). No supplier sheet: every fact
// comes from the product page on armooutdoor.fr. Square measured on the flat
// gallery photo: centre 498,500, half side 422; protractor ring r140 around
// the bull; correction legend top and right, inch ruler left and bottom.
window.PRODUCT = {
  eyebrow: '25 planches de tir grille de réglage et rapporteur',

  variants: {
    board: {
      photo: 'photos/cgl-splat-150-25-fluo.jpg',
      points: [[[498, 200], [230, 500]], [[110, 650], [640, 880]], [[640, 120], [880, 230]], [[598, 400]], [[498, 498]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la planche',
    callouts: [
      { side: 'l', y: 420, title: 'Réticule rouge', text: 'Grand cercle et quatre branches autour de la mouche' },
      { side: 'l', y: 760, title: 'Règle en pouces', text: 'À gauche et en bas, de 0 au centre à 2 1/2, par quarts' },
      { side: 'r', y: 380, title: 'Légende de correction', text: '5R à 5L en haut, 5D à 5U à droite, C au centre' },
      { side: 'r', y: 600, title: 'Rapporteur', text: 'Cercle gradué de 0 à 180 degrés' },
      { side: 'r', y: 820, title: 'Mouche rouge', text: 'Cernée d’un fin anneau, le point de visée' },
    ],
    footer: {
      tag: '3 outils',
      html: 'La <strong>légende</strong> donne le sens de la correction, la <strong>règle</strong> l’étendue du groupement, le <strong>rapporteur</strong> son angle.',
    },
  },

  // The photo cropped to the square and drawn at true size: the printed
  // legends, ruler and protractor stay exactly as they are on the board.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À l’échelle',
      html: 'La planche et la pièce de 1 € sont dessinées <strong>à la même échelle</strong>. Pour un réglage de <strong>10 à 50 m</strong>.',
    },
    draw(kit, P, V) {
      Infographic.targets.squareToScale(kit, V.photo, {
        centre: [498, 500], half: 422, mm: 150, K: 4, label: '15 cm', heading: 'UNE PLANCHE',
        facts: [['25', 'planches'], ['Une cible', 'centrale par planche'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // The case the protractor is for: impacts stretched along a diagonal.
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [498, 500], radius: 422, shape: 'square', seed: 67,
        hits: [[-130, 100], [-72, 56], [-15, 12], [42, -32], [100, -78]],
        afterLabel: 'IMPACTS EN DIAGONALE',
        caption: ['Relevez l’angle du groupement au rapporteur,', 'et mesurez son étendue à la règle.'],
      });
    },
  },
};
