// 20 reactive shooting boards, 15.24 cm, 5 targets in fluorescent green (SKU
// TGT-FLUO-SQ-20-6IN-GRID-MP, product 153 on prod). No supplier sheet: every
// fact comes from the product page on armooutdoor.fr. Measured on the flat
// gallery photo: square centre 499,505, half side 416; corner targets at
// x 290/707, y 297/713 with outer rings r140 and ring bulls r37; centre
// target r84; dashed grid graded 0 to 2.5 on the four edges.
window.PRODUCT = {
  eyebrow: '20 planches de tir 5 cibles vert fluorescent',

  variants: {
    green: {
      photo: 'photos/tgt-fluo-sq-20-6in-grid-mp.jpg',
      points: [[[191, 396]], [[253, 713]], [[152, 818]], [[870, 365]], [[810, 470]], [[583, 505]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la planche',
    callouts: [
      { side: 'l', y: 420, title: 'Quatre cibles d’angle', text: 'Deux anneaux et une croix de visée' },
      { side: 'l', y: 660, title: 'Mouches en anneau', text: 'Un cercle vert, pas un disque plein' },
      { side: 'l', y: 940, title: 'Graduation', text: 'De 0 au centre à 2,5, sur les quatre bords' },
      { side: 'r', y: 500, title: 'Quadrillage pointillé', text: 'Situe chaque groupement' },
      { side: 'r', y: 660, title: 'Fond sombre réactif', text: 'Gerbe vert fluorescent à l’impact' },
      { side: 'r', y: 840, title: 'Cible centrale', text: 'Plus petite que celles d’angle' },
    ],
    footer: {
      tag: 'Visée',
      html: 'La mouche est un <strong>anneau vert</strong> : le point de visée se pose <strong>à l’intérieur du cercle</strong> au lieu de recouvrir un disque.',
    },
  },

  // The photo cropped to the square and drawn at true size: the printed
  // scale stays exactly as it is on the board.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À l’échelle',
      html: 'La planche et la pièce de 1 € sont dessinées <strong>à la même échelle</strong>. À tirer de préférence entre <strong>10 et 25 m</strong>.',
    },
    draw(kit, P, V) {
      Infographic.targets.squareToScale(kit, V.photo, {
        centre: [499, 505], half: 416, mm: 152.4, K: 4, heading: 'UNE PLANCHE',
        facts: [['20', 'planches'], ['5 cibles', 'par planche'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // The board on a 1 m panel (mounted.html), at true size.
  mounted: { centre: [499, 505], half: 416, cm: 15.24 },

  // Five aim points per board: one hit in each ring bull, green splatter.
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [499, 505], radius: 416, shape: 'square', seed: 153,
        // Panel scale 0.67: corner targets at about ±140 panel px.
        hits: [[-136, -144], [144, -138], [-140, 138], [138, 144], [4, -6]],
        splatColour: '#4cf23a', closeUpTitle: 'Gerbe vert fluorescent',
        afterLabel: 'APRÈS CINQ SÉRIES',
        caption: ['Une gerbe verte autour de chaque trou,', 'repérable sans quitter le pas de tir.'],
      });
    },
  },
};
