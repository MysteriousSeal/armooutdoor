# Changelog

All notable changes to this project since the initial commit are documented here, newest first.

## 2026-08-20 — v0.5.0 — build IW7DUG

### Discounts

- **New discount code type: free relay-point delivery.** Waives the delivery charge when the customer checks out to a relay point. Unlike the percentage and fixed-amount codes it reduces the shipping line rather than the goods, so it has no amount to set.
- Refused when relay delivery is already free for that cart. Free shipping is configured per carrier, so this means free on *every* active relay carrier — if one still charges, the code still has something to do.
- Applying it before a carrier is chosen is fine: it shows "s'applique si vous choisissez un point relais" and only bites once a relay is selected. With home delivery it simply has no effect.
- Re-checked when the order is placed, in case the cart crossed the free-shipping threshold in the meantime. A code that would be worth nothing is dropped rather than consumed, so it stays usable for a later order.
- Recorded on the order as its own figure rather than folded into the goods discount, so the orders cost KPIs stay truthful.

### Under the hood

- `orders` gains `shipping_discount_cents`, and `discount_codes.value` becomes nullable — a free-delivery code has no amount, and a `0` would have read as "0% off". **Needs `php artisan migrate` on deploy.**

## 2026-08-20 — v0.4.2 — build L18919

### Admin

- New customers whose profile nobody has opened yet are now flagged: a badge on the Customers nav item, and a dot, "New" chip and tinted row on the list itself. Opening the profile clears it. Manual-order accounts stay out of it, since they never appear on that list.
- Right-aligned the View button on the conversations and activity tables, which were missing the wrapper every other admin table uses.

### Fixed

- **A customer replying to a closed conversation was shown the admin 403 page** — the back-office nav along with its shop-wide badge counts, including unread conversations across every customer. Replying to a closed thread no longer errors at all; it redirects back with an explanation. The 403 page now picks its chrome from who is asking, and the admin nav counts require an admin rather than merely a login.

## 2026-08-20 — v0.4.1 — build EUXF6Z

### Admin

- The Orders nav item now carries a badge counting orders nobody has started on yet — non-archived orders still at "placed". Narrower than the "to prepare" chip on the orders list, which also counts orders already being prepared.

## 2026-08-20 — v0.4.0 — build DUZR9H

### Messages

- **Contact messages are now two-way conversations.** They were flat rows an admin answered out of band with a `mailto:` link; they are now threads, answerable in the admin and readable and answerable by the customer in their account.
- Customer account gains "Mes messages": a thread list and thread view with a reply box, an unread badge in the account nav and on the account hub, and a count on the user's name in the site header.
- Admin gains a thread timeline with a reply box, Open/Closed tabs, and close/reopen — all recorded in the activity log.
- The customer is emailed when an admin replies. The email links to the thread and deliberately does not quote the reply, keeping thread content out of inboxes.
- An admin can correct their own reply for 30 minutes after sending it, inline and without a page reload. Both sides then see when it was edited.
- Admin replies are shown as coming from the shop, never from the staff member who wrote them; the authoring admin is still recorded for the audit trail.
- Guest messages stay one-way: there is no account behind them, so there is nowhere for a reply to be read. The admin gets the email fallback instead of a reply box.

### Under the hood

- `contact_messages` split into `conversations` + `conversation_messages`, with existing messages folded into a thread plus its opening message. **The migration drops `contact_messages` — take a database copy before deploying.**
- The reply notification needs real SMTP; `MAIL_MAILER` is still `log`. Everything else works without it.

## 2026-08-20 — v0.3.0 — build HGU8K1

### Storefront

- Contact form now validates and submits without reloading the page: fields are checked as you leave them, errors appear inline, and a toast confirms the send. Falls back to a normal form post if JavaScript is unavailable.
- Logged-in customers can attach one of their own orders to a message, so a question about an order arrives with the order already linked.
- Name and email are locked to the account's own values for logged-in customers — the fields are disabled, and the server ignores any submitted value regardless.
- Removed the phone number from the contact sidebar, dropped an em dash from the payment-canceled notice, and gave that notice room to breathe above the discount-code block.

### Admin

- Messages list redesigned: sender avatars, a message snippet under each subject, clearer unread rows, and stat cards for total/unread/last-7-days.
- Customer and order references on a message are now obviously clickable, and a guest message whose email matches a customer account shows a "possibly &lt;name&gt;" hint linking to that customer.
- Messages from an admin account show an "Admin" chip instead of a name link — those links pointed at the customer page, which 404s for an admin.

## 2026-08-20 — v0.2.9 — build D12L9R

### Admin

- Changelog entries now show a build number (random 6-character code) alongside the version, generated once and stored in `CHANGELOG.md` rather than recomputed on each page view.
- Backfilled build numbers onto all 79 existing changelog entries, and version numbers (v0.1.12–v0.1.14) onto the 3 oldest entries that predated versioning.

## 2026-08-20 — v0.2.8 — build BP0TL4

### Storefront

- Added a real Contact page: the nav/footer "Nous contacter" link was previously dead (`href="#"`). Open to guests and customers, with a honeypot and rate limiting; messages are stored for admins only, no emails sent.
- Checkout now shows a warning banner ("Le paiement a été annulé…") after a canceled or declined Stripe payment — previously the customer landed back on checkout with no explanation.

### Admin

- New "Messages" section (top-level nav, with an unread-count badge): lists and shows contact-form submissions.

## 2026-08-20 — v0.2.7 — build VPFYXH

### Admin

- Fixed a bootstrap lockout: `AdminSeeder` never set `role`, so a freshly seeded admin had `isOwner() === false` with no self-service fix (existing production admins were unaffected). The seeder now grants `owner`.
- The per-order Stripe metadata block (payment fee, payment intent ID, Stripe customer ID/links) is now owner-only, closing a gap where staff could still see it despite the RBAC rollout.
- "Deactivate admin" now asks for confirmation first, matching the rest of the admin's destructive actions.
- An owner demoting themselves to staff now gets a warning that they'll lose owner access immediately, and can still confirm.

## 2026-08-20 — v0.2.6 — build M713S7

### Admin

