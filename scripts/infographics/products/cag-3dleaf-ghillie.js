// Ghillie balaclava with 3D leaf cut-outs (SKU CAG-3DLEAF-GHILLIE, product 80
// on prod). Measures from the supplier sheet the user sent
// (sources/cag-3dleaf-ghillie-supplier-dimensions.png): 25 cm across the head,
// 45 cm from the crown to the bottom of the bib, drawn there the same way. The
// rest comes from this product's own page: leaf cut-outs breaking the outline,
// a wide elastic eye band, a long bib, plain polyester, hand wash. Photo: the
// front gallery packshot, resized to 1000 px.
window.PRODUCT = {
  eyebrow: 'Cagoule ghillie feuillage 3D',

  variants: {
    leaf: {
      photo: 'photos/cag-3dleaf-ghillie.jpg',
      points: [[[480, 300]], [[255, 400]], [[360, 850]], [[540, 170]], [[620, 480]], [[740, 690]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la cagoule',
    photoFilter: 'brightness(1.03)',
    // Drawn lower and larger, so the hem sits close to the footer.
    photoBox: { x: 280, y: 310, size: 950 },
    callouts: [
      { side: 'l', y: 540, title: 'Bandeau des yeux', text: 'Large ouverture froncée par un élastique' },
      { side: 'l', y: 790, title: 'Découpes en feuilles', text: 'Elles dépassent du contour de la tête' },
      { side: 'l', y: 1090, title: 'Longue bavette', text: 'Couvre le cou jusqu’aux épaules' },
      { side: 'r', y: 450, title: 'Feuilles et branches', text: 'Imprimé photo-réaliste brun, vert et beige' },
      { side: 'r', y: 700, title: 'Polyester', text: 'Respirant et léger' },
      { side: 'r', y: 970, title: 'Taille unique', text: 'Tissu extensible' },
    ],
    footer: {
      tag: 'Le relief',
      html: 'Une tête lisse garde sa <strong>forme ronde</strong>, que l’œil reconnaît. Ici, les découpes <strong>brisent le contour</strong> : la silhouette se mélange au feuillage.',
    },
  },

  // The supplier's two measures, drawn where its sheet puts them: the head's
  // width above the crown, the full height beside the bib.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'Fragile',
      html: 'Mesures du fabricant. Les découpes restent fragiles : un <strong>lavage à la main</strong> les préserve.',
    },
    draw({ el, text, dim, ext, arrow }, P, V) {
      const X = x => 750 + (x - 500) * 0.95, Y = y => 300 + y * 0.95;
      el('image', { href: V.photo, x: X(0), y: Y(0), width: 950, height: 950, style: 'mix-blend-mode: multiply; filter: brightness(1.03)' });

      // Height: crown to the bottom of the bib, beside it.
      const top = Y(45), bot = Y(950), vx = X(910);
      ext(X(560), top, vx + 14, top); ext(X(640), bot, vx + 14, bot);
      arrow(vx, top, vx, bot);
      text(vx + 24, (top + bot) / 2 + 18, '45 cm', { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });

      // Head width: above the crown, between the head's sides.
      const l = X(290), r = X(680), hy = Y(10);
      ext(l, Y(200), l, hy - 14); ext(r, Y(200), r, hy - 14);
      dim(l, hy, r, hy, '25 cm', 52);
    },
  },
};
