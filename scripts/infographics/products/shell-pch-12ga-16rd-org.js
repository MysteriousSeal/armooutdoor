// Orange pouch for sixteen 12-gauge shells (SKU SHELL-PCH-12GA-16RD-ORG, product 377
// on prod). Facts from the product page on armooutdoor.fr (twelve shells lying in
// two staggered columns, four in elastic loops on the front, two strips of six on
// hook-and-loop that come out, hook-and-loop flap, Oxford nylon, two snap straps at
// the back for PALS or a belt, sold empty, 140 g shipped) and the supplier sheet in
// sources/porte-cartouches-16-supplier-size.png (17,2 x 8,4 x 5,5 cm, drawn there on
// a three-quarter view too). Photos from the gallery, resized to 1000 px: loaded
// front for the anatomy, empty (-empty.jpg) for the dimensions, the back (-back.jpg)
// and the strips (-strips.jpg) for the Mode d'emploi. A three-quarter view is not to
// scale, so no coin.
window.PRODUCT = {
  eyebrow: 'Porte-cartouches calibre 12 orange, 16 coups',

  variants: {
    pouch: {
      photo: 'photos/shell-pch-12ga-16rd-org.jpg',
      empty: 'photos/shell-pch-12ga-16rd-org-empty.jpg',
      back: 'photos/shell-pch-12ga-16rd-org-back.jpg',
      strips: 'photos/shell-pch-12ga-16rd-org-strips.jpg',
      // Corners of the empty photo: front left, front right and side right at the
      // bottom, then the top of the side edge.
      corners: { l: [256, 900], m: [582, 964], r: [724, 885], t: 140 },
      points: [[[400, 240]], [[440, 380]], [[400, 600]], [[480, 115]], [[644, 520]], [[712, 800]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la pochette',
    photoFilter: 'brightness(1.03)',
    photoBox: { x: 275, y: 310, size: 950 },
    callouts: [
      { side: 'l', y: 420, title: 'Panneau à écusson', text: 'Velours orange, de la même teinte que la toile' },
      { side: 'l', y: 660, title: 'Rabat agrippant', text: 'Se soulève du pouce, à une main' },
      { side: 'l', y: 920, title: '4 passants élastiques', text: 'Cousus à même la face avant' },
      { side: 'r', y: 400, title: 'Arêtes surliées', text: 'Sangle cousue partout, rabat compris' },
      { side: 'r', y: 680, title: '12 cartouches couchées', text: 'Deux colonnes de six décalées' },
      { side: 'r', y: 960, title: 'Toile de nylon Oxford', text: 'Orange vif uni, velours du rabat compris' },
    ],
    footer: {
      tag: 'Se voit',
      html: 'Posé sur un banc de ball-trap ou tombé dans l’herbe haute, il <strong>se retrouve du premier coup d’œil</strong>. Le contraire d’un camouflage.',
    },
  },

  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'Calibre 12',
      html: 'Passants taillés pour <strong>la cartouche de 12</strong>. Le fabricant annonce aussi les calibres 16 et 20, <strong>avec moins de serrage</strong>.',
    },
    draw({ el, text, ext, arrow }, P, V) {
      const s = 0.8, X = x => 130 + x * s, Y = y => 290 + y * s;
      el('image', { href: V.empty, x: X(0), y: Y(0), width: 1000 * s, height: 1000 * s, style: 'mix-blend-mode: multiply; filter: brightness(1.03)' });
      const { l, m, r, t } = V.corners, big = { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' };

      // Height: up the side's rear edge.
      const vx = X(r[0]) + 60;
      ext(X(r[0]) + 10, Y(t), vx + 14, Y(t)); ext(X(r[0]) + 10, Y(r[1]), vx + 14, Y(r[1]));
      arrow(vx, Y(t), vx, Y(r[1]));
      text(vx + 24, (Y(t) + Y(r[1])) / 2 + 18, '17,2 cm', big);

      // Width and depth: under the two bottom edges, following them.
      const off = 46;
      ext(X(l[0]), Y(l[1]) + 10, X(l[0]), Y(l[1]) + off + 12); ext(X(m[0]), Y(m[1]) + 10, X(m[0]), Y(m[1]) + off + 12); ext(X(r[0]), Y(r[1]) + 10, X(r[0]), Y(r[1]) + off + 12);
      arrow(X(l[0]), Y(l[1]) + off, X(m[0]), Y(m[1]) + off);
      arrow(X(m[0]), Y(m[1]) + off, X(r[0]), Y(r[1]) + off);
      text((X(l[0]) + X(m[0])) / 2 - 20, (Y(l[1]) + Y(m[1])) / 2 + off + 62, '8,4 cm', { ...big, 'text-anchor': 'middle' });
      text(X(r[0]) + 14, Y(r[1]) + off + 44, '5,5 cm', big);

      [['16', 'cartouches de calibre 12'], ['12 + 4', 'dans le corps, en façade'], ['140 g', 'poids']].forEach(([b, small], i) => {
        const fy = 500 + i * 110;
        text(1090, fy, b, big);
        text(1090, fy + 34, small, { 'font-size': 25, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.02em' });
      });
    },
  },

  effect: {
    title: 'Mode d’emploi',
    footer: {
      tag: 'Livré vide',
      html: 'Cartouches non fournies. Une bande garnie <strong>tient dans une poche de veste</strong> : une recharge d’avance, sans <strong>deuxième sac à la ceinture</strong>.',
    },
    draw(kit, V) {
      const { el } = kit;
      // A photo in a coyote-ringed circle, centred on one point of it.
      const zoom = (id, href, px, py, sc) => (k, x) => {
        const cx = x + 280, cy = 730, R = 250;
        const cp = el('clipPath', { id }, el('defs', {}));
        el('circle', { cx, cy, r: R }, cp);
        el('image', { href, x: cx - px * sc, y: cy - py * sc, width: 1000 * sc, height: 1000 * sc, 'clip-path': `url(#${id})` });
        el('circle', { cx, cy, r: R, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 5 });
      };
      Infographic.pastilles.usage(kit, [
        { head: 'AU DOS', title: 'Deux brides à pression', line: 'dans les rangées PALS, ou autour d’une ceinture.', draw: zoom('backZoom', V.back, 600, 500, 0.5) },
        { head: 'À L’INTÉRIEUR', title: 'Deux bandes de six', line: 'se décollent du velours, garnies ou vides.', draw: zoom('stripZoom', V.strips, 520, 500, 0.46) },
      ]);
    },
  },
};
