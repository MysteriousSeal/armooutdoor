// 8000 round stickers, 6 mm, in eight colours, one roll of 1000 per colour
// (SKU 6MM-8000PC-COLOR-CODE-DOTS-8CLR, product 157 on prod). No supplier
// sheet: every fact comes from the product page on armooutdoor.fr. For colour
// coding only: the page says 6 mm is too small to cover an impact, so no
// sheet shows a repair. Drawn with pastilles.js; the colours are sampled
// from the gallery photo (eight rolls in two rows: columns at x 185, 380,
// 573, 766; rows at y 300 and 690).
const COLOURS = [
  ['rouge', '#f02927'], ['jaune', '#fdf023'], ['vert', '#26aa48'], ['orange', '#fd7618'],
  ['rose', '#fb1e7c'], ['violet', '#6a249f'], ['bleu clair', '#2877e1'], ['bleu foncé', '#273b99'],
];

window.PRODUCT = {
  eyebrow: '8 000 pastilles rondes assorties 6 mm',

  variants: {
    rolls: {
      photo: 'photos/6mm-8000pc-color-code-dots-8clr.jpg',
      points: [[[185, 252]], [[268, 300]], [[820, 300]], [[573, 690]]],
    },
  },

  anatomy: {
    title: 'Anatomie du lot',
    photoFilter: 'brightness(1.03)',
    callouts: [
      { side: 'l', y: 470, title: 'Pastille de 6 mm', text: 'Pour les petites surfaces' },
      { side: 'l', y: 760, title: 'Un rouleau par couleur', text: '1 000 pastilles chacun' },
      { side: 'r', y: 470, title: 'Huit couleurs', text: 'Un code complet d’un coup' },
      { side: 'r', y: 760, title: 'Deux bleus', text: 'Clair et foncé, bien distincts' },
    ],
    footer: {
      tag: 'Codage',
      html: 'Une couleur par <strong>calibre, arme, lot ou usage</strong>. Chaque rouleau est séparé : on n’emporte que <strong>les teintes utiles</strong>.',
    },
  },

  // The eight colours as large dots, no text (swatch.html).
  swatch: { get colours() { return COLOURS.map(([, c]) => c); } },

  // One sticker and the coin, then the eight colours at the same scale.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'Codage uniquement',
      html: 'À 6 mm, la pastille est <strong>trop petite pour recouvrir un impact</strong> : pour réparer une cible, prenez les pastilles de <strong>13, 19 ou 25 mm</strong>.',
    },
    draw({ el, text, dim, ext, section }) {
      const D = Infographic.pastilles, K = 14, r = 6 / 2 * K, cr = 23.25 / 2 * K, sy = 760;
      section(150, 400, 'UNE PASTILLE ET LA PIÈCE');
      D.dot(el, 270, sy + cr - r, r, COLOURS[0][1]);
      ext(270 - r, sy + cr - r - 8, 270 - r, sy + cr - 2 * r - 70); ext(270 + r, sy + cr - r - 8, 270 + r, sy + cr - 2 * r - 70);
      dim(270 - r - 40, sy + cr - 2 * r - 55, 270 + r + 40, sy + cr - 2 * r - 55, '6 mm', 44);
      Infographic.targets.coin({ el, text }, 560, sy, cr);
      [['8 000', 'pastilles'], ['8 rouleaux', '1 000 par couleur']].forEach(([big, small], i) => {
        const fy = 640 + i * 110;
        text(860, fy, big, { 'font-size': 50, 'font-weight': 700, fill: 'var(--ink)' });
        text(860, fy + 32, small, { 'font-size': 26, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.04em' });
      });
      section(150, 1000, 'HUIT COULEURS');
      COLOURS.forEach(([name, c], i) => {
        const cx = 190 + i * 150, cy = 1100;
        D.dot(el, cx, cy, r, c);
        text(cx, cy + 70, name, { 'text-anchor': 'middle', 'font-size': 24, 'font-weight': 700, fill: 'var(--ink)' });
      });
    },
  },

  // The page's labelling uses: small surfaces, and one colour per calibre.
  effect: {
    title: 'Mode d’emploi',
    footer: {
      tag: 'Illustration',
      html: 'Usages reproduits <strong>à titre d’illustration</strong>, d’après la fiche produit. Pastilles en <strong>papier autocollant</strong>.',
    },
    draw(kit) {
      const D = Infographic.pastilles;
      D.usage(kit, [
        { head: 'PETITES SURFACES', title: 'Là où une étiquette ne tient pas', line: 'un chargeur, une boîte, une fiche.',
          draw: ({ el }, x) => {
            D.magazine(el, x + 130, 700);
            D.dot(el, x + 135, 640, 12, COLOURS[2][1]);
            D.ammoBox(el, x + 400, 620, 260, 170);
            D.dot(el, x + 470, 650, 12, COLOURS[0][1]);
            // An index card with a coloured dot in its corner.
            el('rect', { x: x + 280, y: 790, width: 240, height: 160, rx: 6, fill: '#fbfaf6', stroke: '#d8d4cb', 'stroke-width': 2 });
            for (let k = 0; k < 4; k++) el('rect', { x: x + 300, y: 830 + k * 26, width: 160 - k * 20, height: 8, rx: 4, fill: '#d8d4cb' });
            D.dot(el, x + 495, 815, 12, COLOURS[5][1]);
          } },
        { head: 'UN CODE COULEUR', title: 'Une couleur par calibre', line: 'ou par arme, lot ou usage.',
          draw: ({ el, text }, x) => {
            [['.22 LR', 0], ['9 mm', 2], ['.223 Rem', 6], ['.308', 7]].forEach(([cal, ci], i) => {
              const cy = 560 + i * 120;
              D.ammoBox(el, x + 150, cy, 200, 92);
              D.dot(el, x + 210, cy + 14, 14, COLOURS[ci][1]);
              D.dot(el, x + 330, cy, 14, COLOURS[ci][1]);
              text(x + 360, cy + 12, cal, { 'font-size': 34, 'font-weight': 700, fill: 'var(--ink)' });
            });
          } },
      ]);
    },
  },
};
