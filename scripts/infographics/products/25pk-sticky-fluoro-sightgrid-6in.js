// 25 reactive shooting boards, 15.24 cm, 5 diamonds on a numbered grid (SKU
// 25PK-STICKY-FLUORO-SIGHTGRID-6IN, product 118 on prod). No supplier sheet:
// every fact comes from the product page on armooutdoor.fr. Measured on the
// flat gallery photo: square centre 499,500, half side 404; corner diamonds
// at 305/695 on both axes; centre diamond around 499,500, inside a red
// diamond outline whose tips touch the four red bars; numbers 3, 2, 1 on the
// four edges, one band about 97 px.
window.PRODUCT = {
  eyebrow: '25 planches de tir 5 losanges grille graduée',

  variants: {
    diamonds: {
      photo: 'photos/25pk-sticky-fluoro-sightgrid-6in.jpg',
      points: [[[240, 305]], [[200, 500]], [[140, 740]], [[565, 500]], [[750, 560]], [[790, 790]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la planche',
    callouts: [
      { side: 'l', y: 400, title: 'Quatre losanges d’angle', text: 'Cernés de jaune-vert, croix noire sur rouge' },
      { side: 'l', y: 660, title: 'Barres rouges', text: 'Des bords vers la mire centrale' },
      { side: 'l', y: 920, title: 'Grille chiffrée', text: '3, 2, 1 de part et d’autre du centre' },
      { side: 'r', y: 620, title: 'Losange central', text: 'Cerné de rouge, mouche rouge et croix' },
      { side: 'r', y: 830, title: 'Fond noir réactif', text: 'Gerbe jaune fluorescent à l’impact' },
      { side: 'r', y: 1040, title: 'Quadrillage jaune', text: 'Découpé en bandes numérotées' },
    ],
    footer: {
      tag: 'Réglage',
      html: 'La position d’un groupement se note <strong>en bandes, de 1 à 3</strong>, de chaque côté du centre, plutôt qu’à l’estime.',
    },
  },

  // The photo cropped to the square and drawn at true size: the printed
  // numbers stay exactly as they are on the board.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À l’échelle',
      html: 'La planche et la pièce de 1 € sont dessinées <strong>à la même échelle</strong>. À tirer de préférence entre <strong>10 et 25 m</strong>.',
    },
    draw(kit, P, V) {
      Infographic.targets.squareToScale(kit, V.photo, {
        centre: [499, 500], half: 404, mm: 152.4, K: 4, heading: 'UNE PLANCHE',
        facts: [['25', 'planches'], ['5 losanges', 'par planche'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // The board on a 1 m panel (mounted.html), at true size.
  mounted: { centre: [499, 500], half: 404, cm: 15.24 },

  // Five aim points per board: one hit on each diamond.
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [499, 500], radius: 404, shape: 'square', seed: 118,
        // Panel scale 0.69: corner diamonds at about ±135 panel px.
        hits: [[-130, -138], [138, -132], [-136, 132], [132, 138], [6, -6]],
        afterLabel: 'APRÈS CINQ SÉRIES',
        caption: ['Une gerbe jaune autour de chaque trou,', 'repérable sans quitter le pas de tir.'],
      });
    },
  },
};
