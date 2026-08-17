# Armo Outdoor

Armo Outdoor is a French-language online shop selling gear for the shooting range and the outdoors: reactive/paper targets, range equipment and gun care supplies, apparel, field/bushcraft gear, everyday carry accessories, and airgun consumables (pellets, steel BBs, CO2 cartridges). The tagline is "Du matériel discret pour le stand et le terrain" ("Understated gear for the range and the field"). The catalog is intentionally compact and curated rather than a sprawling marketplace — every product is meant to be genuinely useful on the range or in the field. The site is single-locale French (no language switcher) and prices are in euros.

It's a real, working e-commerce storefront (Laravel-based) with a full customer-facing shop and an internal admin back office for running the business day to day.

## Who it's for

Hunters, sport shooters, airgun/precision-shooting hobbyists, and outdoor/bushcraft enthusiasts in France who want no-nonsense equipment without a flashy "tactical" aesthetic — the brand tone is understated, practical, and trustworthy rather than aggressive or gimmicky.

## Product catalog

Six top-level categories, each with subcategories:

- **Cibles** (Targets) — cibles rondes, cibles carrées, planches (target sheets), cibles carton & métal
- **Stand** (Range) — boîtes à munitions, entretien de l'arme (gun care), poches & étuis, accessoires de silencieux et modérateurs de son, housses/fourreaux/mallettes, cartouchières de crosse, étuis à munitions, témoin de chambre vide
- **Vêtements** (Apparel) — casquettes, bonnets, cache-cou, ceintures, patches, polos, shorts, t-shirts, chapeaux, cagoules, écharpes et foulards
- **Terrain** (Field gear) — rubans camo, survival, stand, sacs, sangles, allume-feu, boussoles
- **Quotidien** (Everyday) — pastilles, bracelets, outils, tech, optiques
- **Munitions et Consommables** — plombs et billes d'acier, cartouches de CO2 12g et 88g

Products can have variants (size, color, etc.) with their own price and stock; some products carry an age-restriction notice (18+ sales, proof of age requested at checkout). Products can also be on sale via a direct discounted price shown throughout the site.

## Storefront experience

- **Homepage**: hero banner, trust/reassurance strip (delivery, secure payment, tracked shipping, French-language support), "shop by category" grid, a hand-picked "featured" product selection, a broader "more in the shop" grid, and SEO copy about the shop and each category.
- **Browsing**: category pages with subcategory filters and sorting (price asc/desc, etc.), full-text product search, breadcrumbs.
- **Product pages**: image gallery, variant picker, stock status, star ratings and customer reviews, wishlist button, related/other products, delivery and payment info blurbs.
- **Reviews**: customers who bought a product can leave a star rating and written review; average rating and review count show on product cards and pages.
- **Wishlist**: save products to a personal wishlist from anywhere in the catalog.
- **Cart**: quantity/variant management, live totals, discount code entry, an "add to cart" confirmation modal, and a low-stock warning modal when requesting more than what's available. Falls back gracefully to full-page reloads if JavaScript is unavailable.
- **Discount codes**: percentage or fixed-amount cart-wide coupon codes, optionally restricted to one customer, with an optional expiry date and usage limits.
- **Checkout**: choose a saved address or add a new one, pick a carrier — home delivery (Colissimo, Chronopost, Lettre suivie) or a pickup point (Mondial Relay or Chronopost Shop2Shop, with a live, map-free searchable list of real nearby pickup points), separate billing address, and pay by card or PayPal. Works end-to-end even without JavaScript.
- **Customer accounts**: registration/login/password reset, order history with live status (Placed → Preparing → Shipped, or Refunded) and tracking info, downloadable invoices, saved addresses, and the wishlist.
- **Dark mode** toggle, remembered across visits.
- **Legal pages**: CGV (terms), mentions légales, politique de confidentialité, droit de rétractation — all populated from admin-managed company settings.
- Fully responsive, mobile-first layouts throughout.

## Admin back office

A separate `/admin` area (its own login) for running the shop:

- **Dashboard**: revenue, order counts, average order value, refunds, customer counts, stock alerts, a 7-day sales chart, sales-by-marketplace breakdown, top products, and recent orders — with inline restock for low-stock items.
- **Orders**: full order list with search, status/marketplace/date filters, tabs for active/draft/archived orders, CSV export, and a detail page per order (status workflow, tracking, addresses, items, invoice/delivery-slip PDF downloads). Orders can also be created manually (phone/in-person sales), attributed to a marketplace (e.g. a sale made on another platform), and archived/unarchived without being deleted.
- **Products**: full CRUD with images, variants, pricing, stock, SKU/GTIN, category assignment, age-restriction flag, and CSV export.
- **Categories**: manage the two-level category tree.
- **Discounts**: per-product sale prices and cart-wide discount codes, each with their own active/scheduled/expired views.
- **Customers**: customer list and per-customer profile (order history, addresses, total spent, internal notes for staff, quick email link).
- **Settings**: shipping (free-shipping threshold, per-carrier pricing by weight tier, package types), company/legal info, invoice footer, marketplaces.
- **Search**: a single search box across orders, customers, and products.
- **Activity log**: an audit trail of admin actions (order archiving, product changes, discount changes) for accountability.
- **Changelog**: a browsable in-app history of what shipped, version by version.

## Brand/design notes

- Warm, neutral, understated color palette (taupe/cream, not tactical black-and-orange).
- Flat, modern, minimal visual style — no skeuomorphism, subtle borders over heavy shadows.
- French copy throughout; tone is plain, trustworthy, and practical rather than salesy.
- Emphasis on trust signals: French delivery options, secure payment, tracked shipping, and a real customer-support angle ("une boutique à l'écoute").

## Stack (for developers)

- Laravel 13, PHP 8.3, Blade templates, Eloquent ORM.
- SQLite by default for local development.
- Plain CSS (`public/css/app.css`, `public/css/admin.css`, `public/css/base.css`), no frontend build step required.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

Seeded admin login: `admin@armooutdoor.test` / `password`.
