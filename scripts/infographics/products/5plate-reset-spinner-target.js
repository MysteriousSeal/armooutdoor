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

  effect: [
    // The cycle the supplier and the page describe, as three simplified
    // front views drawn from the front photo's proportions (not to scale):
    // the plates hang; a hit plate turns up and rests on the bar above;
    // a shot on the top plate drops the four.
    {
      id: 'cycle',
      title: 'Le cycle',
      footer: {
        tag: 'Schéma',
        html: 'Vues de face simplifiées, <strong>à titre d’illustration</strong>. La série repart <strong>sans quitter le poste</strong> ni relever les plaques à la main.',
      },
      draw(kit) {
        const { el, text } = kit;
        const steps = [
          ['Quatre plaques pendantes', 'Sous la traverse, prêtes à tirer.', [0, 0, 0, 0], null],
          ['Touchée, elle se relève', 'Et reste posée sur la barre.', [1, 1, 0, 0], 0],
          ['Un tir sur la plaque haute', 'Les quatre retombent d’un coup.', [0, 0, 0, 0], 'top'],
        ];
        steps.forEach(([title, line, up, hit], i) => {
          const s = 0.52, ox = 80 - 140 * s + i * (718 * s + 110), oy = 330;
          spinner(el, ox, oy, s, up, hit);
          const tx = ox + 140 * s;
          el('circle', { cx: tx + 26, cy: 935, r: 26, fill: 'var(--coyote)' });
          text(tx + 26, 946, String(i + 1), { 'text-anchor': 'middle', 'font-size': 32, 'font-weight': 700, fill: '#fff' });
          text(tx + 68, 947, title, { 'font-size': 34, 'font-weight': 700, fill: 'var(--ink)' });
          text(tx + 68, 987, line, { style: 'font-family: Barlow', 'font-size': 25, fill: 'var(--mute)' });
          if (i < 2) {
            const ax = ox + 858 * s + 38;
            el('path', { d: `M${ax} 640 H${ax + 34}`, stroke: 'var(--coyote)', 'stroke-width': 5 });
            el('path', { d: `M${ax + 20} 626 L${ax + 36} 640 L${ax + 20} 654`, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 5, 'stroke-linejoin': 'round' });
          }
        });
      },
    },

    // The page's practical limits on one sheet.
    {
      id: 'safety',
      title: 'Avant de tirer',
      footer: {
        tag: 'Sécurité',
        html: 'Une arme à feu ou un projectile plus énergique <strong>déformerait les plaques</strong> et créerait un vrai <strong>risque de ricochet</strong>.',
      },
      draw(kit) {
        const { el, text } = kit;
        const cards = [
          ['pellet', 'Air comprimé', 'En 4,5 mm (.177) ou 5,5 mm (.22)'],
          ['nofire', 'Jamais d’arme à feu', 'Ni de munition plus énergique'],
          ['glasses', 'Lunettes de protection', 'Comme devant toute cible en acier'],
          ['range', '10 à 25 m', 'La distance conseillée'],
          ['sticker', '10 autocollants', 'Pour renouveler les faces usées'],
          ['legs', 'Pieds en A', 'Repliables pour le transport'],
        ];
        cards.forEach(([icon, title, line], i) => {
          const x = 110 + (i % 2) * 650, y = 320 + Math.floor(i / 2) * 300;
          el('rect', { x, y, width: 630, height: 270, rx: 18, fill: '#f6f1e8' });
          drawIcon(el, icon, x + 115, y + 135);
          text(x + 230, y + 128, title, { 'font-size': 44, 'font-weight': 700, fill: 'var(--ink)' });
          text(x + 230, y + 170, line, { style: 'font-family: Barlow', 'font-size': 24, fill: 'var(--mute)' });
        });
      },
    },
  ],
};

