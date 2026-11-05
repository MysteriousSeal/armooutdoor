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
| `impact.html` | A round target filling the page after a series, **no text**: it replays the effect sheet's after panel (same hits, seed and order through `Infographic.targets.splat`), so the splatter has the same shapes, only larger. Renders for specs that define `impact: { centre, radius, seed, hits }`, plus `zones` for a zoned splatter or `holes: 'plain'` for a non-reactive target (the pink one shows its corrected group as bare holes), or `shape: 'square'` (radius = half the side) for a square target; the effect sheet spreads the same block (`...window.PRODUCT.impact`) so the two never drift apart. |
| `swatch.html` | A product's sticker colours as large flat dots on white, **no text**, up to four per row. Renders for specs that define `swatch: { colours }`. |
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
| `20pk-8bullseye-target-9x7in` | 20 cibles carton 8 blasons 22,86 x 17,78 cm (portrait card: the rectangle options below; **not reactive**, so the third sheet, "Une série par blason", shows eight plain-hole groups tightening over the session; Grandeur nature with `count: 3` on the 1 m panel and `count: 4` on the C50 board; redone on the sharper photo uploaded 2026-11-02, the series computed lazily because `render.sh` reads specs in node without kit.js) | card |
| `20pk-score-target-ylbk-9x7in` | 20 cibles carton score jaune et noir 22,86 x 17,78 cm (same format and options as the 8-bullseye card; a plain oval, not a human shape; **not reactive**: "Compter les points" scores 9 + 9 + 8 + 8 + 7 = 41, each hit checked against the stadium-shaped zone lines and kept out of the inner X oval, whose value the page doesn't give; redone on the sharper photo uploaded 2026-11-02, hits re-checked by counting the yellow lines crossed from the centre, the printed X counting as one) | card |
| `5plate-reset-spinner-target` | Cible métal 5 plaques basculantes (anatomy + dimensions; supplier sheet in `sources/`. The anatomy uses the gallery photo, with no marker on its ghosted motion plates; the dimensions sheet uses a clean straight-on photo the user supplied, as a second photo, `variants.steel.front`, with 36 cm top plate to feet and 26,5 cm across the legs as the supplier drew them, and no coin; its proportions match those sizes. Two extra sheets in an `effect` list: "Le cycle", three simplified front views drawn from the front photo's proportions (plates hang, a hit plate turns up onto the bar, the top plate drops the four), and "Avant de tirer", six cards with drawn icons for the page's limits: air guns only, never a firearm, safety glasses, 10 to 25 m, 10 stickers, folding legs) | steel |
| `cag-blk-breath` | Cagoule noire unie respirante (anatomy + dimensions; supplier sheets `sources/cagoule-supplier-*`. The black one measures 35 x 25 cm, head 55 to 60 cm; the flat photo's 36 cm and 55 to 62 cm belong to the camo version and are not used. Dimensions on the three-quarter packshot, with a ring round the crown for the head circumference; the supplier's "warm" left out, the page sells it for spring to mid-season) | black |
| `cag-rltap-breath` | Cagoule camouflage branches respirante (same cut, supplier sheets and layout as `cag-blk-breath`; its page describes a fine printed knit, so no "tricot ajouré"; footer on the pale background for late-autumn woods) | branches |
| `cag-urbblk-breath`, `cag-mcdes-breath`, `cag-mcforest-breath` | Cagoules camouflage A-TACS noir, sable and vert (same cut, supplier sheets and layout as `cag-rltap-breath`; each pattern callout and footer from its own page: low light, arid ground, leafy woods; their face panel rises to a point under the nose) | black, sand, green |
| `cag-brwnsnd-breath` | Cagoule coyote unie respirante (same cut, supplier sheets and layout as `cag-blk-breath`, whose open-hole knit and panel wording its page shares; footer on hearing-protection compatibility. Its dimensions drawing centres the balaclava on the page from its measured extents, at the user's request: `X = x => 750 + (x - (left + right) / 2) * 0.95`) | coyote |
| `cag-orng-breath` | Cagoule orange unie respirante (same layout and centred dimensions as `cag-brwnsnd-breath`; its page says it is **not certified high visibility** and does not replace a hi-vis vest in a battue, so the anatomy footer says that) | orange |
| `cag-desert-breath` | Cagoule camouflage désert respirante (Desert DPM; camo callouts as `cag-mcdes-breath`, centred dimensions as `cag-brwnsnd-breath`; footer on dry open ground from its page) | desert |
| `cag-grey-breath`, `cag-navy-breath`, `cag-dgtgrygrn-breath`, `cag-dgtdesert-breath`, `cag-pydes-breath`, `cag-pyfor-breath` | Cagoules grise, bleu marine, digital gris vert, digital désert, python désert and python forêt (same cut, supplier sheets and centred dimensions as `cag-brwnsnd-breath`; each colour callout and footer from its own page. Grey, navy and digital gris vert are open-hole knit, so "Tricot ajouré"; digital désert is a fine printed knit and the two pythons a smooth surface with no perforation, so "Polyester extensible" there and no "ajouré") | grey, navy, greygreen, desert, python, python |
| `cag-atacs-urb-breath`, `cag-mcmix-breath`, `cag-wood-breath`, `cag-olvgrn-breath`, `cag-folgrn-breath`, `cag-pyblk-breath` | Cagoules A-TACS urbain, multi-terrain, Woodland, vert olive, vert feuillage and python noir (same cut, supplier sheets and centred dimensions as `cag-brwnsnd-breath`; each colour callout and footer from its own page. Olive and python noir are open-hole knit, so "Tricot ajouré", the others "Polyester extensible". `cag-mcmix-breath` says "Multi-terrain" on its sheets, not the trademark its page name still carries, and its packshot is shorter, so its hem marker sits higher) | urban, multi, woodland, olive, foliage, python |
| `cag-mcurbn-breath`, `cag-mctropic-breath` | Cagoules noir et gris and multi-terrain tropical (same cut, supplier sheets and centred dimensions as `cag-brwnsnd-breath`; both open-hole knit, so "Tricot ajouré"; `cag-mctropic-breath` says "Multi-terrain tropical", not the trademark its page name still carries, as `cag-mcmix-breath` does) | blackgrey, tropic |
| `cag-mcbeige-breath`, `cag-urbdgt-breath`, `cag-dgtforest-breath` | Cagoules multi-terrain beige, digital urbain and digital forêt (same cut, supplier sheets and centred dimensions as `cag-brwnsnd-breath`; multi-terrain beige is open-hole knit, so "Tricot ajouré", and says "Multi-terrain", not the trademark its page name still carries, as `cag-mcmix-breath` does; the two digital ones are a fine printed knit, so "Polyester extensible". On `cag-urbdgt-breath`, whose packshot is more frontal, the face panel marker sits lower, on the fabric) | beige, urban, forest |
| `cag-3dleaf-ghillie` | Cagoule ghillie feuillage 3D (a different cut from the breathable ones: measures from its own supplier sheet `sources/cag-3dleaf-ghillie-supplier-dimensions.png`, 25 cm across the head and 45 cm crown to bib, drawn where that sheet puts them; no head circumference, the sheet gives none; plain "Polyester" and a hand-wash footer from its page) | leaf |
| `tgt-splat-200mm-10-fluo` | 10 planches de tir 20 cm zones numérotées et pastilles (same board and layout as `tgt-splat-200mm-20-fluo`, on the sharper flat photo the user uploaded; drops 346's "lisibles à 30 ou 50 m", which its page doesn't make) | board |
| `tgt-plain-200mm-25` | 25 planches de tir 20 cm **non réactives** (same board and photo as `tgt-splat-200mm-10-fluo`; the third sheet is "Compter les points" with plain holes, 9 + 9 + 8 + 8 + 7 = 41, kept out of the centre zone whose value the page doesn't give; 10 to 25 m) | board |
| `tgt-fluo-sa-25s-42up-20x20`, `tgt-fluo-sa-10s-42up-20x25` | 25 and 10 planches de 42 cibles autocollantes 25 mm (peel-off stickers, see below; sheet size never printed: the user confirmed the 2,54 cm stickers, which make the photo's sheet about 22,3 cm, not the page's 20 cm) | sheet |
| `bullseye-patch-360pc-25mm` | 360 pastilles bullseye 25 mm (peel-off, **not reactive**: no impact image, plain holes only; sheet size never printed, the 25 mm stickers make it about 15,3 cm, not the page's 18 cm) | patches |
| `20pk-sticky-fluoro-sightgrid-6in` | 20 planches de tir 15,24 cm 5 losanges grille graduée (the board of `25pk-sticky-fluoro-sightgrid-6in` in a pack of 20, on its own sharper flat photo; callouts and footer from its page: red reticle in quadrants, double central diamond, 100 aim points) | diamonds |
| `lab-red-013-1000` | 1 000 pastilles rondes rouges 13 mm (plain red stickers on a roll: anatomy of the roll; dimensions at 14 px per mm with the coin and the holes the page says a sticker covers, 4,5 and 5,5 mm and .22 LR, dashed under three stickers; Mode d'emploi with a before / after red bull repair, `usedTarget` scaled down to 250 px) | roll |
| `lab-wht-013-1000`, `lab-blk-…`, `lab-blue-…`, `lab-ylw-…`, `lab-org-…`, `lab-pnk-…`, `lab-grn-…`, `lab-prpl-…` | 1 000 pastilles rondes 13 mm in eight more colours (the family of `lab-red-013-1000`, drawn with `pastilles.js`: flat paper dots in the colour sampled from each page's close-up, the same dimensions sheet, and a Mode d'emploi built from that colour's own use on its page: white repairs light zones, black blends into reactive targets and stays non-reactive, blue one colour per shooter plus a repair, yellow aim points on a dark board, orange marks a series, pink identifies ammunition batches, green marks gear ready, violet marks a target stand) | roll |
| `lab-wht-019-1000`, `lab-ylw-025-500`, `lab-red-025-500`, `lab-blk-025-500`, `lab-orgf-038-300` | Pastilles 19, 25 and 38 mm (same `pastilles.js` tooling; `dimensions` takes `mm`, `count` and `holes`, and scales the sticker and coin to fit; the user asked for the same six calibres on all of them: 4,5 mm, .22 LR, 9 mm, .223 Rem, .308, .45 ACP. Mode d'emploi from each page: white 19 mm discreet repair and the 13 / 19 / 25 mm range, yellow 25 mm a full aim point and a drill, red 25 mm a new centre on our 20 cm board, black 25 mm a whole group under one sticker and the 13 / 25 mm pair, fluo orange 38 mm an aim point and a safety marker) | roll |
| `6mm-8000pc-color-code-dots-8clr` | 8 000 pastilles 6 mm, eight rolls of 1 000 (colour coding only: the page says 6 mm is too small to cover an impact, so no repair anywhere; the dimensions sheet shows the eight colours at true scale instead of holes; Mode d'emploi on small surfaces and one colour per calibre) | rolls |
| `22lr-55rd-mini-ammo-case-blk`, `-red`, `-grn` | Mini boîte 55 munitions .22 LR, black, red or green lid (supplier sheets `sources/boite-22lr-supplier-*`; anatomy on the open box, dimensions on the closed one as the supplier measures it, 7,1 x 3,4 cm with no coin, a three-quarter view; third sheet "Un couvercle par lot" shows the three lids with this one ringed) | case |
| `50rd-9mm-ammo-box-blk` | Boîte de rangement 50 munitions 9 mm noire (anatomy on the open box; dimensions as three arrows along its edges with the grip sheets' `measure()`, no coin; third sheet "50 cartouches debout", a simplified top view of the 10 x 5 grid) | box |
| `100rd-9mm-ammo-box-blk`, `-grn` | Boîte de rangement 100 munitions 9 mm, black or green (supplier size sheet `sources/boite-100-munitions-supplier-size.png`, 11,5 x 11,5 cm; the 3,5 cm height from the user; dimensions arrows follow the box's perspective like `50rd-9mm-ammo-box-blk`; Mode d'emploi: the tab close-up, plus a detailed 2 x 50 = 100 on both) | box |
| `40rd-rfl-ammo-box-blk` | Mallette 40 cartouches carabine .22-250 à 7.62x39 (no supplier sheet, the page's size image kept as `sources/mallette-40-cartouches-size.png`; the gallery has no open view, so the anatomy is on the closed case and the footer names the foam; dimensions as the page's size image measures them, no coin; third sheet "Plusieurs calibres", a section through the foam with a .222 Rem, a .223 Rem, a .300 Blackout and a 7.62x39 drawn to their relative scale and an empty hole) | case |
| `phone-pouch-molle-coy`, `-blk`, `-od` | Étui téléphone tactique MOLLE, coyote, black or olive (no supplier sheet; generated from one template, each colour's photos are framed differently so its points and the dimension corners are traced per colour; anatomy on the open pouch, the lining pointed at the supplier's own close-up; dimensions on the left pouch of the pair photo, clipped out and turned upright, straight arrows, no coin; Mode d'emploi: the back on a belt and the open flap) | pouch |
| `shell-pch-12ga-10rd`, `-blk`, `-odg` | Porte-cartouches calibre 12 10 coups, coyote, black or olive (the black and olive specs are the coyote one with their own photos, back box `box`, pouch colour `POUCH`, snap finish and 70 g; the open views share the coyote's framing, so the same points; supplier size sheet `sources/porte-cartouches-12-supplier-size.png`, 13 x 9 cm on the back; anatomy on the open pouch; dimensions on the square-on back, true to scale at 71 px per cm, so it gets the coin; Mode d'emploi: the back's two snap straps, and a drawn top view of the ten heads in two rows offset by half a pitch) | pouch |
| `shell-pch-12ga-16rd-blk`, `-odg`, `-coy`, `-org` | Porte-cartouches calibre 12 16 coups MOLLE, black, olive, coyote or orange (one spec per colour with its own photos, corners, panel wording and footer; supplier sheet `sources/porte-cartouches-16-supplier-size.png`, 17,2 x 8,4 x 5,5 cm, drawn on a three-quarter view like that sheet, so no coin; anatomy on the loaded front, dimensions on the empty front, height up the side's rear edge and width and depth following the two bottom edges; Mode d'emploi: the back's two snap straps and the two strips of six, each a photo in a ringed circle) | pouch |
| `iwb-holster-blk` | Holster pistolet IWB OWB ambidextre, black (supplier sheets sent as screenshots, not kept: 15,2 x 10,1 cm with the height taken from the mouth to the bottom, clip 5,5 x 2,5 cm; dimensions on the square-on pistol photo, about 44 px per cm, so it gets the coin; three effect sheets: front views at a drawn belt and jeans, no wearer, inside with the photo's clip laid over them, outside on a retouched copy with the clip covered (`photos/iwb-holster-blk-noclip.png`); the side stitching before and after the cut on the kit photo's body, the bottom is open; the kit laid out) | holster |
| `ncc-grey-poly-ux`, `ncc-coy-poly-ux` | Cache-cou respirant, gris anthracite or coyote (supplier sheets `sources/cache-cou-supplier-*`, 24 x 42 cm flat; the gallery has only a fabric close-up, so the anatomy is "Anatomie de la maille" on it, the flat tube is drawn to scale with the coin, `knit()` in the spec; no effect sheet) | tube |
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
- `effect` (optional, or a list of sheets each with an `id`, rendered as `-effect-<id>-`):
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

## Peel-off stickers

Sheets of small stickers are used one by one, so their set differs:

- **Two photos per product:** the sheet (`variants.<v>.photo`) and one
  sticker's close-up (`photos/<sku>-sticker.jpg`, disc centre 500,500, r483).
- **Mode d'emploi** replaces the effect sheet (it uses the `effect` slot, so
  the file is `-effect-`): two panels showing the uses the page describes,
  drawn with `Infographic.targets.sticker` (a sticker cut from its close-up,
  with a shadow), `cardboard` (a plain kraft backing) and `usedTarget` (our
  76 mm target already shot, as the surface a sticker goes on).
- **Dimensions:** `targets.sheetDimensions` draws the sheet unlabelled and one
  sticker next to the 1 € coin at 8 px per mm. Print only sizes that are
  confirmed; when the photo contradicts the page, ask which figure is right.
- **Grandeur nature:** loose stickers on the C50 board, `photo` set to the
  close-up and `grid: [4, 4]`.
- **Impact:** the whole sheet shot row by row, with `size` shrinking the
  splatter to the sticker (0.42 for 25 mm). None for a non-reactive sticker.

### The 13 mm pastille family

`pastilles.js` (loaded by `dimensions.html` and `effect.html` after
`kit.js`) holds what the eight colour specs share: `dot` (a flat paper
sticker), `dimensions`, `usage` (the two-panel Mode d'emploi), `repair`
(before / after on our reactive target, a drawn white paper target, or the
green and black target of product 154) and small drawn props (ammunition
box, magazine, rifle case, target stand, dark board). Specs call it only
from their draw functions, since `render.sh` reads specs in node. A new
colour is a short spec: its fill, its anatomy wording and its two panels,
taken from its own page.

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
- **Other boards:** `panel: [PW, PH]` (cm) and `inset: [x, y]` (the corner
  targets' gap to the sides and to top and bottom) change the board; the
  scale then grows to fit, and the laths and screws follow (40 % and 60 %
  across). `mounted` can also be a list of boards, each with an `id`:
  `render.sh` writes one `<product>-mounted-<id>-<variant>.png` per board.
  The 76 mm round targets have a 55 x 55 cm board, the size of a C50 card
  (`id: 'c50'`, insets 10 cm).
- **Four in a grid:** `count: 4` puts a 2 x 2 grid with equal gaps both
  ways. The 15 cm boards use it on the C50 board, where five would
  overlap; their default 1 m board stays first in the `mounted` list with no
  `id`, so its file keeps the name `<product>-mounted-<variant>.png`.
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
