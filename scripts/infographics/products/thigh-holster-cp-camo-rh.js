// Right-handed tactical thigh holster, multi-terrain camo (SKU
// THIGH-HOLSTER-CP-CAMO-RH, product 234 on prod). Facts from the product page
// on armooutdoor.fr (47 cm from the top strap to the bottom, belt loop with
// two snaps, side-release buckle to set the height, two 58 cm thigh straps
// with an anti-slip back and quick-release buckles, three hook-and-loop bands
// on the back of the pocket, up to 35 cm around, flap pocket on the side for a
// torch, grip strap, nylon, 300 g, sold without a gun) and the supplier's size
// sheet in sources/holster-cuisse-supplier-size.jpg, from the Temu listing
// (47 x 5 cm, 8 cm over the buckle, 7 cm from the holster to the buckle end).
// Photos from the gallery, whitened: the front (photos/<sku>.jpg, x 315 to
// 690, y 85 to 920, 17,8 px per cm both ways, so it gets the coin), the back
// laid out (-back.jpg) and the buckles (-straps.jpg).
window.PRODUCT = {
  eyebrow: 'Holster de cuisse multi-terrain droitier',

  variants: {
    holster: {
      photo: 'photos/thigh-holster-cp-camo-rh.jpg',
      back: 'photos/thigh-holster-cp-camo-rh-back.jpg',
      straps: 'photos/thigh-holster-cp-camo-rh-straps.jpg',
      points: [[[470, 130]], [[515, 590]], [[335, 615]], [[420, 860]], [[540, 330]], [[650, 615]], [[575, 760]]],
    },
  },

  anatomy: {
    title: 'Anatomie du holster',
    photoBox: { x: 250, y: 210, size: 1000 },
    callouts: [
      { side: 'l', y: 360, title: 'Passant de ceinture', text: 'Fermé par deux boutons pression' },
      { side: 'l', y: 560, title: 'Sangle de poignée', text: 'Passe sur la poignée, fermée par scratch' },
      { side: 'l', y: 760, title: 'Sangles de cuisse', text: 'Deux sangles de 58 cm, dos antidérapant' },
      { side: 'l', y: 960, title: 'Multi-terrain', text: 'Nylon camouflage, sangles coyote' },
      { side: 'r', y: 400, title: 'Boucle haute', text: 'À dégagement latéral, pour régler la hauteur' },
      { side: 'r', y: 660, title: 'Boucles rapides', text: 'Sur les deux sangles, ouvertes d’une pression' },
      { side: 'r', y: 920, title: 'Poche à rabat', text: 'Sur le flanc, pour une lampe torche' },
    ],
    footer: {
      tag: 'Sans arme',
      html: 'Modèle <strong>pour droitier</strong>. Holster livré seul, <strong>arme non fournie</strong>.',
    },
  },

  // A square-on view, true to scale, with the supplier's four measures.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'Hauteur',
      html: 'Les <strong>47 cm</strong> vont de la sangle haute au bas du holster. La boucle haute <strong>règle la hauteur</strong> sur la cuisse.',
    },
    draw({ el, text, ext, arrow }, P, V) {
      const s = 0.95, X = x => 60 + x * s, Y = y => 235 + y * s;
      el('image', { href: V.photo, x: X(0), y: Y(0), width: 1000 * s, height: 1000 * s, style: 'mix-blend-mode: multiply' });
      const big = { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' };
      // Total height, on the left.
      const t = Y(85), b = Y(920), vx = X(315) - 55;
      ext(X(455) - 12, t, vx - 14, t); ext(X(395) - 12, b, vx - 14, b);
      arrow(vx, t, vx, b);
      text(vx - 18, (t + b) / 2 + 18, '47 cm', { 'text-anchor': 'end', ...big });
      // Strap width, above it.
      const sl = X(455), sr = X(550), sy = t - 30;
      ext(sl, t - 8, sl, sy - 12); ext(sr, t - 8, sr, sy - 12);
      arrow(sl, sy, sr, sy);
      text(sr + 22, sy + 18, '5 cm', big);
      // Over the buckle, on its right.
      const bt = Y(258), bb = Y(395), bx = X(560) + 45;
      ext(X(560) + 8, bt, bx + 14, bt); ext(X(560) + 8, bb, bx + 14, bb);
      arrow(bx, bt, bx, bb);
      text(bx + 24, (bt + bb) / 2 + 18, '8 cm', big);
      // From the holster's edge to the end of the thigh buckle.
      const dl = X(560), dr = X(688), dy = Y(535);
      ext(dl, Y(572), dl, dy - 12); ext(dr, Y(572), dr, dy - 12);
      arrow(dl, dy, dr, dy);
      text(dr + 22, dy + 18, '7 cm', big);
      [['58 cm', 'deux sangles de cuisse'], ['35 cm', 'de tour de logement, au plus'], ['Droitier', 'main droite'], ['300 g', 'poids'], ['Pièce de 1 €', '23,25 mm, à la même échelle']].forEach(([head, small], i) => {
        const fy = 480 + i * 105;
        text(1040, fy, head, big);
        text(1040, fy + 34, small, { 'font-size': 25, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.02em' });
      });
      const cr = 23.25 / 2 / 10 * 17.8 * s;
      Infographic.targets.coin({ el, text }, 1040 + cr, 480 + 4 * 105 + 34 + 26 + cr, cr, { caption: false });
    },
  },

  effect: {
    title: 'Réglages',
    footer: {
      tag: 'Calage',
      html: 'Bien serrées, les sangles au <strong>dos antidérapant</strong> empêchent le holster de <strong>pivoter ou de glisser</strong> vers le genou.',
    },
    draw(kit, V) {
      const { el } = kit;
      const zoom = (x, href, [px, py], scale, id) => {
        const cx = x + 280, cy = 730, R = 250;
        const cp = el('clipPath', { id }, el('defs', {}));
        el('circle', { cx, cy, r: R }, cp);
        el('image', { href, x: cx - px * scale, y: cy - py * scale, width: 1000 * scale, height: 1000 * scale, 'clip-path': `url(#${id})` });
        el('circle', { cx, cy, r: R, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 5 });
      };
      Infographic.pastilles.usage(kit, [
        { head: 'LE LOGEMENT', title: 'Trois bandes de scratch', line: 'on le resserre ou l’élargit, jusqu’à 35 cm de tour.',
          draw: (k, x) => zoom(x, V.back, [560, 640], 0.72, 'backZoom') },
        { head: 'LA JAMBE', title: 'Boucles à ergots', line: 'ouvertes, le holster s’ôte sans glisser sur la jambe.',
          draw: (k, x) => zoom(x, V.straps, [330, 680], 1.05, 'buckleZoom') },
      ]);
    },
  },
};
