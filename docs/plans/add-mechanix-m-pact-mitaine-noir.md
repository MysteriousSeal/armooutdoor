# Plan — Add Mechanix M-Pact Mitaine Noir via Admin API

For Claude. Add one catalogue product over HTTP. Do not edit seeders, Blade, or CSS. Do not download supplier photos.

Read `docs/admin/api/products.md` in full first, especially **Rules that will bite you** and the worked example at the bottom.

---

## What this product is

Fingerless / mitaine version of the existing Mechanix M-Pact line. Official name: **M-Pact® Fingerless Covert** (Mechanix part **MFL-55**). DM Diffusion lists it as **Mechanix Gants M-PACT Mitaine Noir**.

It is **not** the same SKU family as the full-finger gloves already in the shop (`MPT-55-*` Noir, `MPT-72-*` Coyote, `MPT-77-*` Woodland). New product, new variants.

### Sources

| Source | What it gave |
|---|---|
| [DM Diffusion L](https://www.dmdiffusion.com/mechanix-gants-m-pact-mitaine-noir-taille-l-mfl-55-010-c2x29504539) | Supplier refs, GTINs, 152 g, in stock |
| [DM M](https://www.dmdiffusion.com/mechanix-gants-m-pact-mitaine-taille-m-mfl-55-009-c2x29504540) / [DM XL](https://www.dmdiffusion.com/mechanix-gants-m-pact-mitaine-taille-xl-mfl-55-011-c2x29504541) | Same family, codes 830104M / 830104XL |
| [Mechanix FR](https://www.mechanix.com/fr-fr/gants/gants-tactiques-et-de-tir/MFL-55.html) | Official copy, EN 388 / 2121XP, sizes **M · L · XL** only |
| Mode Tactique, Arsenal Collection, IPSCStore, TacticalGear | Cross-check of features and market TTC (~22–32 €) |

No S and no XXL from the manufacturer or from DM. Do not invent those sizes.

---

## Locked decisions

| Decision | Choice |
|---|---|
| How | Admin API only (`POST` then `PATCH`). Token from `ADMIN_API_TOKEN`. |
| Name | `Gants Mechanix M-Pact Mitaine Noir` |
| Slug | Server-generated from the name → expect `gants-mechanix-m-pact-mitaine-noir`. Do not send `slug`. |
| Category | `gants` (`category_id` **59**, under Vêtements) |
| Supplier | DM Diffusion, `supplier_id` **1**, on **each variant** |
| HT buy | **16,60 €** → send `supplier_price: 16.60` on create (see cost note below) |
| TTC sell | **29,99 €** (`price: 29.99`) — approved. Cost TTC is 19,92 €. |
| VAT | Shop applies 20 % on HT itself. Do not multiply 16,60 before sending. |
| Sizes | **M, L, XL** only |
| Stock | `quantity: 0` on every variant, `available_at_supplier: true` (same as the other M-Pact) |
| Images | Do **not** fetch DM/Mechanix files. Leave `image` unset on create (API stores `""`). No gallery. |
| Carriers | `[1, 2, 3, 4, 5]` like the siblings |
| Changelog / git | Out of scope unless asked |

### Cost fields caveat

`supplier_price` / `markup_percent` are write-only and **cleared on the parent as soon as variants exist** (API rule 2). Variant rows have no cost fields. Sending `supplier_price` on `POST` does not survive the `PATCH` that adds sizes. Still send it on create so the first write is complete; do not expect it back on `GET`. Cost after that is web-admin only.

---

## Mirror the existing Gants M-Pact products

Live siblings to copy structure from (not photos, not SKUs):

- `gants-mechanix-m-pact-noir` (id 326) — closest colour
- `gants-mechanix-m-pact-coyote` (id 321)
- `gants-mechanix-m-pact-woodland` (id 339)

Pattern they all share, and this product **must** share:

- One product per colourway, not one product with a Couleur attribute
- Variants = `Taille` only (`S`/`M`/`L`/`XL`/`XXL` on full-finger; **M/L/XL** here)
- Parent `sku` / `gtin` / `supplier_*` empty once variants exist
- Manufacturer SKU on the variant (`MPT-55-010` style → here `MFL-55-010`)
- DM code on `supplier_reference` (`830101L` style → here `830104L`)
- DM product URL per size
- `filter_attributes`: Marque + Couleur
- `characteristics`: Couleur, Marque, Matière, Protection, Écran tactile, Entretien
- French HTML description, two or three `<p>` tags, no heading soup
- `weight_grams: 152` (DM lists 152 g on every size, same as the full-finger line)
- `carrier_ids: [1,2,3,4,5]`
- `is_active: true`, `featured: false`, `age_restricted: false`

**Do not copy** the full-finger protection line blindly. Official Mechanix cert for the mitaine is **EN 388 (2121XP)**, not EN 13594.

Storefront page behaviour (already implemented — no code change):

- Breadcrumb Accueil → Vêtements → Gants → product name
- Size picker from variant `Taille`
- Price from the parent unless a variant overrides `price` (leave variant `price` null)
- Backorder when `quantity` is 0 and `available_at_supplier` is true

---

## Variant table (authoritative)

| Taille | Mechanix SKU | GTIN-12 | DM code | DM URL |
|---|---|---|---|---|
| M | `MFL-55-009` | `781513631034` | `830104M` | https://www.dmdiffusion.com/mechanix-gants-m-pact-mitaine-taille-m-mfl-55-009-c2x29504540 |
| L | `MFL-55-010` | `781513631041` | `830104L` | https://www.dmdiffusion.com/mechanix-gants-m-pact-mitaine-noir-taille-l-mfl-55-010-c2x29504539 |
| XL | `MFL-55-011` | `781513631058` | `830104XL` | https://www.dmdiffusion.com/mechanix-gants-m-pact-mitaine-taille-xl-mfl-55-011-c2x29504541 |

GTINs are 12 digits (UPC). The API accepts 8/12/13/14. Send them as 12-digit strings, no leading zero.

---

## Description (use this HTML)

Sanitised server-side. Send as one string.

```html
<p>Les mitaines Mechanix M-Pact Noir (Fingerless Covert, réf. MFL-55) gardent le contrôle à doigts libres sans abandonner la protection M-Pact. Pensées pour les forces de l'ordre, le tir sportif et l'airsoft, elles laissent l'index et le reste des doigts libres pour la détente, un écran ou un outil, pendant que le dos de main reste protégé.</p>
<p>La protection contre les impacts en élastomère thermoplastique (TPE) répond à la norme EN 388 (2121XP). Un rembourrage D3O® à la paume absorbe et dissipe l'énergie de grand impact. La paume en cuir synthétique est renforcée Armortex® ; le TrekDry® respirant garde la main fraîche. Fermeture TPE au poignet, boucles nylon, lavable en machine.</p>
<p>Proposée en M, L et XL, coloris noir (Covert). Idéale quand la dextérité prime sur le gant complet.</p>
```

---

## Characteristics and filters

```json
"characteristics": [
  { "label": "Couleur", "value": "Noir" },
  { "label": "Marque", "value": "Mechanix" },
  { "label": "Matière", "value": "Cuir synthétique, TrekDry, TPE, Armortex" },
  { "label": "Protection", "value": "D3O + TPE, norme EN 388 (2121XP)" },
  { "label": "Modèle", "value": "Mitaine / fingerless" },
  { "label": "Écran tactile", "value": "Doigts libres" },
  { "label": "Entretien", "value": "Lavable en machine" }
],
"filter_attributes": [
  { "label": "Marque", "value": "Mechanix" },
  { "label": "Couleur", "value": "Noir" }
]
```

---

## Procedure (two requests — mandatory split)

Base: `$HOST/api/admin` (local: `http://127.0.0.1:8003/api/admin`).

Headers on every call:

```
Authorization: Bearer $ADMIN_API_TOKEN
Accept: application/json
Content-Type: application/json
```

### 0. Collision check

```
GET /products?search=m-pact-mitaine
GET /products?sku=MFL-55-010
```

If a mitaine Noir already exists, **stop**. If a SKU/GTIN collides, **stop**.

Confirm `category_id` 59 is still `gants` via `GET /api/admin/categories` if that endpoint is used; otherwise trust the live catalogue (`Category` id 59).

### 1. Create the parent — no `quantity`, no `variants`

`POST /products`

```json
{
  "name": "Gants Mechanix M-Pact Mitaine Noir",
  "description": "<p>Les mitaines Mechanix M-Pact Noir (Fingerless Covert, réf. MFL-55) gardent le contrôle à doigts libres sans abandonner la protection M-Pact. Pensées pour les forces de l'ordre, le tir sportif et l'airsoft, elles laissent l'index et le reste des doigts libres pour la détente, un écran ou un outil, pendant que le dos de main reste protégé.</p><p>La protection contre les impacts en élastomère thermoplastique (TPE) répond à la norme EN 388 (2121XP). Un rembourrage D3O® à la paume absorbe et dissipe l'énergie de grand impact. La paume en cuir synthétique est renforcée Armortex® ; le TrekDry® respirant garde la main fraîche. Fermeture TPE au poignet, boucles nylon, lavable en machine.</p><p>Proposée en M, L et XL, coloris noir (Covert). Idéale quand la dextérité prime sur le gant complet.</p>",
  "category_id": 59,
  "price": 29.99,
  "weight_grams": 152,
  "carrier_ids": [1, 2, 3, 4, 5],
  "is_active": true,
  "featured": false,
  "age_restricted": false,
  "characteristics": [
    { "label": "Couleur", "value": "Noir" },
    { "label": "Marque", "value": "Mechanix" },
    { "label": "Matière", "value": "Cuir synthétique, TrekDry, TPE, Armortex" },
    { "label": "Protection", "value": "D3O + TPE, norme EN 388 (2121XP)" },
    { "label": "Modèle", "value": "Mitaine / fingerless" },
    { "label": "Écran tactile", "value": "Doigts libres" },
    { "label": "Entretien", "value": "Lavable en machine" }
  ],
  "filter_attributes": [
    { "label": "Marque", "value": "Mechanix" },
    { "label": "Couleur", "value": "Noir" }
  ],
  "supplier_id": 1,
  "available_at_supplier": true,
  "supplier_price": 16.60
}
```

Expect **201**. Capture `.data.id`. Expect `.data.slug` = `gants-mechanix-m-pact-mitaine-noir` and `.data.price_cents` = `2999`. `image` will be `""`.

Do **not** send `quantity` here if you also plan to add variants in the same request. The worked example splits on purpose: a create that sends both `quantity` and `variants` is a **422**.

### 2. Add the three sizes

`PATCH /products/{id}`

```json
{
  "variants": [
    {
      "attributes": [{ "label": "Taille", "value": "M" }],
      "sku": "MFL-55-009",
      "gtin": "781513631034",
      "quantity": 0,
      "is_active": true,
      "sort_order": 0,
      "supplier_id": 1,
      "available_at_supplier": true,
      "supplier_reference": "830104M",
      "supplier_product_url": "https://www.dmdiffusion.com/mechanix-gants-m-pact-mitaine-taille-m-mfl-55-009-c2x29504540"
    },
    {
      "attributes": [{ "label": "Taille", "value": "L" }],
      "sku": "MFL-55-010",
      "gtin": "781513631041",
      "quantity": 0,
      "is_active": true,
      "sort_order": 1,
      "supplier_id": 1,
      "available_at_supplier": true,
      "supplier_reference": "830104L",
      "supplier_product_url": "https://www.dmdiffusion.com/mechanix-gants-m-pact-mitaine-noir-taille-l-mfl-55-010-c2x29504539"
    },
    {
      "attributes": [{ "label": "Taille", "value": "XL" }],
      "sku": "MFL-55-011",
      "gtin": "781513631058",
      "quantity": 0,
      "is_active": true,
      "sort_order": 2,
      "supplier_id": 1,
      "available_at_supplier": true,
      "supplier_reference": "830104XL",
      "supplier_product_url": "https://www.dmdiffusion.com/mechanix-gants-m-pact-mitaine-taille-xl-mfl-55-011-c2x29504541"
    }
  ]
}
```

Expect **200**. Parent `sku`/`gtin`/`supplier_id` will be cleared. `quantity` on the product becomes 0 (sum of variants). `has_variants` true.

On **422**, branch on `errors` keys, never on the French `message`. On **429**, honour `Retry-After`.

### 3. Verify

```
GET /products/{id}
```

Check:

- name, slug, `price_cents` 2999, `category.slug` `gants`
- 3 variants, Taille M/L/XL, SKUs and GTINs as in the table
- `image` empty (placeholder — photos later in web admin)
- storefront `/products/gants-mechanix-m-pact-mitaine-noir` (or whatever slug came back) loads, shows three sizes, 29,99 €, Gants breadcrumb

Do not commit, do not write CHANGELOG, unless asked.

---

## Out of scope

- Downloading or converting DM / Mechanix images
- Seeder / factory / test changes
- A coyote mitaine (Mechanix also sells one; not requested)
- S or XXL variants

---

## If something is missing

| Gap | What to do |
|---|---|
| Token missing | Stop. Need `ADMIN_API_TOKEN` in the environment. |
| `category_id` 59 is not `gants` | `GET` categories, use the id whose slug is `gants`. |
| SKU/GTIN 422 uniqueness | Do not reuse a sibling M-Pact SKU. Report the colliding code. |
| Want photos | Web admin upload after this API pass. |
