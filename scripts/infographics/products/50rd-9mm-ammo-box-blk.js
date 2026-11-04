// Black storage box for 50 rounds of 9 mm (SKU 50RD-9MM-AMMO-BOX-BLK,
// product 69 on prod). No supplier sheet: every fact comes from the product
// page on armooutdoor.fr (11.5 x 6 x 3.5 cm, 50 cells in 10 columns by 5
// rows, full-width multi-knuckle hinge, moulded tab that clips on, no lock,
// grooved finger recess at the front). Photo: the open box (gallery image 1,
// whitened). Tray corners on it: front left top 114,545, front left bottom
// 140,655, front right bottom 702,826, back right bottom 832,645.
window.PRODUCT = {
  eyebrow: 'Boîte de rangement 50 munitions 9 mm',

  variants: {
    box: {
      photo: 'photos/50rd-9mm-ammo-box-blk.jpg',
      points: [[[300, 250]], [[300, 560]], [[395, 705]], [[585, 213]], [[700, 476]], [[775, 760]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la boîte',
    callouts: [
      { side: 'l', y: 420, title: 'Couvercle pleine longueur', text: 'Il se couche à plat, ouvert en grand' },
      { side: 'l', y: 690, title: '50 alvéoles', text: 'Dix colonnes sur cinq rangées' },
      { side: 'l', y: 960, title: 'Prise rainurée', text: 'Un creux en façade pour décliper' },
      { side: 'r', y: 400, title: 'Languette moulée', text: 'Se clipse sur la façade du bac' },
      { side: 'r', y: 650, title: 'Charnière pleine largeur', text: 'À articulations multiples' },
      { side: 'r', y: 900, title: 'Plastique rigide', text: 'Noir mat' },
    ],
    footer: {
      tag: 'Fermeture',
      html: 'Un <strong>simple encliquetage</strong>, sans loquet ni serrure : la boîte reste fermée pendant le transport, <strong>elle n’est pas verrouillée</strong>.',
    },
  },

  // The open box with its three measures along its edges, as on the page's
  // own size image: length across the front, depth along the right side,
  // height at the front left corner. No coin: a three-quarter view.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'Format poche',
      html: 'Avec <strong>11,5 cm de long</strong>, elle se glisse dans une poche de gilet ou un compartiment de sac.',
    },
    draw({ el, text, ext, arrow }, P, V) {
      const X = x => 200 + x * 0.86, Y = y => 260 + y * 0.86;
      el('image', { href: V.photo, x: X(0), y: Y(0), width: 860, height: 860, style: 'mix-blend-mode: multiply' });
      // Offset a box edge A-B along another of the box's own axes (u, in
      // photo px), so the extension lines follow the perspective of the
      // drawing instead of a right angle; arrow, then the label.
      function measure([ax, ay], [bx, by], [ux, uy], off, label, [lx, ly], anchor) {
        const A = [X(ax), Y(ay)], B = [X(bx), Y(by)], n = Math.hypot(ux, uy), nx = ux / n * off, ny = uy / n * off;
        ext(A[0] + nx * 0.15, A[1] + ny * 0.15, A[0] + nx * 1.15, A[1] + ny * 1.15);
        ext(B[0] + nx * 0.15, B[1] + ny * 0.15, B[0] + nx * 1.15, B[1] + ny * 1.15);
        arrow(A[0] + nx, A[1] + ny, B[0] + nx, B[1] + ny);
        text(lx, ly, label, { 'text-anchor': anchor, 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
      }
      // Box axes on the photo, traced from its silhouette: length along the
      // front bottom edge (140,655 to 702,826), depth along the right bottom
      // edge (702,826 to 832,645), height at the front left corner.
      const LEN = [562, 171], DEPTH = [130, -181];
      measure([140, 655], [702, 826], [-DEPTH[0], -DEPTH[1]], 70, '11,5 cm', [X(380) - 30, Y(800) + 75], 'end');
      measure([702, 826], [832, 645], LEN, 70, '6 cm', [X(800) + 95, Y(790) + 40], 'start');
      measure([114, 545], [140, 655], [-LEN[0], -LEN[1]], 60, '3,5 cm', [X(114) - 75, Y(600) + 6], 'end');
      [['50', 'cartouches de 9 mm'], ['70 g', 'poids de la boîte']].forEach(([big, small], i) => {
        const fy = 500 + i * 120;
        text(1120, fy, big, { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
        text(1120, fy + 34, small, { 'font-size': 25, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.02em' });
      });
    },
  },

  // The page's point that a factory box of 50 goes in whole: the grid seen
  // from above, each round standing in its cell.
  effect: {
    title: '50 cartouches debout',
    footer: {
      tag: 'Schéma',
      html: 'Vue de dessus simplifiée. Les douilles <strong>ne se touchent pas</strong>, et un regard suffit pour voir <strong>combien il en reste</strong>.',
    },
    draw({ el, text }) {
      const cols = 10, rows = 5, c = 104, x0 = 750 - cols * c / 2, y0 = 470;
      el('rect', { x: x0 - 24, y: y0 - 24, width: cols * c + 48, height: rows * c + 48, rx: 18, fill: '#26282b' });
      for (let j = 0; j < rows; j++) {
        for (let i = 0; i < cols; i++) {
          const x = x0 + i * c, y = y0 + j * c, cx = x + c / 2, cy = y + c / 2;
          el('rect', { x: x + 4, y: y + 4, width: c - 8, height: c - 8, rx: 6, fill: '#121314' });
          // The last eight cells are empty, to show the count at a glance.
          if (j * cols + i >= 42) continue;
          el('circle', { cx, cy, r: 36, fill: '#c9a24b', stroke: '#8a6a2c', 'stroke-width': 3 });
          el('circle', { cx, cy, r: 26, fill: 'none', stroke: '#a88438', 'stroke-width': 2 });
          el('circle', { cx, cy, r: 10, fill: '#b8b2a4', stroke: '#7a7466', 'stroke-width': 2 });
        }
      }
      text(x0, y0 + rows * c + 100, '10 colonnes × 5 rangées = 50 cartouches', { 'font-size': 44, 'font-weight': 700, fill: 'var(--ink)' });
      text(x0, y0 + rows * c + 145, 'Le contenu d’une boîte d’usine de 9 mm se transvase en entier.', { style: 'font-family: Barlow', 'font-size': 28, fill: 'var(--mute)' });
    },
  },
};
