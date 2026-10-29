// 200 square reactive targets, 76 mm, 5 red diamonds (SKU CCS-5DIA-076-200-FLUO, product 92 on prod).
// No supplier sheet: every fact comes from the product page on armooutdoor.fr.
// Square measured on the flat gallery photo: centre 500,500, half side 446.
window.PRODUCT = {
  eyebrow: '200 cibles carrées réactives 5 losanges rouges',

  variants: {
    diamonds: {
      photo: 'photos/ccs-5dia-076-200-fluo.jpg',
      points: [[[200, 200], [200, 800]], [[330, 905]], [[800, 120]], [[650, 650]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
    callouts: [
      { side: 'l', y: 450, title: 'Cinq losanges', text: 'Un au centre, quatre vers les coins' },
      { side: 'l', y: 1000, title: 'Échelle chiffrée', text: '1 · 0.5 · 0 · 0.5 · 1 le long du bord' },
      { side: 'r', y: 360, title: 'Quatre pointes', text: 'Pour caler la visée sur un repère précis' },
      { side: 'r', y: 760, title: 'Grille jaune', text: 'Situe le groupement par rapport au centre' },
    ],
    footer: {
      tag: '5 visées',
      html: 'Une <strong>série par losange</strong> : cinq groupements par cible, et une <strong>échelle chiffrée</strong> pour situer chacun.',
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
        centre: [500, 500], half: 446, mm: 76, K: 7.9,
        facts: [['200', 'cibles'], ['Rouleau', 'conditionnement'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // One shot on each diamond, a series per aim point.
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [500, 500], radius: 446, shape: 'square', seed: 92,
        hits: [[-180, -196], [196, -180], [-196, 180], [180, 196], [12, -14]],
        afterLabel: 'APRÈS CINQ SÉRIES',
        caption: ['Le jaune tranche sur le rouge et le noir : on voit', 'si le tir a touché le losange ou la case voisine.'],
      });
    },
  },
};
