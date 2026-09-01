# Admin API — Products

Read and write the product catalogue over HTTP. Four endpoints, JSON in and JSON out.

- **Base URL:** `https://<host>/api/admin`
- **Auth:** static bearer token
- **Content type:** `application/json` (send `Accept: application/json` on every request)

> **For AI agents:** every rule in this document is enforced by the server. Sections marked **MUST** / **MUST NOT** describe hard 422 failures, not style advice. Read [Rules that will bite you](#rules-that-will-bite-you) before writing any request.

---

## Endpoints

| Method | Path | Purpose | Success |
|---|---|---|---|
| `GET` | `/products` | List, filtered and paginated | `200` |
| `POST` | `/products` | Create a product | `201` |
| `GET` | `/products/{id}` | Fetch one product | `200` |
| `PATCH` | `/products/{id}` | Partially update a product | `200` |

There is no `DELETE`, here or in the web admin. Retire a product with `PATCH {"is_active": false}`: it leaves the storefront while its order history stays intact.

`{id}` is the numeric primary key, not the slug.

---

## Authentication

Send the token as a bearer header on every request:

```
Authorization: Bearer <ADMIN_API_TOKEN>
```

The token is a single shared secret from `services.admin_api.token` (env `ADMIN_API_TOKEN`). It does not expire and is not scoped per consumer — treat it as a password, keep it out of source control and out of URLs.

A missing, malformed, or wrong token returns `401`:

```json
{ "message": "Unauthenticated." }
```

## Rate limiting

**120 requests per minute, keyed by client IP.** The limiter runs *before* the token check, so rejected requests count against the same budget — retrying a bad token in a loop will lock you out.

Exceeding it returns `429` with a `Retry-After` header. Agents **MUST** honour that header and back off rather than retrying immediately.

---

## Response envelope

Every endpoint wraps its payload in `data`.

**Single product** (`GET /products/{id}`, `POST`, `PATCH`):

```json
{ "data": { "id": 1, "slug": "…", … } }
```

**List** (`GET /products`) adds Laravel's standard pagination blocks:

```json
{
  "data": [ { "id": 1, … }, { "id": 2, … } ],
  "links": { "first": "…", "last": "…", "prev": null, "next": "…" },
  "meta": {
    "current_page": 1, "from": 1, "to": 50,
    "last_page": 6, "per_page": 50, "total": 287,
    "path": "…", "links": [ … ]
  }
}
```

> **Breaking change.** Before this revision, the list returned the raw paginator with `current_page` and `total` at the *top level*. They now live under `meta`. Any client reading `response.current_page` must read `response.meta.current_page` instead.

---

## The product object

```json
{
  "id": 1,
  "slug": "gants-mechanix-m-pact-woodland",
  "name": "Gants Mechanix M-Pact Woodland",
  "description": "<p>…</p>",
  "meta_title": null,
  "meta_description": null,
  "brand": null,
  "category_id": 59,
  "category": { "id": 59, "slug": "gants", "name": "Gants" },
  "price_cents": 3999,
  "quantity": 3,
  "sku": null,
  "gtin": null,
  "is_active": true,
  "ai_validated": false,
  "age_restricted": false,
  "image_may_vary": false,
  "featured": false,
  "sort_order": 240,
  "weight_grams": 152,
  "carrier_ids": [1, 2, 3, 4, 5],
  "characteristics": [ { "label": "Couleur", "value": "Woodland" } ],
  "filter_attributes": [ { "label": "Marque", "value": "Mechanix" } ],
  "supplier_id": 1,
  "available_at_supplier": true,
  "supplier_reference": "830103L",
  "supplier_product_url": "https://…",
  "image": "products/gants-mechanix-m-pact-woodland-placeholder-1.webp",
  "images": [ { "id": 12, "image": "products/…-2.webp", "sort_order": 0 } ],
  "variants": [
    {
      "id": 1,
      "attributes": [ { "label": "Taille", "value": "L" } ],
      "sku": "MPT-77-010",
      "gtin": null,
      "price_cents": null,
      "quantity": 3,
      "is_active": true,
      "sort_order": 0,
      "image": null,
      "supplier_id": 1,
      "available_at_supplier": true,
      "supplier_reference": "830103L",
      "supplier_product_url": "https://…"
    }
  ],
  "has_variants": true,
  "created_at": "2026-08-24T17:23:34+00:00",
  "updated_at": "2026-08-24T17:23:34+00:00"
}
```

### Field notes

| Field | Notes |
|---|---|
| `price_cents` | **Read only, integer cents.** To write it, send `price` as a decimal (see below). |
| `quantity` | On a product with variants this is the **sum of its variants** and cannot be written directly. |
| `image`, `images` | Paths relative to `public/`. `image` is writable as a path; **uploading a file is the web admin's job**, and `images` (the gallery) is read-only. |
| `carrier_ids` | Which carriers may ship this product. Empty array means none configured. |
| `characteristics` | Free-form spec rows shown on the product page. Array of `{label, value}`. |
| `filter_attributes` | Drives storefront filtering. Same shape, kept short and repeated across products. |
| `has_variants` | Convenience flag; present only when variants are loaded. |
| `sort_order` | Ascending. Storefront listings order by it, so **lower appears first**. |
| `slug` | The product's public URL. Writable; every previous slug keeps redirecting here with a `301`. |
| `ai_validated` | Whether the page has been reviewed and passed. `false` on every new product. Nothing on the storefront reads it; it is a bookkeeping flag for whoever is working through the catalogue. |

### Write-only fields

`supplier_price_cents` and `markup_basis_points` **can be written but are never returned**. They are your buy price and margin; the API token is a single shared secret, so cost data does not leave the server. Write them as `supplier_price` and `markup_percent`.

To read cost or margin, use the web admin.

---

## `GET /products`

### Query parameters

| Param | Type | Effect |
|---|---|---|
| `search` | string | Partial match on name, slug or SKU |
| `sku` | string | Exact match |
| `gtin` | string | Exact match |
| `slug` | string | Exact match |
| `category_id` | int | Exact match |
| `supplier_id` | int | Exact match |
| `is_active` | bool | `1`/`0`, `true`/`false` |
| `updated_since` | date/datetime | Products with `updated_at >= ` this value |
| `per_page` | int | Default `50`, **capped at 100** (higher values are clamped, not rejected) |
| `page` | int | 1-based |

Unknown parameters are ignored. Results are ordered by `id` ascending, which is stable across pages.

```bash
curl -s -H "Authorization: Bearer $ADMIN_API_TOKEN" \
     -H "Accept: application/json" \
     "https://example.com/api/admin/products?category_id=59&is_active=1&per_page=100"
```

**Incremental sync:** store the timestamp *before* you start a run, then next time pass it as `updated_since`. Paginate to the end — `meta.last_page` tells you when to stop.

---

## `POST /products`

Creates a product. Returns `201` with the created object.

**Required:** `name`, `description`, `category_id`, `price`.

```bash
curl -s -X POST \
  -H "Authorization: Bearer $ADMIN_API_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
        "name": "Gants Mechanix M-Pact Woodland",
        "description": "<p>Gant tactique renforcé.</p>",
        "category_id": 59,
        "price": 39.99,
        "weight_grams": 152,
        "carrier_ids": [1,2,3,4,5],
        "is_active": true
      }' \
  "https://example.com/api/admin/products"
```

The server assigns:

- **`slug`** from `name`, deduplicated with `-2`, `-3`, … Pass `slug` explicitly to choose your own.
- **`sort_order`** to `max(sort_order) + 1`, i.e. the end of every listing. Pass it explicitly to place the product somewhere else.
- **`image`** to an empty string. Add real images through the web admin.

---

## `PATCH /products/{id}`

Partial update. **Only the keys you send are touched** — omitted fields keep their values.

Sending `null` clears a field only where the column allows it: `meta_title`, `meta_description`, `brand`, `sku`, `gtin`, `weight_grams`, `supplier_id`, `supplier_reference`, `supplier_product_url`, `supplier_price`, `markup_percent`. Sending `null` for `carrier_ids` is a `422` — send `[]` instead. Sending `null` or `""` for `image` clears it to an empty string.

```bash
curl -s -X PATCH \
  -H "Authorization: Bearer $ADMIN_API_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"price": 34.99, "weight_grams": 160}' \
  "https://example.com/api/admin/products/339"
```

---

## Writable fields

Same set for `POST` and `PATCH`.

| Field | Type | Constraints |
|---|---|---|
| `name` | string | ≤ 120 chars |
| `description` | string | ≤ 50 000 chars, HTML allowed and **sanitised server-side** |
| `category_id` | int | must exist |
| `slug` | string | ≤ 255, lowercase letters, digits and single hyphens (`^[a-z0-9]+(?:-[a-z0-9]+)*$`). Unique against current **and retired** slugs of every other product — see [rule 7](#rules-that-will-bite-you) |
| `price` | decimal | ≥ 0, ≤ 99 999.99 → stored as `price_cents` |
| `quantity` | int | 0–99 999. **MUST NOT** be sent for a product with variants |
| `meta_title` | string\|null | ≤ 70. The title a search result shows. Empty or omitted: the product's `name` is used. Worth setting when the name runs past ~60 characters, which a result truncates |
| `meta_description` | string\|null | ≤ 160. The description a search result shows. Empty or omitted: the product's `description` is used, cut at its last whole sentence. 160 is what a result shows, so nothing sent here is truncated |
| `brand` | string\|null | ≤ 80. Who made the product, not who sells it. Empty or omitted: no brand is published, which is correct for unbranded stock |
| `sku` | string\|null | ≤ 64, unique across products **and** variants |
| `gtin` | string\|null | 8, 12, 13 or 14 digits; unique across products **and** variants |
| `weight_grams` | int\|null | 0–99 999 |
| `carrier_ids` | int[] | each must exist. **Not nullable** — send `[]` to clear |
| `is_active` | bool | |
| `ai_validated` | bool | defaults to `false` on create |
| `age_restricted` | bool | |
| `image_may_vary` | bool | |
| `featured` | bool | |
| `sort_order` | int | 0–99 999 |
| `image` | string\|null | ≤ 2048, relative path. `null` or `""` stores an empty string — the column is never null |
| `characteristics` | array | `[{label ≤120, value ≤500}]`, both required per row |
| `filter_attributes` | array | same shape |
| `supplier_id` | int\|null | must exist |
| `available_at_supplier` | bool | |
| `supplier_reference` | string\|null | ≤ 120 |
| `supplier_product_url` | string\|null | valid URL, ≤ 2048 |
| `supplier_price` | decimal\|null | write-only → `supplier_price_cents` |
| `markup_percent` | decimal\|null | write-only → `markup_basis_points` (30 → 3000) |
| `variants` | array | see below |

Sending an array replaces the whole array for `characteristics`, `filter_attributes` and `carrier_ids`. There is no per-row patching of those.

---

## Variants

Send `variants` as a list of operations. Rows are matched by `id`.

| Row shape | Effect |
|---|---|
| no `id` | **creates** a variant |
| `id` present | **updates** that variant (partial — only keys sent) |
| `id` + `"_delete": true` | **deletes** that variant |
| *omitted entirely* | **left untouched** |

That last line matters: `variants` is not a full replacement. A `PATCH` that sends one variant does not delete the others.

```json
{
  "variants": [
    { "attributes": [{"label": "Taille", "value": "S"}], "sku": "MPT-77-008", "quantity": 4 },
    { "id": 67, "quantity": 9 },
    { "id": 68, "_delete": true }
  ]
}
```

### Variant fields

| Field | Type | Notes |
|---|---|---|
| `attributes` | array | `[{label, value}]` — e.g. size, colour. Defaults to `[]` on create |
| `sku` | string\|null | unique across products **and** variants |
| `gtin` | string\|null | 8/12/13/14 digits, unique across products **and** variants |
| `price` | decimal\|null | overrides the product price; `null` means "use the product's" |
| `quantity` | int | defaults to `0` on create |
| `is_active` | bool | defaults to `true` on create |
| `sort_order` | int | defaults to the row's position |
| `supplier_id`, `available_at_supplier`, `supplier_reference`, `supplier_product_url` | | per variant, since sizes are often ordered separately |

Variant images are read-only here; set them in the web admin.

---

## Rules that will bite you

These are enforced. Each returns `422` unless stated otherwise.

**1. Stock on a variant product belongs to the variants.**
A product with variants takes `quantity` as the sum of its variants. Sending `quantity` for such a product — including in the same request that creates its first variant — is rejected:

```json
{ "errors": { "quantity": ["A product with variants takes its stock from its variants. Set the quantity on each variant instead."] } }
```

Set stock per variant instead; the parent total is recomputed automatically. This is not merely advisory: a directly written total would be silently overwritten the next time anything recalculated it.

**2. A product with variants loses its own identity fields.**
The moment a product has at least one variant, the server clears its `sku`, `gtin`, `supplier_id`, `supplier_reference`, `supplier_product_url`, `supplier_price_cents` and `markup_basis_points`. Those belong on the variants. Do not expect values you sent in the same request to survive.

**3. Deleting the last variant resets stock to `0`.**
The old sum no longer describes anything real. Set a fresh `quantity` in a follow-up request if the product still has stock.

**4. SKU and GTIN are unique across the whole catalogue.**
Not per table. A product may not take a SKU held by any variant, and vice versa. Duplicates inside one request are rejected too.

**5. `price` is decimal on the way in, `price_cents` on the way out.**
Sending `price_cents` does nothing — it is not a writable field.

**6. Cost fields are write-only.** See [Write-only fields](#write-only-fields).

**7. Old slugs keep working, and are never freed.**
Every slug a product has ever carried is kept. Visiting a retired one returns a `301` to the product's current URL, so links already shared, indexed or printed on a marketplace listing survive a rename. The flip side: a retired slug **belongs to that product forever**. Sending another product's old slug is a `422`, since the redirect would start pointing at the wrong item. A product may always return to one of its own former slugs.

A slug must be unique and match `^[a-z0-9]+(?:-[a-z0-9]+)*$` — no capitals, no spaces, no underscores, no leading, trailing or doubled hyphens.

**8. `ai_validated` means nothing to the storefront.**
It changes no price, no visibility, no availability. It records that someone reviewed the page. Set it to `true` when a page has been checked, `false` to send it back for another look.

---

## Errors

| Status | Meaning |
|---|---|
| `401` | Missing, malformed or wrong token |
| `403` | Request reached a handler without passing the token middleware (should not occur in normal use) |
| `404` | No product with that id |
| `422` | Validation failed |
| `429` | Rate limit exceeded — honour `Retry-After` |

Validation errors use Laravel's standard shape:

```json
{
  "message": "Le champ nom est obligatoire. (and 3 more errors)",
  "errors": {
    "name": ["Le champ nom est obligatoire."],
    "description": ["Le champ description est obligatoire."],
    "category_id": ["Le champ category id est obligatoire."],
    "price": ["Le champ price doit être au moins 0."]
  }
}
```

Nested keys are dotted: `variants.0.gtin`.

> **Messages are French** — the application locale is `fr`. Agents **MUST** branch on the `errors` object keys and the HTTP status, never on message text.

---

## Worked example: add a product with sizes

Two requests. The first creates the product, the second adds its variants — the split is required, because a create carrying both `quantity` and variants would be rejected by rule 1.

```bash
# 1. Create the product
PRODUCT_ID=$(curl -s -X POST \
  -H "Authorization: Bearer $ADMIN_API_TOKEN" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{
        "name": "Gants Mechanix M-Pact Woodland",
        "description": "<p>Gant tactique renforcé.</p>",
        "category_id": 59,
        "price": 39.99,
        "weight_grams": 152,
        "carrier_ids": [1,2,3,4,5]
      }' \
  "https://example.com/api/admin/products" | jq -r '.data.id')

# 2. Add the sizes; the product total becomes 4 + 6 = 10
curl -s -X PATCH \
  -H "Authorization: Bearer $ADMIN_API_TOKEN" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{
        "variants": [
          {"attributes":[{"label":"Taille","value":"S"}],"sku":"MPT-77-008","quantity":4},
          {"attributes":[{"label":"Taille","value":"L"}],"sku":"MPT-77-010","quantity":6}
        ]
      }' \
  "https://example.com/api/admin/products/$PRODUCT_ID"
```

---

## Agent checklist

Before sending a write request:

1. `Accept: application/json` and `Authorization: Bearer …` set.
2. `PATCH` carries **only** the fields being changed.
3. `price` as decimal, never `price_cents`.
4. If the product has variants (`has_variants: true`), **no** `quantity` at product level.
5. `variants` rows carry `id` to update, omit `id` to create, `_delete` to remove — and omitting a variant leaves it alone.
6. SKU/GTIN checked for collisions against products *and* variants.
7. `slug` changes are permanent: the old one redirects forever and can never be given to another product.
8. On `429`, wait for `Retry-After`. On `422`, read `errors` keys — never parse the French message text.

---

## Related

- [Blog API](blog.md) — same token, same envelope, same limiter.
- Web admin product form — same catalogue, and the only place to upload images or read cost and margin.
- `GET /api/admin/categories` — for valid `category_id` values.
