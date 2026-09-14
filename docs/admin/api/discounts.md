# Admin API: Discounts

Read, create and edit **product discounts**: a sale price on one product, no code needed. These are the discounts the web admin lists under `/admin/discounts?tab=products`. Discount codes are not covered.

- **Base URL:** `https://<host>/api/admin`
- **Auth:** static bearer token, same token and rules as [products](products.md#authentication)
- **Content type:** `application/json` (send `Accept: application/json` on every request)

---

## Endpoints

| Method | Path | Purpose | Success |
|---|---|---|---|
| `GET` | `/discounts` | List discounts, optionally filtered | `200` |
| `POST` | `/discounts` | Create a discount | `201` |
| `GET` | `/discounts/products` | Every product with stock and average paid price | `200` |
| `GET` | `/discounts/{id}` | Fetch one discount | `200` |
| `PATCH` | `/discounts/{id}` | Partially update a discount | `200` |

There is no `DELETE`: removing a discount stays an owner action in the web admin. To stop a discount now, `PATCH` its `ends_at` to the current time.

`{id}` is the discount's own id, not the product id. Find it with `GET /discounts?product_id=<product id>`.

### List filters

| Query | Notes |
|---|---|
| `status` | `active`, `scheduled` or `expired`. Anything else is a `422`. Omit it for all discounts. |
| `product_id` | Only the discount on that product (at most one). |

The list is not paginated.

### Products, stock and average paid price

`GET /discounts/products` returns every product, active or not, ordered by id, not paginated. It gives what you need to price a discount without going below cost:

```json
{
  "data": [
    {
      "id": 215, "name": "…", "sku": "…", "is_active": true,
      "price_cents": 390,
      "quantity": 4,
      "average_paid_incl_vat_cents": 205,
      "received_units": 6,
      "discount_id": null
    }
  ]
}
```

- `quantity` is the available stock, the "Available quantity" field of the product edit page. On a product with variants it is the sum of the variants' stock.
- `average_paid_incl_vat_cents` is the "Average paid, incl. VAT" figure of the product edit page: every received purchase order line, VAT and shared charges included, weighted by units received. Lines ordered but never received do not count. `null` when nothing has been received.
- `received_units` is the "from N units received" count that average is drawn from.
- `discount_id` is the product's current discount, whatever its status, or `null`. Use it with `PATCH /discounts/{id}` instead of creating a second one.

---

## Fields

| Field | Notes |
|---|---|
| `product_id` | Required on create. An existing product id. **A product has at most one discount**: a second one is a `422`. |
| `type` | Required on create. `percentage` or `fixed`. |
| `value` | Required on create. Decimal, greater than 0. For `percentage`, a percent up to `100` (rounded to a whole number). For `fixed`, an amount **in euros** up to `99999.99` (stored as cents). |
| `starts_at` | Optional, nullable date (ISO 8601 recommended). `null` means the discount applies from now. |
| `ends_at` | Optional, nullable date, on or after `starts_at`. `null` means no end date. |

On `PATCH`, absent fields keep their value: send only what changes.

---

## The discount object

```json
{
  "data": {
    "id": 7,
    "product_id": 50,
    "type": "fixed",
    "value": 12.5,
    "label": "-12,50 €",
    "status": "active",
    "starts_at": null,
    "ends_at": "2026-10-01T00:00:00+02:00",
    "product": {
      "id": 50, "name": "…", "sku": "LAB-BLK-013-1000",
      "price_cents": 5000, "discounted_price_cents": 3750
    },
    "created_at": "…",
    "updated_at": "…"
  }
}
```

- `value` comes back in the unit it was written in: a percent, or euros.
- `status` is computed from the dates at request time: `scheduled` before `starts_at`, `expired` after `ends_at`, `active` otherwise.
- `product.price_cents` and `discounted_price_cents` are the product's base price. A variant carrying its own price is sold at that price with no discount, so these two fields do not describe it.
- `GET /discounts` returns an array of these objects under `data`.

---

## Examples

Create a 20 % discount running until the end of the month:

```
POST /api/admin/discounts
{"product_id": 50, "type": "percentage", "value": 20, "ends_at": "2026-09-30T23:59:00+02:00"}
```

Switch it to 10 € off:

```
PATCH /api/admin/discounts/7
{"type": "fixed", "value": 10}
```

Stop it now:

```
PATCH /api/admin/discounts/7
{"ends_at": "2026-09-14T12:00:00+02:00"}
```

---

## Rules that will bite you

- **Changing `type` requires sending `value` in the same request** (`422` otherwise). The unit changes with the type, so the old number would mean something else.
- **Product ids differ between local and production.** Resolve the product with `GET /products?sku=…` on the target host before creating a discount.
- The end date is checked against the start date the discount will have after the save, so a `PATCH` sending only `ends_at` can still fail against the stored `starts_at`.
- Every create and edit is written to the admin activity log, marked `(API)`.
