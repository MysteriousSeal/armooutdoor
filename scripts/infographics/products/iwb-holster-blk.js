// Ambidextrous IWB/OWB belt holster, black (SKU IWB-HOLSTER-BLK, product 368
// on prod). Facts from the product page on armooutdoor.fr (15,2 x 10,1 cm,
// about 3 cm at the mouth, 5,5 x 2,5 cm clip mounted either side, belts up to
// 5 cm, snap tab over the slide, short and long tab, nylon, 90 g, as the
// user confirmed) and the supplier sheets
// the user sent as screenshots, not kept (same measures, 15,2 cm taken from
// the mouth to the bottom; before and after the cut; IWB, OWB, right, left;
// stretch, breathable, lightweight, moisture wicking). The bottom is open:
// the line of stitches to cut runs along the side and widens the pocket for
// wider firearms, as the user corrected (the page's sewn bottom is wrong). Photos from the
// gallery, whitened: the holster with a pistol (photos/<sku>.jpg, body x 220
// to 673, mouth y 270, bottom y 925, about 44 px per cm both ways, so it
// gets the coin) and the laid-out kit (-kit.jpg).
const DENIM = '#3f5f8a', BELT = '#7a5233';

// Horizontal gradient from [offset, colour] stops, returned as a fill.
function steel(el, id, stops, vertical) {
  const g = el('linearGradient', { id, x1: 0, x2: vertical ? 0 : 1, y1: 0, y2: vertical ? 1 : 0 }, el('defs', {}));
  stops.forEach(([offset, c]) => el('stop', { offset, 'stop-color': c }, g));
  return `url(#${id})`;
}

// Open scissors, pivot at (cx, cy), tips towards the left, each blade tapered
// with a ground edge, its shank leading to a moulded black finger loop.
function scissors(el, cx, cy) {
  const fill = steel(el, 'bladeSteel', [[0, '#dfe3e8'], [.5, '#aeb4bb'], [1, '#6f747b']], true);
  [[-1, -11], [1, 11]].forEach(([flip, turn]) => {
    const g = el('g', { transform: `translate(${cx} ${cy}) rotate(${turn}) scale(1 ${flip})` });
    el('path', { d: 'M12 -11 L-146 -1 Q-156 2 -146 4 L-6 12 Q12 12 12 3 Z', fill, stroke: '#5a5e64', 'stroke-width': 1.5, 'stroke-linejoin': 'round' }, g);
    el('path', { d: 'M-144 2 L-6 7', stroke: '#f4f6f8', 'stroke-width': 2, 'stroke-linecap': 'round' }, g);
    el('path', { d: 'M8 -9 Q50 -8 76 8 L70 24 Q42 8 8 9 Z', fill: '#26272a' }, g);
    el('ellipse', { cx: 112, cy: 28, rx: 42, ry: 24, fill: 'none', stroke: '#26272a', 'stroke-width': 11, transform: 'rotate(14 112 28)' }, g);
    el('path', { d: 'M88 12 Q112 4 134 16', fill: 'none', stroke: '#5a5e64', 'stroke-width': 2.5, 'stroke-linecap': 'round' }, g);
  });
  el('circle', { cx, cy, r: 7.5, fill: '#c9ced4', stroke: '#5a5e64', 'stroke-width': 1.5 });
  el('line', { x1: cx - 4.5, y1: cy - 2, x2: cx + 4.5, y2: cy + 2, stroke: '#3a3d42', 'stroke-width': 2, 'stroke-linecap': 'round' });
}

