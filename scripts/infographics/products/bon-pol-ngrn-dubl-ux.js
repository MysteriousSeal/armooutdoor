// Fluo green fleece beanie (SKU BON-POL-NGRN-DUBL-UX, product 136 on prod).
// Facts from the product page and the supplier sheets in sources/
// (bonnet-polaire-*): 26 x 22 cm measured flat, "Fleece Winter Beanie Hat",
// worn for skiing, mountaineering, cycling and fishing. The supplier's size
// photo is a black beanie of the same cut; our only photo is a three-quarter
// view, so the dimensions sheet draws a flat silhouette to scale instead.
window.PRODUCT = {
  eyebrow: 'Bonnet polaire vert fluo unisexe',

  variants: {
    green: {
      photo: 'photos/bon-pol-ngrn-dubl-ux.jpg',
      points: [[[330, 230]], [[200, 500]], [[350, 680]], [[620, 300]], [[760, 500]]],
      palette: { fill: '#d4f52a', shade: '#b9dc12', edge: '#8fae0c', stitch: '#7d9a08' },
    },
  },

  anatomy: {
    title: 'Anatomie du bonnet',
    photoFilter: 'brightness(1.03)',
    // The beanie fills only the top of its photo: drawn lower and larger so
    // it sits closer to the footer.
    photoBox: { x: 270, y: 320, size: 960 },
    callouts: [
      { side: 'l', y: 480, title: 'Coupe près du crâne', text: 'Tient sous une capuche ou un casque' },
      { side: 'l', y: 760, title: 'Bord replié', text: 'Double épaisseur sur le tour de tête' },
      { side: 'l', y: 1030, title: 'Taille unique', text: 'Coupe ajustée, unisexe' },
      { side: 'r', y: 520, title: 'Polaire épaisse', text: 'Polyester, chaud et léger' },
      { side: 'r', y: 800, title: 'Vert fluo', text: 'Visible de loin' },
    ],
    footer: {
      tag: 'Usage',
      html: 'Se porte <strong>sous une capuche ou un casque</strong> : ski, alpinisme, vélo, pêche, par temps froid.',
    },
  },

  // A flat front view at 25 px/cm, the way the supplier measured it.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À plat',
      html: 'Mesures relevées <strong>bonnet posé à plat</strong>. <strong>Taille unique</strong>, coupe ajustée.',
    },
    draw({ el, text, dim, ext, arrow, sideLabel, section }, P) {
      const K = 25, W = 26 * K, H = 22 * K, CX = 600;
      const L = CX - W / 2, R = CX + W / 2, B = 1010, T = B - H;
      // The cuff seam sits about 28 % up, as on the supplier's photo; it
      // is drawn but not measured.
      const seam = B - 0.28 * H;
      section(150, 340, 'À PLAT');

      const defs = el('defs', {});
      const g = el('linearGradient', { id: 'fleece', x1: 0, y1: 0, x2: 1, y2: 0 }, defs);
      el('stop', { offset: 0, 'stop-color': P.shade }, g);
      el('stop', { offset: 0.45, 'stop-color': P.fill }, g);
      el('stop', { offset: 1, 'stop-color': P.shade }, g);
      // Fleece pile: seeded fine noise darkening the fill a little.
      const f = el('filter', { id: 'pile', x: 0, y: 0, width: 1, height: 1 }, defs);
      el('feTurbulence', { type: 'fractalNoise', baseFrequency: 0.55, numOctaves: 2, seed: 136, result: 'n' }, f);
      el('feColorMatrix', { in: 'n', type: 'matrix', values: '0 0 0 0 0.25  0 0 0 0 0.3  0 0 0 0 0  0 0 0 -2.2 1.25', result: 'dots' }, f);
      el('feComposite', { in: 'dots', in2: 'SourceGraphic', operator: 'in', result: 'pile' }, f);
      const m = el('feMerge', {}, f);
      el('feMergeNode', { in: 'SourceGraphic' }, m);
      el('feMergeNode', { in: 'pile' }, m);

      el('path', {
        d: `M ${L} ${B} L ${L + 16} ${B - 0.55 * H}
            C ${L + 38} ${T + 56} ${L + 190} ${T} ${CX} ${T}
            C ${R - 190} ${T} ${R - 38} ${T + 56} ${R - 16} ${B - 0.55 * H}
            L ${R} ${B} Q ${CX} ${B + 20} ${L} ${B} Z`,
        fill: 'url(#fleece)', stroke: P.edge, 'stroke-width': 3, filter: 'url(#pile)',
      });
      el('path', { d: `M ${L + 6} ${seam} Q ${CX} ${seam + 16} ${R - 6} ${seam}`, fill: 'none', stroke: P.stitch, 'stroke-width': 3, 'stroke-dasharray': '7 6' });
      text(CX, seam + (B - seam) / 2 + 12, 'BORD REPLIÉ', { 'text-anchor': 'middle', 'font-size': 26, 'font-weight': 700, 'letter-spacing': '.2em', fill: P.stitch });

      ext(L, B + 10, L, B + 100);
      ext(R, B + 10, R, B + 100);
      dim(L, B + 80, R, B + 80, '26 cm', 52);

      ext(L - 10, T, L - 100, T);
      ext(L - 10, B, L - 100, B);
      arrow(L - 80, T, L - 80, B);
      sideLabel(L - 100, (T + B) / 2 + 16, '22 cm', 'end', 52);

      [['Taille unique', 'coupe ajustée'], ['Unisexe', 'homme et femme'], ['Polaire', 'polyester']].forEach(([t, s], i) => {
        const y = T + 40 + i * 120;
        text(1010, y, t, { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
        text(1010, y + 38, s, { 'font-size': 28, 'font-weight': 500, fill: 'var(--mute)' });
      });
    },
  },
};
