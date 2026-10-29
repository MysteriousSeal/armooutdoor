// 200 square reactive targets, 76 mm, scope reticle, reinforced paper (SKU CCS-VIS-076-200-FLUO, product 91 on prod).
// No supplier sheet: every fact comes from the product page on armooutdoor.fr.
// Square measured on the flat gallery photo: centre 500,486, half side 377.
window.PRODUCT = {
  eyebrow: '200 cibles carrées réactives réticule de visée',

  variants: {
    reticle: {
      photo: 'photos/ccs-vis-076-200-fluo.jpg',
      points: [[[500, 180], [323, 309]], [[530, 515]], [[860, 486]], [[700, 750]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
    callouts: [
      { side: 'l', y: 400, title: 'Réticule', text: 'Un cercle et quatre branches' },
      { side: 'l', y: 760, title: 'Pastille rouge', text: 'Le point de visée, laissé dégagé' },
      { side: 'r', y: 520, title: 'Branches jusqu’aux bords', text: 'Se prolongent d’une cible à l’autre, en damier' },
      { side: 'r', y: 860, title: 'Papier renforcé', text: 'Encaisse les impacts rapprochés' },
    ],
    footer: {
      tag: 'Visée directe',
      html: 'Aucune graduation : tout converge vers la <strong>pastille rouge</strong>. Posées bord à bord, les cibles se prolongent en <strong>damier</strong>.',
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
        centre: [500, 486], half: 377, mm: 76, K: 7.9,
        facts: [['200', 'cibles'], ['Papier renforcé', 'anti-déchirure'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // A group around the red dot, yellow splatter on the bare black.
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [500, 486], radius: 377, shape: 'square', seed: 91,
        hits: [[-55, -45], [55, -55], [58, 48], [-48, 60], [120, -130]],
        afterLabel: 'APRÈS UNE SÉRIE',
        caption: ['Sur ce fond presque vide, les trous d’une série', 'ressortent dès les premiers tirs.'],
      });
    },
  },
};
