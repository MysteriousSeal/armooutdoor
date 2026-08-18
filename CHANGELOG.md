# Changelog

All notable changes to this project since the initial commit are documented here, newest first.

## 2026-08-19 — v0.1.67

### Storefront

- **French category URLs**: renamed 21 category slugs from English to French for SEO (e.g. `targets` → `cibles`, `apparel` → `vetements`, `ammo-boxes` → `boites-munitions`). Old URLs 301-redirect to the new ones, mapped in `config/category_slug_redirects.php`.

## 2026-08-19 — v0.1.66

### Admin

- **De-duplicated category icon mapping**: the root-category-to-icon lookup was copy-pasted in 4 different views; it's now a single `Category::iconName()` method.

## 2026-08-19 — v0.1.65

### Storefront

- **Unified category page header**: subcategory pages now use the same hero layout as root categories (icon, kicker, title, description, product count), with the kicker showing the parent category name instead of the generic tagline.

## 2026-08-19 — v0.1.64

### Storefront

- **Category icon in nav dropdowns**: each header submenu now shows the parent category's icon next to its subcategory list.
- **Multi-column nav dropdowns**: submenus with more than 6 subcategories now flow into 3 columns instead of one long list.

## 2026-08-19 — v0.1.63

### Admin

- **Fixed "quantité obligatoire" error on saving a variant product**: the quantity field was being fully disabled (not just read-only) once a product had variants, so browsers stopped submitting it and the required-field check failed. It's now read-only instead, and the server no longer requires it.

### Catalog

- **Added Mechanix M-Pact gloves (Noir)**: 1 product with 5 size variants (S–XXL) from DM Diffusion, filed under the existing Gants category.

## 2026-08-19 — v0.1.62

### Admin

- **Locked main product fields once variants exist**: SKU, GTIN, quantity and all Supplier fields on the product edit form are now disabled (and cleared) once a product has variants, since that data lives per-variant instead. Enforced server-side too, and cleaned up 15 existing variant products that still had stale values on the main product.

## 2026-08-19 — v0.1.61

### Admin

- **Prettier variant sub-table**: the products list now nests variants in a framed panel with one column per attribute present (e.g. Taille, Couleur), then separate SKU, GTIN, supplier and supplier-ref columns, plus stock chips for low/out and slightly faded inherited images.

## 2026-08-19 — v0.1.60

### Admin

- **Variant details in the products table**: products with variants now show a sub-row with a compact table listing each variant's image, attribute (e.g. "Taille: S"), SKU, GTIN, supplier, supplier reference, price and stock.

## 2026-08-19 — v0.1.59

### Store

- **Dynamic shipping delay on variant selection**: the "Délai d'expédition estimé" note on a product page now updates as you switch variants, showing/hiding and changing its day count based on the selected variant's own supplier.
- **Variant-aware product card badge**: category, home, search, wishlist and other product-card listings now show "Dispo fournisseur" when every variant is out of stock but at least one can still be backordered, instead of always showing "Épuisé".
- Renamed the product page's per-variant backorder chip label from "Sur commande" to "Dispo fournisseur".

## 2026-08-19 — v0.1.58

### Store

- **Supplier badge for out-of-stock variant products**: on a product page, when every variant is out of stock but at least one can still be backordered from a supplier, the price-area badge now shows "Disponible chez notre fournisseur" instead of "Épuisé".

## 2026-08-19 — v0.1.57

### Admin

- **Per-variant supplier fields**: each product variant now has its own supplier, availability, reference and product URL, independent from the parent product's supplier. Out-of-stock variants with a supplier can be backordered the same way non-variant products already could.

## 2026-08-19 — v0.1.56

### Catalog

- **Added Mechanix M-Pact gloves (Coyote)**: 1 product with 5 size variants (S–XXL) from DM Diffusion, new "Gants" subcategory under Vêtements.

## 2026-08-19 — v0.1.55

### Admin

- **Fixed squeezed product thumbnails**: long category and product names were forcing the products table wider, squeezing the thumbnail column off-square. Category names now hard-truncate to 20 characters and product names to 30, both with a full-text tooltip on hover.

## 2026-08-19 — v0.1.54

### Admin

- **Products list defaults to newest first**: default sort changed from ID ascending to ID descending, so newly added products show up immediately without changing the sort.

## 2026-08-19 — v0.1.53

### Storefront

- **Shorter stock chip text on the homepage's 5-per-row grids**: "Disponibilité fournisseur" and "Derniers stock disponibles" now show as "Dispo fournisseur" and "Derniers stocks" there, so the badge doesn't overflow the card.

## 2026-08-19 — v0.1.52

