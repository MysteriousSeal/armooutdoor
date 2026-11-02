// 1000 plain red round stickers, 13 mm, on a roll (SKU LAB-RED-013-1000,
// product 48 on prod). No supplier sheet: every fact comes from the product
// page on armooutdoor.fr (13 mm, red, self-adhesive paper, peeled off with a
// fingernail; covers a 4.5 or 5.5 mm pellet or BB hole and a .22 LR hole;
// repairs a red bull, or serves as an improvised aim point). Photos: the
// roll (gallery image 1, 1000 px: stickers at x 270 to 495, core x 585 to
// 705) and one sticker's close-up cropped from gallery image 2
// (photos/<sku>-sticker.jpg, disc centre 500,500, r483).
const STICKER = 'photos/lab-red-013-1000-sticker.jpg';

window.PRODUCT = {
  eyebrow: '1 000 pastilles rondes rouges 13 mm',

  variants: {
    roll: {
      photo: 'photos/lab-red-013-1000.jpg',
      points: [[[430, 250]], [[380, 560]], [[735, 300]], [[645, 500]]],
    },
  },

  anatomy: {
    title: 'Anatomie du rouleau',
    callouts: [
      { side: 'l', y: 470, title: 'Papier autocollant', text: 'Adhère à une surface propre et sèche' },
      { side: 'l', y: 790, title: 'Pastille de 13 mm', text: 'Ronde, d’un rouge qui tranche' },
      { side: 'r', y: 450, title: 'Se décolle d’un ongle', text: 'Pose au doigt, sans outil' },
      { side: 'r', y: 760, title: 'Rouleau de 1 000', text: 'Se range dans une mallette de tir' },
    ],
    footer: {
      tag: 'Réparer',
      html: 'Posée sur les trous du centre, la pastille <strong>rend son aspect à la mouche rouge</strong> : la série suivante repart sur <strong>une référence propre</strong>.',
    },
  },

  // One sticker and the coin at 14 px per mm, then the holes the page says a
  // sticker covers, at the same scale, dashed under three stickers.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À l’échelle',
      html: 'La pastille, la pièce de 1 € et les trous sont agrandis <strong>à la même échelle</strong> : la pastille couvre l’impact <strong>avec une petite marge</strong>.',
    },
    draw({ el, text, dim, ext, section }) {
      const T = Infographic.targets, K = 14, r = 13 / 2 * K, cr = 23.25 / 2 * K;
      section(150, 400, 'UNE PASTILLE ET LA PIÈCE');
      const sx = 270, sy = 760;
      T.sticker(el, STICKER, sx, sy, r);
      ext(sx - r, sy - 8, sx - r, sy - r - 70); ext(sx + r, sy - 8, sx + r, sy - r - 70);
      dim(sx - r, sy - r - 55, sx + r, sy - r - 55, '13 mm', 44);
      T.coin({ el, text }, 610, sy + r - cr, cr);
      [['1 000', 'pastilles'], ['Rouleau', 'à dérouler au fil des séances']].forEach(([big, small], i) => {
        const fy = 640 + i * 110;
        text(860, fy, big, { 'font-size': 50, 'font-weight': 700, fill: 'var(--ink)' });
        text(860, fy + 32, small, { 'font-size': 26, 'font-weight': 500, fill: 'var(--mute)', 'letter-spacing': '.04em' });
      });

      section(150, 960, 'IMPACTS COUVERTS');
      // Hole sizes: the pellet calibres, and a .22 LR bullet (5.7 mm).
      [['4,5 mm', 'plomb ou bille', 4.5], ['5,5 mm', 'plomb ou bille', 5.5], ['.22 LR', 'impact', 5.7]].forEach(([big, small, mm], i) => {
        const cx = 245 + i * 350, cy = 1120;
        T.sticker(el, STICKER, cx, cy, r);
        el('circle', { cx, cy, r: mm / 2 * K, fill: 'none', stroke: '#1b1d19', 'stroke-width': 3, 'stroke-dasharray': '6 5' });
        text(cx + r + 24, cy - 4, big, { 'font-size': 38, 'font-weight': 700, fill: 'var(--ink)' });
        text(cx + r + 24, cy + 28, small, { 'font-size': 24, 'font-weight': 500, fill: 'var(--mute)' });
      });
    },
  },

  // The page's two uses: remaking the red bull of a used target, and an
  // improvised aim point on cardboard.
  effect: {
    title: 'Mode d’emploi',
    footer: {
      tag: 'Illustration',
      html: 'Usages reproduits <strong>à titre d’illustration</strong>, d’après la fiche produit. Pastilles en <strong>papier autocollant</strong>.',
    },
    draw(kit) {
      const { el, text, section } = kit, T = Infographic.targets, rnd = Infographic.rng(48);
      // Left: our 76 mm target before and after, two hits in its red bull;
      // one 13 mm sticker (true relative size: r 22 on a 260 px target)
      // remakes it (r 21 on a 250 px target). Both use the same seed, so the
      // splatters match.
      const hits = [[-7, 5], [8, -6], [44, -58], [65, 28], [-28, 70], [-77, 19], [84, -70]];
      T.usedTarget(el, 255, 730, 250, Infographic.rng(480), hits);
      T.usedTarget(el, 565, 730, 250, Infographic.rng(480), hits);
      T.sticker(el, STICKER, 565, 730, 21);
      el('path', { d: 'M394 730 H426', stroke: 'var(--coyote)', 'stroke-width': 5 });
      el('path', { d: 'M414 718 L428 730 L414 742', fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 5, 'stroke-linejoin': 'round' });
      text(255, 900, 'AVANT', { 'text-anchor': 'middle', 'font-size': 24, 'font-weight': 700, 'letter-spacing': '.2em', fill: 'var(--mute)' });
      text(565, 900, 'APRÈS', { 'text-anchor': 'middle', 'font-size': 24, 'font-weight': 700, 'letter-spacing': '.2em', fill: 'var(--mute)' });
      // Right: red stickers as aim points on bare cardboard, two shot.
      T.cardboard(el, 810, 450, 560, 560, 48);
      [[950, 590], [1230, 590], [1090, 730], [950, 870], [1230, 870]].forEach(([cx, cy], i) => {
        T.sticker(el, STICKER, cx, cy, 34);
        if (i === 0 || i === 2) el('circle', { cx: cx + (rnd() - 0.5) * 10, cy: cy + (rnd() - 0.5) * 10, r: 7, fill: '#141414', stroke: 'rgba(255,255,255,.7)', 'stroke-width': 1.5 });
      });
      [['RÉPARER', 'Refaire la mouche rouge', 'les trous du centre disparaissent.'], ['VISER', 'Un point de visée improvisé', 'lisible de loin sur un fond clair.']].forEach(([head, title, line], i) => {
        const x = i ? 810 : 130;
        section(x, 425, head);
        text(x, 1065, title, { 'font-size': 44, 'font-weight': 700, fill: 'var(--ink)' });
        text(x, 1105, line, { style: 'font-family: Barlow', 'font-size': 27, fill: 'var(--mute)' });
      });
    },
  },
};
