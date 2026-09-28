# Admin API: Carriers

List the carriers and their ids. One read-only endpoint, JSON out.

- **Base URL:** `https://<host>/api/admin`
- **Auth:** static bearer token, same token and rules as [products](products.md#authentication)
- **Content type:** send `Accept: application/json` on every request

Carrier ids differ between databases, so look a carrier up here by its `slug`
rather than reusing an id seen elsewhere. The id is what a draft order takes as
`carrier_id` (`POST /orders`, `PATCH /orders/{number}`).

---

## Endpoint

| Method | Path | Purpose | Success |
|---|---|---|---|
| `GET` | `/carriers` | Every carrier, in display order | `200` |

Every carrier is listed, inactive and manual-only ones included. Carriers are
created and priced in the web admin (Settings, Shipping); the API does not
change them.

---

## Fields

| Field | Notes |
|---|---|
| `id` | Numeric primary key: the value for an order's `carrier_id`. |
| `slug` | Stable identifier, the same in every database: `colissimo-home`, `chronopost-home`, `mondial-relay`, `relais-pickup`, `lettre-suivie`, `vinted-go`. |
| `name` | Display name. |
| `method` | `home` (delivered to the address) or `relay` (pickup point or locker). An order on a `relay` carrier carries a `relay` block (`name`, `line1`, `postal_code`, `city`); a draft may leave it empty, a placed order may not. |
| `price_cents` | Default price in cents, before weight tiers and free shipping. A draft order's `shipping_price` overrides it. |
| `active` | `false` means the carrier cannot be used at all, not even by an order. |
| `manual_only` | `true` means the carrier is never offered to customers at checkout, but manual orders and draft orders can use it. `vinted-go` is one. |

---

## Example

```
GET /api/admin/carriers
```

```json
{
  "data": [
    {"id": 1, "slug": "colissimo-home", "name": "Colissimo", "method": "home", "price_cents": 690, "active": true, "manual_only": false},
    {"id": 3, "slug": "mondial-relay", "name": "Mondial Relay", "method": "relay", "price_cents": 390, "active": true, "manual_only": false},
    {"id": 6, "slug": "vinted-go", "name": "Vinted Go", "method": "relay", "price_cents": 0, "active": true, "manual_only": true}
  ]
}
```

Ids above are illustrative: read them from your own database.

---

## Errors

| Status | When |
|---|---|
| `401` | Missing or wrong bearer token. |