- Deactivated admins can now be reactivated (Settings → Admins), restoring access without recreating the account.
- Added owner/staff roles: staff can no longer refund orders, delete discounts or discount codes, view Stripe payment data, or manage admin accounts — owner-only.
- Activity log now also covers order status transitions (prepare/ship/refund), tracking and address edits, all settings pages, supplier/marketplace/package-type CRUD, and admin-user management — closing the gap where those actions left no trail.
- Deleting a discount, discount code, supplier, marketplace, or package type now asks for confirmation first, matching the existing order refund/archive/ship modals.
- Admins can edit a customer's name and email, and send them a password reset link, from the customer page — previously only a free-text notes field was editable.

## 2026-08-20 — v0.2.5 — build UYXMR7

### Admin

- Added admin-user management (Settings → Admins): create new admins, edit name/email/reset password, deactivate — no more direct DB access needed to onboard staff. Guards against deactivating yourself or the last remaining admin.
- Added 10 tests covering creation, validation, password reset, and deactivation guards; extended the route-authorization sweep to cover the new routes.

## 2026-08-19 — v0.2.4 — build SESHJG

### Orders

- Admin orders list: added a "Free delivery" column (Yes/No, based on charged shipping being zero).
- Admin orders list: row actions (delivery slip, invoice, archive) replaced with a single "Actions" dropdown, sized to match the other chips; removed the redundant "View" button since the order number already links to it.
- Admin marketplaces: added logo upload, auto-resized to 100×100px WebP; shown in the settings page and in the order list's Channel chip.
- Redesigned the Orders settings page as a card grid, matching the rest of the admin.

## 2026-08-19 — v0.2.3 — build VI990E

### Storefront

- Added a custom 500 page, matching the 404 page's design (shared `.error-page*` styles in `errors.css`), with "Réessayer" and "Retour à l'accueil" actions.
- Added a local-only debug route (`/debug/throw-500`) to preview the 500 page without needing a real server error.

## 2026-08-19 — v0.2.2 — build BIQ5LR

### Storefront

- Added a custom 404 page: search box, link back to the homepage, and a list of top-level categories, styled to match the shop and kept in its own `errors.css` stylesheet.
- Fixed a bug that crashed any 404 for an unresolved product or category route (`layouts.app` tried to read `->category` off the raw route parameter when implicit model binding failed).

## 2026-08-19 — v0.2.1 — build YVUJYG

### Orders

- Admin orders list: the cost KPI cards (own shipping cost, commission cost, payment fees) now show their share of both the total order amount and the total costs. Total costs and Total perceived show their share of the total amount too.
- Admin orders list: the Total amount KPI card is narrower, giving more room to the others.

## 2026-08-19 — v0.2.0 — build CVCH5V

### Payments

- **Stripe Checkout integration (sandbox/test mode)**: Carte bancaire now goes through a real Stripe Checkout session. The order is only created once payment is confirmed — via the success redirect, with a webhook (`checkout.session.completed`) as a backup — never at checkout submission, so no order exists for an unpaid cart.
- Reuses an existing Stripe Customer for a returning email instead of creating a new one on every order.
- New admin page — Settings → Stripe payments: lists every Stripe Checkout Session from the last 30 days (paid, pending, failed) with its matching order if any, KPIs (revenue, success rate, fees, orphaned payments), and status tabs.
- New admin diagnostic: orphaned-payment recovery (`StripeCheckoutFinalizer`/`StripePaymentController::finalize`) can recreate an order from a paid session that never produced one — kept out of the main listing page for now, available as a guarded fallback.
- Orders now store `stripe_checkout_session_id`, `stripe_payment_intent_id`, `stripe_customer_id`, and `payment_fee_cents` (generic column, ready for PayPal too) so the admin never needs to call Stripe just to render a page — a self-healing fallback fetches a missing fee once (a Stripe race condition can leave it briefly unset) and persists it.
- Admin order page: Payment card now shows the payment processor fee (amount + % of order total), payment intent id and Stripe customer id as linked chips into the Stripe dashboard; "Carte bancaire" reads as "Carte bancaire (Stripe)" in the admin only.

### Orders

- Admin orders list: new KPI row (total amount, own shipping cost, commission cost, payment fees, total costs, total perceived) and two new columns — "Various costs" and "Total perceived" (total minus commission, shipping paid, and payment fee).
- Admin orders list: Channel/Status/Tracking chips redesigned into one consistent component.
- Admin order page: moved Customer, Shipping address, Billing address and Payment into a single row above Status history.
- Admin order page: the order number is now clickable to copy it to the clipboard, with a confirmation toast.

## 2026-08-19 — v0.1.81 — build DTCE7N

### Orders

- Increased font sizes ~15% on the invoice and delivery slip PDFs for better readability.

## 2026-08-19 — v0.1.80 — build QBDU21

### Catalog

- Added an "image may vary" flag on products (admin checkbox, off by default) shown as a boxed notice on the product page when the delivered item's visual can differ from the pictures depending on supply.

## 2026-08-19 — v0.1.79 — build UHCIAK

### Storefront

- Widened the category-hero copy panel (`.cat-hero-copy`) so longer titles and accented text have more room before wrapping or clipping.

## 2026-08-19 — v0.1.78 — build Q54BK6

### Testing

- **Expanded the automated test suite from 53 to 206 tests**, covering password reset, wishlist, reviews, product/cart discounts and discount codes, storefront discovery (search, sitemaps, FAQ/help/legal, theme preference, 404s), a full admin-route authorization audit (every `admin.*` route rejects guests/non-admins), the admin JSON API, admin catalogue/order management, and unit tests for `Cart`, `ShippingEstimate`, `Csv`, `HtmlSanitizer`, and `SendcloudRelayClient`.
- Fixed 3 stale test assertions that no longer matched current app behaviour (uppercase-last-name formatting, products-list default sort).
- Fixed a pre-existing test (`AdminTest`) that was writing real image files to `public/images/products` on every run without cleaning up after itself; added the same safeguard to new tests that upload images.

## 2026-08-19 — v0.1.77 — build BW5OMX

### Storefront

- **Hero images for catalog listing pages**: added flat-icon hero banners to Meilleures ventes, Promotions and Nouveautés, matching the category page hero treatment.
- **Single-line title on Meilleures ventes hero**: added an opt-in `titleNoWrap` option to the shared page-hero partial, used only on that page; other heroes keep their existing wrapping.
- **Renamed hamburger menu label**: "Catégories" is now "Menu".

## 2026-08-19 — v0.1.76 — build TRPDDK

### Storefront

- **Shared page hero partial**: extracted a `partials.page-hero` component (kicker, title, description, tag chips) and adopted it on the all-categories, best-sellers, new-arrivals and promotions pages for a consistent header.

