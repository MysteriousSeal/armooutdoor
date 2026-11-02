// 20 cardboard score targets, yellow and black, 22.86 x 17.78 cm portrait
// (SKU 20PK-SCORE-TARGET-YLBK-9X7IN, product 230 on prod). No supplier sheet:
// every fact comes from the product page on armooutdoor.fr. Not reactive and
// not adhesive. A plain oval, not a human shape. Measured on the sharper
// flat photo the user uploaded on 2026-11-02: card x 147 to 851, y 62 to 939
// (centre 499, 500.5, half 352 x 438.5); stadium-shaped lines around 500,503:
// black oval x 212 to 788, y 88 to 920; outer line x 237 to 763; 8 line x 292
// to 708, y 218 to 788; 9 line x 370 to 630, y 297 to 707; inner oval x 440
// to 560, y 400 to 607; red X bull x 467 to 533, y 452 to 557 (the X itself
// is printed yellow). Zones: 9 between the inner oval and the 9 line, 8 to
// the 8 line, 7 to the outer line.
const CARD = { centre: [499, 500.5], half: 352, halfH: 438.5 };
window.PRODUCT = {
  eyebrow: '20 cibles carton score jaune et noir',

  variants: {
    card: {
      photo: 'photos/20pk-score-target-ylbk-9x7in.jpg',
      points: [[[224, 380]], [[292, 640]], [[180, 850]], [[562, 177]], [[500, 466]], [[820, 920]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
    photoFilter: 'brightness(1.03)',
    callouts: [
      { side: 'l', y: 380, title: 'Ovale noir', text: 'Plus haut que large, il épouse le carton' },
      { side: 'l', y: 640, title: 'Zones 7, 8 et 9', text: 'Délimitées par des lignes jaunes' },
      { side: 'l', y: 900, title: 'Fond jaune', text: 'Un tir sorti de l’ovale se voit tout de suite' },
      { side: 'r', y: 420, title: 'Chiffres répétés', text: 'En haut, en bas, à gauche et à droite' },
      { side: 'r', y: 660, title: 'Mouche X', text: 'Rouge, dans un petit ovale cerclé de jaune' },
      { side: 'r', y: 900, title: 'Carton', text: 'À agrafer ou punaiser' },
    ],
    footer: {
      tag: 'Non réactive',
      html: 'Le trou se lit <strong>directement sur le noir ou sur le jaune</strong>. Plus rigide qu’une feuille, le carton <strong>tient mieux au vent</strong>.',
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
        ...CARD, mm: 177.8, mmH: 228.6, K: 3,
        facts: [['20', 'cibles'], ['Zones 7 à 9', 'et mouche X'], ['Carton', 'non adhésif']],
      });
    },
  },

  // Three cards in a row on a 1 m panel (mounted.html), at true size, as
  // for the 8-bullseye card of the same format.
  mounted: [
    { ...CARD, cm: 17.78, cmH: 22.86, count: 3 },
    // Four in a 2 x 2 grid on a 55 x 55 cm board, the size of a C50 card.
    { id: 'c50', ...CARD, cm: 17.78, cmH: 22.86, panel: [55, 55], count: 4 },
  ],

  // A pile of cards, no text (stack.html): the pack holds 20.
  stack: { ...CARD, cm: 17.78, cmH: 22.86, count: 7 },

  // The scored series both the effect sheet and the text-free impact.html
  // draw. Each hit was checked on the photo by counting the yellow lines
  // crossed from the centre, and keeps 27 px or more from any line: photo
  // (575,415) 9, (420,600) 9, (660,380) 8, (340,640) 8, (650,210) 7, at
  // panel scale 0.6386.
  impact: {
    ...CARD, radius: CARD.half, radiusH: CARD.halfH, shape: 'rect', seed: 230, holes: 'plain',
    hits: [[48.5, -54.6], [-50.4, 63.5], [102.8, -77], [-101.5, 89.1], [96.4, -185.5]],
  },

  // Before / after the scored series, plain holes.
  effect: {
    title: 'Compter les points',
    footer: {
      tag: 'Illustration',
      html: 'Série reproduite <strong>à titre d’illustration</strong>. Cible <strong>non réactive</strong> : les impacts restent de simples trous.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        ...window.PRODUCT.impact,
        afterLabel: 'APRÈS UNE SÉRIE',
        closeUp: false,
        closeUpTitle: 'Le score de la série',
        caption: [
          'Chaque trou vaut le chiffre de sa zone : 9 + 9 + 8 + 8 + 7 = 41 points.',
          'Comparez le total d’une série à l’autre.',
        ],
      });
    },
  },
};
