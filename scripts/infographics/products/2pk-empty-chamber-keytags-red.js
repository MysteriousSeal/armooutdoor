// Pack of 2 empty chamber flags with red embroidered tags (SKU
// 2PK-EMPTY-CHAMBER-KEYTAGS-RED, product 114 on prod). Facts from the product
// page and the Temu listing the user saved (2 flags, 2 tags, 2 rings; plastic
// flag, nylon tag, metal ring; text sewn in, not printed; for rifles, pistols
// and shotguns). The listing gives no measure, so there is no dimensions sheet.
// Its material sources disagree ("silicone polymer", "polyethylene", "strong
// plastic"): the sheets say rigid plastic. Photos from the gallery, whitened:
// flags and tags apart (photos/<sku>.jpg), hanging (-hanging.jpg), in place
// (-inplace.jpg, not whitened).
const ZOOM = { cx: 280, cy: 730, R: 250 };

window.PRODUCT = {
  eyebrow: 'Témoin de chambre vide, lot de 2',

  variants: {
    lot: {
      photo: 'photos/2pk-empty-chamber-keytags-red.jpg',
      hanging: 'photos/2pk-empty-chamber-keytags-red-hanging.jpg',
      inplace: 'photos/2pk-empty-chamber-keytags-red-inplace.jpg',
      points: [[[70, 300]], [[205, 335]], [[140, 650]], [[770, 270]], [[905, 330]], [[800, 600]]],
    },
  },

  anatomy: {
    title: 'Anatomie du lot',
    callouts: [
      { side: 'l', y: 400, title: 'Anneau métal', text: 'Relie l’étiquette au témoin' },
      { side: 'l', y: 680, title: 'Œillet', text: 'Renforce le trou de l’étiquette' },
      { side: 'l', y: 960, title: 'Bord surjeté', text: 'Un contour cousu tout autour' },
      { side: 'r', y: 380, title: 'Passant triangulaire', text: 'Reçoit l’anneau de l’étiquette' },
      { side: 'r', y: 620, title: 'Témoin orange', text: 'Plastique rigide, moulé d’un bloc' },
      { side: 'r', y: 880, title: 'Texte brodé', text: 'Empty chamber, blanc sur rouge' },
    ],
    footer: {
      tag: 'Le lot',
      html: '« Empty chamber » veut dire <strong>chambre vide</strong>. Le lot contient <strong>2 témoins, 2 étiquettes et 2 anneaux</strong>.',
    },
  },

  effect: [
    {
      id: 'usage',
      title: 'En place',
      footer: {
        tag: 'Sécurité',
        html: 'Le témoin <strong>ne remplace pas le contrôle</strong> : toujours vérifier soi-même que la chambre est vide.',
      },
      draw(kit, V) {
        const zoom = (x, href, [px, py], scale, id) => {
          const { el } = kit, cx = x + ZOOM.cx, cy = ZOOM.cy, R = ZOOM.R;
          const cp = el('clipPath', { id }, el('defs', {}));
          el('circle', { cx, cy, r: R }, cp);
          el('image', { href, x: cx - px * scale, y: cy - py * scale, width: 1000 * scale, height: 1000 * scale, 'clip-path': `url(#${id})` });
          el('circle', { cx, cy, r: R, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 5 });
        };
        Infographic.pastilles.usage(kit, [
          { head: 'LE TÉMOIN', title: 'Il dépasse de la fenêtre', line: 'logé dans la fenêtre d’éjection, il se voit de loin.',
            draw: (k, x) => zoom(x, V.inplace, [610, 560], 0.85, 'inPlace') },
          { head: 'L’ÉTIQUETTE', title: 'Elle pend à l’anneau', line: 'le rouge et le texte signalent la chambre vide.',
            draw: (k, x) => zoom(x, V.hanging, [500, 500], 0.5, 'hanging') },
        ]);
      },
    },
    {
      id: 'details',
      title: 'Les détails',
      footer: {
        tag: 'Usage',
        html: 'Donné par le fabricant pour <strong>carabines, fusils et pistolets</strong>. Le témoin se loge dans la fenêtre d’éjection.',
      },
      draw(kit, V) {
        const zoom = (x, [px, py], scale, id) => {
          const { el } = kit, cx = x + ZOOM.cx, cy = ZOOM.cy, R = ZOOM.R;
          const cp = el('clipPath', { id }, el('defs', {}));
          el('circle', { cx, cy, r: R }, cp);
          el('image', { href: V.photo, x: cx - px * scale, y: cy - py * scale, width: 1000 * scale, height: 1000 * scale, 'clip-path': `url(#${id})`, style: 'mix-blend-mode: multiply' });
          el('circle', { cx, cy, r: R, fill: 'none', stroke: 'var(--coyote)', 'stroke-width': 5 });
        };
        Infographic.pastilles.usage(kit, [
          { head: 'LA BRODERIE', title: 'Texte brodé', line: 'cousu dans le tissu, pas imprimé.',
            draw: (k, x) => zoom(x, [600, 510], 1.25, 'stitch') },
          { head: 'LA PIÈCE', title: 'Plastique rigide', line: 'un seul bloc orange, avec son passant pour l’anneau.',
            draw: (k, x) => zoom(x, [385, 180], 1.2, 'flag') },
        ]);
      },
    },
  ],
};