## 2026-08-19 — v0.1.75 — build RN106E

### Storefront

- **"Nous contacter" in the main nav**: added as the last item in the top nav bar, alongside Accueil, Nouveautés, Promotions and Meilleures ventes.

## 2026-08-19 — v0.1.74 — build 3E3VXX

### Storefront

- **Fixed account page order count**: it included archived orders; now only counts non-archived ones.

## 2026-08-19 — v0.1.73 — build 6NT0CR

### Admin

- **Tracking status "N/A"**: the orders list's tracking column now shows "N/A" for orders that have never been shipped, instead of "Missing" — "Missing" is reserved for orders that were shipped but have no tracking number. Orders with a tracking number always show "Available", regardless of status.

## 2026-08-19 — v0.1.72 — build 65W5DN

### Admin

- **Shipping paid field for manual orders**: marketplace-linked manual orders now have a "Shipping paid" field, next to Commission, to record what you actually paid for shipping. Shown as a deduction chip on the orders list too.

## 2026-08-19 — v0.1.71 — build L94I6T

### Storefront

- **Order history explanations**: each status in "Historique de la commande" now shows a short note explaining what it means.

## 2026-08-19 — v0.1.70 — build ZDFAI6

### Storefront

- **Category hero redesign**: photo now bleeds from the right edge and fades into the page background on the left, with the title/description in a blurred glass card over the fade — replaces the flat dark-scrim overlay. Added hero photos for Stand de tir, Vêtements, Terrain, Quotidien, Munitions and Répliques airsoft.

## 2026-08-19 — v0.1.69 — build ZY17P7

### Admin

- **Invoice footer toggle**: the invoice settings page now has a "Show footer on invoices" switch, so the footer line can be hidden on PDFs without losing the saved text and URL.

## 2026-08-19 — v0.1.68 — build ZNP5P0

### Admin

- **Category hero images**: category edit pages now have an optional hero image field. Uploads are center-cropped to 21:9 and converted to WebP automatically. Shown as a full-bleed banner behind the category page header on the storefront.

## 2026-08-19 — v0.1.67 — build 3URQCM

### Storefront

- **French category URLs**: renamed 21 category slugs from English to French for SEO (e.g. `targets` → `cibles`, `apparel` → `vetements`, `ammo-boxes` → `boites-munitions`). Old URLs 301-redirect to the new ones, mapped in `config/category_slug_redirects.php`.

## 2026-08-19 — v0.1.66 — build 22FBGS

### Admin

- **De-duplicated category icon mapping**: the root-category-to-icon lookup was copy-pasted in 4 different views; it's now a single `Category::iconName()` method.

## 2026-08-19 — v0.1.65 — build ACJXUI

### Storefront

- **Unified category page header**: subcategory pages now use the same hero layout as root categories (icon, kicker, title, description, product count), with the kicker showing the parent category name instead of the generic tagline.

## 2026-08-19 — v0.1.64 — build 5WUJ1Z

### Storefront

- **Category icon in nav dropdowns**: each header submenu now shows the parent category's icon next to its subcategory list.
- **Multi-column nav dropdowns**: submenus with more than 6 subcategories now flow into 3 columns instead of one long list.

## 2026-08-19 — v0.1.63 — build RT36PR

### Admin

- **Fixed "quantité obligatoire" error on saving a variant product**: the quantity field was being fully disabled (not just read-only) once a product had variants, so browsers stopped submitting it and the required-field check failed. It's now read-only instead, and the server no longer requires it.

### Catalog

- **Added Mechanix M-Pact gloves (Noir)**: 1 product with 5 size variants (S–XXL) from DM Diffusion, filed under the existing Gants category.

## 2026-08-19 — v0.1.62 — build Y41OEZ

### Admin

- **Locked main product fields once variants exist**: SKU, GTIN, quantity and all Supplier fields on the product edit form are now disabled (and cleared) once a product has variants, since that data lives per-variant instead. Enforced server-side too, and cleaned up 15 existing variant products that still had stale values on the main product.

## 2026-08-19 — v0.1.61 — build 2VZWCR

### Admin

- **Prettier variant sub-table**: the products list now nests variants in a framed panel with one column per attribute present (e.g. Taille, Couleur), then separate SKU, GTIN, supplier and supplier-ref columns, plus stock chips for low/out and slightly faded inherited images.

## 2026-08-19 — v0.1.60 — build QGM3XG

### Admin

- **Variant details in the products table**: products with variants now show a sub-row with a compact table listing each variant's image, attribute (e.g. "Taille: S"), SKU, GTIN, supplier, supplier reference, price and stock.

## 2026-08-19 — v0.1.59 — build IKF81Q

### Store

- **Dynamic shipping delay on variant selection**: the "Délai d'expédition estimé" note on a product page now updates as you switch variants, showing/hiding and changing its day count based on the selected variant's own supplier.
- **Variant-aware product card badge**: category, home, search, wishlist and other product-card listings now show "Dispo fournisseur" when every variant is out of stock but at least one can still be backordered, instead of always showing "Épuisé".
- Renamed the product page's per-variant backorder chip label from "Sur commande" to "Dispo fournisseur".

## 2026-08-19 — v0.1.58 — build 62AFWM

### Store

- **Supplier badge for out-of-stock variant products**: on a product page, when every variant is out of stock but at least one can still be backordered from a supplier, the price-area badge now shows "Disponible chez notre fournisseur" instead of "Épuisé".

## 2026-08-19 — v0.1.57 — build AMCCY9

### Admin

- **Per-variant supplier fields**: each product variant now has its own supplier, availability, reference and product URL, independent from the parent product's supplier. Out-of-stock variants with a supplier can be backordered the same way non-variant products already could.

## 2026-08-19 — v0.1.56 — build AIC983

### Catalog

- **Added Mechanix M-Pact gloves (Coyote)**: 1 product with 5 size variants (S–XXL) from DM Diffusion, new "Gants" subcategory under Vêtements.

## 2026-08-19 — v0.1.55 — build FK1DO1

### Admin

- **Fixed squeezed product thumbnails**: long category and product names were forcing the products table wider, squeezing the thumbnail column off-square. Category names now hard-truncate to 20 characters and product names to 30, both with a full-text tooltip on hover.

## 2026-08-19 — v0.1.54 — build 8Z8Y38

### Admin

