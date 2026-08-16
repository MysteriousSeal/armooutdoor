# Changelog

All notable changes to this project since the initial commit are documented here, newest first.

## 2026-08-16

- **Admin API**: new `GET /api/admin/products/{product}` route returning a single product's full details (category, images, variants), `GET /api/admin/products` for a paginated list (50 per page), and `GET /api/admin/categories` returning every category with its products' IDs and names.
- **Admin products list**: numbered pagination with clickable page links, a bold ID column, and disabled Previous/Next buttons that are now actually inert (not just visually dimmed) on the first/last page.
- **Product page**: the "À domicile"/"Point relais" delivery lines now list only the carriers actually allowed for that product, and hide the line entirely if none apply.

## 2026-08-15

- **Per-product carrier restrictions**: each product's edit page has a new "Carriers" section (below Price and stock) to uncheck which carriers can ship it. If any product in a cart restricts a carrier, that carrier is hidden at checkout for the whole order — and if that empties out the "À domicile" or "Point relais" group entirely, that section is hidden too instead of showing empty.
- **New carrier**: "Lettre suivie", 3,50 €, home delivery.
- **Product weight**: products (not variants) can have a weight in grams, editable on the product form.
- **New Settings → Carriers page**: edit every carrier's shipping price in one batch update.
- **Admin products list**: long titles now truncate with an ellipsis; added Variants and Weight columns (em-dash when a product has neither).
- **Admin product form redesign**: two-column panel layout (Images/Product/Characteristics on the left, Description/Price and stock on the right), and variants are now cards with a live title (from the attributes field), an inline photo preview, and an empty-state message when a product has none yet.
- **Invoice footer link**: new Settings → Invoice page for an optional footer text + URL, shown on every invoice PDF under the VAT mention.
- **Variant display on cart, checkout, and order pages**: the variant is now shown as its own labeled pill instead of being crammed into the SKU line ("Label · SKU xxx"), and the cart/checkout line thumbnail shows the variant's own image when it has one instead of always the parent product's.
- **Variant picker redesign**: the product page's variant selector is now a chip grid with a thumbnail per variant (when it has one), swapping the main product image on selection and showing the current pick's label next to the "Variantes" heading. Per-variant price is only shown when variants are actually priced differently.
- **Stock badge now reflects total product stock**: the "En stock"/"Rupture de stock" badge next to the price used to follow whichever variant was selected, so picking a sold-out variant made a product with plenty of other stock look unavailable. It now always reflects the product's overall stock (the sum across all variants), while the add-to-cart controls (quantity stepper, submit button) still correctly disable when the *selected* variant specifically is out of stock.
- **Product stock accuracy**:
  - `Product.quantity` is now reconciled immediately after a variant sale (checkout and admin manual orders), fixing a bug where the admin products list, dashboard low-stock widget, and product edit page could show a stale quantity until the product was resaved.
  - Removing a product's last remaining variant now resets its quantity to 0 instead of leaving it frozen at the old variant sum, which previously made a product with zero variants look like it still had stock.
  - Fixed the variant delete button in the admin product form: it silently hid the row without disabling submission of a stale hidden field, making deletion look broken. Now shows a "marked for deletion" state with an undo option.
  - Deleting a variant no longer erases its SKU/label from past orders — `order_items` snapshots them at purchase time, same as the product SKU.
- **Variant SKU display fixes**: the variant label was replacing the SKU instead of showing alongside it on the cart, checkout, and order pages; the invoice PDF's SKU column only ever looked at the parent product, which is null once a product has variants. Now shows "Label · SKU xxx" everywhere and reads the SKU from the variant when one is selected.
- **Admin products list**: now shows SKU and GTIN per product.

## 2026-08-14 (8)

- **Product variant selection**: shoppers can now actually pick a variant (size, color, etc.) instead of just seeing them listed — the product page renders active variants as selectable cards inside the add-to-cart form, with price/stock/quantity updating live as the selection changes; a variant is required before adding to cart when the product has any.
  - **Cart**: `cart_items` gained `product_variant_id`; guest (session) and signed-in (DB) carts both key by product + variant, so two variants of the same product form separate lines with their own price and stock limit.
  - **Checkout & orders**: `order_items` gained `product_variant_id`/`variant_label`; placing an order decrements the chosen variant's stock (not the parent product's), and the variant is shown on the cart, checkout summary, order confirmation, admin order detail, and invoice PDF.
  - **Admin manual order creation**: the product line-item picker now exposes a variant dropdown, validated and priced the same way as the storefront, with the same stock decrement behavior.

## 2026-08-14 (7)

- **Draft manual orders**: manual orders can now be saved as a draft (still fully validated, stock untouched) and edited completely — customer, items, carrier, marketplace, both addresses — via a new edit page, until explicitly finalized into a real order. The admin orders list is now split into "Orders" and "Drafts" tabs with a count on each; drafts are hidden from the customer's own order pages and excluded from dashboard/order-count revenue aggregates.
- **Token-authenticated admin API** (`/api/admin/...`, bearer token via `ADMIN_API_TOKEN`):
  - `PATCH /products/{id}` — partial update of a product's core fields.
  - `POST /orders` and `PATCH /orders/{id}` — create/update a draft order with the same validation as the admin form; always saves as a draft regardless of what's sent, and 404s if the target order isn't a draft.

