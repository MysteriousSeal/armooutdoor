// Tactical belt + padded MOLLE belt set (SKU family molle-belt-eva).
// Sources: supplier sheets "Belt information", the padded-belt close-up
// ("Suspension system pull ring x4", "rebound material", "nylon braid") and
// "Product size" (125 x 5.3 cm belt, 75 x 8 cm padded belt).
window.PRODUCT = {
  eyebrow: 'Ceinture tactique + ceinture rembourrée MOLLE',

  // points: one list per callout, in callout order, in source-photo pixels.
  variants: {
    black: {
      photo: 'photos/molle-belt-eva-blk.jpg',
      points: [[[200, 120]], [[150, 215]], [[451, 290]], [[355, 455], [212, 645]], [[100, 790]], [[505, 224]], [[666, 265], [666, 336]], [[575, 585]], [[700, 820]]],
      palette: { fill: '#2d2e2c', edge: '#141514', stitch: '#555852', hw: '#1f201e', snap: '#3d3f3b' },
    },
    khaki: {
      photo: 'photos/molle-belt-eva-khaki.jpg',
      points: [[[220, 160]], [[150, 230]], [[432, 300]], [[370, 455], [200, 650]], [[120, 760]], [[485, 235]], [[632, 272], [634, 352]], [[560, 580]], [[690, 800]]],
      palette: { fill: '#c6aa74', edge: '#94794a', stitch: '#e2cfa4', hw: '#b0965f', snap: '#9b8455' },
    },
    odg: {
      photo: 'photos/molle-belt-eva-odg.jpg',
      points: [[[220, 140]], [[150, 230]], [[432, 300]], [[335, 480], [190, 625]], [[110, 760]], [[490, 225]], [[640, 245], [640, 322]], [[520, 600]], [[700, 800]]],
      palette: { fill: '#6b7046', edge: '#43472a', stitch: '#949a68', hw: '#5c6140', snap: '#4c5034' },
    },
  },

  anatomy: {
    title: 'Anatomie de la ceinture',
    callouts: [
      { side: 'l', y: 330, title: 'Face velcro', text: 'Intérieur auto-agrippant, la ceinture ne glisse pas' },
      { side: 'l', y: 460, title: 'Nylon tressé', text: 'Sangle haute résistance' },
      { side: 'l', y: 600, title: 'Verrou de sécurité', text: 'Empêche toute ouverture accidentelle' },
      { side: 'l', y: 800, title: 'Anneaux de suspension ×4', text: 'Pour bretelles ou harnais de portage' },
      { side: 'l', y: 1030, title: 'Sangles MOLLE', text: 'Fixez poches, étuis et porte-chargeurs' },
      { side: 'r', y: 345, title: 'Clips de la boucle', text: 'Deux clips, en haut et en bas de la boucle' },
      { side: 'r', y: 545, title: 'Passants à pressions', text: 'Bloquent la sangle une fois réglée' },
      { side: 'r', y: 790, title: 'Filet respirant', text: 'Respire et sèche rapidement' },
      { side: 'r', y: 1020, title: 'Rembourrage EVA', text: 'Mousse à rebond, répartit la charge' },
    ],
    footer: {
      tag: 'Ouverture',
      html: 'Pour ouvrir la boucle, appuyez <strong>en même temps</strong> sur le <strong>verrou de sécurité</strong> et sur les <strong>clips de la boucle</strong>.',
    },
  },

  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À l’échelle',
      html: 'Les deux ceintures sont dessinées à la même échelle. <strong>Mesures prises à la main</strong> : un léger écart est possible.',
    },
    draw({ el, text, dim, ext, arrow, sideLabel, section }, P) {
      const K = 9.6; // px per cm, shared by both drawings

      // Tactical belt: 125 x 5.3 cm, laid flat with both buckle halves on.
      const bL = 125 * K, bH = 5.3 * K, bx = (1500 - bL) / 2, by = 490;
      section(bx, 350, 'CEINTURE TACTIQUE');
      ext(bx, by - 10, bx, 415); ext(bx + bL, by - 10, bx + bL, 415);
      dim(bx, 430, bx + bL, 430, '125 cm', 52);
      el('rect', { x: bx, y: by, width: bL, height: bH, rx: 10, fill: P.fill, stroke: P.edge, 'stroke-width': 3 });
      for (const f of [0.28, 0.72]) {
        el('line', { x1: bx + 8, y1: by + bH * f, x2: bx + bL - 8, y2: by + bH * f, stroke: P.stitch, 'stroke-width': 1.5, 'stroke-dasharray': '7 5' });
      }
      // Buckle halves with their snap keepers.
      for (const [cx, dir] of [[bx + bL * 0.17, 1], [bx + bL * 0.83, -1]]) {
        el('rect', { x: cx - 26, y: by - 6, width: 52, height: bH + 12, rx: 4, fill: P.fill, stroke: P.edge, 'stroke-width': 3 });
        for (const sy of [by + bH * 0.3, by + bH * 0.7]) el('circle', { cx, cy: sy, r: 7, fill: P.snap, stroke: P.edge, 'stroke-width': 2 });
        const hx = cx + dir * 34;
        el('path', { d: `M${hx} ${by - 9} h${dir * 30} v${bH + 18} h${-dir * 30} Z`, fill: P.hw, stroke: P.edge, 'stroke-width': 3, 'stroke-linejoin': 'round' });
      }
      // Width.
      const wx = bx - 26;
      ext(bx - 4, by, wx - 8, by); ext(bx - 4, by + bH, wx - 8, by + bH);
      arrow(wx, by, wx, by + bH);
      sideLabel(wx - 20, by + bH / 2 + 12, '5,3 cm', 'end');
      // Hook-and-loop runs the whole inner face.
      const vy = by + bH + 30;
      el('path', { d: `M${bx} ${vy - 10} V${vy} H${bx + bL} V${vy - 10}`, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 3 });
      text(bx + bL / 2, vy + 44, 'Face intérieure velcro sur toute la longueur', { 'text-anchor': 'middle', 'font-size': 32, 'font-weight': 600, fill: 'var(--coyote)' });

      // Padded belt: 75 x 8 cm, four suspension rings on top.
      const pL = 75 * K, pH = 8 * K, px = (1500 - pL) / 2, py = 990;
      section(bx, 840, 'CEINTURE REMBOURRÉE');
      for (const f of [0.07, 0.37, 0.63, 0.93]) {
        const rx = px + pL * f;
        el('path', { d: `M${rx - 16} ${py + 4} V${py - 34} a16 16 0 0 1 32 0 V${py + 4}`, fill: 'none', stroke: P.hw, 'stroke-width': 7, 'stroke-linejoin': 'round' });
        el('rect', { x: rx - 13, y: py - 18, width: 26, height: 30, rx: 3, fill: P.fill, stroke: P.edge, 'stroke-width': 2 });
      }
      el('rect', { x: px, y: py, width: pL, height: pH, rx: 34, fill: P.fill, stroke: P.edge, 'stroke-width': 3 });
      // MOLLE rows and columns.
      for (const f of [0.33, 0.66]) {
        el('line', { x1: px + 30, y1: py + pH * f, x2: px + pL - 30, y2: py + pH * f, stroke: P.stitch, 'stroke-width': 2 });
      }
      for (let x = px + 75; x < px + pL - 40; x += 75) {
        el('line', { x1: x, y1: py + 8, x2: x, y2: py + pH - 8, stroke: P.edge, 'stroke-width': 2 });
      }
      el('rect', { x: px + 7, y: py + 7, width: pL - 14, height: pH - 14, rx: 28, fill: 'none', stroke: P.stitch, 'stroke-width': 1.5, 'stroke-dasharray': '7 5' });
      // Height.
      const hx = px - 30;
      ext(px + 20, py, hx - 8, py); ext(px + 20, py + pH, hx - 8, py + pH);
      arrow(hx, py, hx, py + pH);
      sideLabel(hx - 16, py + pH / 2 + 14, '8 cm', 'end');
      // Length.
      ext(px, py + pH + 10, px, py + pH + 92); ext(px + pL, py + pH + 10, px + pL, py + pH + 92);
      dim(px, py + pH + 80, px + pL, py + pH + 80, '75 cm', 52);
    },
  },
};