- **Products list defaults to newest first**: default sort changed from ID ascending to ID descending, so newly added products show up immediately without changing the sort.

## 2026-08-19 — v0.1.53 — build UQOQ4W

### Storefront

- **Shorter stock chip text on the homepage's 5-per-row grids**: "Disponibilité fournisseur" and "Derniers stock disponibles" now show as "Dispo fournisseur" and "Derniers stocks" there, so the badge doesn't overflow the card.

## 2026-08-19 — v0.1.52 — build WPBAYY

### Storefront

- **Product cards show supplier availability**: cards for out-of-stock, backorderable products now show a "Disponibilité fournisseur" chip, stay undimmed, and keep a working "Add to cart" button, everywhere the shared product card appears (homepage, search, category, wishlist).

## 2026-08-19 — v0.1.51 — build Q2HODH

### Storefront

- **Live cart shipping estimate**: removing a line from the cart now updates the estimated shipping date in place instead of requiring a full reload.
- **Backordered cart lines**: the quantity field and "Update quantity" button are hidden for lines available at supplier (always capped at 1 anyway) — Remove stays available.

## 2026-08-19 — v0.1.50 — build JQ44SZ

### Storefront

- **Cart shipping estimate**: the cart page now shows an estimated shipping date at the top, computed live as "if you ordered right now" — same 10am/weekend rules as the order page, factoring in any backordered line's supplier lead time.

## 2026-08-19 — v0.1.49 — build NT41KD

### Storefront

- **Estimated shipping date**: the customer order page now shows an estimated shipping date (before 10am ships same day, otherwise next day; weekends push to Monday), accounting for backordered items' supplier lead time — whichever is latest wins. Backordered line items also show a note under the SKU. Not shown on the admin order page.

## 2026-08-18 — v0.1.48 — build RT57KY

### Admin

- **Fixed products list "ID" sort**: sort links used to omit `?sort=id-asc` from the URL as a cleanup, since it was always the default. That broke once the last-used sort started being remembered via cookie — omitting it meant "keep the remembered sort" instead of "sort by ID". Every sort link now always includes `sort` explicitly.

## 2026-08-19 — v0.1.47 — build E70YX1

### Admin

- **Supplier reference & product link**: the product edit page's Supplier section now has fields for the supplier's own product code and a link to the product on the supplier's website.
- **Order detail supplier note**: when an order item is out of stock but available at its supplier, the order page now shows a note with the supplier name and lead time.
- **New catalog product**: added Specna Arms BIO Core 0,20 g airsoft BBs (3000-count bottle), filed under the existing Billes airsoft subcategory.

## 2026-08-18 — v0.1.46 — build DW9XMZ

### Storefront

- **Cart line supplier availability**: a backordered cart line now shows "Disponible chez notre fournisseur" with the supplier's estimated lead time, next to the SKU.

## 2026-08-18 — v0.1.45 — build 84H1I4

### Storefront

- **Backorder from supplier**: an out-of-stock product with a supplier assigned and "Available at supplier" checked can now be added to cart and ordered, capped at 1 unit, showing the supplier's estimated lead time with a Font Awesome hourglass icon.

### Admin

- **Fixed a 500 error** when sorting the products list by Supplier while also searching: the search filter's unqualified `name` column collided with the joined suppliers table's own `name` column. Now qualified as `products.name`.

## 2026-08-18 — v0.1.44 — build AQ5L4Q

### Admin

- **Products list sorting**: Price and Supplier columns are now sortable (base price, supplier name with unassigned products always last), matching ID/Name/Stock. The last-used sort is remembered in a cookie and reapplied on your next visit, unless the URL explicitly specifies one.

## 2026-08-18 — v0.1.43 — build P5GY3K

### Admin

- **Changelog page**: releases now sit on a vertical timeline rail instead of a plain stacked list.

## 2026-08-18 — v0.1.42 — build 6O7XJO

### Storefront

- **Category page redesign**: filters moved into a dedicated sidebar (radio buttons instead of dropdowns) on subcategory pages, with a reworked toolbar for subcategory navigation and sorting.

## 2026-08-18 — v0.1.41 — build 9IL488

### Storefront

- **Category filters restricted to subcategories**: the filter dropdowns no longer show on main category pages (those with subcategories), only on leaf subcategory pages. Filtering via URL still works either way.

## 2026-08-18 — v0.1.40 — build HVC5CM

### Storefront

- **Fixed category filters with numeric-only values**: PHP silently casts array keys that look like integers (e.g. "3300"), which broke selecting any such filter option — it always reset to "Tous". Fixed in `CategoryController::availableFilterValues()`.
- **New Billes airsoft filters**: added Quantité, Contenant, Poids and Bio facets across all 4 products in that subcategory, via the Admin API.

## 2026-08-18 — v0.1.39 — build PM48CE

### Storefront

- **Category sort options**: added "Pertinence" (now the default, first in the list) and "Nouveautés" to every category page's sort dropdown. Pertinence currently orders the same as Nouveautés (newest first) until real relevance scoring is added.
- **New catalog product**: added Specna Arms BIO Core 0,20 g airsoft BBs (5000-count, 1 kg sachet), filed under the existing Billes airsoft subcategory.

## 2026-08-18 — v0.1.38 — build 6RWZDE

### Admin

- **Available at supplier**: new switch in the product edit page's Supplier section, tracking whether the supplier currently has the item in stock for reordering. Defaults to on.

## 2026-08-18 — v0.1.37 — build 9CIM2K

### Admin

- **Product supplier**: products can now be linked to a supplier (optional, one per product) via a dropdown on the product edit page, below Carriers.
- **Products list**: shows the supplier per product, with a filter dropdown and a Supplier column in the CSV export.

## 2026-08-18 — v0.1.36 — build 5FC3AM

### Admin

- **Suppliers settings page**: new page under Settings to manually track suppliers, with name, website and lead time (days to deliver once an order is placed).

## 2026-08-18 — v0.1.35 — build 8OC9TB

### Storefront

- **New "Promotions" and "Meilleures ventes" pages**: Promotions lists every product with an active discount; Meilleures ventes ranks products by units sold across placed/preparing/shipped orders. Both linked from the footer, listed in both sitemaps, with full SEO (title, meta description, canonical, breadcrumb and CollectionPage/ItemList structured data).
- **New catalog product**: added Specna Arms BIO Core 0,20 g airsoft BBs (1000-count sachet), filed under the existing Billes airsoft subcategory.

