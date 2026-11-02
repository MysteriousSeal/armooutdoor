// 20 reactive shooting boards, 15.24 cm, 5 diamonds on a numbered grid (SKU
// 20PK-STICKY-FLUORO-SIGHTGRID-6IN, product 349 on prod), the same board as
// 25pk-sticky-fluoro-sightgrid-6in (product 118) in a pack of 20. No supplier
// sheet: every fact comes from the product page on armooutdoor.fr. Measured
// on its own flat gallery photo (sharper): square centre 500,500, half side
// 405; corner diamonds at 303/693 across, 303/688 down; centre diamond around 500,500, inside a red
// diamond outline whose tips touch the four red bars; numbers 3, 2, 1 on the
// four edges, one band about 97 px.
window.PRODUCT = {
  eyebrow: '20 planches de tir 5 losanges grille graduée',

  variants: {
    diamonds: {
      photo: 'photos/20pk-sticky-fluoro-sightgrid-6in.jpg',
      points: [[[240, 305]], [[200, 500]], [[165, 829]], [[565, 500]], [[750, 560]], [[790, 790]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la planche',
    photoFilter: 'brightness(1.03)',
    callouts: [
      { side: 'l', y: 400, title: 'Quatre losanges d’angle', text: 'Cernés de jaune-vert, croix noire sur rouge' },
      { side: 'l', y: 660, title: 'Réticule rouge', text: 'Partage la planche en quatre quadrants' },
      { side: 'l', y: 920, title: 'Grille chiffrée', text: '3, 2, 1 de part et d’autre du centre' },
      { side: 'r', y: 620, title: 'Double losange central', text: 'Deux contours, une mouche rouge' },
      { side: 'r', y: 830, title: 'Fond noir réactif', text: 'Gerbe jaune fluorescent à l’impact' },
      { side: 'r', y: 1040, title: 'Quadrillage jaune', text: 'Découpé en bandes numérotées' },
    ],
    footer: {
      tag: 'Réglage',
      html: 'Les chiffres <strong>1, 2 et 3</strong> découpent la grille en bandes : on relève <strong>dans quelle bande</strong> tombe un groupement.',
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
        centre: [500, 500], half: 405, mm: 152.4, K: 4, heading: 'UNE PLANCHE',
        facts: [['20', 'planches'], ['100', 'points de visée'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // The board at true size (mounted.html): five on the default 1 m panel,
  // and four in a 2 x 2 grid on a 55 x 55 cm board, the size of a C50 card
  // (five 15,24 cm boards do not fit on it without overlapping).
  mounted: [
    { centre: [500, 500], half: 405, cm: 15.24 },
    { id: 'c50', centre: [500, 500], half: 405, cm: 15.24, panel: [55, 55], count: 4 },
  ],

  // A pile of boards, no text (stack.html): the pack holds 20.
  stack: { centre: [500, 500], half: 405, cm: 15.24, count: 7 },

  // The series both the effect sheet and the text-free impact.html draw:
  // one hit on each diamond.
  impact: {
    centre: [500, 500], radius: 405, shape: 'square', seed: 349,
    hits: [[-130, -138], [138, -132], [-136, 132], [132, 138], [6, -6]],
  },

  // Five aim points per board: one hit on each diamond.
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        ...window.PRODUCT.impact,
        afterLabel: 'APRÈS CINQ SÉRIES',
        caption: ['Une gerbe jaune autour de chaque trou,', 'repérable sans quitter le pas de tir.'],
      });
    },
  },
};
