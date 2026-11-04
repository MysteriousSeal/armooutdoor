// Cagoule camouflage python forêt breathable balaclava (SKU CAG-PYFOR-BREATH, product 360 on
// prod). Same cut and supplier as cag-blk-breath (sources/cagoule-supplier-*):
// 35 x 25 cm, head 55 to 60 cm, the four ways to wear it. The rest comes from
// this product's own page: olive, brown and black scales outlined in black, a
// smooth surface with no perforation, two panels, aimed at dense undergrowth.
// Photo: the three-quarter gallery packshot, resized to 1000 px; extents
// x 240 to 759, y 61 to 926.
window.PRODUCT = {
  eyebrow: 'Cagoule camouflage python forêt respirante',

  variants: {
    python: {
      photo: 'photos/cag-pyfor-breath.jpg',
      points: [[[330, 300]], [[290, 405]], [[430, 880]], [[560, 140]], [[640, 320]], [[715, 720]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la cagoule',
    photoFilter: 'brightness(1.03)',
    // Drawn lower and larger, so the hem sits close to the footer.
    photoBox: { x: 280, y: 310, size: 950 },
    callouts: [
      { side: 'l', y: 540, title: 'Ouverture des yeux', text: 'Le seul passage laissé au visage' },
      { side: 'l', y: 790, title: 'Pan de visage', text: 'Remonte sous les yeux, file jusqu’à la nuque' },
      { side: 'l', y: 1090, title: 'Tête, visage et cou', text: 'Couverture intégrale, d’une pièce' },
      { side: 'r', y: 450, title: 'Python forêt', text: 'Écailles vert olive, brunes et noires' },
      { side: 'r', y: 700, title: 'Polyester extensible', text: 'Surface lisse, sans perforation' },
      { side: 'r', y: 970, title: 'Taille unique', text: 'Le polyester élastique suit les formes' },
    ],
    footer: {
      tag: 'Sous-bois',
      html: 'Elle s’efface dans les <strong>taillis, ronciers et lisières à l’ombre</strong>. Contre un ciel ouvert ou un mur clair, <strong>une forme sombre se repère</strong>.',
    },
  },

  // The same photo with the supplier's three measures: height on the right,
  // width below, head circumference as a ring round the crown.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'Au repos',
      html: 'Mesures du fabricant, <strong>au repos</strong> : le tissu s’étire. Se porte aussi en <strong>tour de cou, masque ou bonnet</strong>.',
    },
    draw({ el, text, dim, ext, arrow }, P, V) {
      // The balaclava's extents (x 240 to 759) centred on the page.
      const X = x => 750 + (x - (240 + 759) / 2) * 0.95, Y = y => 300 + y * 0.95;
      el('image', { href: V.photo, x: X(0), y: Y(0), width: 950, height: 950, style: 'mix-blend-mode: multiply; filter: brightness(1.03)' });

      // Height: crown to the bottom of the neck, on the right.
      const top = Y(61), bot = Y(926), vx = X(794);
      ext(X(530), top, vx + 14, top); ext(X(640), bot, vx + 14, bot);
      arrow(vx, top, vx, bot);
      text(vx + 24, (top + bot) / 2 + 18, '35 cm', { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });

      // Width: the face panel's front edge to the back of the neck, below.
      const l = X(240), r = X(759), hy = bot + 46;
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