## 2026-08-18 — v0.1.34 — build 89X0PD

### Storefront

- **Répliques airsoft icon**: the category now shows a Font Awesome gun icon on the homepage and the "Toutes les catégories" page, instead of the generic default icon.
- **Product card redesign**: the "add to cart" button now floats over the card corner instead of sitting in a fixed-width footer bar, with tighter card spacing.

## 2026-08-18 — v0.1.33 — build 1JEW70

### Storefront

- **New "Nouveautés" page**: lists the latest products added to the catalog, linked from the footer, with full SEO (title, meta description, canonical, breadcrumb and CollectionPage/ItemList structured data) and listed in both sitemaps.

## 2026-08-18 — v0.1.32 — build PQUUOP

### Storefront

- **New catalog products**: added a Browning 1911 Spring 6 mm BB airsoft pistol (with its full image gallery), in a new "Pistolets" subcategory under Répliques airsoft.
- **Subheader nav layout**: left-aligned instead of right-aligned, with bigger tap targets on category links and the dropdown menu.

## 2026-08-18 — v0.1.31 — build KDHJWW

### Storefront

- **New catalog products**: added two ASG Blaster 6 mm airsoft BB bottles (0,12 g and 0,20 g), in a new "Billes airsoft" subcategory under Munitions et Consommables.
- **CSS cache busting**: every stylesheet link now carries `?v=<site version>`, so a version bump forces browsers to fetch the latest CSS instead of a stale cached copy.

## 2026-08-18 — v0.1.30 — build 67OFZV

### Storefront

- **New "Toutes les catégories" page**: lists every category and subcategory with product counts, linked from the footer and the homepage's category section, with full SEO (title, meta description, canonical, breadcrumb and CollectionPage/ItemList structured data) and listed in both sitemaps.

## 2026-08-18 — v0.1.29 — build 9TPXT2

### Storefront

- **Paiement sécurisé page**: clarified that the invoice becomes available once the order ships or is refunded.

## 2026-08-18 — v0.1.28 — build PTB1WQ

### Storefront

- **New help pages**: added an FAQ page (with FAQPage structured data for SEO), a "Livraison & Retours" page and a "Paiement sécurisé" page, all reachable from the footer's "Aide & Infos" column and listed in both sitemaps.

## 2026-08-18 — v0.1.27 — build DQFDTI

### Admin

- **Marketplace commission**: manual orders placed for a marketplace now have an editable commission field on the order detail page; when filled, it shows as a deduction under the total on the orders list.

### Storefront & Admin

- **Consistent name formatting**: customer and address names now always display with the last name in uppercase and the first name capitalized, everywhere a name is shown (orders, invoices, delivery slips, accounts, admin lists).

## 2026-08-18 — v0.1.26 — build VLFZLH

### Storefront

- **HTML sitemap**: footer's "Plan du site" link now leads to a real page (`/plan-du-site`) listing every page, category/subcategory and active product, built dynamically from the same data as `sitemap.xml`.
- **Footer tidy-up**: added an "Aide & Infos" column, renamed "Compte" to "Mon compte" with direct links to orders/addresses/wishlist/profile, replaced "Boutique" with placeholder links (all categories, new arrivals, promotions, best sellers) for pages not built yet, and removed orphaned translation keys.

### Admin

- **Products list tabs**: "Out of stock", "Missing SKU", "Missing GTIN" and "Missing weight" no longer count or list disabled products; the "Disabled" tab moved to the end of the row.

## 2026-08-17 — v0.1.25 — build J4JW87

### Storefront

- **Homepage icons**: every hand-drawn icon (category tiles, reassurance strip, shipping banner, "Pourquoi choisir" and "Une boutique française" sections) is now a real Font Awesome Free icon, and the "Sélectionné pour vous" grid shows 10 products (5 per row) instead of 4.
- **Single icon partial**: all icons now render through one `partials.icon` component (a small registry of just the SVG paths actually used, not the full Font Awesome library); the old per-category `partials.category-icon` view is gone.

## 2026-08-17 — v0.1.24 — build CHMFE6

### Housekeeping

- **Rewrote README.md**: full description of the shop (catalog, storefront features, admin back office, brand/design notes), not just the old dev-setup summary.

## 2026-08-17 — v0.1.23 — build G0DCAL

### Housekeeping

- **Fixed the feature test suite**: `AuthTest`, `AccountTest`, `StorefrontTest`, and `ExampleTest` were still requesting `/fr`-prefixed URLs from before the app dropped locale-prefixed routing, and `AdminManualOrderVariantTest` still sent the removed `billing_same_as_shipping` flag instead of explicit billing fields. All 50 tests pass again.

## 2026-08-17 — v0.1.22 — build D8PXP9

### Admin

- **Order filters**: the Orders list can now filter by status, marketplace, and a date range, combinable with search and the Orders/Drafts/Archived tabs.
- **CSV export**: an "Export CSV" button on the Orders, Products, and Customers lists downloads the currently filtered/searched rows.
- **Customer notes**: each customer profile has a free-text notes field for internal use ("called about a return", etc.), and the email is now a clickable mailto link.
- **Activity log**: a new Activity page records admin actions on orders (archive/unarchive), products (create/update/enable/disable/restock), and discounts/discount codes (create/update/delete), each linking back to the affected record.

## 2026-08-17 — v0.1.21 — build MI1GT4

### Admin

- **Customer detail page**: clicking a customer now opens their profile — order history, addresses, and total spent, all excluding archived orders.
- **Global search**: a search box in the admin header looks up orders, customers, and products from one place, excluding archived orders.
- **Archive/unarchive from the order page**: same confirm popup as the orders list, now also available directly on an order's detail page, with an "Archived" badge next to its status.
- **Inline restock**: the dashboard's stock alerts list now has a quantity field and Save button per product instead of a read-only count.
- **Dashboard "External" stat**: now counts non-archived manual orders instead of external customer records, so archiving a manual order removes it from this count.

## 2026-08-17 — v0.1.20 — build BFATZP

### Orders

- **Manual order form's pickup point search**: a postal code field now sits above the relay/pickup point list, defaulting to the billing address postal code — editing it re-searches that postal code independently, without touching the billing address.

## 2026-08-17 — v0.1.19 — build 1U14DN

### Orders

- **Archive orders**: new "Archived" tab on the admin orders list, with an archive/unarchive icon button per row (confirm popup before either action). Archived orders drop out of the Orders/Drafts tabs, the admin dashboard's counts, charts, and top-products/marketplace stats, and the customer's own order list — direct links to an archived order now 404 for the customer, same as a draft.

