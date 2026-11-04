// Green storage box for 100 rounds of 9 mm (SKU 100RD-9MM-AMMO-BOX-GRN, product 62 on
// prod). Facts from the product page and the supplier size sheet in
// sources/boite-100-munitions-supplier-size.png (11.5 x 11.5 cm); the 3.5 cm
// height was given by the user (2026-11-02). Photos: the open box (gallery
// image 1, whitened) for the anatomy and dimensions, the tab close-up
// (image 3, photos/<sku>-tab.jpg) for the third sheet. Box edges traced on
// the open photo: front left top 88,640, front left bottom 100,760, front
// right bottom 625,894, back right bottom 852,636.
window.PRODUCT = {
  eyebrow: 'Boîte de rangement 100 munitions 9 mm verte',

  variants: {
    box: {
      photo: 'photos/100rd-9mm-ammo-box-grn.jpg',
      tab: 'photos/100rd-9mm-ammo-box-grn-tab.jpg',
      points: [[[300, 270]], [[400, 570]], [[333, 760]], [[455, 190]], [[815, 495]], [[760, 790]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la boîte',
    callouts: [
      { side: 'l', y: 420, title: 'Couvercle pleine largeur', text: 'Solidaire du bac, rien à égarer' },
      { side: 'l', y: 690, title: '100 logements', text: 'Dix rangées de dix, culot vers le haut' },
      { side: 'l', y: 960, title: 'Encoche rainurée', text: 'Le doigt dégage la languette' },
      { side: 'r', y: 400, title: 'Languette à fenêtre', text: 'Elle coiffe un ergot de façade' },
      { side: 'r', y: 650, title: 'Charnière à barillet', text: 'Articulations sur tout l’arrière' },
      { side: 'r', y: 900, title: 'Plastique rigide', text: 'Vert, repérable d’un coup d’œil' },
    ],
    footer: {
      tag: 'Trier ses lots',
      html: 'Munitions <strong>rechargées dans la verte</strong>, cartouches d’usine dans la noire : elles se distinguent <strong>sans rien ouvrir</strong>.',
    },
  },

  // Arrows along the box's own edges, offset along its other axes so they
  // follow the perspective. No coin: a three-quarter view.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'Deux cartons',
      html: 'Le format cent coups reçoit <strong>deux boîtes d’usine de cinquante</strong> sans reste ni logement vide.',
    },
    draw({ el, text, ext, arrow }, P, V) {
      const X = x => 200 + x * 0.86, Y = y => 250 + y * 0.86;
      el('image', { href: V.photo, x: X(0), y: Y(0), width: 860, height: 860, style: 'mix-blend-mode: multiply' });
      function measure([ax, ay], [bx, by], [ux, uy], off, label, [lx, ly], anchor) {
        const A = [X(ax), Y(ay)], B = [X(bx), Y(by)], n = Math.hypot(ux, uy), nx = ux / n * off, ny = uy / n * off;
        ext(A[0] + nx * 0.15, A[1] + ny * 0.15, A[0] + nx * 1.15, A[1] + ny * 1.15);
        ext(B[0] + nx * 0.15, B[1] + ny * 0.15, B[0] + nx * 1.15, B[1] + ny * 1.15);
        arrow(A[0] + nx, A[1] + ny, B[0] + nx, B[1] + ny);
        text(lx, ly, label, { 'text-anchor': anchor, 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
      }
      const LEN = [525, 134], DEPTH = [227, -258];
      measure([100, 760], [625, 894], [-DEPTH[0], -DEPTH[1]], 70, '11,5 cm', [X(330) - 20, Y(860) + 80], 'end');
      measure([625, 894], [852, 636], LEN, 45, '11,5 cm', [X(780) + 70, Y(800) + 40], 'start');
      measure([88, 640], [100, 760], [-LEN[0], -LEN[1]], 60, '3,5 cm', [X(88) - 75, Y(700) + 6], 'end');
      [['100', 'cartouches de 9 mm'], ['100 g', 'poids de la boîte']].forEach(([big, small], i) => {
        const fy = 440 + i * 120;
        text(1140, fy, big, { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
        text(1140, fy + 34, small, { 'font-size': 25, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.02em' });
      });
    },
  },

  effect: {
    title: 'Mode d’emploi',
    footer: {
      tag: 'Fermeture',
      html: 'La languette <strong>tient pendant le transport</strong> ; elle ne verrouille pas, il suffit de <strong>la soulever pour rouvrir</strong>.',
    },
    draw(kit, V) {
      const { el, text } = kit;
      Infographic.pastilles.usage(kit, [
        { head: 'LA FENÊTRE ET L’ERGOT', title: 'Rabattre et accrocher', line: 'l’encoche rainurée dégage la languette.',
          draw: (k, x) => {
            const cx = x + 280, cy = 730, R = 250, id = 'tabZoom';
            const cp = el('clipPath', { id }, el('defs', {}));
            el('circle', { cx, cy, r: R }, cp);
            el('image', { href: V.tab, x: cx - R * 1.4, y: cy - R * 1.25, width: R * 2.8, height: R * 2.8, 'clip-path': `url(#${id})` });
            el('circle', { cx, cy, r: R, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 5 });
          } },
        { head: 'DEUX CARTONS DANS UNE', title: '2 × 50 = 100', line: 'deux boîtes d’usine, sans reste.',
          draw: ({ el, text }, x) => {
            // Two factory boxes of 50 (10 x 5) poured into the 10 x 10 grid,
            // each round seen from above: brass case head, rim, primer.
            // The 100-round box takes this product's colour; the factory boxes stay dark.
            const c = 24, grid = (gx, gy, rows, box = '#26282b', cell = '#121314') => {
              el('rect', { x: gx - 9, y: gy - 9, width: 10 * c + 18, height: rows * c + 18, rx: 9, fill: box });
              for (let j = 0; j < rows; j++) for (let i = 0; i < 10; i++) {
                const cx = gx + i * c + c / 2, cy = gy + j * c + c / 2;
                el('rect', { x: cx - c / 2 + 1.5, y: cy - c / 2 + 1.5, width: c - 3, height: c - 3, rx: 2, fill: cell });
                el('circle', { cx, cy, r: 9.5, fill: '#c9a24b', stroke: '#8a6a2c', 'stroke-width': 1.5 });
                el('circle', { cx, cy, r: 6.5, fill: 'none', stroke: '#a88438', 'stroke-width': 1 });
                el('circle', { cx, cy, r: 2.8, fill: '#b8b2a4', stroke: '#7a7466', 'stroke-width': 1 });
              }
            };
            grid(x + 9, 520, 5); grid(x + 9, 690, 5);
            grid(x + 310, 560, 10, '#3e9d7d', '#1f5a43');
            el('path', { d: `M${x + 262} 680 H${x + 290}`, stroke: 'var(--coyote)', 'stroke-width': 5 });
            el('path', { d: `M${x + 278} 668 L${x + 292} 680 L${x + 278} 692`, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 5, 'stroke-linejoin': 'round' });
            text(x + 129, 920, '2 boîtes de 50', { 'text-anchor': 'middle', 'font-size': 28, 'font-weight': 700, fill: 'var(--mute)' });
            text(x + 430, 920, '1 boîte de 100', { 'text-anchor': 'middle', 'font-size': 28, 'font-weight': 700, fill: 'var(--mute)' });
          } },
      ]);
    },
  },
};
