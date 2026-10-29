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
