// Plain black breathable balaclava (SKU CAG-BLK-BREATH, product 148 on prod).
// Facts from the supplier sheets in sources/cagoule-supplier-* and the
// product page. Supplier: 100 % polyester, knit fabric, breathable, quick dry,
// soft touch; this black one measures 35 x 25 cm with a head circumference of
// 55 to 60 cm (size-black). The flat photo (size-flat) is the camo version
// and says 36 cm and 55 to 62 cm: not used. Its feature sheet also lists four
// ways to wear it (full head, neck gaiter, facemask, beanie), kept for the
// dimensions footer. Its "warm" claim is left out: the page sells it for
// spring, summer and mid-season. Photo: the three-quarter gallery packshot,
// the same angle as the supplier's size photo, resized to 1000 px.
window.PRODUCT = {
  eyebrow: 'Cagoule noire unie respirante',

  variants: {
    black: {
      photo: 'photos/cag-blk-breath.jpg',
      points: [[[330, 300]], [[300, 412]], [[430, 880]], [[560, 140]], [[650, 330]], [[700, 700]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la cagoule',
    photoFilter: 'brightness(1.03)',
    // Drawn lower and larger, so the hem sits close to the footer.
    photoBox: { x: 280, y: 310, size: 950 },
    callouts: [
      { side: 'l', y: 540, title: 'Ouverture des yeux', text: 'Le seul passage laissé au visage' },
      { side: 'l', y: 790, title: 'Pan de visage', text: 'Cousu sous les yeux, il se rentre dans le col' },
      { side: 'l', y: 1090, title: 'Tête, visage et cou', text: 'Couverture intégrale, d’une pièce' },
      { side: 'r', y: 450, title: '100 % polyester', text: 'Tricot doux, séchage rapide' },
      { side: 'r', y: 700, title: 'Tricot ajouré', text: 'L’air passe sur toute la surface' },
      { side: 'r', y: 970, title: 'Taille unique', text: 'Le tricot extensible ajuste la coupe' },
    ],
    footer: {
      tag: 'Sous casque',
      html: 'Se glisse <strong>sous un casque</strong> de moto, de ski ou de protection, et se porte avec des <strong>lunettes balistiques</strong>.',
    },
  },

  // The same photo with the supplier's three measures: height on the right,
  // width below, head circumference as a ring round the crown.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'Au repos',
      html: 'Mesures du fabricant, <strong>au repos</strong> : le tricot s’étire. Se porte aussi en <strong>tour de cou, masque ou bonnet</strong>.',
    },
    draw({ el, text, dim, ext, arrow }, P, V) {
      const X = x => 230 + x * 0.95, Y = y => 300 + y * 0.95;
      el('image', { href: V.photo, x: X(0), y: Y(0), width: 950, height: 950, style: 'mix-blend-mode: multiply; filter: brightness(1.03)' });

      // Height: crown to the bottom of the neck, on the right.
      const top = Y(62), bot = Y(932), vx = X(800);
      ext(X(560), top, vx + 14, top); ext(X(640), bot, vx + 14, bot);
      arrow(vx, top, vx, bot);
      text(vx + 24, (top + bot) / 2 + 18, '35 cm', { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });

      // Width: the face panel's front edge to the back of the neck, below.
      const l = X(244), r = X(755), hy = bot + 46;
      ext(l, Y(420), l, hy + 14); ext(r, Y(740), r, hy + 14);
      dim(l, hy, r, hy, '25 cm', 52);

      // Head circumference: a ring round the crown, its back half dashed.
      const cx = X(490), cy = Y(215), rx = 272, ry = 44;
      el('path', { d: `M${cx - rx} ${cy} A${rx} ${ry} 0 0 1 ${cx + rx} ${cy}`, fill: 'none', stroke: 'var(--ink)', 'stroke-width': 2.5, 'stroke-dasharray': '8 7' });
      el('path', { d: `M${cx - rx} ${cy} A${rx} ${ry} 0 0 0 ${cx + rx} ${cy}`, fill: 'none', stroke: '#fff', 'stroke-width': 7 });
      el('path', { d: `M${cx - rx} ${cy} A${rx} ${ry} 0 0 0 ${cx + rx} ${cy}`, fill: 'none', stroke: 'var(--ink)', 'stroke-width': 2.5 });
      text(cx - rx - 24, cy - 8, 'Tour de tête', { 'text-anchor': 'end', 'font-size': 34, 'font-weight': 700, fill: 'var(--ink)' });
      text(cx - rx - 24, cy + 40, '55 à 60 cm', { 'text-anchor': 'end', 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
    },
  },
};
