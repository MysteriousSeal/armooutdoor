// 20 cardboard targets, 8 bullseyes, 22.86 x 17.78 cm portrait (SKU
// 20PK-8BULLSEYE-TARGET-9X7IN, product 229 on prod). No supplier sheet:
// every fact comes from the product page on armooutdoor.fr. Not reactive and
// not adhesive: holes stay plain, the card is stapled or pinned. Measured on
// the flat gallery photo: card x 152 to 845, y 60 to 939 (centre 498.5,
// 499.5, half 346.5 x 439.5, about 1.4 % squatter than the real card, hence
// the separate width and height scales); yellow discs r98 centred at x 302 /
// 500 / 697, y 200 / 350 / 495 / 645 / 795; red bulls r12; grid numbered 0 to
// 3 across and 0 to 4 down from the centre.
const BLASONS = [[302, 200], [697, 203], [500, 350], [302, 495], [697, 495], [500, 645], [302, 795], [697, 795]];

window.PRODUCT = {
  eyebrow: '20 cibles carton 8 blasons',

  variants: {
    card: {
      photo: 'photos/20pk-8bullseye-target-9x7in.jpg',
      points: [[[230, 250]], [[302, 495]], [[205, 650]], [[760, 203]], [[795, 555]], [[780, 935]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
    callouts: [
      { side: 'l', y: 400, title: 'Huit blasons', text: 'En trois colonnes, ceux du centre décalés' },
      { side: 'l', y: 650, title: 'Mouche rouge', text: 'Au cœur de chaque blason' },
      { side: 'l', y: 900, title: 'Quadrillage chiffré', text: '0 à 3 en largeur, 0 à 4 en hauteur' },
      { side: 'r', y: 430, title: 'Anneaux fins', text: 'Disque jaune cerclé de noir près du centre' },
      { side: 'r', y: 680, title: 'Fond noir quadrillé', text: 'Le jaune ressort, les trous se lisent' },
      { side: 'r', y: 930, title: 'Carton', text: 'À agrafer, pincer ou punaiser' },
    ],
    footer: {
      tag: 'Non réactive',
      html: 'Ni adhésive ni réactive : les impacts restent de <strong>simples trous</strong>, lisibles grâce au contraste du <strong>jaune et du noir</strong>.',
    },
  },

  // The photo cropped to the card and drawn at true size.
  dimensions: {
    title: 'Dimensions',
    footer: {
      tag: 'À l’échelle',
      html: 'La cible et la pièce de 1 € sont dessinées <strong>à la même échelle</strong>. À tirer de préférence entre <strong>10 et 25 m</strong>.',
    },
    draw(kit, P, V) {
      Infographic.targets.squareToScale(kit, V.photo, {
        centre: [498.5, 499.5], half: 346.5, halfH: 439.5, mm: 177.8, mmH: 228.6, K: 3,
        facts: [['20', 'cibles'], ['8 blasons', 'par cible'], ['Carton', 'non adhésif']],
      });
    },
  },

  // Three cards in a row on a 1 m panel (mounted.html), at true size: the
  // user asked for three, five don't fit at this size.
  mounted: { centre: [498.5, 499.5], half: 346.5, halfH: 439.5, cm: 17.78, cmH: 22.86, count: 3 },

  // A pile of cards, no text (stack.html): the pack holds 20.
  stack: { centre: [498.5, 499.5], half: 346.5, halfH: 439.5, cm: 17.78, cmH: 22.86, count: 7 },

  // One series per blason, in reading order, the groups tightening as the
  // session goes: the page's "see at a glance whether precision improved".
  effect: {
    title: 'Une série par blason',
    footer: {
      tag: 'Illustration',
      html: 'Séance reproduite <strong>à titre d’illustration</strong>. Cible <strong>non réactive</strong> : les impacts restent de simples trous.',
    },
    draw(kit, V) {
      const rnd = Infographic.rng(229), s = 560 / (2 * 439.5), hits = [];
      // Three holes a series, spread round the bull so each stays visible
      // (holes are 22 px across); the spread shrinks from 40 to 16 px.
      BLASONS.forEach(([bx, by], i) => {
        const spread = 40 - i * 24 / 7, a0 = rnd() * 2 * Math.PI;
        for (let k = 0; k < 3; k++) {
          const a = a0 + k * 2 * Math.PI / 3 + (rnd() - 0.5) * 0.6, r = spread * (0.8 + rnd() * 0.2);
          hits.push([(bx - 498.5) * s + Math.cos(a) * r, (by - 499.5) * s + Math.sin(a) * r]);
        }
      });
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [498.5, 499.5], radius: 346.5, radiusH: 439.5, shape: 'rect', seed: 229,
        holes: 'plain', hits,
        afterLabel: 'APRÈS HUIT SÉRIES',
        closeUp: false,
        closeUpTitle: 'Huit séries, un seul carton',
        caption: [
          'Un blason par série : en fin de séance, les huit groupements restent',
          'réunis et montrent d’un regard si la précision a progressé.',
        ],
      });
    },
  },
};
