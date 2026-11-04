// Mini round case for 55 .22 LR rounds, black lid (SKU 22LR-55RD-MINI-AMMO-CASE-BLK, product 252
// on prod). Facts from the product page and the supplier sheets in
// sources/boite-22lr-supplier-* (55 .22 rounds, palm size, 7.1 x 3.4 cm, for
// hunting, field training and storage). The supplier's 2 3/4 x 1 3/8 in
// line is the same box in inches; the sheets use the page's 7,1 x 3,4 cm.
// Photos: the open box (gallery image 1) for the anatomy, the closed box
// (image 2, photos/<sku>-closed.jpg: box x 166 to 852, y 166 to 812) for the
// dimensions, measured as the supplier and our gallery do on this view:
// width across the base, height over the whole box. No coin: a three-quarter
// view is not a true-scale drawing.
const SERIES = [['noir', 'blk'], ['rouge', 'red'], ['vert', 'grn']];
const SELF = 'blk';

window.PRODUCT = {
  eyebrow: 'Mini boîte 55 munitions .22 LR noire',

  variants: {
    case: {
      photo: 'photos/22lr-55rd-mini-ammo-case-blk.jpg',
      closed: 'photos/22lr-55rd-mini-ammo-case-blk-closed.jpg',
      points: [[[300, 470]], [[300, 690]], [[812, 338]], [[670, 425]], [[690, 660]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la boîte',
    callouts: [
      { side: 'l', y: 470, title: '55 alvéoles', text: 'En rangées décalées, chaque cartouche debout' },
      { side: 'l', y: 790, title: 'Base cannelée', text: 'Striée sur tout son tour, pour la prise' },
      { side: 'r', y: 400, title: 'Couvercle à visser', text: 'Il ne s’ouvre pas tout seul dans un sac' },
      { side: 'r', y: 620, title: 'Marquage 22LR', text: 'Corps et couvercle noirs, 22LR en blanc' },
      { side: 'r', y: 850, title: 'Reliefs striés', text: 'Pour dévisser sans glisser, même ganté' },
    ],
    footer: {
      tag: 'Tout en noir',
      html: 'La version la plus sobre de la série : le calibre reste <strong>lisible dans un sac</strong>, en blanc sur le noir.',
    },
  },

  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'Format poche',
      html: 'De la <strong>taille d’une paume</strong> : le palet se glisse dans une poche ou une trousse avec <strong>55 cartouches de .22 LR</strong>.',
    },
    draw({ el, text, dim, ext, arrow }, P, V) {
      // The closed box at (250, 300) scaled 0.86.
      const X = x => 250 + x * 0.86, Y = y => 300 + y * 0.86;
      el('image', { href: V.closed, x: X(0), y: Y(0), width: 860, height: 860, style: 'mix-blend-mode: multiply' });
      const l = X(166), r = X(852), t = Y(166), b = Y(812), vx = l - 50, hy = b + 50;
      ext(X(400), t, vx - 14, t); ext(X(300), b, vx - 14, b);
      arrow(vx, t, vx, b);
      text(vx - 18, (t + b) / 2 + 18, '3,4 cm', { 'text-anchor': 'end', 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
      ext(l, Y(560), l, hy + 14); ext(r, Y(560), r, hy + 14);
      dim(l, hy, r, hy, '7,1 cm', 52);
      [['55', 'cartouches .22 LR'], ['60 g', 'poids de la boîte']].forEach(([big, small], i) => {
        const fy = 560 + i * 120;
        text(1170, fy, big, { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
        text(1170, fy + 34, small, { 'font-size': 25, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.02em' });
      });
    },
  },

  // The series: three lid colours to tell batches apart, this one ringed.
  effect: {
    title: 'Un couvercle par lot',
    footer: {
      tag: 'Série',
      html: 'La série existe avec couvercle <strong>noir, rouge ou vert</strong>, 22LR imprimé sur chacun : <strong>une couleur par munition</strong>.',
    },
    draw({ el, text }) {
      SERIES.forEach(([name, key], i) => {
        const cx = 300 + i * 450, cy = 720, s = 0.42;
        if (key === SELF) el('circle', { cx, cy, r: 205, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 6 });
        el('image', { href: `photos/22lr-55rd-mini-ammo-case-${key}-closed.jpg`, x: cx - 509 * s, y: cy - 489 * s, width: 1000 * s, height: 1000 * s, style: 'mix-blend-mode: multiply' });
        text(cx, cy + 270, `Couvercle ${name}`, { 'text-anchor': 'middle', 'font-size': 40, 'font-weight': 700, fill: key === SELF ? 'var(--ink)' : 'var(--mute)' });
      });
      text(750, 1110, 'Le tri se fait à la couleur, sans rien ouvrir.', { 'text-anchor': 'middle', style: 'font-family: Barlow', 'font-size': 32, fill: 'var(--mute)' });
    },
  },
};
