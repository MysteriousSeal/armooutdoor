// Black hook-and-loop suppressor cover, in five lengths (product 284 on prod,
// variants SLC-BLK-NYL-FIRE-10, -13, -155, -18, -235). Facts from the product
// page (woven nylon, wraps and closes by hook-and-loop, 2 straps up to 13 cm
// and 3 from 15,5 cm, a pull tab sewn on each strap, an elastic cord wound in
// the middle, bound edges, open at both ends, no heat rating, 50 g), the
// AliExpress listing the user saved ("fireproof material": the user chose to
// call it heat resistant, kept as the maker's claim) and each
// variant's supplier size image on the gallery: laid flat, length x unrolled
// height of 10 x 14, 13 x 15,5, 15,5 x 15,5, 18 x 15,5 and 23,5 x 18 cm, for
// 4, 5, 6, 7 and 9 in. Photos: each size rolled, sent by the user
// (photos/slc-blk-nyl-fire-<size>.jpg, whitened, the four shorter ones cropped
// to fill the frame, so not to one scale); the flat panels are drawn to scale
// from those measures, and each size laid flat, also from the user
// (-<size>-flat.jpg). On those the panel is 7 to 19 % wider for its height
// than the supplier's figures, so they carry labelled arrows and no coin.
// flat: the panel on that photo, left, top, right, bottom, straps excluded.
// points: cord, nylon, open end, binding, pull tab, strap, on that photo.
const SIZES = [
  { key: '10', len: 10, h: 14, straps: 2, inch: 4, flat: [240, 271, 759, 914], points: [[520, 500], [260, 640], [190, 720], [850, 330], [680, 310], [720, 410]] },
  { key: '13', len: 13, h: 15.5, straps: 2, inch: 5, flat: [200, 262, 797, 918], points: [[560, 450], [280, 560], [260, 700], [870, 290], [660, 310], [760, 420]] },
  { key: '155', len: 15.5, h: 15.5, straps: 3, inch: 6, flat: [136, 258, 864, 903], points: [[440, 560], [230, 700], [130, 760], [920, 250], [700, 240], [790, 380]] },
  { key: '18', len: 18, h: 15.5, straps: 3, inch: 7, flat: [127, 301, 872, 839], points: [[470, 520], [240, 640], [160, 760], [890, 260], [700, 250], [790, 360]] },
  { key: '235', len: 23.5, h: 18, straps: 3, inch: 9, flat: [136, 297, 863, 816], points: [[500, 500], [380, 600], [170, 760], [890, 240], [720, 220], [760, 400]] },
];
const cm = v => `${String(v).replace('.', ',')} cm`;
const big = { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' };
const small = { 'font-size': 25, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.02em' };

// The cover rolled, seen from the side: len cm long, a tube of fixed
// diameter, its straps around it; highlighted for the size on show.
function rolled(el, x, cy, S, K, on) {
  const w = S.len * K, d = 3.4 * K, xs = S.straps === 2 ? [.25, .75] : [.16, .5, .84];
  el('rect', { x, y: cy - d / 2, width: w, height: d, rx: d * .18, fill: on ? '#1d1e21' : '#9a9ca1', stroke: on ? 'var(--coyote)' : 'none', 'stroke-width': 5 });
  xs.forEach(f => el('rect', { x: x + w * f - 1.25 * K, y: cy - d / 2 - 4, width: 2.5 * K, height: d + 8, rx: 4, fill: on ? '#36383d' : '#b8babe' }));
  el('ellipse', { cx: x + 4, cy, rx: 6, ry: d / 2 - 4, fill: on ? '#0b0c0d' : '#7d7f84' });
}

window.PRODUCT = {
  eyebrow: 'Housse de silencieux noire à scratch',

  variants: Object.fromEntries(SIZES.map(S => [S.key, {
    photo: `photos/slc-blk-nyl-fire-${S.key}.jpg`,
    flatPhoto: `photos/slc-blk-nyl-fire-${S.key}-flat.jpg`,
    size: S,
    points: S.points.map(p => [p]),
  }])),

  anatomy: {
    title: 'Anatomie de la housse',
    callouts: [
      { side: 'l', y: 400, title: 'Cordon élastique', text: 'Enroulé au centre, complète le maintien' },
      { side: 'l', y: 680, title: 'Nylon tissé noir', text: 'Mat, reprend les frottements' },
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
    draw({ el, text, dim, ext, arrow, section }, P, V) {
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
        const cy = 470 + i * 148, on = S.key === V.size.key;
        rolled(el, x, cy, S, K, on);
        text(x - 30, cy + 15, cm(S.len), { 'text-anchor': 'end', 'font-size': on ? 48 : 40, 'font-weight': 700, fill: on ? 'var(--ink)' : 'var(--mute)' });
        text(x + S.len * K + 30, cy + 10, `${S.inch} pouces, ${S.straps} sangles`, { 'font-size': 32, 'font-weight': on ? 700 : 500, fill: on ? 'var(--ink)' : 'var(--mute)' });
        if (on) text(x + S.len * K + 30, cy + 46, 'la taille de cette fiche', { 'font-size': 27, 'font-weight': 600, fill: 'var(--coyote)' });
      });
    },
  },
};