## 2026-08-17 — v0.1.18 — build CSO6PS

### Admin

- **Changelog page**: this file is now browsable at Admin → Changelog, parsed into cards (version, date, category, bullets) newest first, with the latest release flagged and the version number in the admin header linking straight to it.

## 2026-08-17 — v0.1.17 — build VEK7H8

### Orders

- **Manual order form (admin)**: billing address now comes before shipping in the form order, and the shipping address section moved below the carrier so it can be filled from a relay point (see below). The "Same as shipping address" checkbox is gone — billing is now always its own required form.
  - Billing address prefills from the customer's default saved address; shipping is left blank rather than guessed.
  - Shipping first/last name auto-fill from the billing name as it's typed or prefilled, until manually edited.
  - Selecting Mondial Relay or Chronopost Shop2Shop shows the 10 nearest pickup points (name and address only) for the billing postal code; clicking one fills its name and address into the shipping address fields.
  - "Save as draft" and "Create order"/"Finalize order" stay disabled until the customer, products, carrier, and both addresses are filled in.
  - Submitting with no marketplace selected now asks for confirmation ("Are you sure you don't want to select a marketplace?") before continuing.

## 2026-08-17 — v0.1.16 — build GGD6QV

### Shipping & carriers

- **Checkout works fully without JavaScript**: relay-carrier selection now has a `<noscript>` fallback — a postal code field and "Rechercher" button trigger a real server-rendered relay point list (the picker still appears instantly on carrier selection too, via a CSS-only rule). The search reuses the main checkout form's fields so it always reflects whichever carrier is actually selected, rather than resubmitting a stale one.
- **Payment method locked until a relay point is picked**: for relay carriers, the payment cards stay visible but grayed out (and any prior selection is cleared) until a point is chosen, with a hint above them explaining why. "Valider la commande" is disabled until the whole form — address, carrier, relay point if needed, billing address, and payment method — is filled in.

## 2026-08-17 — v0.1.15 — build 1TTC1W

### Storefront

- **Mobile responsive layout**: the header now collapses to a grid (logo/cart/menu row, then a full-width search row) with the tagline hidden below 640px; the product detail page, category filters, and sort controls also get tighter mobile spacing and full-width stacking.
- **Website version**: now shown in the footer next to the copyright line (`shop.version` config, starting at 0.1.14).

### Shipping & carriers

- **Live relay/pickup points for Mondial Relay and Chronopost Shop2Shop**: checkout fetches real pickup points instead of a static seeded list, via Sendcloud's Service Points API (one account, both carrier networks — no separate Mondial Relay or Chronopost webservice integration needed). Points are searched by proximity (15 km radius around the postal code, not a strict postal-code-boundary match) so sparse/rural postal codes that used to return zero or one point now return a proper nearby list; results are capped at 40, sorted nearest-first, exact-postcode matches first. Cached locally so order snapshots keep working unchanged.
  - Only fetched once the customer actually selects a relay carrier (not on page load, which was adding significant load time) and re-fetched when they switch address. Mondial Relay and Chronopost Shop2Shop are fetched independently — selecting one never triggers a lookup for the other.
  - The list shows 10 points at a time with an "Afficher plus" button for more, 2 per row on desktop. Selecting a point collapses the search/list down to just that point's details, with a "Changer de point relais" button to go back.
  - Point titles and addresses always render uppercase.
- **Postcode/city autocomplete**: a new "Rechercher par ville ou code postal" search on the relay picker suggests matches as you type, backed by a new `GET /checkout/postal-codes` endpoint over a downloaded official French postcode dataset kept server-side (never shipped to the client).
- **Relay point opening hours**: now shown, condensed so consecutive days with the same hours collapse into one line ("Lun-Ven 09:00-19:00") instead of repeating per day.

## 2026-08-16 — v0.1.14 — build 47YJLZ

### Discounts

- **Discount codes**: new "Discount codes" tab on the admin Discounts page, alongside the existing per-product discounts (now labeled "Product discounts"). Admin CRUD for cart-wide coupon codes:
  - Percentage or fixed-amount off, applied to the whole cart total rather than a single product.
  - Optional customer restriction — leave blank for any customer, or pick one via the same searchable picker used for manual orders.
  - Optional quantity limit — leave blank for unlimited uses.
  - "Generate" button on the code field fills in a random 8-character code (uppercase letters/digits, excluding 0/O and 1/I/L to avoid ambiguity), checking it against existing codes via a new `GET /admin/discount-codes/check-code` endpoint before accepting it.
  - "Max uses per customer": a second, independent usage cap alongside the total quantity available — validated (client-side and server-side, `lte:quantity`) so it can never exceed the total quantity when one is set.
  - Discount codes list: an ID column, the code links straight to its edit page, and a dedicated copy-to-clipboard icon button next to it (falls back from the Clipboard API to `execCommand`, then shows an error toast if both fail) — with a small bottom-center toast confirming "Copied "CODE" to clipboard."
  - **Deadline**: an optional expiry date/time, defaulting to 30 days from creation at 23:59. Shown on the edit form's live preview and as an "Expires…"/"Expired…" status on the codes list; clear the field for no deadline.
- **Discount code redemption at checkout**: new "Code de réduction" section on the checkout page, before the address section. Entering a code validates it (exists, not expired, customer restriction, quantity remaining, per-customer usage limit) and applies it to the cart total, with the grand total updating live as the carrier is picked. On order placement the code is re-validated under a row lock, its quantity decremented if limited, and snapshotted onto the order so it stays accurate even if the code is later edited or deleted — shown on the customer and admin order pages, and as a "Réduction" line (amount only, no code) on the invoice PDF.
- **Discount codes list**: sorted by ID descending, and split into Active/Expired/No usage remaining sub-tabs (like the product discounts tab). A code restricted to one customer now also counts as "no usage remaining" once that customer has hit their own per-customer cap, even if the shared quantity pool isn't exhausted.
- **Admin customer picker**: the searchable customer field (manual orders, discount codes) now renders each match as a two-line name/email row instead of plain "Name (email)" text.
- **Manual order discount**: the manual order create/edit form has a new "Discount" section — percentage or fixed euro amount off the subtotal, no discount code created. Shown as a generic "Discount"/"Réduction" line on the order pages since there's no code.

### Catalog

