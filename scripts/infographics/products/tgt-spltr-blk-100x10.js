// 100 reactive round targets, 10 cm, black and numbered (SKU
// TGT-SPLTR-BLK-100X10, product 96 on prod). No supplier sheet: every fact
// comes from the product page on armooutdoor.fr. Geometry measured on the
// flat gallery photo: centre 500,498; yellow edge r400, black from r387;
// thin yellow rings r299 and r200; red 10 disc r97 ringed yellow; zone
// numbers 7, 8, 9 on the axes at r350, r250, r148; four "10" and a small
// cross inside the red disc.
window.PRODUCT = {
  eyebrow: '100 cibles réactives noires numérotées de 10 cm',

  variants: {
    black: {
      photo: 'photos/tgt-spltr-blk-100x10.jpg',
      points: [[[400, 398], [300, 298], [230, 228]], [[436, 562]], [[648, 445]], [[790, 790]]],
      palette: { black: '#160f0c', yellow: '#e6f51a', red: '#e60000', ink: '#efe7a6' },
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
    callouts: [
      { side: 'l', y: 430, title: 'Zones 7, 8 et 9', text: 'Délimitées par des anneaux jaune fluorescent' },
      { side: 'l', y: 760, title: 'Centre « 10 »', text: 'Disque rouge et petite croix de visée' },
      { side: 'r', y: 520, title: 'Chiffres répétés', text: 'Le score se lit depuis le pas de tir' },
      { side: 'r', y: 900, title: 'Liseré jaune', text: 'Délimite le bord de la cible' },
    ],
    footer: {
      tag: 'Score',
      html: 'La <strong>gerbe jaune</strong> montre l’impact, le <strong>chiffre de la zone</strong> donne les points : le score s’annonce sans aller à la cible.',
    },
  },

  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À l’échelle',
      html: 'La cible et la pièce de 1 € sont dessinées <strong>à la même échelle</strong>. À tirer de préférence entre <strong>10 et 25 m</strong>.',
    },
    draw({ el, text, dim, ext, section }, P) {
      const K = 6; // px per mm: a 10 cm target needs a smaller scale than the 76 mm ones

      // The target, redrawn from the photo's proportions (photo px times u).
      const R = 100 / 2 * K, cx = 560, cy = 800, u = R / 400;
      el('circle', { cx, cy, r: R, fill: P.yellow });
      el('circle', { cx, cy, r: 387 * u, fill: P.black });
      for (const r of [299, 200]) el('circle', { cx, cy, r: r * u, fill: 'none', stroke: P.ink, 'stroke-width': 4 * u });
      el('circle', { cx, cy, r: 97 * u, fill: P.red, stroke: P.ink, 'stroke-width': 4 * u });
      const num = (x, y, n, size = 44) => text(x, y + size * 0.36 * u, n, { 'text-anchor': 'middle', 'font-size': size * u, fill: P.ink, style: 'font-family: Barlow' });
      [[350, '7'], [250, '8'], [148, '9']].forEach(([r, n]) => {
        for (const [dx, dy] of [[0, -1], [1, 0], [0, 1], [-1, 0]]) num(cx + dx * r * u, cy + dy * r * u, n);
      });
      for (const [dx, dy] of [[0, -1], [1, 0], [0, 1], [-1, 0]]) num(cx + dx * 58 * u, cy + dy * 58 * u, '10', 34);
      el('path', { d: `M${cx - 14 * u} ${cy} H${cx + 14 * u} M${cx} ${cy - 14 * u} V${cy + 14 * u}`, stroke: P.ink, 'stroke-width': 3 * u });

      section(150, 350, 'UNE CIBLE');
      ext(cx - R, cy - 20, cx - R, cy - R - 70); ext(cx + R, cy - 20, cx + R, cy - R - 70);
      dim(cx - R, cy - R - 55, cx + R, cy - R - 55, '10 cm', 56);

      Infographic.targets.coinAndFacts({ el, text }, {
        K, cy, R, facts: [['100', 'cibles'], ['Rouleau', 'conditionnement'], ['Dos adhésif', 'pose en quelques secondes']],
      });
    },
  },

  // Before / after a series scored by zone, yellow splatter on the
  // diagonals, clear of the printed numbers.
  effect: {
    title: 'L’effet à l’impact',
    footer: {
      tag: 'Illustration',
      html: 'Effet reproduit <strong>à titre d’illustration</strong> : la forme de chaque gerbe varie d’un tir à l’autre.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [500, 498], radius: 400, seed: 96,
        // In panel px the zone edges are r68 (10), r140 (9), r209 (8), r271 (7).
        hits: [[-60, -62], [64, -56], [52, 66], [-108, 112], [150, -150]],
        afterLabel: 'APRÈS UNE SÉRIE',
        caption: ['Chaque impact se repère depuis le pas de tir,', 'et le chiffre de la zone donne le score.'],
      });
    },
  },
};
