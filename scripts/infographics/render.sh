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

if [[ $# -eq 0 ]]; then
  # The spec is browser JS: give it a fake window and read the variant keys.
  read -r -a variants <<< "$(node --input-type=commonjs -e '
    global.window = {}; eval(require("fs").readFileSync(process.argv[1], "utf8"));
    console.log(Object.keys(window.PRODUCT.variants).join(" "));' "$spec")"
else
  variants=("$@")
fi

mkdir -p "$DIR/out"
for v in "${variants[@]}"; do
  for sheet in anatomy dimensions; do
    out="$DIR/out/$product-$sheet-$v.png"
    "$CHROME" --headless=new --disable-gpu --hide-scrollbars --allow-file-access-from-files \
      --virtual-time-budget=8000 --window-size=1500,1500 \
      --screenshot="$out" "file://$DIR/$sheet.html?p=$product&v=$v" 2>/dev/null
    echo "$out"
  done
done
