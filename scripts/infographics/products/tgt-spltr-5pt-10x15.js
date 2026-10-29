// 10 reactive shooting boards, 15.24 cm, 5 yellow fluo targets (SKU
// TGT-SPLTR-5PT-10X15, product 90 on prod). No supplier sheet: every fact
// comes from the product page on armooutdoor.fr. Square measured on the flat
// gallery photo: centre 501,498, half side 436; corner targets at x 284/718,
// y 280/716 with outer rings r146; centre target r88; scale 0 to 2.5 on the
// four edges.
window.PRODUCT = {
  eyebrow: '10 planches de tir 5 cibles jaune fluorescent',

  variants: {
    five: {
      photo: 'photos/tgt-spltr-5pt-10x15.jpg',
      points: [[[180, 280]], [[412, 498]], [[748, 280]], [[905, 640]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la planche',
    callouts: [
      { side: 'l', y: 430, title: 'Quatre cibles d’angle', text: 'Deux anneaux et une croix de visée' },
      { side: 'l', y: 760, title: 'Cible centrale', text: 'Plus petite, un seul anneau : plus exigeante' },
      { side: 'r', y: 430, title: 'Mouches rouges', text: 'Une au cœur de chaque cible' },
      { side: 'r', y: 800, title: 'Grille graduée', text: 'De 0 au centre à 2,5, sur les quatre bords' },
    ],
    footer: {
      tag: 'Séance',
      html: 'Commencez par les <strong>quatre cibles d’angle</strong>, terminez sur la <strong>petite cible centrale</strong>, la plus exigeante.',
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
        centre: [501, 498], half: 436, mm: 152.4, K: 4, heading: 'UNE PLANCHE',
        facts: [['10', 'planches'], ['5 cibles', 'par planche'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // The board on a 1 m panel (mounted.html), at true size.
  mounted: { centre: [501, 498], half: 436, cm: 15.24 },

  // The session the page describes: one hit on each corner target, then the
  // centre one.
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [501, 498], radius: 436, shape: 'square', seed: 90,
        hits: [[-136, -136], [134, -138], [-140, 134], [136, 140], [4, -4]],
        afterLabel: 'APRÈS CINQ SÉRIES',
        caption: ['Une gerbe jaune autour de chaque trou,', 'repérable sans quitter le pas de tir.'],
      });
    },
  },
};