### Storefront

- **Product cards show supplier availability**: cards for out-of-stock, backorderable products now show a "Disponibilité fournisseur" chip, stay undimmed, and keep a working "Add to cart" button, everywhere the shared product card appears (homepage, search, category, wishlist).

## 2026-08-19 — v0.1.51

### Storefront

- **Live cart shipping estimate**: removing a line from the cart now updates the estimated shipping date in place instead of requiring a full reload.
- **Backordered cart lines**: the quantity field and "Update quantity" button are hidden for lines available at supplier (always capped at 1 anyway) — Remove stays available.

## 2026-08-19 — v0.1.50

### Storefront

- **Cart shipping estimate**: the cart page now shows an estimated shipping date at the top, computed live as "if you ordered right now" — same 10am/weekend rules as the order page, factoring in any backordered line's supplier lead time.

## 2026-08-19 — v0.1.49

### Storefront

- **Estimated shipping date**: the customer order page now shows an estimated shipping date (before 10am ships same day, otherwise next day; weekends push to Monday), accounting for backordered items' supplier lead time — whichever is latest wins. Backordered line items also show a note under the SKU. Not shown on the admin order page.

## 2026-08-18 — v0.1.48

### Admin

- **Fixed products list "ID" sort**: sort links used to omit `?sort=id-asc` from the URL as a cleanup, since it was always the default. That broke once the last-used sort started being remembered via cookie — omitting it meant "keep the remembered sort" instead of "sort by ID". Every sort link now always includes `sort` explicitly.

## 2026-08-19 — v0.1.47

### Admin

- **Supplier reference & product link**: the product edit page's Supplier section now has fields for the supplier's own product code and a link to the product on the supplier's website.
- **Order detail supplier note**: when an order item is out of stock but available at its supplier, the order page now shows a note with the supplier name and lead time.
- **New catalog product**: added Specna Arms BIO Core 0,20 g airsoft BBs (3000-count bottle), filed under the existing Billes airsoft subcategory.

## 2026-08-18 — v0.1.46

### Storefront

- **Cart line supplier availability**: a backordered cart line now shows "Disponible chez notre fournisseur" with the supplier's estimated lead time, next to the SKU.

## 2026-08-18 — v0.1.45

### Storefront

- **Backorder from supplier**: an out-of-stock product with a supplier assigned and "Available at supplier" checked can now be added to cart and ordered, capped at 1 unit, showing the supplier's estimated lead time with a Font Awesome hourglass icon.

### Admin

- **Fixed a 500 error** when sorting the products list by Supplier while also searching: the search filter's unqualified `name` column collided with the joined suppliers table's own `name` column. Now qualified as `products.name`.

## 2026-08-18 — v0.1.44

### Admin

- **Products list sorting**: Price and Supplier columns are now sortable (base price, supplier name with unassigned products always last), matching ID/Name/Stock. The last-used sort is remembered in a cookie and reapplied on your next visit, unless the URL explicitly specifies one.

## 2026-08-18 — v0.1.43

### Admin

- **Changelog page**: releases now sit on a vertical timeline rail instead of a plain stacked list.

## 2026-08-18 — v0.1.42

### Storefront

- **Category page redesign**: filters moved into a dedicated sidebar (radio buttons instead of dropdowns) on subcategory pages, with a reworked toolbar for subcategory navigation and sorting.

## 2026-08-18 — v0.1.41

### Storefront

- **Category filters restricted to subcategories**: the filter dropdowns no longer show on main category pages (those with subcategories), only on leaf subcategory pages. Filtering via URL still works either way.

## 2026-08-18 — v0.1.40

### Storefront

- **Fixed category filters with numeric-only values**: PHP silently casts array keys that look like integers (e.g. "3300"), which broke selecting any such filter option — it always reset to "Tous". Fixed in `CategoryController::availableFilterValues()`.
- **New Billes airsoft filters**: added Quantité, Contenant, Poids and Bio facets across all 4 products in that subcategory, via the Admin API.

## 2026-08-18 — v0.1.39

### Storefront

- **Category sort options**: added "Pertinence" (now the default, first in the list) and "Nouveautés" to every category page's sort dropdown. Pertinence currently orders the same as Nouveautés (newest first) until real relevance scoring is added.
- **New catalog product**: added Specna Arms BIO Core 0,20 g airsoft BBs (5000-count, 1 kg sachet), filed under the existing Billes airsoft subcategory.

## 2026-08-18 — v0.1.38

### Admin

- **Available at supplier**: new switch in the product edit page's Supplier section, tracking whether the supplier currently has the item in stock for reordering. Defaults to on.

