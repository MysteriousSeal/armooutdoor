// 200 reactive round targets, 76 mm, 10 lettered zones (SKU RLT-SPLAT-076-200-FLUO,
// product 46 on prod). No supplier sheet: every fact comes from the product's
// own characteristics and description on armooutdoor.fr. Geometry measured on
// the flat gallery photo: centre 500,487; outer edge r390, black from r370,
// red crown r200, centre disc r70, letters at r334 mid-sector.
window.PRODUCT = {
  eyebrow: '200 cibles réactives à 10 zones lettrées',

  variants: {
    fluo: {
      photo: 'photos/rlt-splat-076-200-fluo.jpg',
      points: [[[404, 256], [269, 391]], [[500, 545]], [[620, 560]], [[705, 692]]],
      palette: { black: '#0d0d0c', yellow: '#d4f20f', red: '#f20c05' },
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
    callouts: [
      { side: 'l', y: 450, title: 'Secteurs A à H', text: 'Situez chaque impact d’une lettre' },
      { side: 'l', y: 760, title: 'Centre « 10 »', text: 'Le disque noir, point de visée' },
      { side: 'r', y: 700, title: 'Couronne rouge', text: 'Entoure le centre de la cible' },
      { side: 'r', y: 950, title: 'Huit lignes fluo', text: 'Partent du centre et délimitent les secteurs' },
    ],
    footer: {
      tag: '10 zones',
      html: '<strong>Huit secteurs lettrés</strong>, la <strong>couronne rouge</strong> et le <strong>centre</strong> : notez où tombe chaque impact pour comparer vos séries.',
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
      const R = 76 / 2 * K, cx = 560, cy = 800, u = R / 390;
      el('circle', { cx, cy, r: R, fill: P.yellow });
      el('circle', { cx, cy, r: 370 * u, fill: P.black });
      el('circle', { cx, cy, r: 200 * u, fill: P.red, stroke: P.yellow, 'stroke-width': 5 * u });
      for (let i = 0; i < 8; i++) {
        const a = (i * Math.PI) / 4;
        el('line', { x1: cx + Math.cos(a) * 70 * u, y1: cy + Math.sin(a) * 70 * u, x2: cx + Math.cos(a) * 370 * u, y2: cy + Math.sin(a) * 370 * u, stroke: P.yellow, 'stroke-width': 4 * u });
      }
      el('circle', { cx, cy, r: 70 * u, fill: P.black, stroke: P.yellow, 'stroke-width': 6 * u });
      text(cx, cy + 32 * u, '10', { 'text-anchor': 'middle', 'font-size': 96 * u, 'font-weight': 600, fill: P.yellow, style: 'font-family: Barlow' });
      // Letters mid-sector, A top left then anticlockwise to H top right, tops facing out.
      'AHGFEDCB'.split('').forEach((ch, i) => {
        const deg = -112.5 + i * 45;
        const a = (deg * Math.PI) / 180;
        const lx = cx + Math.cos(a) * 334 * u, ly = cy + Math.sin(a) * 334 * u;
        text(lx, ly + 14 * u, ch, { 'text-anchor': 'middle', 'font-size': 44 * u, 'font-weight': 700, fill: P.yellow, style: 'font-family: Barlow', transform: `rotate(${deg + 90} ${lx} ${ly})` });
      });

      section(150, 350, 'UNE CIBLE');
      ext(cx - R, cy - 20, cx - R, cy - R - 70); ext(cx + R, cy - 20, cx + R, cy - R - 70);
      dim(cx - R, cy - R - 55, cx + R, cy - R - 55, '76 mm', 56);

      Infographic.targets.coinAndFacts({ el, text }, {
        K, cy, R, facts: [['200', 'cibles'], ['Rouleau', 'conditionnement'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // Before / after a series grouped in sector H, the splatter drawn over the
  // real photo. An illustration of the documented "gerbe jaune fluorescente".
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [500, 487], radius: 390, seed: 46,
        // A group in sector H (between 12 o'clock and the upper-right line),
        // one hit in the centre and a flyer in B, all clear of the letters.
        hits: [[30, -110], [80, -120], [50, -165], [115, -160], [5, -200], [6, -6], [-164, -60]],
        afterLabel: 'APRÈS UNE SÉRIE',
        caption: ['Le contour de chaque trou vire au jaune vif :', 'on lit aussitôt le secteur touché.'],
      });
    },
  },
};
