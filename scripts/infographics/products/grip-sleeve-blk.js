// Black rubber grip sleeve for pistols (SKU GRIP-SLEEVE-BLK, product 369 on
// prod), the same mould as grip-sleeve-snd and photographed the same way.
// Facts from the product page; sizes as there, 7.2 x 5.4 cm at rest, drawn
// as arrows without the coin for the same reason as the sand one.
window.PRODUCT = {
  eyebrow: 'Grip caoutchouc antidérapant pour pistolet',

  variants: {
    black: {
      photo: 'photos/grip-sleeve-blk.jpg',
      points: [[[230, 460]], [[420, 740]], [[597, 340], [515, 405]], [[880, 450], [826, 655]], [[700, 862]]],
    },
  },

  anatomy: {
    title: 'Anatomie du grip',
    photoFilter: 'brightness(1.03)',
    callouts: [
      { side: 'l', y: 560, title: 'Nervures', text: 'Transversales, sur la face arrière' },
      { side: 'l', y: 900, title: 'Panneau picoté', text: 'Sur les flancs, accroche sous la paume' },
      { side: 'r', y: 470, title: 'Ajours en losange', text: 'Un groupe de trous sur chaque flanc' },
      { side: 'r', y: 760, title: 'Trois gorges moulées', text: 'Replacent les doigts au même endroit' },
      { side: 'r', y: 1010, title: 'Caoutchouc souple', text: 'S’étire pour enfiler la poignée' },
    ],
    footer: {
      tag: 'Pose',
      html: 'S’enfile <strong>par le bas de la poignée</strong>, sans outil ni modification, et se retire <strong>sans laisser de trace</strong>.',
    },
  },

  // Labelled arrows along the back and bottom edges, the way the supplier
  // shows them; no coin, since the photo is not a true-scale reference.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'Au repos',
      html: 'Mesures prises <strong>au repos</strong> : le caoutchouc s’étire sur la poignée et ajoute quelques millimètres, à vérifier avec un <strong>holster ajusté</strong>.',
    },
    draw({ el, text, ext, arrow, section }, P, V) {
      // Photo px to page: drawn at (280, 290), 0.84 scale.
      const X = x => 280 + x * 0.84, Y = y => 290 + y * 0.84;
      el('image', { href: V.photo, x: X(0), y: Y(0), width: 840, height: 840, style: 'mix-blend-mode: multiply; filter: brightness(1.03)' });
      section(150, 350, 'LE MANCHON');

      // Offset a segment outward along its normal, draw extension lines and
      // the arrow, then the label beyond its middle.
      function measure([ax, ay], [bx, by], off, label, [lx, ly], anchor) {
        const A = [X(ax), Y(ay)], B = [X(bx), Y(by)];
        const dx = B[0] - A[0], dy = B[1] - A[1], len = Math.hypot(dx, dy);
        const nx = -dy / len * off, ny = dx / len * off;
        ext(A[0], A[1], A[0] + nx * 1.15, A[1] + ny * 1.15);
        ext(B[0], B[1], B[0] + nx * 1.15, B[1] + ny * 1.15);
        arrow(A[0] + nx, A[1] + ny, B[0] + nx, B[1] + ny);
        text(lx, ly, label, { 'text-anchor': anchor, 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
      }
      // Back edge, bottom-left corner up to the top of the sleeve.
      measure([103, 840], [340, 105], -70, '7,2 cm', [X(218) - 90, Y(476) + 18], 'end');
      // Bottom edge, back to front.
      measure([103, 845], [762, 885], 70, '5,4 cm', [X(425), Y(865) + 140], 'middle');
    },
  },
};
