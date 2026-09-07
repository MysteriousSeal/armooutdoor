# Brand assets

Sources kept as supplied, not for serving. Nothing here is public: the
files under `public/` are the ones a browser fetches, and they are derived
from these.

## `armo-mark.svg`

The shop's mark: the A with its bar, and the ring beneath. Three shapes,
no background, `#282828` for the ink and `#887868` for the ring.

Five things are drawn from it. None of them is a copy of the file, so a
new version of the mark means redoing each:

| where | what changes |
| --- | --- |
| `resources/views/partials/armo-mark.blade.php` | the header mark; fills swapped for `armo-ink` and `armo-ring`, which take the page's `--text` and `--accent` |
| `public/favicon.svg` | ink flipped by `prefers-color-scheme`, since a dark A vanishes on a dark tab strip |
| `public/favicon-32.png` | rendered at 32, opaque on `#f7f6f4` |
| `public/apple-touch-icon.png` | rendered at 180, opaque and padded: iOS rounds the corners and composites transparency onto black |
| `resources/brand/armo-mark-print.svg` | same shapes and inks, box cropped to the drawing, for the invoice and the delivery slip |

The print variant exists because Dompdf, which draws the order paperwork,
sizes a viewBox with an offset origin wrongly: given a height it drew the
ink at about two thirds of it. Cropping the box to the artwork makes the
image behave like any other, and the mark now sits square with the
wordmark beside it.

The PNGs were rendered from the SVG at 512 and resized down, which is
sharper than rendering small directly.