- **Product age restriction**: new "Vente libre aux plus de 18 ans" checkbox on the admin product form (also settable via the Admin API). When checked, the product page shows an amber notice that sales are restricted to adults, plus a note that proof of age will be requested at checkout.
- **Product page**: the "À domicile"/"Point relais" delivery lines now list only the carriers actually allowed for that product, and hide the line entirely if none apply.

### Orders

- **Order delivery slip**: new "Download delivery slip" PDF on the admin order page (`ds-<order-number>.pdf`), next to the invoice download — shipping address, tracking, and items with quantities, no prices. Available for any non-draft order. The invoice PDF filename is now `inv-<order-number>.pdf`. Both PDFs show each line's variant in its own column ("-" when there isn't one).
- **Order item pricing layout**: on both the customer and admin order detail pages, quantity and unit price are now grouped with the line total instead of a plain meta line, and the discount badge no longer duplicates the before/after price already shown next to the total.

### Storefront

- **Cart shipping estimate**: the cart summary now shows "À partir de X €" using the lowest active carrier price for the cart's contents (respecting per-product carrier restrictions and weight tiers), falling back to the free-shipping badge whenever that lowest price is 0.
- **Add-to-cart modal**: adding a product from a card (category, home, search) or the product page no longer reloads the page — a modal shows the product (image, name, variant, price/discount, quantity added) with "Continuer les achats" and "Voir le panier" actions, and the header cart badge updates live. A separate modal explains when stock is too limited to fulfill the request. Falls back to a normal page reload if the request fails or JS is unavailable.
- **Product cards for products with variants** now show a "Voir les options" link to the product page instead of a quick-add button that silently failed (there's no variant picker on the card) — fixes a "nothing happens when I click add to cart" bug on those products.
- **Cart quantity/removal**: updating a line's quantity or removing it is now real-time too — line total, unit price, subtotal, item counts, shipping estimate, and the cart badge all update in place, with a success toast. The "too many requested" warning moved from the browser's native tooltip to the same toast. Both fall back to a normal page reload when JS is unavailable.
- **Checkout discount codes** are now validated in real time too: applying or removing a code swaps the section in place, updates the order summary's discount row and grand total live, and shows a toast for every outcome — no page reload, falls back to a normal form submit when the request fails or JS is unavailable.
- **Toasts** (cart, checkout): bottom-center, color-coded left border for success/error, longer 4.5s display, and a `status` ARIA role.
- **Customer address country**: the account address book and checkout's new-address form now only offer France (client and server-side). Admin manual order addresses are unaffected and still offer all shop countries.

### Admin

- **Admin products list**: numbered pagination with clickable page links, a bold ID column, and disabled Previous/Next buttons that are now actually inert (not just visually dimmed) on the first/last page.

### Admin API

- New `GET /api/admin/products/{product}` route returning a single product's full details (category, images, variants), `GET /api/admin/products` for a paginated list (50 per page), and `GET /api/admin/categories` returning every category with its products' IDs and names.

### Housekeeping

- **routes/web.php**: reorganized into commented sections (Sitemap, Admin — with sub-comments per resource, Storefront, Cart, Customer auth, Customer account) and grouped/alphabetized the `use` imports. No route path, method, name, or middleware changed — verified byte-identical route definitions before and after.

## 2026-08-15 — v0.1.13 — build QZA3NF

### Shipping & carriers

- **Per-product carrier restrictions**: each product's edit page has a new "Carriers" section (below Price and stock) to uncheck which carriers can ship it. If any product in a cart restricts a carrier, that carrier is hidden at checkout for the whole order — and if that empties out the "À domicile" or "Point relais" group entirely, that section is hidden too instead of showing empty.
- **New carrier**: "Lettre suivie", 3,50 €, home delivery.
- **New Settings → Carriers page**: edit every carrier's shipping price in one batch update.

### Catalog

- **Product weight**: products (not variants) can have a weight in grams, editable on the product form.

### Variants & stock

- **Variant display on cart, checkout, and order pages**: the variant is now shown as its own labeled pill instead of being crammed into the SKU line ("Label · SKU xxx"), and the cart/checkout line thumbnail shows the variant's own image when it has one instead of always the parent product's.
- **Variant picker redesign**: the product page's variant selector is now a chip grid with a thumbnail per variant (when it has one), swapping the main product image on selection and showing the current pick's label next to the "Variantes" heading. Per-variant price is only shown when variants are actually priced differently.
- **Stock badge now reflects total product stock**: the "En stock"/"Rupture de stock" badge next to the price used to follow whichever variant was selected, so picking a sold-out variant made a product with plenty of other stock look unavailable. It now always reflects the product's overall stock (the sum across all variants), while the add-to-cart controls (quantity stepper, submit button) still correctly disable when the *selected* variant specifically is out of stock.
- **Product stock accuracy**:
  - `Product.quantity` is now reconciled immediately after a variant sale (checkout and admin manual orders), fixing a bug where the admin products list, dashboard low-stock widget, and product edit page could show a stale quantity until the product was resaved.
  - Removing a product's last remaining variant now resets its quantity to 0 instead of leaving it frozen at the old variant sum, which previously made a product with zero variants look like it still had stock.
  - Fixed the variant delete button in the admin product form: it silently hid the row without disabling submission of a stale hidden field, making deletion look broken. Now shows a "marked for deletion" state with an undo option.
  - Deleting a variant no longer erases its SKU/label from past orders — `order_items` snapshots them at purchase time, same as the product SKU.
- **Variant SKU display fixes**: the variant label was replacing the SKU instead of showing alongside it on the cart, checkout, and order pages; the invoice PDF's SKU column only ever looked at the parent product, which is null once a product has variants. Now shows "Label · SKU xxx" everywhere and reads the SKU from the variant when one is selected.

### Admin

- **Admin products list**: long titles now truncate with an ellipsis; added Variants and Weight columns (em-dash when a product has neither). Also now shows SKU and GTIN per product.
- **Admin product form redesign**: two-column panel layout (Images/Product/Characteristics on the left, Description/Price and stock on the right), and variants are now cards with a live title (from the attributes field), an inline photo preview, and an empty-state message when a product has none yet.

### Invoicing

- **Invoice footer link**: new Settings → Invoice page for an optional footer text + URL, shown on every invoice PDF under the VAT mention.

## 2026-08-14 — v0.1.12 — build Z6VTMW

### Variants

- **Product variant selection**: shoppers can now actually pick a variant (size, color, etc.) instead of just seeing them listed — the product page renders active variants as selectable cards inside the add-to-cart form, with price/stock/quantity updating live as the selection changes; a variant is required before adding to cart when the product has any.
  - **Cart**: `cart_items` gained `product_variant_id`; guest (session) and signed-in (DB) carts both key by product + variant, so two variants of the same product form separate lines with their own price and stock limit.
  - **Checkout & orders**: `order_items` gained `product_variant_id`/`variant_label`; placing an order decrements the chosen variant's stock (not the parent product's), and the variant is shown on the cart, checkout summary, order confirmation, admin order detail, and invoice PDF.
  - **Admin manual order creation**: the product line-item picker now exposes a variant dropdown, validated and priced the same way as the storefront, with the same stock decrement behavior.

