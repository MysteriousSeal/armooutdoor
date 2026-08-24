# Plan — Add Beretta Px4 Storm Spring (Umarex 2.5198) via Admin API

For Claude. Add one catalogue product over HTTP. Do not edit seeders, Blade, or CSS. Do not download supplier photos.

Read `docs/admin/api/products.md` in full first, especially **Rules that will bite you**.

This product has **no size variants**. One `POST` is enough. Do **not** send a `variants` array.

---

## What this product is

Licensed airsoft **Beretta Px4 Storm**, spring-powered, **&lt; 0,5 J**, **metal slide**, 6 mm BB. Made by **Umarex** (item **2.5198**), Beretta trademarks licensed. DM Diffusion lists it as **Beretta PX4 Storm Noir Culasse Métal SPRING 0,5J** (code **25198**).

Not a firearm. Manual slide cocking before each shot. Magazine is dual: 12 BBs in the feed + 100-BB reservoir. 100 BBs in the box.

### Sources

| Source | What it gave |
|---|---|
| [DM Diffusion](https://www.dmdiffusion.com/beretta-px4-storm-noir-culasse-metal-spring-05j-c2x39086549) | Code `25198`, EAN `4000844490483`, 600 g packed, in stock |
| [Umarex 2.5198](https://www.umarex.com/products/airsoft/spring-operated/2.5198.html) | Official specs: &lt; 0,5 J, 6 mm, 12/100 mag, 193 mm, 478 g replica, hop-up fixed, 0,12 / 0,20 g BBs, holster A/D, includes 100 BBs |
| Airsoftzone, DarkBull, Game-On, AirsoftEire | Street TTC ~36,90–44,99 €, metal slide, licensed marks, ~230 fps |

---

## Locked decisions

| Decision | Choice |
|---|---|
| How | Admin API `POST /products` only. Token from `ADMIN_API_TOKEN`. |
| Name | `Pistolet Beretta Px4 Storm Umarex Spring Culasse Métal 6 mm BB Airsoft` |
| Slug | Server-generated → expect `pistolet-beretta-px4-storm-umarex-spring-culasse-metal-6-mm-bb-airsoft`. Do not send `slug`. |
| Category | `repliques-de-poing` (`category_id` **55**, under Répliques airsoft) |
| Supplier | DM Diffusion, `supplier_id` **1** on the **product** (no variants) |
| HT buy | **25,85 €** → `supplier_price: 25.85` |
| TTC sell | **39,99 €** (`price: 39.99`) — approved. Cost TTC is 31,02 €. |
| VAT | Shop applies 20 % on HT itself. Do not multiply 25,85 before sending. |
| Variants | **None** |
| Stock | `quantity: 0`, `available_at_supplier: true` (same as other répliques) |
| Age | `age_restricted: true` (all répliques in this shop are 18+) |
| Images | Do **not** fetch DM/Umarex files. Leave `image` unset (API stores `""`). |
| Carriers | `[1, 2, 3, 4]` — same as the other DM Umarex spring pistols (PPQ, PPK/S), **not** 5 |
| Changelog / git | Out of scope unless asked |

No size/colour options. Sending `variants` is unnecessary. Sending `quantity` on this product is allowed because it has no variants.

---

## Mirror the existing Répliques de poing spring pistols

Closest siblings (copy structure, not photos or SKUs):

- `pistolet-walther-ppq-spring-culasse-metal-6mm-airsoft` (id 333) — Umarex spring, metal slide, DM, **use this as the template**
- `pistolet-walther-ppks-spring-culasse-metal-6mm-airsoft` (id 338)
- `pistolet-browning-1911-spring-6-mm-bb-airsoft` (id 304) — same class, other supplier

Pattern they share, and this product **must** share:

- **No variants**
- French name: `Pistolet {licence} {model} Umarex Spring … 6 mm BB Airsoft`
- Shop SKU: `REPLICA-UMAREX-{MODEL}-SPRING-6MM`
- GTIN = EAN-13 from DM (`4000844…`)
- `supplier_reference` = DM code; `supplier_product_url` = the DM product page
- `weight_grams` = **packed** weight from DM, not the bare replica weight
- `age_restricted: true`
- Long `characteristics` list (type, marque, modèle, calibre, puissance, vitesse, propulsion, chargeur, hop-up, matière, longueur, poids réplique, poids colis, billes, contenu, usage)
- `filter_attributes`: Calibre, Marque, Propulsion, Puissance, Type
- Marque filter = **Umarex** (licence holder), not Beretta — same as Walther PPQ/PPK
- French HTML description, 3–4 `<p>` tags
- `carrier_ids: [1,2,3,4]`
- `is_active: true`, `featured: false`

Storefront (already implemented — no code change):

- Breadcrumb Accueil → Répliques airsoft → Répliques de poing → product
- 18+ notice from `age_restricted`
- Backorder when qty 0 and `available_at_supplier`

---

## Identity table

| Field | Value |
|---|---|
| Umarex item | `2.5198` |
| DM code | `25198` |
| EAN-13 / GTIN | `4000844490483` |
| Shop SKU | `REPLICA-UMAREX-PX4-STORM-SPRING-6MM` |
| Packed weight (DM) | 600 g |
| Replica weight (Umarex) | 478 g |

---

## Specs for characteristics (from Umarex + DM)

| Spec | Value |
|---|---|
| Energy | &lt; 0,5 J |
| Calibre | 6 mm BB |
| Power | Spring, slide cocked by hand each shot |
| Simulated DA | Yes (Umarex) |
| Magazine | Hi-cap 12 shots + 100-BB reservoir |
| Hop-up | Fixed, not adjustable |
| Safety | Lever |
| Length | 193 mm |
| Replica weight | 478 g |
| Packed weight | 600 g |
| Recommended BBs | 0,12 g and 0,20 g |
| Velocity | ~70 m/s (230 fps) with 0,20 g |
| Body | Polymer frame, metal slide |
| Colour | Black |
| Licence | Beretta (Italy) via Umarex |
| In the box | Replica, magazine, 100 BBs, manual |

---

## Description (use this HTML)

```html
<p>Pistolet airsoft Beretta Px4 Storm signé Umarex : réplique 6 mm à ressort, culasse métal, chargeur 12/100 billes et 100 billes fournies, moins de 0,5 joule.</p>
<p>Le Px4 Storm est le pistolet de service Beretta à carcasse polymère et culasse rotative. Cette version sous licence reprend la ligne au format réel (193 mm) avec une culasse métal, ce qui donne du poids à l'avant et un armement franc, plus proche de la sensation d'origine qu'une culasse plastique. La carcasse reste en polymère.</p>
<p>Le fonctionnement est à ressort : la culasse s'arme à la main avant chaque tir, sans gaz, sans CO2 et sans pile. Umarex simule aussi le double action. La sécurité se manœuvre au levier. Le hop-up est fixe, non réglable.</p>
<p>Le chargeur hi-cap alimente 12 billes au tir et contient un réservoir de 100 billes pour recharger sans démonter tout le magasin. Un sachet de 100 billes est dans la boîte. Billes de 0,12 g ou 0,20 g conseillées. Usage : initiation, manipulation et tir de loisir sur cible — pas une réplique de partie.</p>
```

---

## Characteristics and filters

```json
"characteristics": [
  { "label": "Type", "value": "Pistolet airsoft à ressort" },
  { "label": "Marque", "value": "Umarex" },
  { "label": "Modèle", "value": "Beretta Px4 Storm, sous licence officielle" },
  { "label": "Calibre", "value": "6 mm airsoft" },
  { "label": "Puissance", "value": "Moins de 0,5 joule" },
  { "label": "Vitesse", "value": "Environ 70 m/s (230 fps) en 0,20 g" },
  { "label": "Propulsion", "value": "Ressort, culasse à armer manuellement" },
  { "label": "Modes de tir", "value": "Coup par coup, double action simulé" },
  { "label": "Chargeur", "value": "Hi-cap, 12 billes + réservoir 100 billes" },
  { "label": "Hop-Up", "value": "Non réglable" },
  { "label": "Matière", "value": "Carcasse polymère, culasse métal" },
  { "label": "Couleur", "value": "Noir" },
  { "label": "Sécurité", "value": "Levier de sécurité" },
  { "label": "Longueur", "value": "193 mm" },
  { "label": "Poids de la réplique", "value": "478 g" },
  { "label": "Poids du colis", "value": "600 g" },
  { "label": "Billes conseillées", "value": "0,12 g et 0,20 g" },
  { "label": "Référence fabricant", "value": "Umarex 2.5198" },
  { "label": "Contenu", "value": "Réplique, chargeur 12/100 billes, sachet de 100 billes, notice" },
  { "label": "Usage conseillé", "value": "Initiation et entraînement à la manipulation" }
],
"filter_attributes": [
  { "label": "Calibre", "value": "6mm" },
  { "label": "Marque", "value": "Umarex" },
  { "label": "Propulsion", "value": "Ressort" },
  { "label": "Puissance", "value": "0,5 joule" },
  { "label": "Type", "value": "Pistolet" }
]
```

---

## Procedure (one request)

Base: `$HOST/api/admin` (local: `http://127.0.0.1:8003/api/admin`).

Headers on every call:

```
Authorization: Bearer $ADMIN_API_TOKEN
Accept: application/json
Content-Type: application/json
```

### 0. Collision check

```
GET /products?search=px4
GET /products?sku=REPLICA-UMAREX-PX4-STORM-SPRING-6MM
GET /products?gtin=4000844490483
```

If a Px4 Storm spring already exists, **stop**. Confirm `category_id` **55** is still `repliques-de-poing`.

### 1. Create

`POST /products`

```json
{
  "name": "Pistolet Beretta Px4 Storm Umarex Spring Culasse Métal 6 mm BB Airsoft",
  "description": "<p>Pistolet airsoft Beretta Px4 Storm signé Umarex : réplique 6 mm à ressort, culasse métal, chargeur 12/100 billes et 100 billes fournies, moins de 0,5 joule.</p><p>Le Px4 Storm est le pistolet de service Beretta à carcasse polymère et culasse rotative. Cette version sous licence reprend la ligne au format réel (193 mm) avec une culasse métal, ce qui donne du poids à l'avant et un armement franc, plus proche de la sensation d'origine qu'une culasse plastique. La carcasse reste en polymère.</p><p>Le fonctionnement est à ressort : la culasse s'arme à la main avant chaque tir, sans gaz, sans CO2 et sans pile. Umarex simule aussi le double action. La sécurité se manœuvre au levier. Le hop-up est fixe, non réglable.</p><p>Le chargeur hi-cap alimente 12 billes au tir et contient un réservoir de 100 billes pour recharger sans démonter tout le magasin. Un sachet de 100 billes est dans la boîte. Billes de 0,12 g ou 0,20 g conseillées. Usage : initiation, manipulation et tir de loisir sur cible — pas une réplique de partie.</p>",
  "category_id": 55,
  "price": 39.99,
  "quantity": 0,
  "sku": "REPLICA-UMAREX-PX4-STORM-SPRING-6MM",
  "gtin": "4000844490483",
  "weight_grams": 600,
  "carrier_ids": [1, 2, 3, 4],
  "is_active": true,
  "featured": false,
  "age_restricted": true,
  "characteristics": [
    { "label": "Type", "value": "Pistolet airsoft à ressort" },
    { "label": "Marque", "value": "Umarex" },
    { "label": "Modèle", "value": "Beretta Px4 Storm, sous licence officielle" },
    { "label": "Calibre", "value": "6 mm airsoft" },
    { "label": "Puissance", "value": "Moins de 0,5 joule" },
    { "label": "Vitesse", "value": "Environ 70 m/s (230 fps) en 0,20 g" },
    { "label": "Propulsion", "value": "Ressort, culasse à armer manuellement" },
    { "label": "Modes de tir", "value": "Coup par coup, double action simulé" },
    { "label": "Chargeur", "value": "Hi-cap, 12 billes + réservoir 100 billes" },
    { "label": "Hop-Up", "value": "Non réglable" },
    { "label": "Matière", "value": "Carcasse polymère, culasse métal" },
    { "label": "Couleur", "value": "Noir" },
    { "label": "Sécurité", "value": "Levier de sécurité" },
    { "label": "Longueur", "value": "193 mm" },
    { "label": "Poids de la réplique", "value": "478 g" },
    { "label": "Poids du colis", "value": "600 g" },
    { "label": "Billes conseillées", "value": "0,12 g et 0,20 g" },
    { "label": "Référence fabricant", "value": "Umarex 2.5198" },
    { "label": "Contenu", "value": "Réplique, chargeur 12/100 billes, sachet de 100 billes, notice" },
    { "label": "Usage conseillé", "value": "Initiation et entraînement à la manipulation" }
  ],
  "filter_attributes": [
    { "label": "Calibre", "value": "6mm" },
    { "label": "Marque", "value": "Umarex" },
    { "label": "Propulsion", "value": "Ressort" },
    { "label": "Puissance", "value": "0,5 joule" },
    { "label": "Type", "value": "Pistolet" }
  ],
  "supplier_id": 1,
  "available_at_supplier": true,
  "supplier_reference": "25198",
  "supplier_product_url": "https://www.dmdiffusion.com/beretta-px4-storm-noir-culasse-metal-spring-05j-c2x39086549",
  "supplier_price": 25.85
}
```

Expect **201**. Capture `.data.id`. Expect `.data.price_cents` = `3999`, `.data.age_restricted` = true, `.data.sku` and `.data.gtin` kept on the product (no variants), `.data.image` = `""`.

Do **not** send `variants`. Do **not** send `price_cents`.

On **422**, branch on `errors` keys, never on the French `message`. On **429**, honour `Retry-After`.

### 2. Verify

```
GET /products/{id}
```

Check:

- name, slug, `price_cents` 3999, `category.slug` `repliques-de-poing`
- `sku` `REPLICA-UMAREX-PX4-STORM-SPRING-6MM`, `gtin` `4000844490483`
- `weight_grams` 600, `age_restricted` true, `quantity` 0, `available_at_supplier` true
- `has_variants` false / empty variants
- `image` empty
- storefront product URL loads, 18+ notice, 39,99 €, breadcrumb Répliques de poing
- existing PPQ / PPK/S unchanged

Do not commit, do not write CHANGELOG, unless asked.

---

## Out of scope

- Downloading or converting DM / Umarex images
- Seeder / factory / test changes
- CO2 or GBB Px4 variants (different Umarex SKUs)

---

## If something is missing

| Gap | What to do |
|---|---|
| Token missing | Stop. Need `ADMIN_API_TOKEN`. |
| `category_id` 55 is not `repliques-de-poing` | Use the id whose slug is `repliques-de-poing`. |
| SKU/GTIN 422 uniqueness | Report the colliding code. Do not invent a new SKU without asking. |
| Want photos | Web admin upload after this API pass. |
