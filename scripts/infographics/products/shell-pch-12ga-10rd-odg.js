// Olive pouch for ten 12-gauge shells (SKU SHELL-PCH-12GA-10RD-ODG, product 373
// on prod), the same pouch as shell-pch-12ga-10rd in another colour. Facts from the product page on armooutdoor.fr (two staggered
// rows, head up, hook-and-loop flap with no buckle or snap, Oxford cloth,
// every edge bound in webbing, two snap straps at the back for a belt or
// PALS rows, sold empty) and the supplier size sheet in
// sources/porte-cartouches-12-supplier-size.png (13 x 9 cm, measured on
// the back). Photos, from the gallery resized to 1000 px and whitened: the
// open pouch for the anatomy, the back (-back.jpg: pouch x 35 to 962, y 211
// to 850, about 71 px per cm both ways) for the dimensions and the straps.
// The back is a square-on view, true to scale: it gets the coin. Sold
// empty, as on 85; the page gives 70 g.
const FACTS = [['70 g', 'poids de la pochette']];
// The pouch's colour on the drawn top view: fill, edge.
const POUCH = ['#4e5a35', '#343d22'];

window.PRODUCT = {
  eyebrow: 'Porte-cartouches calibre 12 olive',

  variants: {
    pouch: {
      photo: 'photos/shell-pch-12ga-10rd-odg.jpg',
      back: 'photos/shell-pch-12ga-10rd-odg-back.jpg',
      // The pouch on the back photo: left, top, right, bottom.
      box: [35, 211, 962, 850],
      points: [[[400, 230]], [[460, 335]], [[330, 600]], [[700, 215]], [[680, 700]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la pochette',
    callouts: [
      { side: 'l', y: 400, title: 'Bande velours', text: 'Sur la face avant de la pochette' },
      { side: 'l', y: 640, title: '10 cartouches de 12', text: 'Deux rangées décalées, culot en haut' },
      { side: 'l', y: 880, title: 'Toile Oxford', text: 'Vert olive, surpiqûres ton sur ton' },
      { side: 'r', y: 420, title: 'Bordures surliées', text: 'Sangle cousue sur toutes les arêtes' },
      { side: 'r', y: 700, title: 'Crochet sous le rabat', text: 'Ni boucle ni pression, il se repose à toute hauteur' },
    ],
    footer: {
      tag: 'Vert d’armée',
      html: 'Un vert qui penche vers le jaune : il <strong>s’accorde au feuillage d’été</strong> et aux fougères, et <strong>devient inutile</strong> sur la neige ou un labour nu.',
    },
  },

  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'Calibre 12',
      html: 'Dimensionné pour la cartouche de 12 : <strong>les calibres 16, 20 et 410 y flotteraient</strong>.',
    },
    draw({ el, text, dim, ext, arrow }, P, V) {
      // The back at 0.78: 55,4 px per cm on the sheet.
      const s = 0.78, X = x => 230 + x * s, Y = y => 350 + y * s;
      el('image', { href: V.back, x: X(0), y: Y(0), width: 1000 * s, height: 1000 * s, style: 'mix-blend-mode: multiply' });
      const [bl, bt, br, bb] = V.box, l = X(bl), r = X(br), t = Y(bt), b = Y(bb), hy = t - 55, vx = l - 55;
      ext(l, t - 12, l, hy - 14); ext(r, t - 12, r, hy - 14);
      dim(l, hy, r, hy, '13 cm', 52);
      ext(l - 12, t, vx - 14, t); ext(l - 12, b, vx - 14, b);
      arrow(vx, t, vx, b);
      text(vx - 18, (t + b) / 2 + 18, '9 cm', { 'text-anchor': 'end', 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
      const cr = 23.25 / 2 * (br - bl) / 130 * s;
      Infographic.targets.coin({ el, text }, 1180, b - cr, cr);
      [['10', 'cartouches de calibre 12'], ['2 rangées', 'décalées d’un demi-pas'], ...FACTS].forEach(([big, small], i) => {
        const fy = 500 + i * 110;
        text(1040, fy, big, { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
        text(1040, fy + 34, small, { 'font-size': 25, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.02em' });
      });
    },
  },

  effect: {
    title: 'Mode d’emploi',
    footer: {
      tag: 'À savoir',
      html: 'Une bande agrippante de cette taille <strong>claque en s’ouvrant</strong> : à l’affût, décoller le rabat lentement ou <strong>le laisser ouvert pendant le poste</strong>.',
    },
    draw(kit, V) {
      const { el, text } = kit;
      Infographic.pastilles.usage(kit, [
        { head: 'AU DOS', title: 'Deux pressions laquées noir', line: 'derrière la ceinture ou dans les rangées PALS.',
          draw: (k, x) => {
            const cx = x + 280, cy = 730, R = 250, id = 'backZoom', sc = 0.52;
            const cp = el('clipPath', { id }, el('defs', {}));
            el('circle', { cx, cy, r: R }, cp);
            el('image', { href: V.back, x: cx - 498 * sc, y: cy - 530 * sc, width: 1000 * sc, height: 1000 * sc, 'clip-path': `url(#${id})` });
            el('circle', { cx, cy, r: R, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 5 });
          } },
        { head: 'VUE DE DESSUS', title: 'Deux rangs imbriqués', line: 'les culots d’un rang se logent entre ceux de l’autre.',
          draw: (k, x) => {
            // Ten shell heads in two rows offset by half a pitch, drawn to
            // scale with each other (22,5 mm heads), the pouch around them.
            const r = 44, p = 96, row = 84, x0 = x + 40 + r, y0 = 660;
            el('rect', { x: x + 10, y: y0 - r - 34, width: 5 * p + p / 2 + 40, height: row + 2 * r + 68, rx: 22, fill: POUCH[0], stroke: POUCH[1], 'stroke-width': 3 });
            for (let j = 0; j < 2; j++) for (let i = 0; i < 5; i++) {
              const cx = x0 + i * p + j * p / 2, cy = y0 + j * row;
              el('circle', { cx, cy, r, fill: '#c9a24b', stroke: '#8a6a2c', 'stroke-width': 3 });
              el('circle', { cx, cy, r: r * 0.8, fill: 'none', stroke: '#a88438', 'stroke-width': 2 });
              el('circle', { cx, cy, r: 10, fill: '#b8b2a4', stroke: '#7a7466', 'stroke-width': 2.5 });
              text(cx, cy + 26, '12', { 'text-anchor': 'middle', 'font-size': 15, 'font-weight': 700, fill: '#8a6a2c' });
            }
            // The half-pitch offset between the rows.
            const ax = x0 + 4 * p, bx = ax + p / 2, ty = y0 + row + r + 70;
            el('line', { x1: ax, y1: y0 + r + 8, x2: ax, y2: ty + 12, stroke: 'var(--mute)', 'stroke-width': 1.5, 'stroke-dasharray': '5 5' });
            el('line', { x1: bx, y1: y0 + row + r + 8, x2: bx, y2: ty + 12, stroke: 'var(--mute)', 'stroke-width': 1.5, 'stroke-dasharray': '5 5' });
            k.arrow(ax, ty, bx, ty);
            text(ax - 16, ty + 10, 'demi-pas', { 'text-anchor': 'end', 'font-size': 28, 'font-weight': 700, fill: 'var(--mute)' });
          } },
      ]);
    },
  },
};
