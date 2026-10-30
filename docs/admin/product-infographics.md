# Product infographics

How to make the square gallery images that explain a product: an **anatomy**
sheet (the product photo with each part labelled), a **dimensions** sheet
(a technical drawing of the product with its measurements) and, when the
product does something visible in use, an optional **effect** sheet (for
example a reactive target before and after a series). Both are
rendered from HTML and SVG to a 1500x1500 PNG with headless Chrome.

Everything lives in `scripts/infographics/`:

| Path | What it is |
|------|------------|
| `anatomy.html`, `dimensions.html`, `effect.html` | The sheet templates. Generic, never edited per product. `effect` renders only for specs that define it. |
| `mounted.html` | The "Grandeur nature" sheet: five of the product's targets stuck at true size on a plain plywood panel 1 m wide and 60 cm high (one in the centre, four towards the corners), standing on pine laths in a steel base, under the house header, with the panel's width and height measured. Renders for specs that define `mounted`, see below. |
| `stack.html` | A pile of the product's cards on white, **no text**, to show the pack holds several: fanned cards in balanced pairs behind a squared-up pile. Renders for specs that define `stack` (same fields as `mounted`, plus `count`, the number of cards drawn, 7 by default in the specs so far). |
| `kit.js` | Shared runtime: loads the product spec, draws the labels, leader lines, reticle markers and dimension lines. |
| `shared.css` | House style: colours, fonts, header, labels, the note box at the bottom. |
| `products/<product>.js` | One spec per product family: text, colourways, marker positions, and the dimensions drawing. |
| `photos/` | Source product photos, 1000x1000, one per colourway. |
| `sources/` | The supplier's info sheets the text was checked against. |
| `out/` | The rendered images. **Tracked in git**: they are the reference for what a finished sheet looks like. |
| `render.sh` | Renders both sheets for every colourway of a product into `out/`. |
| `grid.py` | Overlays a coordinate grid on a photo to read marker positions. |

## Existing products

