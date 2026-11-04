// Tactical MOLLE phone pouch, black (SKU PHONE-POUCH-MOLLE-BLK, product 362 on prod). No
// supplier sheet: every fact comes from the product page on armooutdoor.fr
// (16,5 x 9 x 1,8 cm, phones of 6 to 7,5 in, hook under the flap and a wide
// loop band across the front, strap tab to open, two vertical straps with
// metal snaps for a belt or PALS rows, soft lining, 75 g). Photos from the
// gallery, whitened: the open pouch (photos/<sku>.jpg) for the anatomy, the
// pair (-pair.jpg) for the dimensions, its left pouch clipped out, and the
// back on a belt (-belt.jpg). Each colour's photos are framed differently,
// so every point and corner below is traced on this colour's own photos.
window.PRODUCT = {
  eyebrow: 'Étui téléphone tactique MOLLE noir',

  variants: {
    pouch: {
      photo: 'photos/phone-pouch-molle-blk.jpg',
      pair: 'photos/phone-pouch-molle-blk-pair.jpg',
      belt: 'photos/phone-pouch-molle-blk-belt.jpg',
      points: [[[130, 225]], [[250, 320]], [[230, 720]], [[450, 440]], [[640, 570]], [[740, 820]]],
      // Left pouch of the pair photo, front face: top left, top right,
      // bottom right, bottom left.
      face: [[97, 437], [312, 302], [638, 725], [395, 895]],
      zoom: [530, 500],
    },
  },

  anatomy: {
    title: 'Anatomie de l’étui',
    callouts: [
      { side: 'l', y: 380, title: 'Languette d’ouverture', text: 'Le pouce tire, le scratch lâche' },
      { side: 'l', y: 640, title: 'Crochet sous le rabat', text: 'Il s’agrippe là où il retombe' },
      { side: 'l', y: 900, title: 'Doublure textile', text: 'Lisse, sans rivet face à l’écran' },
      { side: 'r', y: 400, title: 'Grands écrans', text: 'Téléphones de 6 à 7,5 pouces' },
      { side: 'r', y: 660, title: 'Large bande velours', text: 'Toute la façade, sans hauteur imposée' },
      { side: 'r', y: 900, title: 'Nylon noir', text: 'Sobre sur un jean, assorti au noir' },
    ],
    footer: {
      tag: 'Discret',
      html: 'Sur une ceinture de ville, le noir <strong>se lit comme un étui d’outil ordinaire</strong>, là où une teinte sable attire l’œil.',
    },
  },

  // The left pouch stood upright, with straight arrows: width across the
  // top, height down the right side. No coin: a three-quarter view.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'Grand format',
      html: 'Taillé pour les <strong>grands écrans de 6 à 7,5 pouces</strong>. En dessous de 4,7 pouces, l’appareil <strong>bougerait dans le logement</strong>.',
    },
    draw({ el, text, dim, ext, arrow }, P, V) {
      const [TL, TR, BR, BL] = V.face;
      // Stand the pouch upright: the photo turned about the face's centre so
      // its top edge is level, that centre placed at (CX, CY).
      const k = 1.1, CX = 560, CY = 770, a = -Math.atan2(TR[1] - TL[1], TR[0] - TL[0]);
      const c = [0, 1].map(i => (TL[i] + TR[i] + BR[i] + BL[i]) / 4), cos = Math.cos(a), sin = Math.sin(a);
      const T = ([x, y]) => { const dx = (x - c[0]) * k, dy = (y - c[1]) * k; return [CX + dx * cos - dy * sin, CY + dx * sin + dy * cos]; };
      const g = el('g', { transform: `translate(${CX} ${CY}) rotate(${a * 180 / Math.PI}) scale(${k}) translate(${-c[0]} ${-c[1]})` });
      const id = 'leftPouch';
      const cp = el('clipPath', { id }, el('defs', {}));
      const grow = 34, pad = ([x, y], [dx, dy]) => `${x + dx * grow},${y + dy * grow}`;
      el('polygon', { points: [pad(TL, [-1, -0.6]), pad(TR, [0.2, -1]), pad(BR, [1, 0.2]), pad(BL, [0.2, 1]), pad(BL, [-1, 0.6])].join(' ') }, cp);
      el('image', { href: V.pair, x: 0, y: 0, width: 1000, height: 1000, 'clip-path': `url(#${id})`, style: 'mix-blend-mode: multiply' }, g);
      const [tl, tr, br] = [TL, TR, BR].map(T);
      const ty = Math.min(tl[1], tr[1]) - 70;
      ext(tl[0], tl[1] - 15, tl[0], ty - 14); ext(tr[0], tr[1] - 15, tr[0], ty - 14);
      dim(tl[0], ty, tr[0], ty, '9 cm', 52);
      const rx = Math.max(tr[0], br[0]) + 70;
      ext(tr[0] + 15, tr[1], rx + 14, tr[1]); ext(br[0] + 15, br[1], rx + 14, br[1]);
      arrow(rx, tr[1], rx, br[1]);
      text(rx + 24, (tr[1] + br[1]) / 2 + 18, '16,5 cm', { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
      [['1,8 cm', 'd’épaisseur'], ['6 à 7,5', 'pouces d’écran'], ['75 g', 'poids de l’étui']].forEach(([big, small], i) => {
        const fy = 830 + i * 110;
        text(1080, fy, big, { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
        text(1080, fy + 34, small, { 'font-size': 25, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.02em' });
      });
    },
  },

  effect: {
    title: 'Mode d’emploi',
    footer: {
      tag: 'MOLLE',
      html: 'Les mêmes brides <strong>se tissent dans les rangées PALS</strong> d’un sac, d’un gilet ou d’un porte-plaques, une sur deux, puis se referment.',
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
        { head: 'SUR LA CEINTURE', title: 'Sans rien déboucler', line: 'les brides passent derrière, puis pression.',
          draw: (k, x) => zoom(x, V.belt, [500, 500], 0.5, 'beltZoom') },
        { head: 'LE RABAT', title: 'À la hauteur du téléphone', line: 'le crochet prend sur toute la façade velours.',
          draw: (k, x) => zoom(x, V.photo, V.zoom, 0.62, 'flapZoom') },
      ]);
    },
  },
};
