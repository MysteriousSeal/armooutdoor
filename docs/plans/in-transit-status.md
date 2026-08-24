# Implementation Plan — Add an "In transit" order status

Written against `main` @ `9c80d9a` (v0.14.0), from the code rather than from memory.

## Context

Order status is a plain `varchar` on `orders.status` with **no enum, no cast, and no database constraint**. The six values in use — `draft`, `placed`, `preparing`, `shipped`, `delivered`, `refunded` — are string literals spread across roughly twenty places, and they double as CSS class suffixes (`badge-shipped`, `is-shipped`, `order-chip--shipped`).

That matters more than the feature itself: **nothing fails loudly when a new value appears.** An order sitting on an unknown status simply drops out of every `whereIn` that doesn't list it, and renders with no badge colour. The bulk of this plan is finding those places, not adding the status.

## Locked decisions

| Decision | Choice |
|---|---|
| Stored value | `in_transit` |
| Entered by | A button on the order page, like every other step |
| Position | **Mandatory**: `shipped → in_transit → delivered` |
| Customer-visible | Yes — label, banner and timeline note in French |
| Treated as `shipped` for | Review eligibility · best sellers · missing-tracking alert |
| Existing 20 `shipped` orders | Left alone — no data migration |
| Dashboard pipeline | Grows to 5 stages, with a re-validated colour ramp |

### French copy (approved)

| Key | Text |
|---|---|
| `order_status_in_transit` | En transit |
| `order_thanks_in_transit` | Votre colis est en cours d'acheminement. |
| `order_status_note_in_transit` | Votre colis voyage vers l'adresse de livraison. |

---

## 1. The transition

**`routes/web.php`** — beside the other transitions, not owner-gated (matching `prepare` / `ship` / `deliver`):

```php
Route::patch('/orders/{order}/in-transit', [AdminOrderController::class, 'markInTransit'])->name('orders.in-transit');
```

**`app/Http/Controllers/Admin/OrderController.php`** — a sibling of `ship()` (~line 597), same shape: `abort_if($order->isDraft(), 404)`, `markStatus('in_transit')`, an `AdminActivityLog::record('order.in_transit', …)` line, and `statusChangeResponse()`.

**`resources/views/admin/orders/show.blade.php`** — the action chain at **lines 38–44** is an `@if / @elseif` on the current status. Insert the new step so it reads:

```
placed     → "Mark as being prepared"
preparing  → "Mark as shipped"
shipped    → "Mark as in transit"     ← new
in_transit → "Mark as delivered"      ← moved off `shipped`
```

Add an `in-transit-confirm-modal` next to the others in `#order-modals` (~line 570), and **change the existing `@if ($order->status === 'shipped')` guard on the deliver modal at line 583** to `in_transit`. Miss that one and the Delivered modal renders for the wrong status.

> `admin-order-status.js` swaps `order-heading`, `order-actions`, `order-downloads`, `order-timeline` and `order-modals` after a status change. All five already cover the new button and modal — no JS change.

---

## 2. Every place that enumerates statuses

This is the part that breaks quietly. Each row is a real line that changes behaviour the moment an order lands on `in_transit`.

### Must include `in_transit` — omitting it loses data

| File | Line | What it is | Why |
|---|---|---|---|
| `app/Http/Controllers/Admin/OrderController.php` | 131 | `'statuses' => [...]` (KPI list) | Missing → no KPI chip for the status |
| `app/Http/Controllers/Admin/OrderController.php` | 221 | status filter allowlist | Missing → `?status=in_transit` silently ignored, filter unusable |
| `app/Http/Controllers/BestSellersController.php` | 17 | `whereIn('orders.status', [...])` | **Missing → in-transit orders vanish from best sellers** |
| `app/Models/Product.php` | 285 | `whereIn('status', ['shipped','delivered'])` | **Missing → a customer loses the right to review while the parcel is in transit** |
| `app/Services/DashboardMetrics.php` | 274 | pipeline `whereIn` | Missing → order absent from the pipeline |
| `app/Services/DashboardMetrics.php` | 282 | pipeline stage labels | Add `'in_transit' => 'In transit'` between shipped and delivered |

### Must **also** match `in_transit` — currently keyed to `shipped` alone

| File | Line | What it is | Decision |
|---|---|---|---|
| `app/Services/DashboardMetrics.php` | 302 | missing-tracking alert: `where('status','shipped')` | → `whereIn(['shipped','in_transit'])` |
| `app/Services/DashboardMetrics.php` | 325 | that alert's link target | Link still points at `?status=shipped`; decide whether it should carry both |
| `app/Http/Controllers/Admin/OrderController.php` | 110 | `shipped_count` KPI | Decide: separate `in_transit_count`, or fold into shipped |
| `app/Http/Controllers/Admin/OrderController.php` | 115 | missing-tracking KPI, `where('status','shipped')` | Mirror line 302 |

### Correct as-is — do not touch

| File | Line | Why it's fine |
|---|---|---|
| `app/Models/Order.php` | 175 | `addressIsEditable()` is an **allowlist** `['placed','preparing']`. Its own comment says future statuses should be locked out by default. `in_transit` is correctly excluded. |
| `app/Models/Order.php` | 378 | `hasBeenShipped()` reads the **history**, and an in-transit order passed through `shipped` first. Still true. |
| `app/Models/Order.php` | 430, 436 | Both invoice checks are **denylists** of early statuses. `in_transit` is neither, so invoices stay available. |
| `resources/views/admin/orders/index.blade.php` | 328, 330 | `order-chip--shipped` here styles the **Free delivery** cell, not a status. Leave it. |

