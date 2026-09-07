# Brand assets

Sources kept as supplied, not for serving. Nothing here is public: the
files under `public/` are the ones a browser fetches, and they are derived
from these.

## `armo-mark.svg`

The shop's mark: the A with its bar, and the ring beneath. Three shapes,
no background, `#282828` for the ink and `#887868` for the ring.

Four things are drawn from it. None of them is a copy of the file, so a
new version of the mark means redoing each:

| where | what changes |
| --- | --- |
| `resources/views/partials/armo-mark.blade.php` | the header mark; fills swapped for `armo-ink` and `armo-ring`, which take the page's `--text` and `--accent` |
| `public/favicon.svg` | ink flipped by `prefers-color-scheme`, since a dark A vanishes on a dark tab strip |
| `public/favicon-32.png` | rendered at 32, opaque on `#f7f6f4` |
| `public/apple-touch-icon.png` | rendered at 180, opaque and padded: iOS rounds the corners and composites transparency onto black |

The PNGs were rendered from the SVG at 512 and resized down, which is
sharper than rendering small directly.
