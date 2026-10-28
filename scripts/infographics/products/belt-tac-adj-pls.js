// Quick-release tactical belt with a cobra-style buckle (SKU family belt-tac-*-adj-pls).
// Source: supplier sheet "plastic buckle", 125 cm long, 3.8 cm strap,
// buckle 5.6 x 5.3 cm. The buckle is plastic: never quote the 12kN / 24kN
// stamped on it.
window.PRODUCT = {
  eyebrow: 'Ceinture tactique à boucle rapide',

  variants: {
    black: {
      photo: 'photos/belt-tac-adj-pls-blk.jpg',
      points: [[[450, 160]], [[155, 570]], [[250, 790]], [[290, 440]], [[850, 500]]],
      palette: { fill: '#2b2c2a', edge: '#121312', stitch: '#50524d' },
    },
    green: {
      photo: 'photos/belt-tac-adj-pls-grn.jpg',
      points: [[[450, 160]], [[160, 560]], [[250, 790]], [[320, 440]], [[850, 500]]],
      palette: { fill: '#5e6339', edge: '#3a3e22', stitch: '#868c5c' },
    },
  },

  anatomy: {
    title: 'Anatomie de la ceinture',
    // The supplier photos sit on off-white; lift them so the page stays white.
    photoFilter: 'brightness(1.06) contrast(1.04)',
    callouts: [
      { side: 'l', y: 400, title: 'Passant', text: 'Maintient l’extrémité de la sangle' },
      { side: 'l', y: 700, title: 'Leviers latéraux', text: 'Pressez les deux pour ouvrir la boucle' },
      { side: 'l', y: 990, title: 'Réglage sans trous', text: 'La sangle se règle en continu, à votre taille' },
      { side: 'r', y: 560, title: 'Boucle à clip', text: 'En plastique, légère, se ferme d’un geste' },
      { side: 'r', y: 830, title: 'Nylon tressé', text: 'Sangle de 3,8 cm de large' },
    ],
    footer: {
      tag: 'Ouverture',
      html: 'Pour ouvrir la boucle, <strong>pressez les deux leviers latéraux</strong> puis dégagez la partie haute de la boucle.',
    },
  },

  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'Mesures',
      html: '<strong>Mesures prises à la main</strong> : un léger écart est possible. Le détail de la boucle est agrandi.',
    },
    draw({ el, dim, ext, arrow, sideLabel, section }, P) {
      const HW = { fill: '#1f201e', edge: '#050505', hi: '#3a3c38', lever: '#c9a25a', leverEdge: '#8d6d33' };

      function strap(x, y, w, h, k) {
        el('rect', { x, y, width: w, height: h, fill: P.fill, stroke: P.edge, 'stroke-width': 3 });
        for (const f of [0.25, 0.5, 0.75]) {
          el('line', { x1: x + 4, y1: y + h * f, x2: x + w - 4, y2: y + h * f, stroke: P.stitch, 'stroke-width': Math.max(1, k / 20), 'stroke-dasharray': `${k * 0.4} ${k * 0.25}` });
        }
      }

      // Buckle seen from the front, belt running left to right: the female frame
      // holds the strap end, the male plate clips into it with a lever top and bottom.
      function buckle(x, y, w, h, k) {
        const fw = w * 0.56;
        el('rect', { x, y, width: fw, height: h, rx: k * 0.5, fill: HW.fill, stroke: HW.edge, 'stroke-width': Math.max(2, k / 10) });
        el('rect', { x: x + fw * 0.2, y: y + h * 0.17, width: fw * 0.5, height: h * 0.66, rx: k * 0.15, fill: P.fill, stroke: HW.edge, 'stroke-width': Math.max(1.5, k / 14) });
        const mx = x + w * 0.46, mw = w * 0.54, mh = h * 0.8, my = y + (h - mh) / 2;
        for (const ly of [my - k * 0.28, my + mh + k * 0.28]) {
          const dir = ly < my ? -1 : 1;
          el('path', { d: `M${mx + mw * 0.18} ${ly - dir * k * 0.4} l${k * 0.55} ${dir * k * 0.55} l${k * 0.5} ${-dir * k * 0.55} Z`, fill: HW.lever, stroke: HW.leverEdge, 'stroke-width': Math.max(1, k / 16), 'stroke-linejoin': 'round' });
        }
        el('path', {
          d: `M${mx} ${my + mh * 0.18} q0 ${-mh * 0.18} ${mw * 0.18} ${-mh * 0.18} H${mx + mw - k * 0.3} q${k * 0.3} 0 ${k * 0.3} ${k * 0.3} V${my + mh - k * 0.3} q0 ${k * 0.3} ${-k * 0.3} ${k * 0.3} H${mx + mw * 0.18} q${-mw * 0.18} 0 ${-mw * 0.18} ${-mh * 0.18} Z`,
          fill: HW.fill, stroke: HW.edge, 'stroke-width': Math.max(2, k / 10),
        });
        el('line', { x1: mx + mw * 0.72, y1: my + mh * 0.12, x2: mx + mw * 0.72, y2: my + mh * 0.88, stroke: HW.hi, 'stroke-width': Math.max(1, k / 14) });
      }

      // Whole belt, to scale: 125 cm including the 5.6 x 5.3 cm buckle, 3.8 cm strap.
      const K = 9.6;
      const L = 125 * K, bw = 5.6 * K, bh = 5.3 * K, sh = 3.8 * K;
      const x0 = (1500 - L) / 2, cy = 500;
      section(x0, 350, 'CEINTURE COMPLÈTE');
      strap(x0 + bw - 4, cy - sh / 2, L - bw + 4, sh, K);
      buckle(x0, cy - bh / 2, bw, bh, K);
      ext(x0, cy - bh / 2 - 8, x0, 410); ext(x0 + L, cy - sh / 2 - 8, x0 + L, 410);
      dim(x0, 425, x0 + L, 425, '125 cm', 52);

      // Buckle detail, enlarged.
      const k = 48;
      const dw = 5.6 * k, dh = 5.3 * k, ds = 3.8 * k;
      const dx = 360, dy = 930, sw = 560;
      section(x0, 660, 'DÉTAIL DE LA BOUCLE');
      strap(dx + dw - 6, dy - ds / 2, sw, ds, k);
      const ex = dx + dw - 6 + sw;
      buckle(dx, dy - dh / 2, dw, dh, k);

      // 5.6 cm across the buckle.
      ext(dx, dy - dh / 2 - 10, dx, dy - dh / 2 - 92); ext(dx + dw, dy - dh / 2 - 10, dx + dw, dy - dh / 2 - 92);
      dim(dx, dy - dh / 2 - 76, dx + dw, dy - dh / 2 - 76, '5,6 cm', 44);
      // 5.3 cm buckle height.
      const hx = dx - 50;
      ext(dx - 8, dy - dh / 2, hx - 10, dy - dh / 2); ext(dx - 8, dy + dh / 2, hx - 10, dy + dh / 2);
      arrow(hx, dy - dh / 2, hx, dy + dh / 2);
      sideLabel(hx - 18, dy + 15, '5,3 cm', 'end', 44);
      // 3.8 cm strap width.
      const wx = ex + 40;
      ext(ex + 6, dy - ds / 2, wx + 10, dy - ds / 2); ext(ex + 6, dy + ds / 2, wx + 10, dy + ds / 2);
      arrow(wx, dy - ds / 2, wx, dy + ds / 2);
      sideLabel(wx + 18, dy + 15, '3,8 cm', 'start', 44);
    },
  },
};
