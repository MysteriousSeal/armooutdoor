// Webbing belt with eyelets, side-release buckle and webbing clips
// (SKU family men-nylon-belt-qr). Sources: supplier sheets "product size"
// (body 125 x 5.5 cm, 134 cm fully extended, buckle 6.5 cm), "webbing clip
// buckle", "quick-release buckle" and "polypropylene". The strap is
// polypropylene, not nylon, whatever the file name says.
window.PRODUCT = {
  eyebrow: 'Ceinture en sangle à boucle rapide',

  variants: {
    odg: {
      photo: 'photos/men-nylon-belt-qr-odg.jpg',
      points: [[[373, 342]], [[230, 380], [270, 580]], [[475, 560]], [[850, 540]]],
      palette: { fill: '#55623f', edge: '#36402a', stitch: '#7a875e', hw: '#1c1d1b', hwEdge: '#050505' },
    },
  },

  anatomy: {
    title: 'Anatomie de la ceinture',
    // The belt only fills the middle of its photo: draw it larger.
    photoBox: { x: 250, y: 250, size: 1000 },
    callouts: [
      { side: 'l', y: 470, title: 'Œillets', text: 'Double rangée le long de la sangle' },
      { side: 'l', y: 760, title: 'Clips de réglage', text: 'Réglage précis et sûr de la longueur' },
      { side: 'r', y: 600, title: 'Boucle à ouverture rapide', text: 'Se ferme et s’ouvre en un geste' },
      { side: 'r', y: 880, title: 'Sangle polypropylène', text: 'Respirante, confortable, durable et résiste aux plis' },
    ],
    footer: {
      tag: 'Longueur',
      html: 'Sangle de <strong>125 cm</strong>, jusqu’à <strong>134 cm</strong> entièrement déployée. Les <strong>clips de réglage</strong> fixent la longueur voulue.',
    },
  },

  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'Mesures',
      html: '<strong>Mesures prises à la main</strong> : un léger écart est possible. Le détail de la boucle est agrandi.',
    },
    draw({ el, dim, ext, arrow, sideLabel, section }, P) {
      function strap(x, y, w, h, k) {
        el('rect', { x, y, width: w, height: h, fill: P.fill, stroke: P.edge, 'stroke-width': 3 });
        for (let ex = x + 3 * k; ex < x + w - 2 * k; ex += 8 * k) {
          for (const f of [0.3, 0.7]) el('circle', { cx: ex, cy: y + h * f, r: Math.max(2.5, k * 0.28), fill: '#0d0e0c', stroke: P.stitch, 'stroke-width': Math.max(1, k / 16) });
        }
      }
      const clip = (cx, cy, h, k) => el('rect', { x: cx - k * 0.6, y: cy - h / 2 - k * 0.35, width: k * 1.2, height: h + k * 0.7, rx: k * 0.2, fill: P.hw, stroke: P.hwEdge, 'stroke-width': Math.max(1.5, k / 12) });

      // Side-release buckle seen from the front: two strap bars and the
      // centre body where the male tongue locks in.
      function buckle(x, cy, w, h, k) {
        const sw = Math.max(1.5, k / 10);
        for (const bx of [x, x + w - w * 0.14]) {
          el('rect', { x: bx, y: cy - h * 0.46, width: w * 0.14, height: h * 0.92, rx: k * 0.15, fill: P.hw, stroke: P.hwEdge, 'stroke-width': sw });
        }
        el('path', {
          d: `M${x + w * 0.2} ${cy - h / 2} H${x + w * 0.8} l${-w * 0.06} ${h * 0.22} v${h * 0.56} l${w * 0.06} ${h * 0.22} H${x + w * 0.2} l${w * 0.06} ${-h * 0.22} v${-h * 0.56} Z`,
          fill: P.hw, stroke: P.hwEdge, 'stroke-width': sw, 'stroke-linejoin': 'round',
        });
        el('rect', { x: x + w * 0.36, y: cy - h * 0.2, width: w * 0.28, height: h * 0.4, rx: k * 0.2, fill: 'none', stroke: '#3a3c38', 'stroke-width': Math.max(1, k / 14) });
      }

      // Whole belt laid flat, to scale: 125 cm body, 134 cm with both buckle halves.
      const K = 9;
      const L = 134 * K, body = 125 * K, half = (L - body) / 2, sh = 5.5 * K, bh = 6.5 * K;
      const x0 = (1500 - L) / 2, cy = 520;
      section(x0, 350, 'CEINTURE COMPLÈTE');
      strap(x0 + half, cy - sh / 2, body, sh, K);
      for (const [bx, open] of [[x0, 1], [x0 + L - half, -1]]) {
        el('rect', { x: bx, y: cy - bh / 2, width: half, height: bh, rx: 6, fill: P.hw, stroke: P.hwEdge, 'stroke-width': 2 });
      }
      for (const f of [0.06, 0.1, 0.9, 0.94]) clip(x0 + half + body * f, cy, sh, K);
      ext(x0, cy - bh / 2 - 8, x0, 410); ext(x0 + L, cy - bh / 2 - 8, x0 + L, 410);
      dim(x0, 425, x0 + L, 425, '134 cm déployée', 46);
      ext(x0 + half, cy + sh / 2 + 8, x0 + half, 630); ext(x0 + L - half, cy + sh / 2 + 8, x0 + L - half, 630);
      dim(x0 + half, 615, x0 + L - half, 615, '125 cm de sangle', 40);

      // Buckle detail, enlarged.
      const k = 38;
      const dh = 6.5 * k, ds = 5.5 * k, dw = 9 * k;
      const dx = 540, dy = 1000, sl = 260, sr = 300;
      section(x0, 760, 'DÉTAIL DE LA BOUCLE');
      strap(dx - sl, dy - ds / 2, sl + 10, ds, k);
      strap(dx + dw - 10, dy - ds / 2, sr, ds, k);
      buckle(dx, dy, dw, dh, k);
      // 6.5 cm buckle.
      const hx = dx + dw / 2;
      ext(dx + dw * 0.2, dy - dh / 2 - 6, dx + dw * 0.2, dy - dh / 2 - 40);
      ext(dx + dw * 0.8, dy - dh / 2 - 6, dx + dw * 0.8, dy - dh / 2 - 40);
      const bx = dx + dw + sr + 30;
      ext(dx + dw * 0.8, dy - dh / 2, bx + 10, dy - dh / 2); ext(dx + dw * 0.8, dy + dh / 2, bx + 10, dy + dh / 2);
      arrow(bx, dy - dh / 2, bx, dy + dh / 2);
      sideLabel(bx + 16, dy - 4, '6,5 cm', 'start', 44);
      sideLabel(bx + 16, dy + 34, 'boucle', 'start', 28);
      // 5.5 cm strap width.
      const wx = dx - sl - 34;
      ext(dx - sl - 6, dy - ds / 2, wx - 10, dy - ds / 2); ext(dx - sl - 6, dy + ds / 2, wx - 10, dy + ds / 2);
      arrow(wx, dy - ds / 2, wx, dy + ds / 2);
      sideLabel(wx - 16, dy - 4, '5,5 cm', 'end', 44);
      sideLabel(wx - 16, dy + 34, 'sangle', 'end', 28);
    },
  },
};