| Spec | Product | Colourways |
|------|---------|------------|
| `molle-belt-eva` | Ceinture tactique + ceinture rembourrée MOLLE | black, khaki, odg |
| `belt-tac-adj-pls` | Ceinture tactique à boucle rapide (cobra-style, plastic buckle) | black, green |
| `men-nylon-belt-qr` | Ceinture en sangle à boucle rapide (eyelets, webbing clips; polypropylene, not nylon) | odg |
| `crt-react-076-200-fluo` | 200 cibles rondes réactives 76 mm (no supplier sheet: facts from the product page; a 1 € coin drawn to scale; effect sheet with the splatter) | fluo |
| `rlt-splat-076-200-fluo` | 200 cibles rondes réactives 76 mm 10 zones lettrées (same approach; the series is grouped in sector H) | fluo |
| `tgt-splat-pnk-076-200` | 200 cibles rondes 76 mm rose fluorescent réglage visée (**not reactive**: the effect sheet shows a sight correction with plain holes) | pink |
| `cr-ov-splat-076-200` | 200 cibles rondes réactives 76 mm gerbe zonée 3 couleurs (splatter green / orange / yellow by zone, one close-up per zone) | tricolour |
| `tgt-spltr-org-100x10` | 100 cibles rondes réactives 10 cm orange centre noir (10 cm drawn at 6 px/mm; yellow splatter on orange) | orange |
| `cr-splat-076-100-22lr` | 100 cibles rondes réactives 76 mm jaune fluorescent (same design as the 200 pack, measured on its own photo) | fluo |
| `cr-rn-splat-100mm-100` | 100 cibles rondes réactives 10 cm rouge et noir 5 zones (numbered 6 to 10, four repair patches; hits on the diagonals, off the numbers) | redblack |
| `tgt-fluo-rctr-200-76mm` | 200 cibles rondes réactives 76 mm réticule et chiffres (red reticle, zones 8 and 9) | reticle |
| `tgt-fluo-gnbk-200-5z` | 200 cibles rondes 76 mm vert et noir 5 zones (**not reactive** despite "fluo" in the SKU: the third sheet, "Compter les points", adds up a series with plain holes) | greenblack |
| `tgt-fluo-3in-200` | 200 cibles rondes réactives 76 mm et pastilles réparation (four red patches outside the disc; the 76 mm line sits above them) | patches |
| `cr-rn-react-076-250` | 250 cibles rondes réactives 76 mm réticule rouge (cross stops at the inner ring, no numbers) | red |
| `cr-rn-splat-76mm-100` | 100 cibles rondes réactives 76 mm 5 zones et pastilles (the 76 mm version of `cr-rn-splat-100mm-100`; its gallery photo is the very same file) | redblack |
| `tgt-spltr-blk-100x10` | 100 cibles rondes réactives 10 cm noires numérotées (zones 7 to 9, four "10" in the red centre) | black |
| `cr-react-076-250-fluo` | 250 cibles rondes réactives 76 mm papier anti-déchirure (effect sheet shows a tight group) | fluo |
| `ccg-splat-076-200-fluo` | 200 cibles carrées réactives 76 mm grille en pouces | grid |
| `ccg-splat-051-200-fluo` | 200 cibles carrées réactives 51 mm 5 points de visée (one hit per aim point) | five |
| `tgt-react-sq-100-4in-grid` | 100 cibles carrées réactives 10,16 cm grille de réglage (the page's own example: grouped high left, read D and R) | grid |
| `ccs-5dia-076-200-fluo` | 200 cibles carrées réactives 76 mm 5 losanges rouges (one hit per diamond) | diamonds |
| `ccs-vis-076-200-fluo` | 200 cibles carrées réactives 76 mm réticule de visée (reinforced paper) | reticle |
| `cgl-splat-150-25-fluo` | 25 planches de tir 15 cm grille de réglage et rapporteur (square tooling with `label: '15 cm'`, `heading: 'UNE PLANCHE'`; effect shows a diagonal group, the case the protractor is for) | board |
| `tgt-spltr-5pt-10x15` | 10 planches de tir 15,24 cm 5 cibles jaune fluorescent (square tooling; effect shows one hit per target, the session the page describes) | five |
| `tgt-splat-200mm-20-fluo` | 20 planches de tir 20 cm zones numérotées et pastilles (square tooling at `K: 3` so the 20 cm board clears the facts column) | board |
| `25pk-sticky-fluoro-sightgrid-6in` | 25 planches de tir 15,24 cm 5 losanges grille graduée (square tooling; grid numbered 3, 2, 1 on the four edges; effect shows one hit per diamond) | diamonds |
| `tgt-fluo-sq-20-6in-grid-mp` | 20 planches de tir 15,24 cm 5 cibles vert fluorescent (square tooling; ring bulls, dashed grid 0 to 2,5; effect uses `splatColour: '#4cf23a'` and `closeUpTitle: 'Gerbe vert fluorescent'`) | green |
| `bon-pol-ngrn-dubl-ux` | Bonnet polaire vert fluo unisexe (anatomy + dimensions only; the only photo is a three-quarter view, so the dimensions sheet draws a flat silhouette at 25 px/cm with the supplier's 26 x 22 cm, a seeded `feTurbulence` fleece texture and the cuff seam unmeasured; uses in the footer from the supplier sheet) | green |
| `bon-pol-org-dubl-ux` | Bonnet polaire orange unisexe (same cut, supplier sheets and layout as `bon-pol-ngrn-dubl-ux`; its page says less, so no "under a hood or helmet" and no "thick" fleece; the fleece texture dots are tinted orange in its `feColorMatrix`) | orange |
| `20pk-8bullseye-target-9x7in` | 20 cibles carton 8 blasons 22,86 x 17,78 cm (portrait card: the rectangle options below; **not reactive**, so the third sheet, "Une série par blason", shows eight plain-hole groups tightening over the session; Grandeur nature with `count: 3`) | card |
| `20pk-score-target-ylbk-9x7in` | 20 cibles carton score jaune et noir 22,86 x 17,78 cm (same format and options as the 8-bullseye card; a plain oval, not a human shape; **not reactive**: "Compter les points" scores 9 + 9 + 8 + 8 + 7 = 41, each hit checked against the stadium-shaped zone lines and kept out of the inner X oval, whose value the page doesn't give) | card |
| `grip-sleeve-snd` | Grip caoutchouc antidérapant sable (anatomy + dimensions only; sizes from the product page, 7,2 x 5,4 cm, drawn as arrows along the grip's edges with no coin, because the photo's proportions don't match them: the supplier sheet in `sources/` says 7,5 x 6 x 2,5 cm) | sand |
| `grip-sleeve-blk` | Grip caoutchouc antidérapant pour poignée de pistolet, black (same mould and framing as `grip-sleeve-snd`, same size treatment; its footer carries the page's holster warning) | black |

Square targets carry fine printed legends (inch fractions, 4R to 4L) that a
redrawing would get wrong, so their dimensions sheet uses
`Infographic.targets.squareToScale`: the photo cropped to the square and drawn
at true size (`mm` × `K`), with the side measured on top and on the left,
then the coin and facts. Their effect sheet passes `shape: 'square'` to
`beforeAfter`, with `radius` set to half the side. The dimensions `draw`
receives the variant as a third argument for this.

A rectangular card adds its height to the same helpers: `mmH` and `halfH`
for `squareToScale`, `shape: 'rect'` with `radiusH` for `beforeAfter`, and
`cmH` and `halfH` for `mounted`. Width and height then scale separately, so
a slightly squashed photo still comes out at the true size.

Round reactive targets share `Infographic.targets` in `kit.js`:
`coinAndFacts` (the 1 € coin and the pack facts on the dimensions sheet) and
`beforeAfter` (the whole effect sheet, given the target's centre and radius in
its photo and the hits). A new target only has to redraw its own design for
the dimensions sheet. Keep hits and markers off printed letters and numbers.

Check the product's "Réaction à l'impact" before drawing an effect. A
non-reactive target (the pink one says "Aucune") must never get a splatter,
even when its image file names say "splatter". `beforeAfter` handles it with
`holes: 'plain'`, `beforeHits`, `correction: [dx, dy]` (height and windage
arrows from the before group back to the centre) and `closeUp: false` (a text
block instead of the splatter close-up).

A target whose splatter colour depends on the zone hit sets
`zones: [[maxR, colour], ...]` (radii in photo px, innermost first): each hit
takes the colour of its zone. `closeUps: [{ label, colour, bg }]` replaces
the single close-up with a row of one per zone. `splatColour` and
`closeUpBg` change the splatter colour and the close-up background for a
target that is not black (the orange one has a yellow splatter on orange).

The "1 €" on the coin scales with it (0.72 × its radius, at most 40 px), so
it stays inside the silver centre at small `K`; the user asked for it
smaller on 2026-10-30.

The dimensions sheet's scale `K` (px per mm) is 7.9 for 76 mm targets; a
bigger target needs a smaller one (6 for 10 cm) to stay clear of the facts
column at x 900.

When there is no supplier sheet, take the facts from the product's own
`characteristics` and description (Admin API, see
`docs/admin/api/products.md`), which have already been checked.

Open `out/` to see the finished look before starting a new one.

## Making a new product

### 1. Gather the facts

Collect the supplier's info sheets (dimensions, part names, notes). Save them
into `sources/` as `<product>-<what>.png`. **Every label and measurement must
come from those sheets or be plainly visible in the photo.** Write nothing
from assumption.

Things that have already gone wrong:

- **A part was named by guessing what it looked like.** The loops on top of
  the padded belt looked like belt loops, but the supplier calls them
  suspension rings (x4).
- **An instruction was paraphrased into a different action.** The supplier
  said "press the switch and the buckle at the same time"; a first draft said
  "push, then squeeze".
- **Ratings stamped on cheap hardware are not claims we can make.** The
  cobra-style buckle is stamped 12kN / 24kN, but it is plastic. Those figures
  appear nowhere in the text.
- **If the sheet says plastic, say plastic,** even when the photo looks like
  metal.
- **The supplier sheet beats the file name.** The `men-nylon-belt` strap is
  polypropylene according to its sheet.
- **Flag anything you can't confirm to the user.** Example: "EVA" and "MOLLE"
  only came from file names.

### 2. Add the photos

Copy one photo per colourway into `photos/` as `<product>-<colour>.jpg`. The
anatomy sheet assumes a square 1000x1000 photo on a white or off-white
background. It draws the photo at (300, 265), scaled to 900 px.

### 3. Read the marker positions

```bash
python3 -I scripts/infographics/grid.py photos/<product>-<colour>.jpg      # light product
python3 -I scripts/infographics/grid.py photos/<product>-<colour>.jpg 2.5  # dark product, brightened
```

This prints the path of a gridded copy in the temp dir. Open it and note the
pixel coordinates of each part. Every colourway needs its own reading: the
supplier's photos are never framed identically.

### 4. Write the spec

Copy the closest existing file in `products/` and change it. The fields:

- `eyebrow`: the small line above the title (product type).
- `variants.<colour>`:
  - `photo`: the photo path.
  - `points`: one list of `[x, y]` per callout, in callout order. A callout
    can point at several parts, for example `[[666, 265], [666, 336]]`.
  - `palette`: the fill colours the dimensions drawing uses for that colour.
- `anatomy`:
  - `title`.
  - `callouts`: `{ side: 'l' | 'r', y, title, text }`. `y` is the label's
    vertical centre on the 1500 px page.
  - `footer`: `{ tag, html }`.
  - `photoFilter` (optional): a CSS filter to whiten an off-white photo
    background.
  - `photoBox` (optional): `{ x, y, size }` to draw the photo larger than the
    default `{ x: 300, y: 265, size: 900 }`, when the product only fills the
    middle of its photo. Markers follow automatically.
- `dimensions`:
  - `title`.
  - `footer`.
  - `draw(kit, palette)`: draws the product with the helpers `el`, `text`,
    `dim`, `ext`, `arrow`, `sideLabel` and `section`.
- `effect` (optional):
  - `title` and `footer`.
  - `draw(kit, variant)`: same helpers, and it gets the whole variant so it
    can place `variant.photo` itself.
  - Use `Infographic.rng(seed)` for any randomness, so the image comes out
    the same on every render.
  - An effect drawn over a real photo is an illustration: the footer must
    say so (tag `Illustration`), and it may only show behaviour that the
    product page documents. The target's splatter is the "gerbe jaune
    fluorescent" from its specs.

Layout rules that keep sheets readable:

- **Up to 5 labels per side.** Order them top to bottom to match their
  markers, so leader lines never cross.
- **Labels sit in the margins:** left `x < 310`, right `x > 1190`. A belt
  whose right edge reaches the label column needs the photo smaller, which
  is why it is drawn at 900 px.
- **A leader that has to cross another one means the callout is on the
  wrong side.** Move it, or point at a single representative part and say
  "two clips, top and bottom" in the text.
- **Draw the dimensions sheet to scale** (`K` px per cm) when there are
  several items, so their relative sizes are true. A part too small to read
  at that scale gets an enlarged detail, and the footer says so.
- **Use cm only, with a French decimal comma** (`5,3 cm`). Drop the inch
  figures the supplier sheets carry.

### 5. Render and check

```bash
scripts/infographics/render.sh <product>          # every colourway
scripts/infographics/render.sh <product> black    # just one
```

Look at every PNG in `out/`. Check that:

- each marker sits on its part,
- no label touches the product,
- no leader lines cross,
- the bold text in the footer renders bold.

Then re-read the supplier sheets against the image one last time.

The fonts (Saira Stencil One, Barlow Condensed, Barlow) load from Google
Fonts, so rendering needs network access. Chrome is expected at the macOS
default path; override it with `CHROME=/path/to/chrome`.

### 6. Hand over

The rendered PNGs in `out/` are the deliverables and the reference. Gallery
images can't be added over the Admin API (`images` is read-only), so the user
uploads them through the web admin.

## The "Grandeur nature" sheet

`mounted.html` shows the real size of a target the way a buyer pictures it:
five of them on a 1 m × 60 cm plywood panel on a range stand. A spec opts in
with one line:

```js
mounted: { centre: [498, 500], half: 422, cm: 15 },  // add round: true for a disc
```

`centre` and `half` locate the target in its photo, in photo px (the same
values as the square or round geometry already in the spec), and `cm` is its
real size. Optional `title` replaces "Grandeur nature".

Mount what the shooter actually sticks on the panel, not the backing sheet.
When a sheet carries a target plus extras around it (repair patches, a
coloured margin), point `mounted` at the target alone: the 20 cm boards
mount only their 18 cm disc (`centre` and `half` of the disc,
`cm: 18, round: true`).

- **Never a human-shaped target, not even headless.** The FFTir does not
  allow shooting at human-shaped targets, so no image of ours may show one.
  The panel is a plain rectangle on purpose.
- **Layout:** one target in the centre, four 15 cm in from the sides and
  7 cm from top and bottom. The laths and their screws sit 40 cm from each
  side, in the gap under the centre target.
- **Three instead of five:** `count: 3` puts three in a row, centred in
  height with even gaps, for a card too big for five (the 22,86 cm
  cardboard target, at the user's request).
- **Measures:** "1 m" over the panel at 52 px and "60 cm" beside it at
  44 px, the same Barlow Condensed as the dimensions sheets.
- **Title:** "Grandeur nature", not "À l'échelle": a capital with an accent
  rises into the gap under the eyebrow and makes the header look tighter
  than on the other sheets.
- **Textures:** the birch veneer, the laths and the powder-coated steel are
  generated and seeded, so the image is the same on every render.
- **Gallery position:** right after the dimensions sheet. A typical order is
  the product photos first (packshot, front view, real impacts), then
  anatomy, dimensions, grandeur nature, effect.

## House style

- **Page:** 1500x1500, white.
- **Header:** a coyote eyebrow in Barlow Condensed with wide tracking, above
  a 104 px Saira Stencil One title.
- **Accent colour:** coyote `#a7814b` for the eyebrow, leader lines, reticle
  markers and section headings. Ink `#1b1d19`, muted grey `#6b6e66`.
- **Markers:** a reticle (a circle with four ticks) with a white halo, so it
  reads on any fabric colour.
- **Labels:** an uppercase Barlow Condensed title over one short Barlow line.
- **Footer:** a note box (pale coyote with a coyote left rule) holding the
  one instruction or caveat that matters: how to open the buckle, or that
  measurements were taken by hand.
- **Copy:** French, plain verbs, sentence case, no em dashes.
