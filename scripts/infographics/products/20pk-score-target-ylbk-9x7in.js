// 20 cardboard score targets, yellow and black, 22.86 x 17.78 cm portrait
// (SKU 20PK-SCORE-TARGET-YLBK-9X7IN, product 230 on prod). No supplier sheet:
// every fact comes from the product page on armooutdoor.fr. Not reactive and
// not adhesive. A plain oval, not a human shape. Measured on the flat
// gallery photo: card x 156 to 843, y 63 to 939 (centre 499.5, 501, half
// 343.5 x 438); stadium-shaped lines around 500,505: black oval x 215 to 785,
// y 92 to 920; outer line x 240 to 760, y 117 to 895; 8 line x 293 to 708,
// y 220 to 790; 9 line x 371 to 629, y 297 to 712; inner oval x 440 to 560,
// y 400 to 607; red X bull x 468 to 533, y 452 to 555.
window.PRODUCT = {
  eyebrow: '20 cibles carton score jaune et noir',

  variants: {
    card: {
      photo: 'photos/20pk-score-target-ylbk-9x7in.jpg',
      points: [[[228, 300]], [[293, 640]], [[190, 850]], [[558, 178]], [[500, 462]], [[820, 920]]],
    },
  },

  anatomy: {
    title: 'Anatomie de la cible',
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
        centre: [499.5, 501], half: 343.5, halfH: 438, mm: 177.8, mmH: 228.6, K: 3,
        facts: [['20', 'cibles'], ['Zones 7 à 9', 'et mouche X'], ['Carton', 'non adhésif']],
      });
    },
  },

  // Three cards in a row on a 1 m panel (mounted.html), at true size, as
  // for the 8-bullseye card of the same format.
  mounted: { centre: [499.5, 501], half: 343.5, halfH: 438, cm: 17.78, cmH: 22.86, count: 3 },

  // A pile of cards, no text (stack.html): the pack holds 20.
  stack: { centre: [499.5, 501], half: 343.5, halfH: 438, cm: 17.78, cmH: 22.86, count: 7 },

  // A series scored by zone, plain holes. Each hit was checked against the
  // stadium lines above, off the printed numbers and outside the inner
  // oval (whose value the page doesn't give): 9, 9, 8, 8, 7.
  effect: {
    title: 'Compter les points',
    footer: {
      tag: 'Illustration',
      html: 'Série reproduite <strong>à titre d’illustration</strong>. Cible <strong>non réactive</strong> : les impacts restent de simples trous.',
    },
    draw(kit, V) {
      Infographic.targets.beforeAfter(kit, V.photo, {
        centre: [499.5, 501], radius: 343.5, radiusH: 438, shape: 'rect', seed: 230,
        holes: 'plain',
        // Photo (585,440) 9, (420,600) 9, (660,380) 8, (380,690) 8, (620,200) 7, at 0.639;
        // each at least 25 photo px inside its zone's lines.
        hits: [[55, -39], [-51, 63], [103, -77], [-76, 121], [77, -192]],
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
