# Plan — Add Mechanix M-Pact Mitaine Coyote via Admin API

For Claude. Add one catalogue product over HTTP. Do not edit seeders, Blade, or CSS. Do not download supplier photos.

Read `docs/admin/api/products.md` in full first, especially **Rules that will bite you** and the worked example at the bottom.

The Noir mitaine is already in the catalogue (`gants-mechanix-m-pact-mitaine-noir`, id 340, SKUs `MFL-55-*`). This is the **Coyote colourway** of the same fingerless model. New product, new SKUs (`MFL-72-*`). Do not PATCH the Noir product.

---

## What this product is

Fingerless / mitaine Mechanix M-Pact in coyote. Official name: **M-Pact® Fingerless Coyote** (Mechanix part **MFL-72**). DM Diffusion lists it as **Mechanix Gants M-PACT Mitaine Coyote**.

It is **not** the full-finger Coyote already in the shop (`gants-mechanix-m-pact-coyote`, `MPT-72-*`). Different last, different SKU family.

### Sources

| Source | What it gave |
|---|---|
| [DM Diffusion L](https://www.dmdiffusion.com/mechanix-gants-m-pact-mitaine-coyote-taille-l-mfl-72-010-c2x29504642) | Supplier ref `830148L`, GTIN `781513634684`, 152 g, in stock |
| [DM M](https://www.dmdiffusion.com/mechanix-gants-m-pact-mitaine-coyote-taille-m-mfl-72-009-c2x29504643) / [DM XL](https://www.dmdiffusion.com/mechanix-gants-m-pact-mitaine-coyote-taille-xl-mfl-72-011-c2x29504644) | Codes `830148M` / `830148XL` |
| [Mechanix FR](https://www.mechanix.com/fr-fr/gants/gants-tactiques-et-de-tir/MFL-72.html) | Official copy, EN 388 / 2121XP, sizes **M · L · XL** only, 30,83 € TTC list |
| LayLax (JP authorised) | Size → UPC: M `781513634677`, L `781513634684`, XL `781513634691` |
| Mode Tactique | 32,00 € TTC, sizes M–XL, EAN for L matches DM |

No S and no XXL from the manufacturer or from DM. Do not invent those sizes.

---

## Locked decisions

Same commercial choices as the Noir mitaine (same HT).

| Decision | Choice |
|---|---|
| How | Admin API only (`POST` then `PATCH`). Token from `ADMIN_API_TOKEN`. |
| Name | `Gants Mechanix M-Pact Mitaine Coyote` |
| Slug | Server-generated → expect `gants-mechanix-m-pact-mitaine-coyote`. Do not send `slug`. |
| Category | `gants` (`category_id` **59**, under Vêtements) |
| Supplier | DM Diffusion, `supplier_id` **1**, on **each variant** |
| HT buy | **16,60 €** → send `supplier_price: 16.60` on create (see cost note below) |
| TTC sell | **29,99 €** (`price: 29.99`) — same as the Noir mitaine. Cost TTC is 19,92 €. |
| VAT | Shop applies 20 % on HT itself. Do not multiply 16,60 before sending. |
| Sizes | **M, L, XL** only |
| Stock | `quantity: 0` on every variant, `available_at_supplier: true` |
| Images | Do **not** fetch DM/Mechanix files. Leave `image` unset on create (API stores `""`). No gallery. |
| Carriers | `[1, 2, 3, 4, 5]` like the siblings |
| Changelog / git | Out of scope unless asked |

### Cost fields caveat

`supplier_price` / `markup_percent` are write-only and **cleared on the parent as soon as variants exist** (API rule 2). Variant rows have no cost fields. Sending `supplier_price` on `POST` does not survive the `PATCH` that adds sizes. Still send it on create so the first write is complete; do not expect it back on `GET`. Cost after that is web-admin only.

---

## Mirror the existing Gants M-Pact products

Live siblings to copy structure from (not photos, not SKUs):

- `gants-mechanix-m-pact-mitaine-noir` (id 340) — **same last, other colour** (copy this one first)
- `gants-mechanix-m-pact-coyote` (id 321) — full-finger Coyote (filters/colour wording only)

Pattern they all share, and this product **must** share:

- One product per colourway, not one product with a Couleur attribute
- Variants = `Taille` only (**M/L/XL** here)
- Parent `sku` / `gtin` / `supplier_*` empty once variants exist
- Manufacturer SKU on the variant (`MFL-72-010`)
- DM code on `supplier_reference` (`830148L`)
- DM product URL per size
- `filter_attributes`: Marque + Couleur
- `characteristics`: Couleur, Marque, Matière, Protection, Modèle, Écran tactile, Entretien
- French HTML description, two or three `<p>` tags
- `weight_grams: 152` (DM lists 152 g on the L page)
- `carrier_ids: [1,2,3,4,5]`
- `is_active: true`, `featured: false`, `age_restricted: false`

Official Mechanix cert for the mitaine is **EN 388 (2121XP)**, not EN 13594.

Storefront page behaviour (already implemented — no code change):

- Breadcrumb Accueil → Vêtements → Gants → product name
- Size picker from variant `Taille`
- Price from the parent unless a variant overrides `price` (leave variant `price` null)
- Backorder when `quantity` is 0 and `available_at_supplier` is true

---

## Variant table (authoritative)

| Taille | Mechanix SKU | GTIN-12 | DM code | DM URL |
|---|---|---|---|---|
| M | `MFL-72-009` | `781513634677` | `830148M` | https://www.dmdiffusion.com/mechanix-gants-m-pact-mitaine-coyote-taille-m-mfl-72-009-c2x29504643 |
| L | `MFL-72-010` | `781513634684` | `830148L` | https://www.dmdiffusion.com/mechanix-gants-m-pact-mitaine-coyote-taille-l-mfl-72-010-c2x29504642 |
| XL | `MFL-72-011` | `781513634691` | `830148XL` | https://www.dmdiffusion.com/mechanix-gants-m-pact-mitaine-coyote-taille-xl-mfl-72-011-c2x29504644 |

GTINs are 12 digits (UPC). The API accepts 8/12/13/14. Send them as 12-digit strings, no leading zero.

Do **not** reuse `MFL-55-*` or `MPT-72-*`.

---

## Description (use this HTML)

Sanitised server-side. Send as one string.

```html
<p>Les mitaines Mechanix M-Pact Coyote (Fingerless Coyote, réf. MFL-72) gardent le contrôle à doigts libres sans abandonner la protection M-Pact. Pensées pour les forces de l'ordre, le tir sportif et l'airsoft, elles laissent l'index et le reste des doigts libres pour la détente, un écran ou un outil, pendant que le dos de main reste protégé. Le coloris coyote se fond mieux en terrain découvert que le noir Covert.</p>
<p>La protection contre les impacts en élastomère thermoplastique (TPE) répond à la norme EN 388 (2121XP). Un rembourrage D3O® à la paume absorbe et dissipe l'énergie de grand impact. La paume en cuir synthétique est renforcée Armortex® ; le TrekDry® respirant garde la main fraîche. Fermeture TPE au poignet, boucles nylon, lavable en machine.</p>
<p>Proposée en M, L et XL, coloris coyote. Idéale quand la dextérité prime sur le gant complet.</p>
```

---

## Characteristics and filters

```json
"characteristics": [
  { "label": "Couleur", "value": "Coyote" },
  { "label": "Marque", "value": "Mechanix" },
  { "label": "Matière", "value": "Cuir synthétique, TrekDry, TPE, Armortex" },
  { "label": "Protection", "value": "D3O + TPE, norme EN 388 (2121XP)" },
  { "label": "Modèle", "value": "Mitaine / fingerless" },
  { "label": "Écran tactile", "value": "Doigts libres" },
  { "label": "Entretien", "value": "Lavable en machine" }
],
"filter_attributes": [
  { "label": "Marque", "value": "Mechanix" },
  { "label": "Couleur", "value": "Coyote" }
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
GET /products?search=m-pact-mitaine-coyote
GET /products?sku=MFL-72-010
GET /products?slug=gants-mechanix-m-pact-mitaine-coyote
```

If a mitaine Coyote already exists, **stop**. `gants-mechanix-m-pact-coyote` (full-finger) and `gants-mechanix-m-pact-mitaine-noir` are expected — leave them alone.

Confirm `category_id` 59 is still `gants`.

### 1. Create the parent — no `quantity`, no `variants`

`POST /products`

```json
{
  "name": "Gants Mechanix M-Pact Mitaine Coyote",
  "description": "<p>Les mitaines Mechanix M-Pact Coyote (Fingerless Coyote, réf. MFL-72) gardent le contrôle à doigts libres sans abandonner la protection M-Pact. Pensées pour les forces de l'ordre, le tir sportif et l'airsoft, elles laissent l'index et le reste des doigts libres pour la détente, un écran ou un outil, pendant que le dos de main reste protégé. Le coloris coyote se fond mieux en terrain découvert que le noir Covert.</p><p>La protection contre les impacts en élastomère thermoplastique (TPE) répond à la norme EN 388 (2121XP). Un rembourrage D3O® à la paume absorbe et dissipe l'énergie de grand impact. La paume en cuir synthétique est renforcée Armortex® ; le TrekDry® respirant garde la main fraîche. Fermeture TPE au poignet, boucles nylon, lavable en machine.</p><p>Proposée en M, L et XL, coloris coyote. Idéale quand la dextérité prime sur le gant complet.</p>",
  "category_id": 59,
  "price": 29.99,
  "weight_grams": 152,
  "carrier_ids": [1, 2, 3, 4, 5],
  "is_active": true,
  "featured": false,
  "age_restricted": false,
  "characteristics": [
    { "label": "Couleur", "value": "Coyote" },
    { "label": "Marque", "value": "Mechanix" },
    { "label": "Matière", "value": "Cuir synthétique, TrekDry, TPE, Armortex" },
    { "label": "Protection", "value": "D3O + TPE, norme EN 388 (2121XP)" },
    { "label": "Modèle", "value": "Mitaine / fingerless" },
    { "label": "Écran tactile", "value": "Doigts libres" },
    { "label": "Entretien", "value": "Lavable en machine" }
  ],
  "filter_attributes": [
    { "label": "Marque", "value": "Mechanix" },
    { "label": "Couleur", "value": "Coyote" }
  ],
  "supplier_id": 1,
  "available_at_supplier": true,
  "supplier_price": 16.60
}
```

Expect **201**. Capture `.data.id`. Expect `.data.slug` = `gants-mechanix-m-pact-mitaine-coyote` and `.data.price_cents` = `2999`. `image` will be `""`.

A create that sends both `quantity` and `variants` is a **422**. Split the calls.

### 2. Add the three sizes

`PATCH /products/{id}`

```json
{
  "variants": [
    {
      "attributes": [{ "label": "Taille", "value": "M" }],
      "sku": "MFL-72-009",
      "gtin": "781513634677",
      "quantity": 0,
      "is_active": true,
      "sort_order": 0,
      "supplier_id": 1,
      "available_at_supplier": true,
      "supplier_reference": "830148M",
      "supplier_product_url": "https://www.dmdiffusion.com/mechanix-gants-m-pact-mitaine-coyote-taille-m-mfl-72-009-c2x29504643"
    },
    {
      "attributes": [{ "label": "Taille", "value": "L" }],
      "sku": "MFL-72-010",
      "gtin": "781513634684",
      "quantity": 0,
      "is_active": true,
      "sort_order": 1,
      "supplier_id": 1,
      "available_at_supplier": true,
      "supplier_reference": "830148L",
      "supplier_product_url": "https://www.dmdiffusion.com/mechanix-gants-m-pact-mitaine-coyote-taille-l-mfl-72-010-c2x29504642"
    },
    {
      "attributes": [{ "label": "Taille", "value": "XL" }],
      "sku": "MFL-72-011",
      "gtin": "781513634691",
      "quantity": 0,
      "is_active": true,
      "sort_order": 2,
      "supplier_id": 1,
      "available_at_supplier": true,
      "supplier_reference": "830148XL",
      "supplier_product_url": "https://www.dmdiffusion.com/mechanix-gants-m-pact-mitaine-coyote-taille-xl-mfl-72-011-c2x29504644"
    }
  ]
}
```

Expect **200**. Parent `sku`/`gtin`/`supplier_id` will be cleared. `quantity` on the product becomes 0. `has_variants` true.

On **422**, branch on `errors` keys, never on the French `message`. On **429**, honour `Retry-After`.

### 3. Verify

```
GET /products/{id}
```

Check:

- name, slug, `price_cents` 2999, `category.slug` `gants`
- 3 variants, Taille M/L/XL, SKUs `MFL-72-009/010/011` (not `MFL-55-*`, not `MPT-72-*`)
- `image` empty (placeholder — photos later in web admin)
- storefront `/products/gants-mechanix-m-pact-mitaine-coyote` loads, shows three sizes, 29,99 €, Gants breadcrumb
- existing `gants-mechanix-m-pact-coyote` (full-finger) and `gants-mechanix-m-pact-mitaine-noir` unchanged

Do not commit, do not write CHANGELOG, unless asked.

---

## Out of scope

- Downloading or converting DM / Mechanix images
- Seeder / factory / test changes
- S or XXL variants
- Editing the Noir mitaine or the full-finger Coyote

---

## If something is missing

| Gap | What to do |
|---|---|
| Token missing | Stop. Need `ADMIN_API_TOKEN` in the environment. |
| `category_id` 59 is not `gants` | `GET` categories, use the id whose slug is `gants`. |
| SKU/GTIN 422 uniqueness | Do not reuse `MFL-55-*` or `MPT-72-*`. Report the colliding code. |
| Want photos | Web admin upload after this API pass. |