## 2026-08-18 — v0.1.37

### Admin

- **Product supplier**: products can now be linked to a supplier (optional, one per product) via a dropdown on the product edit page, below Carriers.
- **Products list**: shows the supplier per product, with a filter dropdown and a Supplier column in the CSV export.

## 2026-08-18 — v0.1.36

### Admin

- **Suppliers settings page**: new page under Settings to manually track suppliers, with name, website and lead time (days to deliver once an order is placed).

## 2026-08-18 — v0.1.35

### Storefront

- **New "Promotions" and "Meilleures ventes" pages**: Promotions lists every product with an active discount; Meilleures ventes ranks products by units sold across placed/preparing/shipped orders. Both linked from the footer, listed in both sitemaps, with full SEO (title, meta description, canonical, breadcrumb and CollectionPage/ItemList structured data).
- **New catalog product**: added Specna Arms BIO Core 0,20 g airsoft BBs (1000-count sachet), filed under the existing Billes airsoft subcategory.

## 2026-08-18 — v0.1.34

### Storefront

- **Répliques airsoft icon**: the category now shows a Font Awesome gun icon on the homepage and the "Toutes les catégories" page, instead of the generic default icon.
- **Product card redesign**: the "add to cart" button now floats over the card corner instead of sitting in a fixed-width footer bar, with tighter card spacing.

## 2026-08-18 — v0.1.33

### Storefront

- **New "Nouveautés" page**: lists the latest products added to the catalog, linked from the footer, with full SEO (title, meta description, canonical, breadcrumb and CollectionPage/ItemList structured data) and listed in both sitemaps.

## 2026-08-18 — v0.1.32

### Storefront

- **New catalog products**: added a Browning 1911 Spring 6 mm BB airsoft pistol (with its full image gallery), in a new "Pistolets" subcategory under Répliques airsoft.
- **Subheader nav layout**: left-aligned instead of right-aligned, with bigger tap targets on category links and the dropdown menu.

## 2026-08-18 — v0.1.31

### Storefront

- **New catalog products**: added two ASG Blaster 6 mm airsoft BB bottles (0,12 g and 0,20 g), in a new "Billes airsoft" subcategory under Munitions et Consommables.
- **CSS cache busting**: every stylesheet link now carries `?v=<site version>`, so a version bump forces browsers to fetch the latest CSS instead of a stale cached copy.

## 2026-08-18 — v0.1.30

### Storefront

- **New "Toutes les catégories" page**: lists every category and subcategory with product counts, linked from the footer and the homepage's category section, with full SEO (title, meta description, canonical, breadcrumb and CollectionPage/ItemList structured data) and listed in both sitemaps.

## 2026-08-18 — v0.1.29

### Storefront

- **Paiement sécurisé page**: clarified that the invoice becomes available once the order ships or is refunded.

## 2026-08-18 — v0.1.28

### Storefront

- **New help pages**: added an FAQ page (with FAQPage structured data for SEO), a "Livraison & Retours" page and a "Paiement sécurisé" page, all reachable from the footer's "Aide & Infos" column and listed in both sitemaps.

## 2026-08-18 — v0.1.27

### Admin

- **Marketplace commission**: manual orders placed for a marketplace now have an editable commission field on the order detail page; when filled, it shows as a deduction under the total on the orders list.

### Storefront & Admin

- **Consistent name formatting**: customer and address names now always display with the last name in uppercase and the first name capitalized, everywhere a name is shown (orders, invoices, delivery slips, accounts, admin lists).

## 2026-08-18 — v0.1.26

### Storefront

- **HTML sitemap**: footer's "Plan du site" link now leads to a real page (`/plan-du-site`) listing every page, category/subcategory and active product, built dynamically from the same data as `sitemap.xml`.
- **Footer tidy-up**: added an "Aide & Infos" column, renamed "Compte" to "Mon compte" with direct links to orders/addresses/wishlist/profile, replaced "Boutique" with placeholder links (all categories, new arrivals, promotions, best sellers) for pages not built yet, and removed orphaned translation keys.

### Admin

- **Products list tabs**: "Out of stock", "Missing SKU", "Missing GTIN" and "Missing weight" no longer count or list disabled products; the "Disabled" tab moved to the end of the row.

## 2026-08-17 — v0.1.25

### Storefront

- **Homepage icons**: every hand-drawn icon (category tiles, reassurance strip, shipping banner, "Pourquoi choisir" and "Une boutique française" sections) is now a real Font Awesome Free icon, and the "Sélectionné pour vous" grid shows 10 products (5 per row) instead of 4.
- **Single icon partial**: all icons now render through one `partials.icon` component (a small registry of just the SVG paths actually used, not the full Font Awesome library); the old per-category `partials.category-icon` view is gone.

