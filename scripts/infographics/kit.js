// Shared runtime for anatomy.html and dimensions.html.
// A sheet is opened as `<sheet>.html?p=<product>&v=<variant>`; the product spec
// lives in products/<product>.js and sets window.PRODUCT.
(function () {
  const NS = 'http://www.w3.org/2000/svg';
  const params = new URLSearchParams(location.search);

  function loadProduct(cb) {
    const p = params.get('p');
    const s = document.createElement('script');
    s.src = 'products/' + p + '.js';
    s.onload = () => {
      const product = window.PRODUCT;
      const v = params.get('v') || Object.keys(product.variants)[0];
      if (!product.variants[v]) throw new Error('Unknown variant ' + v);
      cb(product, product.variants[v], v);
    };
    document.head.appendChild(s);
  }

  function fillChrome(product, sheet) {
    document.getElementById('eyebrow').textContent = product.eyebrow;
    document.getElementById('title').textContent = sheet.title;
    document.getElementById('ftag').textContent = sheet.footer.tag;
    document.getElementById('ftext').innerHTML = sheet.footer.html;
  }

  // Small SVG toolkit bound to one <svg>.
  function svgKit(svg) {
    const el = (tag, attrs, parent = svg) => {
      const n = document.createElementNS(NS, tag);
      for (const k in attrs) n.setAttribute(k, attrs[k]);
      parent.appendChild(n);
      return n;
    };
    const text = (x, y, str, attrs) => { const t = el('text', { x, y, ...attrs }); t.textContent = str; return t; };

    const defs = el('defs', {});
    const mk = el('marker', { id: 'arr', viewBox: '0 0 12 12', refX: 11, refY: 6, markerWidth: 12, markerHeight: 12, orient: 'auto-start-reverse' }, defs);
    el('path', { d: 'M1 1 L11 6 L1 11 Z', fill: 'var(--ink)' }, mk);

    const arrow = (x1, y1, x2, y2) => el('line', { x1, y1, x2, y2, stroke: 'var(--ink)', 'stroke-width': 2.5, 'marker-start': 'url(#arr)', 'marker-end': 'url(#arr)' });
    // Dimension line with its measurement in a white gap at the middle.
    function dim(x1, y1, x2, y2, label, size = 46) {
      arrow(x1, y1, x2, y2);
      if (!label) return;
      const mx = (x1 + x2) / 2, my = (y1 + y2) / 2;
      const w = label.length * size * 0.5 + 34;
      el('rect', { x: mx - w / 2, y: my - size * 0.62, width: w, height: size * 1.24, fill: 'var(--paper)' });
      text(mx, my + size * 0.36, label, { 'text-anchor': 'middle', 'font-size': size, 'font-weight': 700, fill: 'var(--ink)' });
    }
    // Dashed extension line from the object to its dimension line.
    const ext = (x1, y1, x2, y2) => el('line', { x1, y1, x2, y2, stroke: 'var(--mute)', 'stroke-width': 1.5, 'stroke-dasharray': '5 5' });
    // Measurement set beside a vertical arrow (anchor 'end' puts it on the left).
    const sideLabel = (x, y, str, anchor = 'start', size = 34) => text(x, y, str, { 'text-anchor': anchor, 'font-size': size, 'font-weight': 700, fill: 'var(--ink)' });
    const section = (x, y, str) => text(x, y, str, { 'font-size': 30, 'font-weight': 700, 'letter-spacing': '.2em', fill: 'var(--coyote)' });

    return { el, text, arrow, dim, ext, sideLabel, section };
  }

  // Anatomy sheet: photo, labels in the side margins, a leader from each label
  // to a reticle marker on the part.
  function renderAnatomy(product, variant) {
    const sheet = product.anatomy;
    fillChrome(product, sheet);
    const photo = document.getElementById('photo');
    photo.src = variant.photo;
    if (sheet.photoFilter) photo.style.filter = sheet.photoFilter;

    // Points are in source-photo pixels (1000x1000), drawn at (300, 265) scaled
    // to 900px unless the spec sets photoBox { x, y, size } to enlarge a small product.
    const box = sheet.photoBox || { x: 300, y: 265, size: 900 };
    const OX = box.x, OY = box.y, S = box.size / 1000;
    Object.assign(photo.style, { left: OX + 'px', top: OY + 'px', width: box.size + 'px', height: box.size + 'px' });
    const k = svgKit(document.getElementById('lines'));
    const labels = document.getElementById('labels');
    const halo = [], ink = [], marks = [];
    const detach = n => { n.remove(); return n; };

    sheet.callouts.forEach((c, i) => {
      const div = document.createElement('div');
      div.className = 'label ' + c.side;
      div.style.top = c.y + 'px';
      div.innerHTML = `<b>${c.title}</b><span>${c.text}</span>`;
      labels.appendChild(div);

      const sx = c.side === 'l' ? 330 : 1170;
      const sy = c.y - 22;
      const kx = c.side === 'l' ? 365 : 1135;
      for (const [px, py] of variant.points[i]) {
        const tx = OX + S * px, ty = OY + S * py;
        const d = `M${sx} ${sy} L${kx} ${sy} L${tx} ${ty}`;
        halo.push(detach(k.el('path', { d, fill: 'none', stroke: '#fff', 'stroke-width': 7, 'stroke-linejoin': 'round', opacity: .85 })));
        ink.push(detach(k.el('path', { d, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 2.5, 'stroke-linejoin': 'round' })));
        const g = detach(k.el('g', { transform: `translate(${tx} ${ty})` }));
        k.el('circle', { r: 17, fill: 'rgba(255,255,255,.18)', stroke: '#fff', 'stroke-width': 7 }, g);
        k.el('circle', { r: 17, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 3 }, g);
        for (const [x1, y1, x2, y2] of [[0, -25, 0, -11], [0, 11, 0, 25], [-25, 0, -11, 0], [11, 0, 25, 0]]) {
          k.el('line', { x1, y1, x2, y2, stroke: '#fff', 'stroke-width': 6, 'stroke-linecap': 'round' }, g);
          k.el('line', { x1, y1, x2, y2, stroke: 'var(--coyote)', 'stroke-width': 2.5, 'stroke-linecap': 'round' }, g);
        }
        k.el('circle', { r: 3.5, fill: 'var(--coyote)' }, g);
        marks.push(g);
      }
      ink.push(detach(k.el('circle', { cx: sx, cy: sy, r: 5, fill: 'var(--coyote)' })));
    });
    // Halos under all leaders, markers over everything.
    const svg = document.getElementById('lines');
    [...halo, ...ink, ...marks].forEach(n => svg.appendChild(n));
  }

  // Dimensions sheet: the product spec draws its own technical drawing.
  function renderDimensions(product, variant) {
    const sheet = product.dimensions;
    fillChrome(product, sheet);
    sheet.draw(svgKit(document.getElementById('draw')), variant.palette);
  }

  // Effect sheet (optional): the spec draws how the product behaves in use,
  // with the variant passed in so it can place the photo itself.
  function renderEffect(product, variant) {
    const sheet = product.effect;
    fillChrome(product, sheet);
    sheet.draw(svgKit(document.getElementById('draw')), variant);
  }

  // Seeded random numbers, so a drawn effect renders the same every time.
  function rng(seed) {
    return () => {
      seed |= 0; seed = seed + 0x6D2B79F5 | 0;
      let t = Math.imul(seed ^ seed >>> 15, 1 | seed);
      t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t;
      return ((t ^ t >>> 14) >>> 0) / 4294967296;
    };
  }

  // Shared drawings for the round reactive targets (stickers on a roll).
  const targets = {
    // Dimensions sheet: a 1 euro coin (23.25 mm) to the right of the target,
    // bottoms aligned, and the pack facts above it. K is px per mm.
    coinAndFacts({ el, text }, { K, cy, R, facts }) {
      const cr = 23.25 / 2 * K, ccx = 1130, ccy = cy + R - cr;
      // Gold-coloured outer ring, silver-coloured centre (the 2 euro is the reverse).
      el('circle', { cx: ccx, cy: ccy, r: cr, fill: '#d6b25e', stroke: '#a8873d', 'stroke-width': 3 });
      el('circle', { cx: ccx, cy: ccy, r: cr * 0.72, fill: '#cfcbc2', stroke: '#8f8a7d', 'stroke-width': 2 });
      text(ccx, ccy + 14, '1 €', { 'text-anchor': 'middle', 'font-size': 40, 'font-weight': 700, fill: '#55524b' });
      text(ccx, ccy - cr - 46, 'Pièce de 1 €', { 'text-anchor': 'middle', 'font-size': 34, 'font-weight': 700, fill: 'var(--ink)' });
      text(ccx, ccy - cr - 14, '23,25 mm', { 'text-anchor': 'middle', 'font-size': 28, 'font-weight': 500, fill: 'var(--mute)' });
      facts.forEach(([big, small], i) => {
        const fy = 470 + i * 110;
        text(900, fy, big, { 'font-size': 50, 'font-weight': 700, fill: 'var(--ink)' });
        text(900, fy + 32, small, { 'font-size': 26, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.04em' });
      });
    },

    // Effect sheet: the photo's target before and after a series, with the
    // fluo splatter drawn on each hit, and a close-up of one hit.
    // centre/radius locate the target disc in the photo; hits are offsets in
    // panel px from the centre of the 560 px "after" panel.
    // A target whose splatter changes colour by zone sets zones: [[maxR, colour], ...]
    // (radii in photo px, innermost first) and closeUps: [{ label, colour, bg }]
    // for a row of one close-up per zone instead of the single one.
    // A non-reactive target sets holes: 'plain' (bare holes, no splatter),
    // can add beforeHits and a correction arrow pair from the before group
    // back to the centre, and swaps the close-up for a text block (closeUp: false).
    beforeAfter({ el, text, section }, photo, {
      centre: [px, py], radius, seed, hits, afterLabel, caption,
      beforeLabel = 'AVANT', beforeHits = [], holes = 'splatter', correction = null,
      closeUp = true, closeUpTitle = 'Gerbe jaune fluorescent', zones = null, closeUps = null,
      splatColour = '#e4f21c', closeUpBg = '#111210',
    }) {
      const rnd = rng(seed);
      const Y = splatColour;
      const defs = el('defs', {});

      // The photo's target disc clipped from its background.
      function panel(id, cx, cy, D) {
        const s = D / (2 * radius);
        const cp = el('clipPath', { id }, defs);
        el('circle', { cx, cy, r: D / 2 }, cp);
        el('image', { href: photo, x: cx - px * s, y: cy - py * s, width: 1000 * s, height: 1000 * s, 'clip-path': `url(#${id})` });
      }

      // One impact: a ragged fluo halo, a few flecks, the hole in the middle.
      function splat(x, y, r, parent, colour = Y) {
        // Jittered outline, smoothed through midpoints so the edge reads torn, not starry.
        const pts = [];
        for (let i = 0, n = 30; i < n; i++) {
          const a = (i / n) * 2 * Math.PI;
          const rr = r * (0.78 + rnd() * 0.32) * (rnd() < 0.12 ? 1.3 : 1);
          pts.push([x + Math.cos(a) * rr, y + Math.sin(a) * rr]);
        }
        const mid = (p, q) => [(p[0] + q[0]) / 2, (p[1] + q[1]) / 2];
        const m0 = mid(pts[pts.length - 1], pts[0]);
        let d = `M${m0[0].toFixed(1)} ${m0[1].toFixed(1)}`;
        pts.forEach((p, i) => {
          const m = mid(p, pts[(i + 1) % pts.length]);
          d += ` Q${p[0].toFixed(1)} ${p[1].toFixed(1)} ${m[0].toFixed(1)} ${m[1].toFixed(1)}`;
        });
        el('path', { d: d + 'Z', fill: colour }, parent);
        for (let i = 0, m = 5 + Math.floor(rnd() * 5); i < m; i++) {
          const a = rnd() * 2 * Math.PI, dd = r * (1.2 + rnd() * 0.8);
          el('circle', { cx: x + Math.cos(a) * dd, cy: y + Math.sin(a) * dd, r: r * (0.05 + rnd() * 0.09), fill: colour }, parent);
        }
        el('circle', { cx: x, cy: y, r: r * 0.27, fill: '#3a3b35' }, parent);
        el('circle', { cx: x, cy: y, r: r * 0.19, fill: '#050505' }, parent);
      }

      // A bare hole in paper: dark centre, a pale torn rim so it shows on black too.
      function hole(x, y, parent) {
        el('circle', { cx: x, cy: y, r: 11, fill: '#141414', stroke: 'rgba(255,255,255,.75)', 'stroke-width': 2.5 }, parent);
      }

      // Coyote arrow with a white halo, readable over any print.
      function arrowTo(x1, y1, x2, y2) {
        const a = Math.atan2(y2 - y1, x2 - x1), h = 18;
        const head = `M${x2 - h * Math.cos(a - 0.5)} ${y2 - h * Math.sin(a - 0.5)} L${x2} ${y2} L${x2 - h * Math.cos(a + 0.5)} ${y2 - h * Math.sin(a + 0.5)}`;
        for (const [stroke, w] of [['#fff', 11], ['var(--coyote)', 5]]) {
          el('path', { d: `M${x1} ${y1} L${x2} ${y2} ${head}`, fill: 'none', stroke, 'stroke-width': w, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' });
        }
      }
      const tag = (x, y, str, anchor) => text(x, y, str, { 'text-anchor': anchor, 'font-size': 30, 'font-weight': 700, fill: 'var(--ink)', stroke: '#fff', 'stroke-width': 7, 'paint-order': 'stroke' });

      const D = 560, cy = 650, lx = 410, rx = 1090;
      // Splatter colour for a hit at (dx, dy) panel px from the centre.
      const zoneColour = (dx, dy) => {
        if (!zones) return Y;
        const r = Math.hypot(dx, dy) * (2 * radius) / D;
        const z = zones.find(([maxR]) => r <= maxR);
        return z ? z[1] : zones[zones.length - 1][1];
      };
      section(lx - D / 2, 335, beforeLabel);
      section(rx - D / 2, 335, afterLabel);
      panel('before', lx, cy, D);
      panel('after', rx, cy, D);
      // Arrow from before to after.
      el('path', { d: `M${lx + D / 2 + 30} ${cy} H${rx - D / 2 - 34}`, stroke: 'var(--coyote)', 'stroke-width': 5 });
      el('path', { d: `M${rx - D / 2 - 50} ${cy - 16} L${rx - D / 2 - 28} ${cy} L${rx - D / 2 - 50} ${cy + 16}`, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 5, 'stroke-linejoin': 'round' });

      const before = el('g', { 'clip-path': 'url(#before)' });
      for (const [dx, dy] of beforeHits) hole(lx + dx, cy + dy, before);
      if (correction) {
        // Height back to the horizontal axis, then windage back to the centre.
        const [fx, fy] = correction;
        arrowTo(lx + fx, cy + fy - 30, lx + fx, cy + 14);
        arrowTo(lx + fx + 14, cy, lx - 30, cy);
        tag(lx + fx - 16, cy + fy / 2, 'Hauteur', 'end');
        tag(lx + fx / 2, cy - 22, 'Dérive', 'middle');
      }

      const after = el('g', { 'clip-path': 'url(#after)' });
      for (const [dx, dy] of hits) {
        if (holes === 'plain') hole(rx + dx, cy + dy, after);
        else splat(rx + dx, cy + dy, 24 + rnd() * 7, after, zoneColour(dx, dy));
      }

      if (closeUps) {
        closeUps.forEach(({ label, colour, bg }, i) => {
          const zx = 230 + i * 220, zy = 1135, zr = 85;
          const zc = el('clipPath', { id: 'zoom' + i }, defs);
          el('circle', { cx: zx, cy: zy, r: zr }, zc);
          const g = el('g', { 'clip-path': `url(#zoom${i})` });
          el('rect', { x: zx - zr, y: zy - zr, width: 2 * zr, height: 2 * zr, fill: bg }, g);
          splat(zx, zy, 46, g, colour);
          el('circle', { cx: zx, cy: zy, r: zr, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 4 });
          text(zx, zy + zr + 40, label, { 'text-anchor': 'middle', 'font-size': 30, 'font-weight': 700, fill: 'var(--ink)' });
        });
        text(870, 1110, closeUpTitle, { 'font-size': 48, 'font-weight': 700, fill: 'var(--ink)' });
        caption.forEach((line, i) => {
          text(870, 1156 + i * 36, line, { style: 'font-family: Barlow', 'font-size': 26, fill: 'var(--mute)' });
        });
        return;
      }

      if (!closeUp) {
        text(150, 1100, closeUpTitle, { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
        caption.forEach((line, i) => {
          text(150, 1150 + i * 38, line, { style: 'font-family: Barlow', 'font-size': 27, fill: 'var(--mute)' });
        });
        return;
      }

      // Close-up of one hit.
      const mx = 330, my = 1125, mr = 125;
      const mc = el('clipPath', { id: 'zoom' }, defs);
      el('circle', { cx: mx, cy: my, r: mr }, mc);
      const g = el('g', { 'clip-path': 'url(#zoom)' });
      el('rect', { x: mx - mr, y: my - mr, width: 2 * mr, height: 2 * mr, fill: closeUpBg }, g);
      splat(mx, my, 70, g);
      el('circle', { cx: mx, cy: my, r: mr, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 5 });
      text(mx + mr + 60, my - 30, closeUpTitle, { 'font-size': 52, 'font-weight': 700, fill: 'var(--ink)' });
      caption.forEach((line, i) => {
        text(mx + mr + 60, my + 18 + i * 38, line, { style: 'font-family: Barlow', 'font-size': 27, fill: 'var(--mute)' });
      });
    },
  };

  window.Infographic = { loadProduct, renderAnatomy, renderDimensions, renderEffect, rng, targets };
})();
