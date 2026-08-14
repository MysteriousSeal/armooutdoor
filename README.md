# Armo Outdoor

Armo Outdoor is a French-only e-commerce storefront for outdoor and shooting-range gear (targets, range equipment, apparel, field gear, everyday accessories), built with Laravel 13.

## Storefront

- Catalog browsing by category, product search, and a site sitemap.
- Product variants (size, color, etc.) with per-variant price and stock, selectable on the product page and carried through the cart, checkout, and order history.
- Cart and checkout with saved shipping/billing addresses, carrier selection (home delivery or relay point), and free-shipping thresholds.
- Customer accounts: order history with status tracking, wishlist, saved addresses.
- Password reset and standard authentication.
- Legal pages (CGV, mentions légales, politique de confidentialité, droit de rétractation), populated from admin-managed company settings.

## Admin back office (`/admin`)

- Product, category, and customer management, including per-product variants (price/stock overrides, SKU/GTIN, image).
- Order management: status workflow (Placed → Preparing → Shipped) with a full history log, shipment tracking (carrier + tracking number), and order detail view.
- Shipping settings (free-shipping threshold and eligible carriers).
- Company & legal settings used to fill in the storefront's legal pages.

## Stack

- Laravel 13, PHP 8.3, Blade templates, Eloquent ORM.
- SQLite by default for local development.
- Plain CSS (`public/css/app.css`), no frontend build step required for the storefront.

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
