// Olive green hook-and-loop suppressor cover, in five lengths (product 285 on
// prod, variants SLC-GRN-NYL-FIRE-10, -13, -155, -18, -235), the same cover as
// the black slc-blk-nyl-fire in another colour, same facts and sizes. Facts from the product
// page (woven nylon, wraps and closes by hook-and-loop, 2 straps up to 13 cm
// and 3 from 15,5 cm, a pull tab sewn on each strap, an elastic cord wound in
// the middle, bound edges, open at both ends, no heat rating, 50 g), the
// AliExpress listing the user saved ("fireproof material": the user chose to
// call it heat resistant, kept as the maker's claim) and each
// variant's supplier size image on the gallery: laid flat, length x unrolled
// height of 10 x 14, 13 x 15,5, 15,5 x 15,5, 18 x 15,5 and 23,5 x 18 cm, for
// 4, 5, 6, 7 and 9 in. Photos: each size rolled, sent by the user
// (photos/slc-grn-nyl-fire-<size>.jpg, whitened, the four shorter ones cropped
// to fill the frame, so not to one scale); the flat panels are drawn to scale
// from those measures, and each size laid flat, also from the user
// (-<size>-flat.jpg). On those the panel is 7 to 19 % wider for its height
// than the supplier's figures, so they carry labelled arrows and no coin.
// flat: the panel on that photo, left, top, right, bottom, straps excluded.
// points: cord, nylon, open end, binding, pull tab, strap, on that photo.
const SIZES = [
  { key: '10', len: 10, h: 14, straps: 2, inch: 4, flat: [241, 271, 759, 908], points: [[470, 430], [230, 600], [110, 690], [700, 250], [560, 235], [620, 350]] },
  { key: '13', len: 13, h: 15.5, straps: 2, inch: 5, flat: [207, 270, 791, 919], points: [[480, 350], [300, 560], [200, 700], [900, 230], [545, 245], [700, 320]] },
  { key: '155', len: 15.5, h: 15.5, straps: 3, inch: 6, flat: [137, 259, 861, 904], points: [[500, 520], [230, 640], [90, 720], [920, 230], [560, 380], [760, 330]] },
  { key: '18', len: 18, h: 15.5, straps: 3, inch: 7, flat: [127, 302, 871, 838], points: [[420, 560], [240, 640], [110, 780], [900, 230], [690, 250], [750, 340]] },
  { key: '235', len: 23.5, h: 18, straps: 3, inch: 9, flat: [135, 297, 865, 816], points: [[560, 470], [360, 610], [150, 760], [900, 180], [760, 180], [700, 330]] },
];
// Top of the straps on each flat photo, for cropping the range sheet.
const TABS = { 10: 80, 13: 78, 155: 81, 18: 140, 235: 168 };
const cm = v => `${String(v).replace('.', ',')} cm`;
const big = { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' };
const small = { 'font-size': 25, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.02em' };

// The five covers laid flat on one sheet, each from its own photo scaled so
// its panel is len x K wide (heights then run a little over, as on the
// photos), the supplier's measures under each.
function range({ el, text }) {
  const K = 16, rows = [['10', '13', '155'], ['18', '235']], gap = 70;
  rows.forEach((row, ri) => {
    const Ss = row.map(k => SIZES.find(S => S.key === k));
    const total = Ss.reduce((a, S) => a + S.len * K, 0) + gap * (row.length - 1);
    let x = 130 + (1240 - total) / 2;
    const base = ri ? 1150 : 690;
    Ss.forEach(S => {
      const [l, t, r, b] = S.flat, s = S.len * K / (r - l), top = TABS[S.key];
      const id = 'flat' + S.key, X = px => x + (px - l) * s, Y = py => base - (b - py) * s;
      el('rect', { x: X(l) - 4, y: Y(top) - 4, width: (r - l) * s + 8, height: (b - top) * s + 8 }, el('clipPath', { id }, el('defs', {})));
      el('image', { href: `photos/slc-grn-nyl-fire-${S.key}-flat.jpg`, x: X(0), y: Y(0), width: 1000 * s, height: 1000 * s, 'clip-path': `url(#${id})`, style: 'mix-blend-mode: multiply' });
      const cx = x + S.len * K / 2;
      text(cx, base + 50, `${cm(S.len)} x ${cm(S.h)}`, { 'text-anchor': 'middle', 'font-size': 36, 'font-weight': 700, fill: 'var(--ink)' });
      text(cx, base + 82, `${S.inch} pouces, ${S.straps} sangles`, { 'text-anchor': 'middle', ...small });
      x += S.len * K + gap;
    });
  });
}

// The cover rolled, seen from the side: len cm long, a tube of fixed
// diameter, its straps around it; on shows it dark, ring outlines it.
function rolled(el, x, cy, S, K, on, ring = on) {
  const w = S.len * K, d = 3.4 * K, xs = S.straps === 2 ? [.25, .75] : [.16, .5, .84];
  el('rect', { x, y: cy - d / 2, width: w, height: d, rx: d * .18, fill: on ? '#4f5a2a' : '#b9bd9f', stroke: ring ? 'var(--coyote)' : 'none', 'stroke-width': 5 });
  xs.forEach(f => el('rect', { x: x + w * f - 1.25 * K, y: cy - d / 2 - 4, width: 2.5 * K, height: d + 8, rx: 4, fill: on ? '#6b7740' : '#cfd2bb' }));
  el('ellipse', { cx: x + 4, cy, rx: 6, ry: d / 2 - 4, fill: on ? '#2c3417' : '#999d80' });
}

window.PRODUCT = {
  eyebrow: 'Housse de silencieux verte à scratch',

  variants: Object.fromEntries(SIZES.map(S => [S.key, {
    photo: `photos/slc-grn-nyl-fire-${S.key}.jpg`,
    flatPhoto: `photos/slc-grn-nyl-fire-${S.key}-flat.jpg`,
    size: S,
    points: S.points.map(p => [p]),
  }]).concat([['gamme', {
    // The product's own gallery, all sizes at once: the anatomy on the
    // longest cover, the five flat covers together, the size guide unmarked.
    photo: 'photos/slc-grn-nyl-fire-235.jpg',
    size: null,
    points: SIZES[4].points.map(p => [p]),
  }]])),

  anatomy: {
    title: 'Anatomie de la housse',
    callouts: [
      { side: 'l', y: 400, title: 'Cordon élastique', text: 'Enroulé au centre, complète le maintien' },
      { side: 'l', y: 680, title: 'Nylon vert olive', text: 'Mat, reprend les frottements' },
      { side: 'l', y: 960, title: 'Ouverte aux deux bouts', text: 'Un manchon enroulé sur le tube' },
      { side: 'r', y: 380, title: 'Galon cousu', text: 'Sur tout le pourtour' },
      { side: 'r', y: 640, title: 'Tirettes', text: 'Une languette au bout de chaque sangle' },
      { side: 'r', y: 900, title: 'Sangles à scratch', text: '2 jusqu’à 13 cm, 3 à partir de 15,5 cm' },
    ],
    footer: {
      tag: 'Chaleur',
      html: 'Tissu <strong>résistant à la chaleur</strong>, selon le fabricant. Après un tir soutenu, <strong>laisser refroidir le modérateur</strong> avant de défaire la housse.',
    },
  },

  // The variant's cover laid flat, on its own photo, with the supplier's two
  // measures along the panel's edges.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À plat',
      html: 'Mesures du fabricant, housse <strong>dépliée à plat</strong>. Le scratch rattrape le diamètre du tube.',
    },
    draw(kit, P, V) {
      const { el, text, dim, ext, arrow, section } = kit;
      if (!V.size) return range(kit);
      const S = V.size, [l, t, r, b] = S.flat, s = 0.74, X = x => 190 + x * s, Y = y => 380 + y * s;
      section(130, 380, `LA HOUSSE DE ${cm(S.len).toUpperCase()}, DÉPLIÉE`);
      el('image', { href: V.flatPhoto, x: X(0), y: Y(0), width: 1000 * s, height: 1000 * s, style: 'mix-blend-mode: multiply' });
      const hy = Y(b) + 50;
      ext(X(l), Y(b) + 8, X(l), hy + 14); ext(X(r), Y(b) + 8, X(r), hy + 14);
      dim(X(l), hy, X(r), hy, cm(S.len), 52);
      const vx = X(l) - 50;
      ext(X(l) - 8, Y(t), vx - 14, Y(t)); ext(X(l) - 8, Y(b), vx - 14, Y(b));
      arrow(vx, Y(t), vx, Y(b));
      text(vx - 18, (Y(t) + Y(b)) / 2 + 18, cm(S.h), { 'text-anchor': 'end', ...big });
      [[cm(S.len), 'de longueur'], [cm(S.h), 'déroulée, en hauteur'], [`${S.straps} sangles`, 'à scratch et tirette'], [`${S.inch} pouces`, 'au format américain'], ['50 g', 'poids']].forEach(([head, sub], i) => {
        const fy = 520 + i * 105;
        text(1060, fy, head, big);
        text(1060, fy + 34, sub, small);
      });
    },
  },

  // The five lengths side by side at one scale, this variant's outlined.
  effect: {
    title: 'Choisir sa taille',
    footer: {
      tag: 'Mesurer',
      html: 'Mesurer la <strong>partie cylindrique</strong> du modérateur et prendre la longueur <strong>égale ou juste inférieure</strong> : la bouche reste dégagée.',
    },
    draw({ el, text }, V) {
      const K = 30, x = 300;
      SIZES.forEach((S, i) => {
        const cy = 470 + i * 148, all = !V.size, on = all || S.key === V.size.key;
        rolled(el, x, cy, S, K, on, !all && on);
        text(x - 30, cy + 15, cm(S.len), { 'text-anchor': 'end', 'font-size': on ? 48 : 40, 'font-weight': 700, fill: on ? 'var(--ink)' : 'var(--mute)' });
        text(x + S.len * K + 30, cy + 10, `${S.inch} pouces, ${S.straps} sangles`, { 'font-size': 32, 'font-weight': on ? 700 : 500, fill: on ? 'var(--ink)' : 'var(--mute)' });
        if (on && !all) text(x + S.len * K + 30, cy + 46, 'la taille de cette fiche', { 'font-size': 27, 'font-weight': 600, fill: 'var(--coyote)' });
      });
    },
  },
};
