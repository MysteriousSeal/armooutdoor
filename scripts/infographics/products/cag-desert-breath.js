// Desert DPM camo breathable balaclava (SKU CAG-DESERT-BREATH, product 187
// on prod). Same cut and supplier as cag-blk-breath (sources/cagoule-supplier-*):
// 35 x 25 cm, head 55 to 60 cm, the four ways to wear it. The rest comes from
// this product's own page (Desert DPM brush strokes in cream, sand and brown,
// a face panel ending in a point, aimed at dry open ground). Photo: the
// three-quarter gallery packshot, resized to 1000 px; extents x 254 to 757,
// y 73 to 928. The dimensions drawing is centred, as on cag-brwnsnd-breath.
window.PRODUCT = {
  eyebrow: 'Cagoule camouflage désert respirante',

  variants: {
    desert: {
      photo: 'photos/cag-desert-breath.jpg',
      points: [[[330, 320]], [[290, 430]], [[430, 880]], [[580, 190]], [[640, 560]], [[705, 720]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la cagoule',
    photoFilter: 'brightness(1.03)',
    // Drawn lower and larger, so the hem sits close to the footer.
    photoBox: { x: 280, y: 310, size: 950 },
    callouts: [
      { side: 'l', y: 540, title: 'Ouverture des yeux', text: 'Le seul passage laissé au visage' },
      { side: 'l', y: 790, title: 'Pan de visage', text: 'Remonte en pointe sous le nez, descend sur le cou' },
      { side: 'l', y: 1090, title: 'Tête, visage et cou', text: 'Couverture intégrale, d’une pièce' },
      { side: 'r', y: 450, title: 'Désert DPM', text: 'Traînées crème, sable et brun' },
      { side: 'r', y: 700, title: 'Polyester extensible', text: 'Respirant, séchage rapide' },
      { side: 'r', y: 970, title: 'Taille unique', text: 'La maille fine suit les formes' },
    ],
    footer: {
      tag: 'Terrain sec',
      html: 'Des tons chauds pour la <strong>roche claire, le sable et l’herbe brûlée</strong> : garrigue, carrière, chaume d’été.',
    },
  },

  // The same photo with the supplier's three measures: height on the right,
  // width below, head circumference as a ring round the crown.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'Au repos',
      html: 'Mesures du fabricant, <strong>au repos</strong> : la maille s’étire. Se porte aussi en <strong>tour de cou, masque ou bonnet</strong>.',
    },
    draw({ el, text, dim, ext, arrow }, P, V) {
      // The balaclava's extents (x 254 to 757) centred on the page.
      const X = x => 750 + (x - (254 + 757) / 2) * 0.95, Y = y => 300 + y * 0.95;
      el('image', { href: V.photo, x: X(0), y: Y(0), width: 950, height: 950, style: 'mix-blend-mode: multiply; filter: brightness(1.03)' });

      // Height: crown to the bottom of the neck, on the right.
      const top = Y(73), bot = Y(928), vx = X(800);
      ext(X(530), top, vx + 14, top); ext(X(640), bot, vx + 14, bot);
      arrow(vx, top, vx, bot);
      text(vx + 24, (top + bot) / 2 + 18, '35 cm', { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });

      // Width: the face panel's front edge to the back of the neck, below.
      const l = X(254), r = X(757), hy = bot + 46;
      ext(l, Y(420), l, hy + 14); ext(r, Y(760), r, hy + 14);
      dim(l, hy, r, hy, '25 cm', 52);

      // Head circumference: a ring round the crown, its back half dashed.
      const cx = X(500), cy = Y(215), rx = 272, ry = 44;
      el('path', { d: `M${cx - rx} ${cy} A${rx} ${ry} 0 0 1 ${cx + rx} ${cy}`, fill: 'none', stroke: 'var(--ink)', 'stroke-width': 2.5, 'stroke-dasharray': '8 7' });
      el('path', { d: `M${cx - rx} ${cy} A${rx} ${ry} 0 0 0 ${cx + rx} ${cy}`, fill: 'none', stroke: '#fff', 'stroke-width': 7 });
      el('path', { d: `M${cx - rx} ${cy} A${rx} ${ry} 0 0 0 ${cx + rx} ${cy}`, fill: 'none', stroke: 'var(--ink)', 'stroke-width': 2.5 });
      text(cx - rx - 24, cy - 8, 'Tour de tête', { 'text-anchor': 'end', 'font-size': 34, 'font-weight': 700, fill: 'var(--ink)' });
      text(cx - rx - 24, cy + 40, '55 à 60 cm', { 'text-anchor': 'end', 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
    },
  },
};
