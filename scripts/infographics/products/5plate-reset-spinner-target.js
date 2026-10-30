// Self-resetting metal target, 5 plates, for air guns (SKU
// 5PLATE-RESET-SPINNER-TARGET, product 228 on prod). Facts from the supplier
// sheet in sources/cible-metal-5-plaques-supplier-size.png and its text (four
// drooping plates turn up and ride on the horizontal bar when hit; the top
// plate resets them; .177 and .22 air rifles; 10 target stickers; alloy steel
// with anti-rust black coating; plates 2.5 mm; 36 x 26.5 cm) and from the
// product page. The anatomy uses the gallery photo (its ghosted plates are
// the maker's motion effect: no marker on them). The dimensions sheet uses
// a clean straight-on photo the user supplied (2026-10-30), squared into
// photos/<sku>-front.jpg; its 36 cm runs from the top plate to the feet and
// its 26.5 cm across the legs, as the supplier drew them (the photo's
// proportions match: 977 x 725 px).
window.PRODUCT = {
  eyebrow: 'Cible métal 5 plaques basculantes',

  variants: {
    steel: {
      photo: 'photos/5plate-reset-spinner-target.jpg',
      front: 'photos/5plate-reset-spinner-target-front.jpg',
      points: [[[418, 108]], [[200, 205]], [[318, 508]], [[650, 447]], [[718, 578]], [[712, 880]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
    photoFilter: 'brightness(1.05)',
    callouts: [
      { side: 'l', y: 380, title: 'Plaque haute', text: 'Un tir dessus fait retomber les quatre autres' },
      { side: 'l', y: 610, title: 'Bâti tubulaire', text: 'Acier allié, revêtement noir anti-rouille' },
      { side: 'l', y: 860, title: 'Quatre plaques basculantes', text: 'Acier de 2,5 mm, se relèvent à l’impact' },
      { side: 'r', y: 560, title: 'Traverse', text: 'Les plaques touchées y restent relevées' },
      { side: 'r', y: 810, title: 'Faces orange', text: 'Mire noire, 10 autocollants de rechange' },
      { side: 'r', y: 1060, title: 'Pieds en A', text: 'Repliables pour le transport' },
    ],
    footer: {
      tag: 'Air comprimé',
      html: 'Uniquement pour les armes à air comprimé en <strong>4,5 mm (.177) et 5,5 mm (.22)</strong>. Portez des <strong>lunettes de protection</strong>.',
    },
  },

  // The supplier's front view, drawn at (260, 290) scaled 0.9, with its two
  // measures where the supplier put them.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'Fabricant',
      html: 'Mesures du fabricant, <strong>plaque haute comprise</strong>. Les pieds en A <strong>se replient</strong> pour le transport.',
    },
    draw({ el, text, dim, ext, arrow, section }, P, V) {
      const X = x => 290 + x * 0.88, Y = y => 300 + y * 0.88;
      el('image', { href: V.front, x: X(0), y: Y(0), width: 880, height: 880, style: 'mix-blend-mode: multiply' });
      section(150, 280, 'VUE DE FACE');

      // Height: top of the reset plate to the feet, on the left.
      const top = Y(5), bot = Y(982), vx = 360;
      ext(X(440), top, vx - 14, top); ext(X(130), bot, vx - 14, bot);
      arrow(vx, top, vx, bot);
      text(vx - 18, (top + bot) / 2 + 18, '36 cm', { 'text-anchor': 'end', 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });

      // Width: between the legs, below.
      const l = X(135), r = X(860), hy = bot + 44;
      ext(l, bot + 6, l, hy + 14); ext(r, bot + 6, r, hy + 14);
      dim(l, hy, r, hy, '26,5 cm', 52);

      [['5 plaques', '4 basculantes, 1 de remise à zéro'], ['2,5 mm', 'd’épaisseur de plaque'], ['10', 'autocollants de rechange']].forEach(([big, small], i) => {
        const fy = 470 + i * 120;
        text(1120, fy, big, { 'font-size': 50, 'font-weight': 700, fill: 'var(--ink)' });
        text(1120, fy + 32, small, { 'font-size': 24, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.02em' });
      });
    },
  },
};
