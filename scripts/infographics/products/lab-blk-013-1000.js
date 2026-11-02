// 1000 plain noir round stickers, 13 mm, on a roll (SKU LAB-BLK-013-1000,
// product 50 on prod). The family of lab-red-013-1000 (product 48): same
// roll, same 13 mm, same holes covered (4.5 and 5.5 mm, .22 LR). No supplier
// sheet: every fact comes from the product page on armooutdoor.fr, which
// gives this colour its own use (the page: the repair colour of reactive targets' black surface; plain paper, a shot on it leaves a clean hole with no splatter). Drawn with pastilles.js; the
// sticker colour is sampled from the page's close-up photo. Roll photo:
// gallery image 1, 1000 px, the anatomy markers mapped from product 48's.
const FILL = '#000000';

window.PRODUCT = {
  eyebrow: '1 000 pastilles rondes noires 13 mm',

  variants: {
    roll: {
      photo: 'photos/lab-blk-013-1000.jpg',
      points: [[[433, 251]], [[383, 567]], [[735, 302]], [[646, 506]]],
    },
  },

  anatomy: {
    title: 'Anatomie du rouleau',
    callouts: [
      { side: 'l', y: 470, title: 'Papier autocollant', text: 'Sur papier, carton, bois ou plastique' },
      { side: 'l', y: 790, title: 'Pastille de 13 mm', text: 'Ronde et noire, comme le fond des cibles' },
      { side: 'r', y: 450, title: 'Se décolle du rouleau', text: 'Pose au doigt, sans outil' },
      { side: 'r', y: 760, title: 'Rouleau de 1 000', text: 'Une réserve pour de nombreuses séances' },
    ],
    footer: {
      tag: 'Cibles réactives',
      html: 'Posée sur un trou, la pastille noire <strong>se fond dans la surface</strong> des cibles réactives : la zone redevient <strong>exploitable</strong>.',
    },
  },

  // One sticker and the coin, then the holes a 13 mm sticker covers.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À l’échelle',
      html: 'La pastille, la pièce de 1 € et les trous sont agrandis <strong>à la même échelle</strong> : la pastille couvre l’impact <strong>avec une petite marge</strong>.',
    },
    draw(kit) { Infographic.pastilles.dimensions(kit, FILL); },
  },

  // The uses this colour's page describes, two panels.
  effect: {
    title: 'Mode d’emploi',
    footer: {
      tag: 'Illustration',
      html: 'Usages reproduits <strong>à titre d’illustration</strong>, d’après la fiche produit. Pastilles en <strong>papier autocollant</strong>.',
    },
    draw(kit) {
      Infographic.pastilles.usage(kit, [
        { head: 'MASQUER', title: 'Sur le fond noir', line: 'la zone redevient exploitable.',
          draw: (k, x) => Infographic.pastilles.repair(k, x, { kind: 'reactive', fill: FILL, seed: 50, hits: Infographic.pastilles.REACTIVE_HITS, cover: Infographic.pastilles.BLACK_ZONES }) },
        { head: 'SANS RÉACTION', title: 'Un trou net', line: 'un nouveau tir dessus, sans gerbe.',
          draw: ({ el }, x) => {
            // A close-up of a reactive target's black surface: a patch hit
            // again (plain hole) beside an unrepaired hit (splatter).
            const cx = x + 280, cy = 730, R = 250, id = 'zoomBlk';
            const cp = el('clipPath', { id }, el('defs', {}));
            el('circle', { cx, cy, r: R }, cp);
            const g = el('g', { 'clip-path': `url(#${id})` });
            el('rect', { x: cx - R, y: cy - R, width: 2 * R, height: 2 * R, fill: '#121211' }, g);
            el('circle', { cx: cx - 70, cy: cy + 10, r: 110, fill: '#050505', stroke: '#2e2e2c', 'stroke-width': 2 }, g);
            Infographic.pastilles.hole(el, cx - 60, cy + 4, 13, g);
            Infographic.targets.splat(el, Infographic.rng(500), cx + 130, cy - 70, 46, g);
            el('circle', { cx, cy, r: R, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 5 });
          } },
      ]);
    },
  },
};
