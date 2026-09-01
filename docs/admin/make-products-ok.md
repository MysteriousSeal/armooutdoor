# Make products OK

How to bring a product page up to standard, and how to tell when it already is.

This is the working procedure for anyone — human or agent — going through the
catalogue a category at a time. It assumes the [Products API](api/products.md);
read that first, because every rule there is enforced by the server and this
document does not repeat the validation details.

- **Marker of done:** `ai_validated = true`
- **Scope of one pass:** one category, every product in it, active and inactive
- **What this never changes:** price (see [Step 6](#step-6--price-check-flag-only))

---

## What "OK" means

A product page is OK when all of the following hold. Anything short of this is
not finished, and `ai_validated` stays `false`.

| # | Requirement | Field |
|---|---|---|
| 1 | 4–5 paragraph French description, roughly 1 000–1 300 characters | `description` |
| 2 | 13+ specification rows, last row is the package weight | `characteristics` |
| 3 | 6–7 filter rows, labels reused verbatim across the category | `filter_attributes` |
| 4 | A shipping weight is set | `weight_grams` |
| 5 | Carriers set to the house default | `carrier_ids` |
| 6 | Slug is readable French, not machine-generated | `slug` |
| 7 | Supplier is tagged | `supplier_id` |
| 8 | Main image plus at least one gallery image, all files present | `image`, `images` |
| 9 | The page renders and every claim on it matches the artwork | — |

A page that is merely *complete* is not automatically OK. Requirement 9 is the
one that takes judgement: a full spec table describing a product that looks
nothing like its photographs is worse than an empty one.

---

## Before you start

Read the token out of the environment rather than pasting it, and never put it
in a URL:

```bash
export ADMIN_API_TOKEN=$(grep '^ADMIN_API_TOKEN=' .env | cut -d= -f2- | tr -d '"')
```

Every request carries both headers:

```bash
curl -s -H "Authorization: Bearer $ADMIN_API_TOKEN" -H "Accept: application/json" …
```

Find the category id you are working from `GET /api/admin/categories`. The web
admin needs an interactive login; the API does not, so prefer the API for
everything except the two jobs it cannot do (uploading images, reading cost).

The limiter allows 120 requests per minute per IP and runs *before* the token
check. A category pass is well inside that, but do not loop on failures.

---

## Step 1 — Audit the category

Pull every product, including inactive ones, and score it against the standard.
Do this before changing anything: the audit tells you which products need work
and gives you the "before" numbers.

```bash
curl -s -H "Authorization: Bearer $ADMIN_API_TOKEN" -H "Accept: application/json" \
  "http://127.0.0.1:8003/api/admin/products?category_id=<ID>&per_page=100" > audit.json
```

```python
import json, os, re

d = json.load(open('audit.json'))
base = 'public/images/'

print(f"{'id':>5} {'para':>4} {'spec':>4} {'filt':>4} {'wt':>5} {'carr':>4} {'img':>3} {'sup':>3} {'val':>3}  status")
for p in sorted(d['data'], key=lambda x: x['id']):
    desc = p.get('description') or ''
    para = len(re.findall(r'<p\b', desc))
    spec = len(p['characteristics'])
    filt = len(p['filter_attributes'])
    wt   = p.get('weight_grams')
    carr = len(p.get('carrier_ids') or [])
    imgs = 1 + len(p.get('images') or [])
    miss = [x for x in [p.get('image')] + [i['image'] for i in (p.get('images') or [])]
            if x and not os.path.exists(base + x)]
    ok = para >= 4 and spec >= 13 and filt >= 6 and wt and carr and imgs >= 2 \
         and p.get('supplier_id') and not miss
    print(f"{p['id']:>5} {para:>4} {spec:>4} {filt:>4} {str(wt):>5} {carr:>4} {imgs:>3} "
          f"{str(p.get('supplier_id') or '-'):>3} {str(p.get('ai_validated'))[:1]:>3}  "
          f"{'OK' if ok else 'INCOMPLETE'}{' IMAGES-MISSING' if miss else ''}")
```

Typical signature of an unfinished page: **1 paragraph of around 280 characters,
8 specification rows, zero filter rows, no weight.** Typical signature of a
finished one: 4–5 paragraphs, 13+ rows, 6–7 filters, a weight.

**Do not use the slug language as a quality signal.** Machine-generated English
slugs and well-written French content coexist freely in both directions. Score
the content, not the URL.

Note that this audit is a completeness screen only. It cannot see whether the
copy is *true*, which is what Steps 2 and 3 are for.

---

## Step 2 — Look at the product before writing about it

Open every image the product has before writing a word. Images live under
`public/images/products/` — not `public/storage/` — and the paths in the API
response are relative to `public/images/`.

```bash
curl -s -H "Authorization: Bearer $ADMIN_API_TOKEN" -H "Accept: application/json" \
  "http://127.0.0.1:8003/api/admin/products/<ID>" \
  | python3 -c "import json,sys; p=json.load(sys.stdin)['data']; print(p['image']); [print(i['image']) for i in p['images']]"
```

Prefix each path with `public/images/` to open it from the repository root.

Then actually view them. This step is not optional and it is not a formality:

- **Never write a product's copy by adapting a sibling product's.** Near-identical
  products differ in ways the copy must capture — ring counts, numbering schemes,
  colour of a reactive layer, presence of extras. Copying a sibling reliably
  produces confident, specific, wrong sentences.
- **Do not assert a feature the images do not show.** If a product's name claims a
  mechanism and no image demonstrates it, describe what you can see and flag the
  gap rather than repeating the claim. Names and SKUs are marketing input, not
  evidence.
- **Correct the existing copy where it contradicts the artwork.** Inherited
  descriptions frequently misstate colours, counts and which element is which.
  A rewrite is the moment to fix that, not to carry it forward.
- **Count things.** Rings, zones, sectors, numbered values, included extras.
  These end up in the spec table and in a filter facet, so a miscount propagates.

Where the images genuinely cannot settle a question, write around it and raise
the question — see [Escalate, don't guess](#escalate-dont-guess).

---

## Step 3 — Write the content

### Description

4–5 paragraphs of French, roughly 1 000–1 300 characters, as HTML `<p>` blocks.
It is sanitised server-side, so keep the markup to paragraphs.

A structure that works, and that keeps pages in a category consistent:

1. **What it is** — one sentence naming the product, its key dimension, its
   quantity and its single most defining attribute.
2. **How it works** — the mechanism, if it has one, and what that gets the user.
3. **What it looks like** — the visual, described precisely enough that someone
   can match it to the photograph.
4. **What makes it different** — the feature that distinguishes it from the
   neighbouring products in the same category.
5. **Practical detail** — compatible surfaces, sizes, conditions of use, how it
   is supplied.

Write about what the feature is *for*, not only what it is. The difference
between "zones numérotées de 6 à 10" and a sentence explaining that you can call
out a score from the firing line without walking to the target is the difference
between a spec and a reason to buy.

Keep the register plain and concrete. No superlatives, no invented test results,
no claims about durability or performance that nothing supports.

### Characteristics

13+ rows of `{label, value}`, rendered as the spec table. **The last row is
always the package weight.** Reuse the same labels across a category so the
tables read consistently; add rows specific to a product where it has something
the others do not.

A row per: type, quantity, key dimension, colour, visual design, mechanism,
any marking or numbering scheme, included extras, format, material, fixing,
compatible surfaces, durability, intended use, recommended conditions, the main
advantage, and the package weight.

Values are capped at 500 characters, labels at 120, and both are required on
every row — a row missing either is a `422`.

### Filter attributes

6–7 rows driving the storefront facets. This is the part most often left empty,
and it is the part with the largest effect on whether a product can be found.

**The labels and values must be reused verbatim across the category.** Facets
group on exact strings, so two spellings of the same measurement — a value in
millimetres beside the identical value in centimetres — produce two separate
facet entries holding one product each, which is worse than no facet at all.
Before writing them, look at what the already-correct products in the category
use and match it exactly, converting to their spelling where a product's own
naming differs.

Pick a stable set of axes for the category — typically type, primary dimension,
quantity, format, fixing method and colour — and add a discriminating attribute
where the category has one. Keep values short; they are facet labels, not prose.

Where a facet value is a count, make sure the number is defensible from the
artwork, since it is the one part of the spec table a customer can filter on and
then find contradicted by the photograph.

---

## Step 4 — Fill the data fields

### Weight

`weight_grams` feeds carrier pricing, so it must be set. When no real figure
exists, derive one from comparable products already carrying a measured weight —
scale by unit count, and by area where the physical size differs — and **mark it
as an estimate in the spec table value** so it can be corrected later. Never
leave the field empty because the true figure is unknown.

Record which weights are estimates when you report the pass. They are a standing
correction item, not a closed one.

### Carriers

The house default is **every carrier except Lettre suivie**:

```json
{ "carrier_ids": [1, 2, 3, 4] }
```

Apply it to every product unless there is a specific reason not to. An empty
`carrier_ids` means the product may not be shippable at checkout, so treat it as
a defect, not a preference. Send `[]` to clear — `null` is a `422`.

Carrier ids come from the carriers table; confirm them rather than assuming the
numbering, and note that the set is a whole-array replacement like the other
arrays.

### Slug

Slugs are writable, and every slug a product has ever carried keeps redirecting
to its current URL with a `301`. Shared links, indexed pages and printed
marketplace listings survive a rename.

Rewrite any slug that is machine-generated, English, or does not describe the
product. Write it in French, derived from the product name — typically quantity,
product type, primary dimension and the defining attribute. Format is
`^[a-z0-9]+(?:-[a-z0-9]+)*$`: lowercase, no accents, no doubled or trailing
hyphens.

**A rename is one-way.** The retired slug belongs to that product forever and can
never be given to another, so get it right rather than iterating. A product may
always return to one of its own former slugs.

### Supplier

`supplier_id` must be set. Fill `supplier_reference` and `supplier_product_url`
too where you have them — they are what makes a restock fast, and a supplier tag
without a reference still leaves someone searching the listing by hand.

### Identifiers

**Never change an existing `sku` without being asked for that change explicitly.**
The SKU is what matches a product to supplier orders and stock, and unlike a slug
there is no redirect to soften a rename. Setting a SKU on a product that has none
counts as a change: ask first. A new product created from scratch may be given one.

It is normal and acceptable for a SKU to stop matching its product name after a
rename. Report the mismatch; do not fix it.

`sku` and `gtin` are unique across products *and* variants. A missing `gtin`
matters for marketplace and shopping-feed listings; note it rather than
inventing one.

---

## Step 5 — Verify on the storefront

The API returning `200` proves the write landed, not that the page is right.
Fetch the rendered page and read it:

```bash
curl -s "http://127.0.0.1:8003/products/<SLUG>" > page.html
```

Check that the description paragraphs and every spec row appear, that the
delivery block lists the carriers you set, that the images load, and that the
category's filter sidebar now buckets the product. A facet showing a count of
one where you expected it to join a group means a label or value does not match
the rest of the category — go back to Step 3.

---

## Step 6 — Price check (flag only)

**Never change a price.** Report and let a human decide.

Compare against the nearest equivalent product at French retail — same format,
same quantity, same size — and normalise to a per-unit figure so packs of
different sizes are comparable. Prefer a comparable of similar market position;
a premium brand and a generic import are not the same benchmark, and quoting
only the premium one makes everything look cheap.

Report, per product: current price, per-unit price, the closest comparable and
its per-unit price, and the percentage gap. Say which direction the gap points
and what in the product justifies a premium or does not.

Two patterns worth calling out explicitly when you see them:

- **Internal inconsistency** — products of identical format priced in different
  tiers with no feature difference behind it.
- **Undercutting a weaker product** — sitting below a competitor's item that has
  fewer features, which usually means the price was set without a market check.

You cannot read cost or margin over the API: `supplier_price_cents` and
`markup_basis_points` are write-only by design. Any recommendation is therefore
about market position only, and must be checked against the buy price in the web
admin before it is acted on. Say so when you report.

---

## Step 7 — Mark it validated

Once a product meets every requirement and the rendered page has been read:

```bash
curl -s -X PATCH \
  -H "Authorization: Bearer $ADMIN_API_TOKEN" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"ai_validated": true}' \
  "http://127.0.0.1:8003/api/admin/products/<ID>"
```

`ai_validated` changes nothing on the storefront — no price, no visibility, no
availability. It records that someone checked the page, which is what makes
progress through the catalogue queryable. Set it back to `false` to send a
product for another look.

Do not set it on a product with a known outstanding defect. An estimated weight
is acceptable and flagged; a description that contradicts the artwork is not.

---

## Reporting a pass

When the category is done, report:

- Products brought up to standard, with before/after counts
- Products already at standard, untouched
- Estimated values written, and what they were derived from
- Claims the artwork could not confirm
- Price observations, with the comparables used
- Anything the API could not fix

Be explicit about what was *not* done and why. A pass that silently skips two
products is worse than one that names them.

---

## Escalate, don't guess

Stop and ask when:

- A product's name or SKU claims a feature no image supports
- Two sources disagree on a dimension, a count or a colour
- A count that would go into a facet is ambiguous from the artwork
- A weight has no comparable to derive from
- A rename would collide with another product's retired slug

Writing a confident sentence around an unresolved question is the failure mode
this document exists to prevent. Describe what is verifiable, flag the rest, and
let it be settled before `ai_validated` goes to `true`.

---

## What the API cannot do

| Job | Where |
|---|---|
| Upload an image, or change the gallery | Web admin — `images` is read-only here |
| Read cost or margin | Web admin — the fields are write-only |
| Delete a product | Nowhere — retire with `{"is_active": false}` |

---

## Pitfalls

Full detail is in [Rules that will bite you](api/products.md#rules-that-will-bite-you).
The ones that come up most in this workflow:

- `PATCH` carries **only** the fields being changed.
- `price` goes in as a decimal; `price_cents` is not writable.
- `characteristics`, `filter_attributes` and `carrier_ids` are **whole-array
  replacements**. Send every row you want to keep, every time.
- `carrier_ids` takes `[]` to clear, never `null`.
- A product with variants takes its stock from the variants; do not send
  `quantity` at product level.
- Validation messages are French. Branch on the `errors` object keys and the
  HTTP status, never on the message text.

---

## Related

- [Products API](api/products.md) — endpoints, fields, validation
- `GET /api/admin/categories` — valid category ids
