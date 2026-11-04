// Charcoal grey breathable tube neck gaiter (SKU NCC-GREY-POLY-UX, product 366 on prod).
// Facts from the product page on armooutdoor.fr (polyester mesh knit with
// small oval holes in offset rows, very breathable, stretchy, quick dry,
// straight tube hemmed and topstitched at both ends, one size, spring and
// summer, does not stop wind or filter air, worn five ways, 40 g) and the
// supplier sheets in sources/cache-cou-supplier-* (24 x 42 cm laid flat;
// the fabric and wearing sheets are grey). The gallery has only the fabric
// close-up (photos/<sku>.jpg), so the tube is drawn in this colour.
const COLOUR = { fill: '#3a4047', dark: '#22272c', hole: '#0c1013' };

// The tube in COLOUR, clipped to path d: fabric with its offset rows of
// holes, shaded darker at the sides like a cylinder, plus fold and hem lines.
function knit(el, id, d, { hems = [], folds = [] } = {}) {
  const defs = el('defs', {});
  const pat = el('pattern', { id: id + 'Pat', width: 14, height: 16, patternUnits: 'userSpaceOnUse' }, defs);
  el('ellipse', { cx: 3.5, cy: 4, rx: 1.6, ry: 2.8, fill: COLOUR.hole, opacity: .55 }, pat);
  el('ellipse', { cx: 10.5, cy: 12, rx: 1.6, ry: 2.8, fill: COLOUR.hole, opacity: .55 }, pat);
  const g = el('linearGradient', { id: id + 'Shade', x1: 0, x2: 1, y1: 0, y2: 0 }, defs);
  [[0, '#000', .34], [.28, '#000', 0], [.46, '#fff', .12], [.62, '#000', 0], [1, '#000', .34]].forEach(([o, c, a]) =>
    el('stop', { offset: o, 'stop-color': c, 'stop-opacity': a }, g));
  el('path', { d, fill: COLOUR.fill, stroke: COLOUR.dark, 'stroke-width': 3, 'stroke-linejoin': 'round' });
  el('path', { d, fill: `url(#${id}Pat)` });
  el('path', { d, fill: `url(#${id}Shade)` });
  folds.forEach(f => {
    el('path', { d: f, fill: 'none', stroke: '#000', 'stroke-width': 4, 'stroke-linecap': 'round', opacity: .28 });
    el('path', { d: f, fill: 'none', stroke: '#fff', 'stroke-width': 2, 'stroke-linecap': 'round', opacity: .14, transform: 'translate(0 -4)' });
  });
  hems.forEach(h => el('path', { d: h, fill: 'none', stroke: COLOUR.dark, 'stroke-width': 2, 'stroke-dasharray': '7 5', opacity: .9 }));
}

window.PRODUCT = {
  eyebrow: 'Cache-cou gris anthracite respirant',

  variants: {
    tube: {
      photo: 'photos/ncc-grey-poly-ux.jpg',
      points: [[[330, 265]], [[420, 560]], [[500, 700]], [[700, 300]], [[800, 600]], [[650, 860]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la maille',
    photoBox: { x: 360, y: 300, size: 780 },
    callouts: [
      { side: 'l', y: 420, title: 'Petits jours ovales', text: 'Percés de part en part' },
      { side: 'l', y: 690, title: 'Rangées décalées', text: 'D’une rangée de jours à l’autre' },
      { side: 'l', y: 960, title: 'Côtes du tricot', text: 'Un relief régulier entre les jours' },
      { side: 'r', y: 420, title: 'Polyester', text: 'Séchage rapide' },
      { side: 'r', y: 690, title: 'Très respirant', text: 'L’air passe au travers du tissu' },
      { side: 'r', y: 960, title: 'Extensible', text: 'Taille unique, la maille s’adapte' },
    ],
    footer: {
      tag: 'Printemps, été',
      html: 'Fait pour la chaleur et l’effort. Les jours qui le rendent agréable font aussi qu’il <strong>ne coupe pas le vent</strong>, <strong>ne tient pas chaud</strong> et ne filtre pas l’air.',
    },
  },

  // The tube laid flat, drawn to scale (17 px per cm) with the supplier's
  // two measures; the coin at the same scale.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'Au repos',
      html: 'Mesures du fabricant, <strong>à plat et au repos</strong> : la maille s’étire, d’où la <strong>taille unique</strong>.',
    },
    draw({ el, text, dim, ext, arrow }) {
      const k = 17, w = 24 * k, h = 42 * k, x0 = 330, y0 = 395, x1 = x0 + w, y1 = y0 + h;
      knit(el, 'flat', `M${x0} ${y0 + 6} Q${x0 + w / 2} ${y0 - 4} ${x1} ${y0 + 6} L${x1 - 3} ${y1 - 6} Q${x0 + w / 2} ${y1 + 4} ${x0 + 3} ${y1 - 6} Z`, {
        hems: [`M${x0 + 4} ${y0 + 26} Q${x0 + w / 2} ${y0 + 16} ${x1 - 4} ${y0 + 26}`, `M${x0 + 6} ${y1 - 26} Q${x0 + w / 2} ${y1 - 16} ${x1 - 6} ${y1 - 26}`],
      });
      const vx = x1 + 60;
      ext(x1 + 12, y0 + 6, vx + 14, y0 + 6); ext(x1 + 12, y1 - 6, vx + 14, y1 - 6);
      arrow(vx, y0 + 6, vx, y1 - 6);
      text(vx + 24, (y0 + y1) / 2 + 18, '42 cm', { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
      const hy = y1 + 50;
      ext(x0 + 3, y1 + 8, x0 + 3, hy + 14); ext(x1 - 3, y1 + 8, x1 - 3, hy + 14);
      dim(x0 + 3, hy, x1 - 3, hy, '24 cm', 52);
      // The coin as a fourth fact, in the same column and type sizes.
      [['Tube', 'ourlé aux deux bouts'], ['Unique', 'taille, la maille s’étire'], ['40 g', 'poids du cache-cou'], ['Pièce de 1 €', '23,25 mm, à la même échelle']].forEach(([big, small], i) => {
        const fy = 520 + i * 120;
        text(1040, fy, big, { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
        text(1040, fy + 34, small, { 'font-size': 25, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.02em' });
      });
      const cr = 23.25 / 2 / 10 * k;
      Infographic.targets.coin({ el, text }, 1040 + cr, 520 + 3 * 120 + 34 + 28 + cr, cr, { caption: false });
    },
  },
};
