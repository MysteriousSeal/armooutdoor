// 200 reactive round targets, 76 mm, yellow fluo (SKU CRT-REACT-076-200-FLUO,
// product 41 on prod). No supplier sheet: every fact comes from the product's
// own characteristics and description on armooutdoor.fr. Ring radii were
// measured on the photo (centre 500,491; red dot r75, rings r161, r270, r385).
window.PRODUCT = {
  eyebrow: '200 cibles réactives autocollantes',

  variants: {
    fluo: {
      photo: 'photos/crt-react-076-200-fluo.jpg',
      points: [[[500, 170]], [[500, 491]], [[656, 449], [721, 336], [721, 176]], [[607, 677]]],
      palette: { black: '#111210', yellow: '#e4f21c', red: '#f20d0d' },
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
    photoFilter: 'brightness(1.03)',
    callouts: [
      { side: 'l', y: 400, title: 'Croix de visée', text: 'Repère pour placer le point de visée' },
      { side: 'l', y: 700, title: 'Pastille centrale', text: 'La mouche rouge, au centre de la cible' },
      { side: 'r', y: 500, title: 'Trois cercles', text: 'Pour lire la répartition des impacts' },
      { side: 'r', y: 880, title: 'Fond noir', text: 'Fait ressortir la gerbe jaune à l’impact' },
    ],
    footer: {
      tag: 'À l’impact',
      html: 'Autour de chaque trou, une <strong>gerbe jaune fluorescent</strong> apparaît sur le fond noir : l’impact se voit <strong>sans lunette</strong>, même en lumière faible.',
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

      // The target, redrawn from the photo's proportions (radii as a share of
      // the 385 px outer ring, line width 15 px).
      const R = 76 / 2 * K, cx = 560, cy = 800, u = R / 392;
      const ring = r => el('circle', { cx, cy, r: r * u, fill: 'none', stroke: P.yellow, 'stroke-width': 15 * u });
      el('circle', { cx, cy, r: R, fill: P.yellow });
      el('circle', { cx, cy, r: 377 * u, fill: P.black });
      for (const [x1, y1, x2, y2] of [[0, -377, 0, -169], [0, 169, 0, 377], [-377, 0, -169, 0], [169, 0, 377, 0]]) {
        el('line', { x1: cx + x1 * u, y1: cy + y1 * u, x2: cx + x2 * u, y2: cy + y2 * u, stroke: P.yellow, 'stroke-width': 15 * u });
      }
      ring(270); ring(161);
      el('circle', { cx, cy, r: 75 * u, fill: P.red });

      section(150, 350, 'UNE CIBLE');
      ext(cx - R, cy - 20, cx - R, cy - R - 70); ext(cx + R, cy - 20, cx + R, cy - R - 70);
      dim(cx - R, cy - R - 55, cx + R, cy - R - 55, '76 mm', 56);

      Infographic.targets.coinAndFacts({ el, text }, {
        K, cy, R, facts: [['200', 'cibles'], ['Rouleau', 'conditionnement'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // Before / after a series, the splatter drawn over the real photo. It is an
  // illustration of the documented "gerbe jaune fluorescent", and says so.
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [500, 491], radius: 392, seed: 76,
        // A tight group around the centre plus two flyers.
        hits: [[-52, -38], [28, -66], [74, 14], [-18, 52], [46, 82], [-88, 30], [10, -4], [170, -125], [-190, 140]],
        afterLabel: 'APRÈS UNE SÉRIE',
        caption: ['Le contour de chaque trou vire au jaune vif sur le fond noir.', 'Visible sans lunette et sans aller jusqu’à la cible.'],
      });
    },
  },
};