window.PRODUCT = {
  eyebrow: 'Holster pistolet ambidextre noir',

  variants: {
    holster: {
      photo: 'photos/iwb-holster-blk.jpg',
      kit: 'photos/iwb-holster-blk-kit.jpg',
      noclip: 'photos/iwb-holster-blk-noclip.png',
      points: [[[232, 290]], [[285, 560]], [[330, 860]], [[497, 310]], [[505, 600]], [[612, 700]]],
    },
  },

  anatomy: {
    title: 'Anatomie du holster',
    callouts: [
      { side: 'l', y: 400, title: 'Bouche renforcée', text: 'Environ 3 cm d’ouverture' },
      { side: 'l', y: 660, title: 'Toile nylon', text: 'Légère, extensible, respirante, évacue l’humidité' },
      { side: 'l', y: 920, title: 'Corps symétrique', text: 'Le même pour droitier et gaucher' },
      { side: 'r', y: 400, title: 'Languette à pression', text: 'Sur la glissière, s’ouvre d’un coup de pouce' },
      { side: 'r', y: 660, title: 'Clip métal', text: 'Monté d’un côté ou de l’autre' },
      { side: 'r', y: 920, title: 'Couture latérale', text: 'Se coupe pour une arme plus large' },
    ],
    footer: {
      tag: 'Sans arme',
      html: 'Le pistolet des photos <strong>montre le volume</strong> : le holster est livré <strong>sans arme</strong>.',
    },
  },

  // A square-on view, true to scale: height from the mouth to the bottom, as
  // the supplier measures it, width of the body, and the coin.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'Compatibilité',
      html: 'Hauteur mesurée <strong>de la bouche au fond</strong>, hors languette. Pour pistolets semi-automatiques <strong>compacts à standard</strong>.',
    },
    draw({ el, text, dim, ext, arrow }, P, V) {
      const s = 0.8, X = x => 200 + x * s, Y = y => 340 + y * s;
      el('image', { href: V.photo, x: X(0), y: Y(0), width: 1000 * s, height: 1000 * s, style: 'mix-blend-mode: multiply' });
      const l = X(220), r = X(673), t = Y(270), b = Y(925), vx = l - 55, hy = b + 55;
      ext(l - 12, t, vx - 14, t); ext(l + 20, b, vx - 14, b);
      arrow(vx, t, vx, b);
      text(vx - 18, (t + b) / 2 + 18, '15,2 cm', { 'text-anchor': 'end', 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
      ext(l + 20, b + 12, l + 20, hy + 14); ext(r, Y(560), r, hy + 14);
      dim(l + 20, hy, r, hy, '10,1 cm', 52);
      [['3 cm', 'd’ouverture, environ'], ['5,5 x 2,5 cm', 'clip métal'], ['5 cm', 'de ceinture au plus'], ['90 g', 'poids'], ['Pièce de 1 €', '23,25 mm, à la même échelle']].forEach(([big, small], i) => {
        const fy = 480 + i * 105;
        text(1040, fy, big, { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
        text(1040, fy + 34, small, { 'font-size': 25, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.02em' });
      });
      const cr = 23.25 / 2 / 10 * (r - l) / 10.1;
      Infographic.targets.coin({ el, text }, 1040 + cr, 480 + 4 * 105 + 34 + 26 + cr, cr, { caption: false });
    },
  },

  effect: [
    // Front views at the belt, no wearer. Inside: the jeans and belt over the
    // holster, the clip from the photo laid back on top, outside them. Outside:
    // the holster over them, on a retouched copy of the photo with the clip
    // and its sleeve covered in nylon weave and its background cut out (-noclip.png), that face being the
    // one away from the body.
    {
      id: 'port',
      title: 'Dedans ou dehors',
      footer: {
        tag: 'Ambidextre',
        html: 'Le clip se monte d’un côté ou de l’autre : <strong>à droite comme à gauche</strong>, sans référence à part. Montage à titre d’illustration.',
      },
      draw(kit, V) {
        const { el } = kit;
        const s = 0.55, Y0 = 437, Y = y => Y0 + y * s, belt = Y(452), beltH = 92;
        const waist = (x, id) => {
          const defs = el('defs', {}), pat = el('pattern', { id, width: 6, height: 6, patternUnits: 'userSpaceOnUse', patternTransform: 'rotate(45)' }, defs);
          el('rect', { width: 6, height: 6, fill: DENIM }, pat);
          el('line', { x1: 0, y1: 0, x2: 0, y2: 6, stroke: '#35527a', 'stroke-width': 2 }, pat);
          el('rect', { x: x + 20, y: belt, width: 540, height: 1012 - belt, rx: 14, fill: `url(#${id})` });
          // Waistband stitching under the belt, a seam and a pocket.
          el('line', { x1: x + 20, y1: belt + beltH + 16, x2: x + 560, y2: belt + beltH + 16, stroke: '#c9a24b', 'stroke-width': 2.5, 'stroke-dasharray': '8 6' });
          el('path', { d: `M${x + 40} ${belt + beltH + 50} Q${x + 110} ${belt + beltH + 56} ${x + 128} ${1012}`, fill: 'none', stroke: '#c9a24b', 'stroke-width': 2.5, 'stroke-dasharray': '8 6' });
          el('rect', { x: x + 12, y: belt, width: 556, height: beltH, rx: 8, fill: BELT });
          [8, beltH - 11].forEach(dy => el('line', { x1: x + 14, y1: belt + dy, x2: x + 566, y2: belt + dy, stroke: '#5d3d24', 'stroke-width': 2, 'stroke-dasharray': '7 5' }));
          [x + 90, x + 500].forEach(lx => el('rect', { x: lx, y: belt - 6, width: 26, height: beltH + 22, rx: 4, fill: DENIM, stroke: '#2f4a6e', 'stroke-width': 2 }));
        };
        Infographic.pastilles.usage(kit, [
          { head: 'DANS LE PANTALON', title: 'Dedans, pour couvrir', line: 'le clip reste dehors, un tee-shirt couvre le reste.',
            draw: (k, x) => {
              const ix = x + 40, X = px => ix + px * s;
              el('image', { href: V.photo, x: ix, y: Y0, width: 1000 * s, height: 1000 * s, style: 'mix-blend-mode: multiply' });
              waist(x, 'denimIn');
              const cp = el('clipPath', { id: 'clipOut' }, el('defs', {}));
              el('rect', { x: X(376), y: Y(448), width: 128 * s, height: 390 * s, rx: 6 }, cp);
              el('rect', { x: X(376) + 5, y: Y(448) + 6, width: 128 * s, height: 390 * s, rx: 6, fill: '#000', opacity: .3 });
              el('image', { href: V.photo, x: ix, y: Y0, width: 1000 * s, height: 1000 * s, 'clip-path': 'url(#clipOut)' });
            } },
          { head: 'SUR LE PANTALON', title: 'Dehors, pour la prise', line: 'par-dessus, la prise de l’arme est plus franche.',
            draw: (k, x) => {
              waist(x, 'denimOut');
              el('image', { href: V.noclip, x: x + 40, y: Y0, width: 1000 * s, height: 1000 * s });
            } },
        ]);
      },
    },
    // The real body from the kit photo, the side stitching to cut marked on
    // it (traced on the inner row, kit coordinates), then opened.
    {
      id: 'couture',
      title: 'La couture latérale',
      footer: {
        tag: 'Définitif',
        html: 'Une couture coupée <strong>ne se referme pas</strong> : essayer l’arme <strong>avant de donner le coup de ciseaux</strong>.',
      },
      draw(kit, V) {
        const { el } = kit;
        const s = 0.62, Y = y => 400 + y * s;
        const body = (x, id) => {
          const X = px => x + 90 + (px - 552) * s;
          const cp = el('clipPath', { id }, el('defs', {}));
          el('rect', { x: X(540), y: Y(100), width: 420 * s, height: 745 * s }, cp);
          el('image', { href: V.kit, x: X(0), y: Y(0), width: 1000 * s, height: 1000 * s, 'clip-path': `url(#${id})`, style: 'mix-blend-mode: multiply' });
          const P = (px, py) => `${X(px)} ${Y(py)}`;
          return { X, seam: `M${P(800, 300)} C${P(840, 355)} ${P(890, 420)} ${P(903, 480)} C${P(910, 540)} ${P(880, 610)} ${P(860, 650)} C${P(845, 690)} ${P(830, 725)} ${P(806, 755)}` };
        };
        Infographic.pastilles.usage(kit, [
          { head: 'À LA LIVRAISON', title: 'Logement serré', line: 'une ligne de couture tient le côté du logement.',
            draw: (k, x) => {
              const { X, seam } = body(x, 'kitClosed');
              el('path', { d: seam, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 5, 'stroke-dasharray': '14 9' });
              scissors(el, X(885) + 145, Y(590));
            } },
          { head: 'APRÈS LA COUPE', title: 'Logement élargi', line: 'la place gagnée accueille une arme plus large.',
            draw: (k, x) => {
              const { X, seam } = body(x, 'kitOpen');
              el('path', { d: seam, fill: 'none', stroke: 'var(--paper)', 'stroke-width': 4 });
              // How the pocket gains width, ending on the opened seam.
              const y = Y(650), a = X(590), b = X(852), c = 'var(--coyote)';
              el('line', { x1: a + 12, y1: y, x2: b - 12, y2: y, stroke: c, 'stroke-width': 6 });
              el('path', { d: `M${a} ${y} l18 -13 l0 26 Z M${b} ${y} l-18 -13 l0 26 Z`, fill: c });
            } },
        ]);
      },
    },
    {
      id: 'sachet',
      title: 'Dans le sachet',
      footer: {
        tag: 'Languettes',
        html: 'Courte ou longue, elles <strong>se permutent par le même bouton pression</strong>. L’arme n’est pas fournie.',
      },
      draw({ el, text }, V) {
        const X = 250, Y = 240;
        el('image', { href: V.kit, x: X, y: Y, width: 1000, height: 1000, style: 'mix-blend-mode: multiply' });
        [[150, 'Clip', '5,5 x 2,5 cm'], [318, 'Courte', 'compact'], [460, 'Longue', 'taille pleine'], [745, 'Holster', '15,2 x 10,1 cm']].forEach(([cx, big, small]) => {
          text(X + cx, 1125, big, { 'text-anchor': 'middle', 'font-size': 44, 'font-weight': 700, fill: 'var(--ink)' });
          text(X + cx, 1160, small, { 'text-anchor': 'middle', 'font-size': 25, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.02em' });
        });
      },
    },
  ],
};