## 2026-08-14 (6)

- **Admin UI polish**: restyled the admin nav bar (brand mark, section links moved to their own row) and the manual order creation page (sections now use the same card style as the rest of the admin).

## 2026-08-14 (5)

- **Marketplaces**: each marketplace can now have an invoice note, printed in the invoice's Notes section (snapshotted onto the order, so it survives the marketplace being renamed or deleted). Orders placed via a marketplace also get a "Paiement sur \<marketplace\>" line above that note.
- **Admin dashboard**: replaced the three bare counters with real sales and stock data — net/refunded revenue, average order value, a 7-day revenue chart, recent orders, top-selling products, orders by status, sales by channel, and low-stock alerts.
- **Admin orders list**: added a "Channel" column showing which marketplace an order came from (or "Manuelle" for a manual order with none), as a chip, shown only for manually created orders. Backfilled via a new `orders.is_manual` flag distinguishing manual orders from regular checkout orders.

## 2026-08-14 (4)

- **Marketplaces**: new admin-managed list (Settings → Orders) of external marketplaces the shop also sells on, seeded with NaturaBuy, CDiscount, Ebay, LeBonCoin, Vinted.
  - Manual order creation now has an optional marketplace selector; the chosen name is snapshotted onto the order so it stays accurate even if the marketplace is later renamed or deleted.
  - Shown in its own "Marketplace" section on the admin order detail page, placed above the Shipping section.

## 2026-08-14 (3)

- **Manual order creation**: email is now optional for a new external customer. `users.email` made nullable (unique constraint still allows multiple customers with no email); admin order pages no longer show a dangling separator or blank line when a customer has none.

## 2026-08-14 (2)

- **Customers now have a first and last name** instead of a single "name" field:
  - `users.first_name`/`users.last_name` (NOT NULL), `name` column dropped; existing users backfilled by splitting the old name, with a fake surname generated for single-word names.
  - `User::name` kept as a computed accessor so the many places that just *display* a name (order facts, header greeting, admin lists) needed no changes.
  - Registration, account profile editing, and the manual-order "new external customer" form all collect first/last name separately; admin customer/order search now matches against both fields.
- **Manual order creation for admin** (`/admin/orders/create`):
  - Pick an existing customer or create a lightweight "external" one inline (hidden from the customers list and dashboard count).
  - Add products with quantities and optional per-line price overrides, a required carrier (priced via the normal free-shipping rules, with an optional shipping price override), and shipping/billing addresses. Payment method is fixed to card.
  - Stock is decremented in a locked transaction, matching the regular checkout flow.
  - Customer and product fields use a searchable autocomplete widget; picking an existing customer can reuse one of their saved addresses.

## 2026-08-14

- **Breadcrumbs**: order detail page now links through Accueil → Votre compte → Commandes instead of jumping straight to the order.
- **Customer order pages**:
  - Added a "Télécharger la facture" button on the order detail page and a matching download icon on each card in the orders list, both shown only once an order is past *placed*/*preparing*.
  - New `orders.invoice` route, ownership-checked, reusing the same PDF as admin.
  - Refunded orders now show the refunded amount under the order total.
  - The hero message under the order number now reflects the actual status (placed, preparing, shipped, refunded) instead of one static sentence for every order.
- **Admin orders list**: added quick stats (orders to prepare, missing tracking) and a per-row invoice download icon, consolidated into the actions column next to "View".
- **Invoice PDF**:
  - Rebuilt as a real Blade template rendered with `barryvdh/laravel-dompdf` (company header, order/invoice/date meta, shipping & billing addresses, itemized table with product thumbnails, shipping notes, totals) — replaces the original hand-rolled placeholder PDF.
  - Fixed page margins (top/bottom/left/right) via body padding after `@page` margin proved unreliable in dompdf.
  - Refunded orders: Total TTC is zeroed and a "Remboursement" line (negative of subtotal + shipping) is added.
  - VAT footer mention set to the CIBS article reference.
- **Refunded order status**: new terminal status, selectable from any point in the order lifecycle (placed/preparing/shipped) via its own confirmation modal; styled consistently across badges and the status history timeline in both themes.
- **Order shipping/billing address editing**: admins can now edit an order's addresses from a modal while the order is still *placed* or *preparing*. Editing only overwrites the order's own address snapshot — the customer's saved address book is never touched. Editability is allowlist-based so future statuses are locked out by default.
- **Package types**: new admin-managed list (Settings → Shipping) of shipping package types, selectable when adding tracking to an order. The chosen name is snapshotted onto the order so it stays accurate even if the package type is later renamed or deleted; also shown on the customer-facing tracking section.
- **Legal pages**: extracted a shared header/nav partial across the four legal pages (CGV, mentions légales, confidentialité, droit de rétractation) and switched mentions légales to a definition-list layout for company facts.
- **README**: replaced the default Laravel boilerplate with real project documentation covering storefront/admin features, stack, and local setup steps.
