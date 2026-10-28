"""Overlay a 50 px coordinate grid on a product photo to read callout points.

    python3 -I scripts/infographics/grid.py photos/<photo>.jpg [brightness]

Writes <photo>-grid.png to the system temp dir (out/ holds only the reference
sheets). Pass a brightness factor (e.g. 2.5) to read dark products.
Coordinates are in the photo's own pixels, which is what the `points` in a
product spec use; photos are expected to be 1000x1000.
"""
import os
import sys
import tempfile

from PIL import Image, ImageDraw, ImageEnhance

src = sys.argv[1]
if not os.path.isabs(src):
    src = os.path.join(os.path.dirname(os.path.abspath(__file__)), src)
brightness = float(sys.argv[2]) if len(sys.argv) > 2 else 1.0

im = Image.open(src).convert("RGB")
if im.size != (1000, 1000):
    print(f"warning: {im.size[0]}x{im.size[1]}, the anatomy sheet assumes 1000x1000")
if brightness != 1.0:
    im = ImageEnhance.Brightness(im).enhance(brightness)

d = ImageDraw.Draw(im)
for v in range(0, max(im.size), 50):
    color = (255, 0, 0) if v % 100 == 0 else (0, 160, 255)
    d.line([(v, 0), (v, im.size[1])], fill=color, width=1)
    d.line([(0, v), (im.size[0], v)], fill=color, width=1)
    d.text((v + 2, 2), str(v), fill=(255, 0, 0))
    d.text((2, v + 2), str(v), fill=(255, 0, 0))

out = os.path.join(tempfile.gettempdir(), os.path.splitext(os.path.basename(src))[0] + "-grid.png")
im.save(out)
print(out)
