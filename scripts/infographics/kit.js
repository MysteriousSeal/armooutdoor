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

    // Points are in source-photo pixels (1000x1000), drawn at (300, 265) scaled to 900px.
    const OX = 300, OY = 265, S = 0.9;
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

  window.Infographic = { loadProduct, renderAnatomy, renderDimensions };
})();