## 2026-08-17 — v0.1.24

### Housekeeping

- **Rewrote README.md**: full description of the shop (catalog, storefront features, admin back office, brand/design notes), not just the old dev-setup summary.

## 2026-08-17 — v0.1.23

### Housekeeping

- **Fixed the feature test suite**: `AuthTest`, `AccountTest`, `StorefrontTest`, and `ExampleTest` were still requesting `/fr`-prefixed URLs from before the app dropped locale-prefixed routing, and `AdminManualOrderVariantTest` still sent the removed `billing_same_as_shipping` flag instead of explicit billing fields. All 50 tests pass again.

## 2026-08-17 — v0.1.22

### Admin

- **Order filters**: the Orders list can now filter by status, marketplace, and a date range, combinable with search and the Orders/Drafts/Archived tabs.
- **CSV export**: an "Export CSV" button on the Orders, Products, and Customers lists downloads the currently filtered/searched rows.
- **Customer notes**: each customer profile has a free-text notes field for internal use ("called about a return", etc.), and the email is now a clickable mailto link.
- **Activity log**: a new Activity page records admin actions on orders (archive/unarchive), products (create/update/enable/disable/restock), and discounts/discount codes (create/update/delete), each linking back to the affected record.

## 2026-08-17 — v0.1.21

### Admin

- **Customer detail page**: clicking a customer now opens their profile — order history, addresses, and total spent, all excluding archived orders.
- **Global search**: a search box in the admin header looks up orders, customers, and products from one place, excluding archived orders.
- **Archive/unarchive from the order page**: same confirm popup as the orders list, now also available directly on an order's detail page, with an "Archived" badge next to its status.
- **Inline restock**: the dashboard's stock alerts list now has a quantity field and Save button per product instead of a read-only count.
- **Dashboard "External" stat**: now counts non-archived manual orders instead of external customer records, so archiving a manual order removes it from this count.

## 2026-08-17 — v0.1.20

### Orders

- **Manual order form's pickup point search**: a postal code field now sits above the relay/pickup point list, defaulting to the billing address postal code — editing it re-searches that postal code independently, without touching the billing address.

## 2026-08-17 — v0.1.19

### Orders

- **Archive orders**: new "Archived" tab on the admin orders list, with an archive/unarchive icon button per row (confirm popup before either action). Archived orders drop out of the Orders/Drafts tabs, the admin dashboard's counts, charts, and top-products/marketplace stats, and the customer's own order list — direct links to an archived order now 404 for the customer, same as a draft.

## 2026-08-17 — v0.1.18

### Admin

- **Changelog page**: this file is now browsable at Admin → Changelog, parsed into cards (version, date, category, bullets) newest first, with the latest release flagged and the version number in the admin header linking straight to it.

## 2026-08-17 — v0.1.17

### Orders

- **Manual order form (admin)**: billing address now comes before shipping in the form order, and the shipping address section moved below the carrier so it can be filled from a relay point (see below). The "Same as shipping address" checkbox is gone — billing is now always its own required form.
  - Billing address prefills from the customer's default saved address; shipping is left blank rather than guessed.
  - Shipping first/last name auto-fill from the billing name as it's typed or prefilled, until manually edited.
  - Selecting Mondial Relay or Chronopost Shop2Shop shows the 10 nearest pickup points (name and address only) for the billing postal code; clicking one fills its name and address into the shipping address fields.
  - "Save as draft" and "Create order"/"Finalize order" stay disabled until the customer, products, carrier, and both addresses are filled in.
  - Submitting with no marketplace selected now asks for confirmation ("Are you sure you don't want to select a marketplace?") before continuing.

## 2026-08-17 — v0.1.16

### Shipping & carriers

- **Checkout works fully without JavaScript**: relay-carrier selection now has a `<noscript>` fallback — a postal code field and "Rechercher" button trigger a real server-rendered relay point list (the picker still appears instantly on carrier selection too, via a CSS-only rule). The search reuses the main checkout form's fields so it always reflects whichever carrier is actually selected, rather than resubmitting a stale one.
- **Payment method locked until a relay point is picked**: for relay carriers, the payment cards stay visible but grayed out (and any prior selection is cleared) until a point is chosen, with a hint above them explaining why. "Valider la commande" is disabled until the whole form — address, carrier, relay point if needed, billing address, and payment method — is filled in.

## 2026-08-17 — v0.1.15

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

## 2026-08-16

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

## 2026-08-15

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

## 2026-08-14

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
