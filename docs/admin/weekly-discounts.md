# Weekly discounts

When you read this document, run it: put **5 random products** on discount for
**7 days**, following the rules below. Pick the products, **show the picks to the
user and wait for a clear yes** before creating anything, then report what you
created.

It uses the [Discounts API](api/discounts.md). Read that for field details; this
document only adds the selection rules.

---

## The rules

A product is **eligible** when all of these hold:

| # | Rule | Field in `GET /discounts/products` |
|---|---|---|
| 1 | It is on sale on the shop | `is_active` is `true` |
| 2 | More than 2 in stock | `quantity` ≥ 3 |
| 3 | It has no discount yet (an **expired** discount counts as none, see below) | `discount_id` is `null`, or its discount is expired |
| 4 | Its average paid price is known, so rule 6 can be checked | `average_paid_incl_vat_cents` is not `null` |
| 5 | It has a price | `price_cents` > 0 |
| 6 | At least a 20 % discount keeps it at 3× its average paid price | see below |

The discount itself:

- **Type:** `percentage`, a whole number between **20 and 30** inclusive, picked at random.
- **Floor:** the discounted price must stay **at least 3 times** `average_paid_incl_vat_cents`.
  A percentage is allowed only if `price_cents - round(price_cents × pct / 100) ≥ 3 × average_paid_incl_vat_cents`,
  which is the server's own rounding. The random pick is made among the allowed percentages only.
  If even 20 % breaks the floor, the product is not eligible.
- **Window:** `starts_at` is now, `ends_at` is now + 7 days.
- **Count:** 5 products picked at random among the eligible ones. If fewer than 5 are
  eligible, discount all of them and say so in the report.

**Expired discounts.** A product holds at most one discount row, and an expired one
stays in the database. Such a product is eligible again: `PATCH` its existing discount
instead of `POST`ing a new one (a `POST` would be a `422`). Active and scheduled
discounts are never touched.

---

## Step 1: Set up

Work against **production** unless the user names another host. The same token
authenticates locally and on production; read it through the app config, into a
variable, and never print it:

```bash
cd /Users/colas/ArmoOutdoor
BASE=https://armooutdoor.fr/api/admin
TOKEN=$(php artisan tinker --execute='echo config("services.admin_api.token");' 2>/dev/null | tr -d '[:space:]')
H=(-H "Authorization: Bearer $TOKEN" -H "Accept: application/json")
WORK=$(mktemp -d)   # or your scratchpad directory
```

## Step 2: Fetch products and expired discounts

```bash
curl -sf "${H[@]}" "$BASE/discounts/products" > "$WORK/products.json" \
  && curl -sf "${H[@]}" "$BASE/discounts?status=expired" > "$WORK/expired.json" \
  && echo ok
```

If this fails with a `404`, the discounts API is not deployed on that host: stop
and tell the user. Do not fall back to another host.

## Step 3: Pick the products and percentages

The randomness comes from PHP, not from you: do not hand-pick products or
percentages.

```bash
php -r '
$products = json_decode(file_get_contents($argv[1]), true)["data"];
$expired = array_column(json_decode(file_get_contents($argv[2]), true)["data"], "id");
$eligible = [];

foreach ($products as $p) {
    if (! $p["is_active"] || $p["quantity"] < 3) continue;
    if (! $p["price_cents"] || $p["average_paid_incl_vat_cents"] === null) continue;
    if ($p["discount_id"] !== null && ! in_array($p["discount_id"], $expired, true)) continue;

    $floor = 3 * $p["average_paid_incl_vat_cents"];
    $allowed = array_values(array_filter(
        range(20, 30),
        fn ($pct) => $p["price_cents"] - (int) round($p["price_cents"] * $pct / 100) >= $floor,
    ));

    if ($allowed !== []) $eligible[] = $p + ["allowed" => $allowed];
}

shuffle($eligible);

foreach (array_slice($eligible, 0, 5) as $p) {
    $pct = $p["allowed"][random_int(0, count($p["allowed"]) - 1)];
    echo json_encode([
        "product_id" => $p["id"],
        "discount_id" => $p["discount_id"],
        "name" => $p["name"],
        "price_cents" => $p["price_cents"],
        "average_paid_incl_vat_cents" => $p["average_paid_incl_vat_cents"],
        "percent" => $pct,
        "max_percent" => max($p["allowed"]),
    ], JSON_UNESCAPED_UNICODE), PHP_EOL;
}

fwrite(STDERR, count($eligible)." eligible product(s)\n");
' "$WORK/products.json" "$WORK/expired.json" > "$WORK/plan.jsonl"

cat "$WORK/plan.jsonl"
```

If `plan.jsonl` is empty, nothing is eligible: create nothing and report that.

## Step 4: Show the picks and wait for approval

**Nothing is created before the user says yes.** Show the plan as a table, one row
per product:

| Product | Price | Average paid | Discount | Discounted price | Ratio to average paid |
|---|---|---|---|---|---|

The discounted price is `price_cents - round(price_cents × percent / 100)` and the
ratio is that price ÷ `average_paid_incl_vat_cents` (always ≥ 3). Also state the
host, the number of eligible products, the end date, and which picks will edit an
expired discount (`discount_id` not `null`) rather than create a new one.

Then:

- **Yes:** go to Step 5 with this exact `plan.jsonl`.
- **The user drops or swaps a product, or changes a percentage:** re-run Step 3 for a
  fresh draw, or edit `plan.jsonl` as asked. A percentage the user sets must still be
  20 to 30 and respect the floor; if it does not, say so rather than applying it.
  Show the updated table and wait for a yes again.
- **No:** stop. Create nothing.

## Step 5: Create the discounts

```bash
STARTS=$(php -r 'echo date(DATE_ATOM);')
ENDS=$(php -r 'echo date(DATE_ATOM, strtotime("+7 days"));')

while read -r line; do
  product_id=$(jq -r .product_id <<<"$line")
  discount_id=$(jq -r .discount_id <<<"$line")
  body=$(jq -c --arg s "$STARTS" --arg e "$ENDS" \
    '{product_id, type: "percentage", value: .percent, starts_at: $s, ends_at: $e}' <<<"$line")

  if [ "$discount_id" = "null" ]; then
    curl -s "${H[@]}" -H "Content-Type: application/json" -X POST "$BASE/discounts" -d "$body"
  else
    curl -s "${H[@]}" -H "Content-Type: application/json" -X PATCH "$BASE/discounts/$discount_id" -d "$body"
  fi
  echo
done < "$WORK/plan.jsonl" | tee "$WORK/results.jsonl"
```

Each line of `results.jsonl` is either `{"data": {…}}` or a validation error. Do
not retry a failed line in a loop: report it.

## Step 6: Check and report

Check every created discount against the floor, from what the server returned:

```bash
jq -r 'select(.data) | .data
  | [.product.name, .label, .status, .product.price_cents, .product.discounted_price_cents, .ends_at] | @tsv' \
  "$WORK/results.jsonl"
```

Then compare each `discounted_price_cents` with `3 × average_paid_incl_vat_cents`
from `plan.jsonl`. If one is below the floor (it should not happen), set its
`ends_at` to now with a `PATCH` and tell the user.

Report to the user, as a table: product, price, average paid, percentage,
discounted price, and the ratio discounted price ÷ average paid (must be ≥ 3).
Also give the number of eligible products, the end date, and any failed request
with its error.