// A front view of the target at (ox, oy), s px per photo px (photo 1000 px).
// up[i] = 1 draws plate i turned up onto the reset bar; hit = plate index or
// 'top' adds the coyote motion arrows of that step.
function spinner(el, ox, oy, s, up, hit) {
  const X = x => ox + x * s, Y = y => oy + y * s, W = w => w * s;
  const steel = '#26282b', face = '#ec6a2c', w = W(14);
  const line = (x1, y1, x2, y2, sw = w) => el('path', { d: `M${X(x1)} ${Y(y1)} L${X(x2)} ${Y(y2)}`, stroke: steel, 'stroke-width': sw, 'stroke-linecap': 'round', fill: 'none' });
  // Frame: uprights, top bar with rounded corners, lower crossbars.
  el('path', { d: `M${X(140)} ${Y(975)} L${X(140)} ${Y(245)} Q${X(140)} ${Y(215)} ${X(170)} ${Y(215)} L${X(828)} ${Y(215)} Q${X(858)} ${Y(215)} ${X(858)} ${Y(245)} L${X(858)} ${Y(975)}`, stroke: steel, 'stroke-width': w, fill: 'none', 'stroke-linecap': 'round' });
  line(150, 757, 840, 757); line(370, 745, 840, 745, W(10));
  // Plates turned up: swung over their pivot, so the disc now sits above
  // the bars and shows its dark back, the reset bar holding it up. Drawn
  // first, so the bars pass in front.
  [240, 410, 580, 750].forEach((px, i) => {
    if (!up[i]) return;
    el('path', { d: `M${X(px - 18)} ${Y(510)} L${X(px + 18)} ${Y(510)} L${X(px + 40)} ${Y(350)} L${X(px - 40)} ${Y(350)} Z`, fill: steel });
    el('circle', { cx: X(px), cy: Y(320), r: W(62), fill: '#3a3d41', stroke: steel, 'stroke-width': W(8) });
  });
  // The arm of the top plate, its rod down to the reset bar, the bars.
  line(500, 120, 500, 455, W(10));
  el('rect', { x: X(465), y: Y(205), width: W(70), height: W(26), rx: W(5), fill: steel });
  line(183, 435, 818, 435, W(16));
  line(150, 500, 852, 500, W(16));
  // Top plate: a disc with its orange face, tipped back in step 3.
  const top = hit === 'top';
  el('ellipse', { cx: X(500), cy: Y(70), rx: W(64), ry: W(top ? 34 : 64), fill: steel });
  el('ellipse', { cx: X(500), cy: Y(70), rx: W(54), ry: W(top ? 26 : 54), fill: face });
  el('circle', { cx: X(500), cy: Y(70), r: W(9), fill: steel });
  // The four plates: hanging below their pivot, or turned up onto the reset
  // bar, seen from the front as their dark back, foreshortened.
  [240, 410, 580, 750].forEach((px, i) => {
    if (up[i]) return;
    {
      // The stem flares down into the disc, as on the real plate.
      el('path', { d: `M${X(px - 18)} ${Y(490)} L${X(px + 18)} ${Y(490)} L${X(px + 40)} ${Y(650)} L${X(px - 40)} ${Y(650)} Z`, fill: steel });
      el('circle', { cx: X(px), cy: Y(680), r: W(62), fill: steel });
      el('circle', { cx: X(px), cy: Y(680), r: W(52), fill: face });
      el('circle', { cx: X(px), cy: Y(680), r: W(9), fill: steel });
    }
  });
  // Motion arrows, coyote with a white halo.
  const arrow = (d, hx, hy, ang) => {
    for (const [stroke, sw] of [['#fff', 11], ['var(--coyote)', 5]]) {
      el('path', { d, fill: 'none', stroke, 'stroke-width': sw, 'stroke-linecap': 'round' });
      const h = 16, a = ang * Math.PI / 180;
      el('path', { d: `M${hx - h * Math.cos(a - 0.5)} ${hy - h * Math.sin(a - 0.5)} L${hx} ${hy} L${hx - h * Math.cos(a + 0.5)} ${hy - h * Math.sin(a + 0.5)}`, fill: 'none', stroke, 'stroke-width': sw, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' });
    }
  };
  if (typeof hit === 'number') {
    // The plate swings from hanging up onto the bar.
    // In the gap right of the plate, from where it hung up to where it rests.
    const px = [240, 410, 580, 750][hit];
    arrow(`M${X(px + 82)} ${Y(730)} Q${X(px + 110)} ${Y(520)} ${X(px + 72)} ${Y(350)}`, X(px + 72), Y(350), -105);
  }
  if (hit === 'top') {
    arrow(`M${X(650)} ${Y(30)} Q${X(600)} ${Y(-20)} ${X(570)} ${Y(30)}`, X(570), Y(30), 120);
    [240, 410, 580, 750].forEach(px => arrow(`M${X(px)} ${Y(330)} L${X(px)} ${Y(415)}`, X(px), Y(415), 90));
  }
}

// The safety sheet's icons, about 160 px across, centred on (cx, cy).
function drawIcon(el, kind, cx, cy) {
  const ink = '#1b1d19', coy = 'var(--coyote)', red = '#c8321f';
  const g = el('g', { transform: `translate(${cx} ${cy})` });
  const add = (tag, a) => el(tag, a, g);
  if (kind === 'pellet') {
    // A diabolo pellet seen side on: domed head, narrow waist, open skirt.
    add('path', { d: 'M-12 -30 C-60 -30 -60 30 -12 30 L8 10 L56 34 L56 -34 L8 -10 Z', fill: '#a7adb3', stroke: ink, 'stroke-width': 5, 'stroke-linejoin': 'round' });
    add('path', { d: 'M8 -10 L8 10', stroke: ink, 'stroke-width': 4 });
    add('ellipse', { cx: 56, rx: 7, ry: 34, fill: '#6f757b', stroke: ink, 'stroke-width': 5 });
  } else if (kind === 'nofire') {
    // A 9 mm Parabellum (9 x 19) round at 4 px per mm, centred, under a red
    // prohibition sign: 29,7 mm long overall, a round-nose copper bullet
    // (9 mm, its nose drawn shorter than life to read at icon size) in a slightly tapered 19,15 mm brass case, rimless, so the rim
    // matches the base with the extractor groove just above it.
    // The bullet's nose kept short and round; the round is raised 6 px so
    // it stays centred in the sign.
    const round = el('g', { transform: 'translate(0 -6)' }, g), part = (tag, a) => el(tag, a, round);
    part('path', { d: 'M-18 -18 L-18 -30 C-18 -42 -10 -46 0 -46 C10 -46 18 -42 18 -30 L18 -18 Z', fill: '#c47a45', stroke: ink, 'stroke-width': 4, 'stroke-linejoin': 'round' });
    part('path', { d: 'M-19.3 -18 L19.3 -18 L19.9 43 L-19.9 43 Z', fill: '#d6ad55', stroke: ink, 'stroke-width': 4, 'stroke-linejoin': 'round' });
    part('path', { d: 'M-11 -10 L-11 36', stroke: '#f0d48c', 'stroke-width': 5, 'stroke-linecap': 'round' });
    part('path', { d: 'M-19.9 43 L-16 47 L16 47 L19.9 43', fill: '#b48d3f', stroke: ink, 'stroke-width': 3, 'stroke-linejoin': 'round' });
    part('rect', { x: -19.9, y: 47, width: 39.8, height: 12, rx: 2, fill: '#d6ad55', stroke: ink, 'stroke-width': 4 });
    add('circle', { r: 74, fill: 'none', stroke: red, 'stroke-width': 13 });
    add('path', { d: 'M-52 -52 L52 52', stroke: red, 'stroke-width': 13 });
  } else if (kind === 'glasses') {
    add('rect', { x: -72, y: -26, width: 62, height: 46, rx: 18, fill: '#d9e4ea', stroke: ink, 'stroke-width': 6 });
    add('rect', { x: 10, y: -26, width: 62, height: 46, rx: 18, fill: '#d9e4ea', stroke: ink, 'stroke-width': 6 });
    add('path', { d: 'M-10 -10 Q0 -20 10 -10', fill: 'none', stroke: ink, 'stroke-width': 6 });
    add('path', { d: 'M-72 -14 L-84 -22 M72 -14 L84 -22', stroke: ink, 'stroke-width': 6, 'stroke-linecap': 'round' });
  } else if (kind === 'range') {
    // A shooter's mark, a dashed line, a small target.
    add('path', { d: 'M-70 30 L-70 -30', stroke: ink, 'stroke-width': 6, 'stroke-linecap': 'round' });
    add('path', { d: 'M-56 0 L44 0', stroke: coy, 'stroke-width': 6, 'stroke-dasharray': '12 10' });
    add('path', { d: 'M30 -12 L46 0 L30 12', fill: 'none', stroke: coy, 'stroke-width': 6, 'stroke-linejoin': 'round' });
    add('circle', { cx: 70, r: 18, fill: '#ec6a2c', stroke: ink, 'stroke-width': 5 });
  } else if (kind === 'sticker') {
    // A small stack of spare faces: orange discs with their black aim mark.
    // Offset so the whole stack is centred on the icon's middle.
    for (const [dx, dy] of [[-11, -9], [0, 0]]) {
      add('circle', { cx: dx, cy: dy, r: 52, fill: '#d9591f', stroke: ink, 'stroke-width': 5 });
    }
    add('circle', { cx: 11, cy: 9, r: 52, fill: '#ec6a2c', stroke: ink, 'stroke-width': 5 });
    add('rect', { x: 2, y: 0, width: 18, height: 18, fill: ink, transform: 'rotate(45 11 9)' });
  } else if (kind === 'legs') {
    // The A-frame legs, open, with a folding arrow.
    add('path', { d: 'M-50 60 L0 -50 L50 60', fill: 'none', stroke: ink, 'stroke-width': 10, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' });
    add('path', { d: 'M-62 10 Q-80 -30 -40 -54', fill: 'none', stroke: coy, 'stroke-width': 6, 'stroke-linecap': 'round' });
    add('path', { d: 'M-52 -60 L-38 -54 L-46 -40', fill: 'none', stroke: coy, 'stroke-width': 6, 'stroke-linejoin': 'round' });
  }
}
