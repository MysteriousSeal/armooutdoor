// 250 reactive round targets, 76 mm, red reticle (SKU CR-RN-REACT-076-250,
// product 97 on prod). No supplier sheet: every fact comes from the product
// page on armooutdoor.fr. Geometry measured on the flat gallery photo: centre
// 500,492; black disc r391; red rings r159 (12 px) and r271 (17 px); red dot
// r94; the cross (16 px) runs from the edge and stops at the inner ring.
window.PRODUCT = {
  eyebrow: '250 cibles réactives réticule rouge',

  variants: {
    red: {
      photo: 'photos/cr-rn-react-076-250.jpg',
      points: [[[500, 200], [210, 492]], [[612, 380], [692, 300]], [[530, 520]], [[700, 680]]],
      palette: { black: '#0d0d0d', red: '#e80a14' },
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
    callouts: [
      { side: 'l', y: 430, title: 'Croix interrompue', text: 'Ses branches s’arrêtent à l’anneau intérieur' },
      { side: 'r', y: 400, title: 'Deux anneaux', text: 'Situent les impacts autour du point de visée' },
      { side: 'r', y: 640, title: 'Disque rouge', text: 'Le point de visée, laissé dégagé' },
      { side: 'r', y: 880, title: 'Fond noir', text: 'Fait ressortir la gerbe jaune à l’impact' },
    ],
    footer: {
      tag: 'Visée directe',
      html: '<strong>Aucun chiffre à lire</strong> ni zone à compter : placez le point de visée au <strong>centre rouge</strong> et tirez.',
    },
  },

  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À l’échelle',
      html: 'La cible et la pièce de 1 € sont dessinées <strong>à la même échelle</strong>. À tirer de préférence entre <strong>5 et 25 m</strong>.',
    },
    draw({ el, text, dim, ext, section }, P) {
      const K = 7.9; // px per mm, shared by the target and the coin

      // The target, redrawn from the photo's proportions (photo px times u).
      const R = 76 / 2 * K, cx = 560, cy = 800, u = R / 391;
      el('circle', { cx, cy, r: R, fill: P.black });
      for (let q = 0; q < 4; q++) {
        const a = (q * Math.PI) / 2;
        el('line', { x1: cx + Math.cos(a) * 159 * u, y1: cy + Math.sin(a) * 159 * u, x2: cx + Math.cos(a) * R, y2: cy + Math.sin(a) * R, stroke: P.red, 'stroke-width': 16 * u });
      }
      el('circle', { cx, cy, r: 271 * u, fill: 'none', stroke: P.red, 'stroke-width': 17 * u });
      el('circle', { cx, cy, r: 159 * u, fill: 'none', stroke: P.red, 'stroke-width': 12 * u });
      el('circle', { cx, cy, r: 94 * u, fill: P.red });

      section(150, 350, 'UNE CIBLE');
      ext(cx - R, cy - 20, cx - R, cy - R - 70); ext(cx + R, cy - 20, cx + R, cy - R - 70);
      dim(cx - R, cy - R - 55, cx + R, cy - R - 55, '76 mm', 56);

      Infographic.targets.coinAndFacts({ el, text }, {
        K, cy, R, facts: [['250', 'cibles'], ['Rouleau', 'conditionnement'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // Before / after a series, the yellow splatter between the red lines.
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [500, 492], radius: 391, seed: 97,
        hits: [[-30, -36], [38, -28], [-12, 44], [70, 52], [-150, 120]],
        afterLabel: 'APRÈS UNE SÉRIE',
        caption: ['Le jaune ressort sur le noir et les repères rouges :', 'on voit le groupement sans aller à la cible.'],
      });
    },
  },
};