---

## 3. Customer-facing

**`lang/fr/store.php`** — add the three approved strings near their neighbours (lines ~310–327).

> While in here: **`order_status_note_delivered` does not exist**, so a delivered order already renders an empty timeline note. Pre-existing, unrelated, out of scope — flagged so it isn't mistaken for fallout from this change.

`resources/views/orders/index.blade.php` and `orders/show.blade.php` build their classes and keys from `$order->status` dynamically, so they need **no edit** — they light up once the strings and CSS exist.

One guard to check by hand: `orders/show.blade.php` **line 141** hides the "tracking will appear here" hint for `['placed','preparing']`. An in-transit order has a tracking number, so the hint is already hidden. No change.

---

## 4. CSS

Five selectors are keyed by status suffix. `in_transit` carries an underscore, which is valid in a CSS class.

| File | Line | Selector to add |
|---|---|---|
| `public/css/base.css` | 1240 | `.badge-in_transit` |
| `public/css/base.css` | 1469, 1527 | `.order-timeline-item.is-in_transit` + its dark variant |
| `public/css/app.css` | 3869, 3887 | `.order-status.is-in_transit` + its dark variant |
| `public/css/admin.css` | 3246 | `.order-chip--in_transit` |

Use the **blue** already carrying "in motion" for `shipped` (`#2f5d8a` light / `#8ab4dd` dark), one step along, so shipped and in-transit read as neighbours rather than unrelated states.

---

## 5. Dashboard pipeline — the colour ramp must be re-validated

The pipeline is an **ordinal** scale (ordered stages), so it uses one hue light→dark, not the categorical palette. Going from 4 stages to 5 means a new ramp: the existing four steps do not subdivide.

**Already generated and validated** against this project's real surfaces:

```css
:root {
    --chart-stage-1: #86b6ef;  /* Placed     */
    --chart-stage-2: #5f98e4;  /* Preparing  */
    --chart-stage-3: #3d7ccf;  /* Shipped    */
    --chart-stage-4: #2661a8;  /* In transit */
    --chart-stage-5: #17457f;  /* Delivered  */
}

[data-theme='dark'] {
    --chart-stage-1: #bcd8f8;
    --chart-stage-2: #8fb9f0;
    --chart-stage-3: #6398e8;
    --chart-stage-4: #3f77c9;
    --chart-stage-5: #2a5896;
}
```

Both sets pass all four ordinal checks — monotone lightness, ΔL ≥ 0.06 between steps, light end clearing the surface, single hue. Verify rather than trust:

```bash
node scripts/validate_palette.js "#86b6ef,#5f98e4,#3d7ccf,#2661a8,#17457f" --surface "#ffffff" --mode light --ordinal
node scripts/validate_palette.js "#bcd8f8,#8fb9f0,#6398e8,#3f77c9,#2a5896" --surface "#262422" --mode dark --ordinal
```

> My first attempt failed: a 5-step ramp starting at `#a9cbf3` put the light end at 1.68:1 against white, under the 2:1 floor. The whole ramp had to shift darker. Don't eyeball a replacement — run the script.

Add `.dash-stage-5 { --swatch-color: var(--chart-stage-5); }` in `admin.css` beside stages 1–4.

---

## 6. Tests

**Sixteen test files reference `'shipped'`.** Most build fixtures and are unaffected, but run the suite early — a `whereIn` you missed usually shows up as an existing test failing, which is the cheapest way to find it.

New file `tests/Feature/Admin/OrderInTransitStatusTest.php`:

- A shipped order shows "Mark as in transit" and **not** "Mark as delivered" (the mandatory ordering)
- An in-transit order shows "Mark as delivered"
- The transition writes the status **and** a status-history row
- A customer sees "En transit" and its timeline note on their own order
- An in-transit order **can still be reviewed** — the regression guard for `Product.php:285`
- An in-transit order **still counts** in best sellers — guard for `BestSellersController:17`
- An in-transit order with no tracking number **still raises** the missing-tracking alert
- `?status=in_transit` filters the admin list
- The dashboard pipeline lists five stages and places In transit between Shipped and Delivered

Extend `tests/Feature/Admin/AdminOrderLifecycleTest.php` to walk the full chain placed → preparing → shipped → in_transit → delivered and assert the history reads in that order.

> `AdminAuthorizationTest` walks the live route registry, so the new route is covered automatically — its `order` binding already exists.

**Verify each new test bites** by reverting the line it guards; the `whereIn` ones are exactly the silent failures this plan exists to prevent.

```bash
php artisan test --filter=InTransit
php artisan test
vendor/bin/pint --dirty
```

---

## 7. Before calling it done

Render it and look, in both themes:

- The action button chain advances correctly at each step, and Delivered is **not** reachable from Shipped
- The new badge, chip and timeline entry are coloured — an unstyled status is the tell that a CSS selector was missed
- The customer order page shows the French label, banner and note
- The dashboard pipeline shows five segments with the 2px surface gaps intact, in light and dark

Then walk it once by hand: take a real order from Placed through to Delivered and confirm each screen — admin list, admin detail, customer detail, dashboard — agrees on where it is.

## Out of scope

- Migrating the 20 existing `shipped` orders — decided against
- Any carrier API integration; there is none in the project today, and the status is entered by hand
- Email or notification on status change; the project sends none for status today
- `order_status_note_delivered`, missing before this change
