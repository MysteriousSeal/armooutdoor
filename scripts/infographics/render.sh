#!/usr/bin/env bash
# Render a product's infographics to scripts/infographics/out/ with headless Chrome.
#   scripts/infographics/render.sh <product> [variant ...]
# Without variants, renders every variant in products/<product>.js.
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
CHROME="${CHROME:-/Applications/Google Chrome.app/Contents/MacOS/Google Chrome}"
product="${1:?usage: render.sh <product> [variant ...]}"; shift
spec="$DIR/products/$product.js"
[[ -f "$spec" ]] || { echo "No spec at $spec" >&2; exit 1; }

# The spec is browser JS: give it a fake window and read what it defines.
read_spec() {
  node --input-type=commonjs -e '
    global.window = {}; eval(require("fs").readFileSync(process.argv[1], "utf8"));
    const P = window.PRODUCT;
    console.log(process.argv[2] === "variants"
      ? Object.keys(P.variants).join(" ")
      : ["anatomy", "dimensions", "effect"].filter(s => P[s]).join(" "));' "$spec" "$1"
}

if [[ $# -eq 0 ]]; then
  read -r -a variants <<< "$(read_spec variants)"
else
  variants=("$@")
fi
read -r -a sheets <<< "$(read_spec sheets)"

mkdir -p "$DIR/out"
for v in "${variants[@]}"; do
  for sheet in "${sheets[@]}"; do
    out="$DIR/out/$product-$sheet-$v.png"
    "$CHROME" --headless=new --disable-gpu --hide-scrollbars --allow-file-access-from-files \
      --virtual-time-budget=8000 --window-size=1500,1500 \
      --screenshot="$out" "file://$DIR/$sheet.html?p=$product&v=$v" 2>/dev/null
    echo "$out"
  done
done
