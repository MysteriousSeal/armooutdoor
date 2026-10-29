// 100 reactive round targets, 76 mm, yellow fluo (SKU CR-SPLAT-076-100-22LR,
// product 79 on prod). Same design as crt-react-076-200-fluo, sold by 100, but
// measured on its own flat gallery photo: centre 500,498; yellow edge r405 to
// r422; rings r176 and r290 (14 px); cross from r183 to the edge; red dot r83.
// No supplier sheet: every fact comes from the product page on armooutdoor.fr.
window.PRODUCT = {
  eyebrow: '100 cibles réactives autocollantes',

  variants: {
    fluo: {
      photo: 'photos/cr-splat-076-100-22lr.jpg',
      points: [[[500, 140]], [[500, 498]], [[670, 452], [738, 332], [737, 160]], [[607, 684]]],
      palette: { black: '#160f0c', yellow: '#e4f21c', red: '#f20d0d' },
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
    photoFilter: 'brightness(1.04)',
    callouts: [
      { side: 'l', y: 400, title: 'Croix de visée', text: 'Repère pour placer le point de visée' },
      { side: 'l', y: 700, title: 'Pastille centrale', text: 'Rouge, un repère net pour caler la visée' },
      { side: 'r', y: 500, title: 'Trois cercles', text: 'Situent les impacts autour du centre' },
      { side: 'r', y: 880, title: 'Fond noir', text: 'Fait ressortir la gerbe jaune à l’impact' },
    ],
    footer: {
      tag: 'À l’impact',
      html: 'Une <strong>gerbe jaune fluorescent</strong> apparaît autour de chaque impact : elle se voit <strong>à l’œil nu depuis le pas de tir</strong>, même en lumière faible.',
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
      const R = 76 / 2 * K, cx = 560, cy = 800, u = R / 422;
      el('circle', { cx, cy, r: R, fill: P.yellow });
      el('circle', { cx, cy, r: 405 * u, fill: P.black });
      for (let q = 0; q < 4; q++) {
        const a = (q * Math.PI) / 2;
        el('line', { x1: cx + Math.cos(a) * 183 * u, y1: cy + Math.sin(a) * 183 * u, x2: cx + Math.cos(a) * 405 * u, y2: cy + Math.sin(a) * 405 * u, stroke: P.yellow, 'stroke-width': 14 * u });
      }
      for (const r of [290, 176]) el('circle', { cx, cy, r: r * u, fill: 'none', stroke: P.yellow, 'stroke-width': 14 * u });
      el('circle', { cx, cy, r: 83 * u, fill: P.red });

      section(150, 350, 'UNE CIBLE');
      ext(cx - R, cy - 20, cx - R, cy - R - 70); ext(cx + R, cy - 20, cx + R, cy - R - 70);
      dim(cx - R, cy - R - 55, cx + R, cy - R - 55, '76 mm', 56);

      Infographic.targets.coinAndFacts({ el, text }, {
        K, cy, R, facts: [['100', 'cibles'], ['Rouleau', 'conditionnement'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // Before / after a series, the splatter drawn over the real photo: an
  // illustration of the documented "gerbe jaune fluorescent".
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [500, 498], radius: 422, seed: 79,
        // A group just right of the centre plus one flyer high left.
        hits: [[50, -40], [105, 5], [40, 55], [110, -75], [70, 105], [-175, -150]],
        afterLabel: 'APRÈS UNE SÉRIE',
        caption: ['Le contour de chaque trou vire au jaune vif sur le fond noir.', 'Visible à l’œil nu depuis le pas de tir.'],
      });
    },
  },
};