### Manual orders

- **Draft manual orders**: manual orders can now be saved as a draft (still fully validated, stock untouched) and edited completely — customer, items, carrier, marketplace, both addresses — via a new edit page, until explicitly finalized into a real order. The admin orders list is now split into "Orders" and "Drafts" tabs with a count on each; drafts are hidden from the customer's own order pages and excluded from dashboard/order-count revenue aggregates.
- **Manual order creation**: email is now optional for a new external customer. `users.email` made nullable (unique constraint still allows multiple customers with no email); admin order pages no longer show a dangling separator or blank line when a customer has none.
- **Manual order creation for admin** (`/admin/orders/create`):
  - Pick an existing customer or create a lightweight "external" one inline (hidden from the customers list and dashboard count).
  - Add products with quantities and optional per-line price overrides, a required carrier (priced via the normal free-shipping rules, with an optional shipping price override), and shipping/billing addresses. Payment method is fixed to card.
  - Stock is decremented in a locked transaction, matching the regular checkout flow.
  - Customer and product fields use a searchable autocomplete widget; picking an existing customer can reuse one of their saved addresses.

### Marketplaces

- **Marketplaces**: each marketplace can now have an invoice note, printed in the invoice's Notes section (snapshotted onto the order, so it survives the marketplace being renamed or deleted). Orders placed via a marketplace also get a "Paiement sur \<marketplace\>" line above that note.
- **Admin orders list**: added a "Channel" column showing which marketplace an order came from (or "Manuelle" for a manual order with none), as a chip, shown only for manually created orders. Backfilled via a new `orders.is_manual` flag distinguishing manual orders from regular checkout orders.
- **Marketplaces**: new admin-managed list (Settings → Orders) of external marketplaces the shop also sells on, seeded with NaturaBuy, CDiscount, Ebay, LeBonCoin, Vinted.
  - Manual order creation now has an optional marketplace selector; the chosen name is snapshotted onto the order so it stays accurate even if the marketplace is later renamed or deleted.
  - Shown in its own "Marketplace" section on the admin order detail page, placed above the Shipping section.

### Orders

- **Breadcrumbs**: order detail page now links through Accueil → Votre compte → Commandes instead of jumping straight to the order.
- **Customer order pages**:
  - Added a "Télécharger la facture" button on the order detail page and a matching download icon on each card in the orders list, both shown only once an order is past *placed*/*preparing*.
  - New `orders.invoice` route, ownership-checked, reusing the same PDF as admin.
  - Refunded orders now show the refunded amount under the order total.
  - The hero message under the order number now reflects the actual status (placed, preparing, shipped, refunded) instead of one static sentence for every order.
- **Refunded order status**: new terminal status, selectable from any point in the order lifecycle (placed/preparing/shipped) via its own confirmation modal; styled consistently across badges and the status history timeline in both themes.
- **Order shipping/billing address editing**: admins can now edit an order's addresses from a modal while the order is still *placed* or *preparing*. Editing only overwrites the order's own address snapshot — the customer's saved address book is never touched. Editability is allowlist-based so future statuses are locked out by default.
- **Package types**: new admin-managed list (Settings → Shipping) of shipping package types, selectable when adding tracking to an order. The chosen name is snapshotted onto the order so it stays accurate even if the package type is later renamed or deleted; also shown on the customer-facing tracking section.

### Customers

- **Customers now have a first and last name** instead of a single "name" field:
  - `users.first_name`/`users.last_name` (NOT NULL), `name` column dropped; existing users backfilled by splitting the old name, with a fake surname generated for single-word names.
  - `User::name` kept as a computed accessor so the many places that just *display* a name (order facts, header greeting, admin lists) needed no changes.
  - Registration, account profile editing, and the manual-order "new external customer" form all collect first/last name separately; admin customer/order search now matches against both fields.

### Admin

- **Admin UI polish**: restyled the admin nav bar (brand mark, section links moved to their own row) and the manual order creation page (sections now use the same card style as the rest of the admin).
- **Admin dashboard**: replaced the three bare counters with real sales and stock data — net/refunded revenue, average order value, a 7-day revenue chart, recent orders, top-selling products, orders by status, sales by channel, and low-stock alerts.
- **Admin orders list**: added quick stats (orders to prepare, missing tracking) and a per-row invoice download icon, consolidated into the actions column next to "View".

### Admin API

- **Token-authenticated admin API** (`/api/admin/...`, bearer token via `ADMIN_API_TOKEN`):
  - `PATCH /products/{id}` — partial update of a product's core fields.
  - `POST /orders` and `PATCH /orders/{id}` — create/update a draft order with the same validation as the admin form; always saves as a draft regardless of what's sent, and 404s if the target order isn't a draft.

### Invoicing

- **Invoice PDF**:
  - Rebuilt as a real Blade template rendered with `barryvdh/laravel-dompdf` (company header, order/invoice/date meta, shipping & billing addresses, itemized table with product thumbnails, shipping notes, totals) — replaces the original hand-rolled placeholder PDF.
  - Fixed page margins (top/bottom/left/right) via body padding after `@page` margin proved unreliable in dompdf.
  - Refunded orders: Total TTC is zeroed and a "Remboursement" line (negative of subtotal + shipping) is added.
  - VAT footer mention set to the CIBS article reference.

### Storefront

- **Legal pages**: extracted a shared header/nav partial across the four legal pages (CGV, mentions légales, confidentialité, droit de rétractation) and switched mentions légales to a definition-list layout for company facts.

### Housekeeping

- **README**: replaced the default Laravel boilerplate with real project documentation covering storefront/admin features, stack, and local setup steps.
