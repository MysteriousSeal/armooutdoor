# Implementation Plan — Blog

Written against `main` @ `8c1deb2`, from the code rather than from memory.

## Locked decisions

| Decision | Choice |
|---|---|
| URL | `/blog`, posts at `/blog/{slug}` |
| Content | Guides, shop news, product deep-dives, legal/practical info — all four |
| Taxonomy | **One category per post**, fixed short list, filter tabs on `/blog` |
| Publishing | `draft` / `published` **plus a publish date**; a future date hides the post |
| Byline | None — posts come from the shop |
| Images | **Landscape**: 1600×900 hero, 800×450 card. Not the square product pipeline |
| Products | A **"Produits mentionnés"** block, and a back-link from the product page |
| Comments | None |

### Categories (initial list)

| Slug | Label | For |
|---|---|---|
| `conseils` | Conseils | Buying guides, how-tos |
| `actualites` | Actualités | Arrivals, restocks, shop news |
| `essais` | À l'essai | Product deep-dives |
| `reglementation` | Réglementation | Law, age rules, transport |

---

## What already exists and should be reused

Nothing here needs inventing — the shop already solves most of it.

| Need | Existing thing |
|---|---|
| Rich text editing | Quill, wired in `admin/products/form.blade.php` (lines ~225–230, assets at ~740/745) |
| HTML cleaning | `App\Support\HtmlSanitizer::clean()` |
| Image resize + thumbnails | `App\Support\ImageThumbnailer` |
| Admin list pages | `.admin-list-page`, `.admin-tabs`, `.admin-filter-bar` |
| Audit trail | `AdminActivityLog::record()` |
| Translatable text columns | The `{"fr": …}` JSON convention on `Product` |
| Slug generation + dedupe | `Api\Admin\ProductController::uniqueSlug()` |

The site is French-only (`APP_LOCALE=fr`, only `lang/fr` exists). Keep the `{"fr": …}` column shape anyway, to match `Product` and leave a second locale possible.

---

## 1. Schema

Three migrations, following the `YYYY_MM_DD_HHMMSS_*` convention already in `database/migrations/`.

### `blog_categories`

| Column | Type | Notes |
|---|---|---|
| `id` | id | |
| `slug` | string, unique | `conseils`, `actualites`, … |
| `name` | text | `{"fr": "Conseils"}` |
| `description` | text, nullable | Optional intro on the filtered list |
| `sort_order` | integer, default 0 | Tab order |
| timestamps | | |

Seeded with the four rows above. A table rather than an enum so labels and order can change without a migration — same reasoning as `categories`.

### `blog_posts`

| Column | Type | Notes |
|---|---|---|
| `id` | id | |
| `blog_category_id` | FK → `blog_categories`, **restrict on delete** | A post always has exactly one |
| `slug` | string, unique | Generated from the title, never changes after creation |
| `title` | text | `{"fr": …}` |
| `excerpt` | text, nullable | `{"fr": …}` — card text and `meta_description` |
| `body` | text | `{"fr": …}` — sanitised HTML |
| `image` | string, nullable | Landscape hero, relative to `public/images/` |
| `status` | string, default `draft` | `draft` \| `published` |
| `published_at` | timestamp, nullable | Ordering **and** visibility |
| `meta_title` | string, nullable | Falls back to `title` |
| `meta_description` | string, nullable | Falls back to `excerpt` |
| timestamps | | |

Index `status`, `published_at`, and `blog_category_id` — every storefront query filters on the first two and the tabs add the third. (The dashboard's missing indexes were a real cost on `orders`; don't repeat it.)

> `status` is a plain string, exactly like `orders.status`. That was fine there but it is *why* adding "In transit" took an audit of twenty call sites. Two values are unlikely to grow, but **put every visibility check behind one scope** (§3) so there is only ever one place to change.

### `blog_post_product`

Pivot, `blog_post_id` + `product_id`, `sort_order`, unique on the pair, cascade on delete both ways.

---

## 2. Models

**`BlogPost`** — `#[Fillable([...])]`, never `$guarded`. Casts: `title`/`excerpt`/`body` to `array`, `published_at` to `datetime`.

```php
public function scopeVisible(Builder $query): void
{
    $query->where('status', 'published')
        ->whereNotNull('published_at')
        ->where('published_at', '<=', now());
}
```

**Everything public goes through `visible()`** — the index, the show route, the sitemap, the product back-link, the homepage teaser if one is added. A future-dated post is a draft that publishes itself; nothing else should know the rule.

Also: `category()` belongsTo, `products()` belongsToMany ordered by pivot `sort_order`, `localizedTitle()` / `localizedExcerpt()` / `localizedBody()` mirroring `Product::localizedName()`, and `heroUrl()` wrapping the thumbnailer.

**`BlogCategory`** — `posts()` hasMany, `localizedName()`.

**`Product`** — add `blogPosts()` belongsToMany, filtered by `visible()` for the back-link.

---

## 3. Storefront

| Route | Name | View |
|---|---|---|
| `GET /blog` | `blog.index` | `blog/index` |
| `GET /blog/{post:slug}` | `blog.show` | `blog/show` |

`/blog` takes `?categorie={slug}` for the tab filter — French query key, matching the French paths. Paginate; order by `published_at` desc.

`show` must **404 on a non-visible post**, not merely hide it from the list. Use the scope, not `findOrFail` on the slug alone.

Views follow `layouts.app` and the existing section blocks: `title`, `meta_description`, `canonical`. The category tabs reuse `.sort-tabs` / `.sort-tab` from the storefront nav rather than inventing a class.

**Nav:** add Blog to `layouts/app.blade.php` (~line 87–97, beside Nouveautés / Promotions / Meilleures ventes) and to the footer's site column. New `lang/fr/store.php` keys: `nav_blog`, `blog_title`, `blog_intro`, `blog_all`, `blog_related_products`, `blog_empty`, `blog_published_on`, `blog_back_to_list`, `product_blog_posts`.

**Product page:** a "Nos conseils" block listing visible posts that mention this product. Only render the block when there is at least one.

---

## 4. Images — the one genuinely new piece

`ImageThumbnailer` today is square-only:

```php
public const SIZE = 400;        // thumbnail
public const MAIN_SIZE = 1000;  // main image
public static function normalizeMain(string $relativePath): ?string
public static function normalizeSquare(string $relativePath, int $size, int $quality = 90): ?string
```

Add a landscape path beside it — `normalizeLandscape($path, 1600, 900)` and a card variant at 800×450 — writing to `public/images/blog/` and `public/images/blog/thumbs/`. Do **not** widen `normalizeSquare` with a ratio argument; products depend on it and a shared parameter would let a wrong ratio reach them.

> **Known bug, adjacent:** `Admin\ProductController::deleteStoredImageFile()` unlinks the full-size file but never its thumbnail, so deleted product images leave 400×400 orphans behind (two exist right now under `public/images/products/thumbs/`). **Do not copy that method.** The blog's delete must remove both files. Fixing the product one is out of scope here.

### Inline images in post bodies — decided: allow, same-origin only

`HtmlSanitizer::ALLOWED_TAGS` has no `img`, `figure` or `figcaption`, and
`ALLOWED_ATTRIBUTES` is `href, title, target, rel, class`. An unknown tag is
**unwrapped rather than dropped** (`sanitizeNode()`), so an `<img>` posted today
silently disappears — no error, no trace.

**Do not widen the shared allowlist.** `clean()` also guards every product
description; products have no need of inline images and should not gain the
ability by accident. Add an opt-in instead:

```php
public static function clean(?string $html, bool $allowImages = false): ?string
```

Products keep calling `clean($html)` and behave exactly as before. The blog
calls `clean($body, allowImages: true)`.

When `$allowImages` is true:

- `img` joins the allowed tags. `figure` and `figcaption` too — Quill wraps
  captioned images in them, and without both the caption survives as loose text.
- For `img` **only**, allow `src`, `alt`, `width`, `height`, `loading`.
  `sanitizeAttributes()` already receives `$tag`, so this is a per-tag branch,
  not a widening of the global list.

**`src` must be same-origin.** Accept exactly two shapes:

1. A root-relative path: starts with a single `/`, and **not** `//`.
2. An absolute URL whose scheme is `http`/`https` **and** whose host equals the
   host of `config('app.url')`.

Reject everything else — `data:`, `javascript:`, and any other host.

> The trap is `//evil.com/x.jpg`. It is protocol-relative, it loads from another
> host, and it passes a naive "starts with `/`" check. Test it explicitly.

An `img` whose `src` fails these rules must be **removed entirely**, not stripped
of its attribute — a bare `<img>` renders as a broken-image icon. This differs
from how `href` is handled on `a`, where dropping the attribute leaves readable
text behind; an image has nothing to fall back to. Give any surviving `img` an
`alt` (empty string if the author left none) so the markup stays valid.

### Consequence: the editor needs somewhere to put images

Same-origin means an author **cannot paste an image URL from another site** —
which is the point, but it means the shop must host the file first. Without a
route for that, "allow img" is unusable in practice.

Smallest thing that works, and the one this plan assumes:

- `POST admin/blog/images` — accepts one upload, runs it through the landscape
  pipeline (or a plain max-width resize for in-body images), returns
  `{"url": "/images/blog/…"}`.
- Wire Quill's image handler to it, so the toolbar button uploads and inserts
  the returned path.
- Same validation as the product upload: `image`, `max:4096`.
- Deleting a post does **not** chase images referenced inside its body. Accept
  the orphans for now, or add a sweep command later — but decide knowingly
  rather than discovering it, which is exactly how the product thumbnail
  orphans happened.

## 5. Admin

Routes under the existing `admin` middleware, named `admin.blog.*`: `index`, `create`, `store`, `edit`, `update`, `destroy`, plus `admin.blog-categories.*` if categories become editable (a seeder alone is enough to start).

Add **Blog** to the admin nav in `layouts/admin.blade.php`, after Categories.

The form mirrors `admin/products/form.blade.php`:

- Title, slug (read-only after creation), category select
- Excerpt (plain textarea, ~300 chars — it is the meta description)
- Body in Quill, through `HtmlSanitizer::clean()` on save
- Hero image upload with the landscape pipeline
- Status radio + `published_at` datetime
- SEO fields
- **Product picker** for "Produits mentionnés" — a search field writing ids into a hidden ordered list, the same shape as the gallery reorder input

List page: `.admin-list-page` with `.admin-tabs` — All / Drafts / Published / Scheduled — and a search box. Scheduled means `published` with a future date; it is worth its own tab precisely because it is otherwise invisible.

Log `blog_post.created` / `.updated` / `.deleted` via `AdminActivityLog::record()`.

---

## 6. SEO

`SitemapController` already splits into pages / categories / products with routes at `routes/web.php:65–69`. Add:

- `GET /sitemap-blog.xml` → `sitemap.blog`, listing visible posts, `lastmod` from `updated_at`
- The new sitemap in `index()`'s `$sitemaps` array
- A Blog section in `html()` for `/plan-du-site`

Each post sets `title`, `meta_description` and `canonical`. Consider `Article` JSON-LD on `show` — optional, and only if the fields are real.

---

## 7. Tests

New `tests/Feature/Blog/`:

- A published, past-dated post appears on `/blog` and its own page
- A draft **404s** on `/blog/{slug}` — not just hidden from the list
- A `published` post dated tomorrow **404s** today, appears when the clock passes it (travel with `Carbon::setTestNow`)
- `?categorie=conseils` filters, and an unknown category slug does not 500
- The "Produits mentionnés" block renders attached products, and is absent when none
- A product page shows visible posts that mention it, and **not** draft ones
- `/sitemap-blog.xml` lists visible posts only
- Admin: create, edit, delete; a non-admin gets redirected
- Body HTML is sanitised on save (`<script>` does not survive)
- A same-origin `img` survives `clean($html, allowImages: true)`
- `//evil.com/x.jpg`, `https://evil.com/x.jpg` and `data:` images are **removed entirely**, not left as bare `<img>`
- The **same** markup passed through `clean($html)` — the product path — still loses its `img`
- Slug survives a title change

**Verify each test bites** by reverting the line it guards. The visibility ones matter most: a scope that quietly returns everything is exactly the failure this design is shaped to prevent.

```bash
php artisan test --filter=Blog
php artisan test
vendor/bin/pint --dirty
```

---

## 8. Order of work

1. Migrations + models + seeder for the four categories
2. `visible()` scope and its tests **first** — everything public depends on it
3. Storefront index + show, nav, French strings
4. Landscape image path in `ImageThumbnailer`
5. Admin CRUD + product picker
6. Product-page back-link
7. Sitemap + meta
8. Full suite, pint, then look at both themes

Steps 1–3 are a working blog. Everything after is polish, and each step is shippable on its own.

---

## Before calling it done

- A draft is invisible from a logged-out browser at its exact URL
- A post dated tomorrow does not appear today
- Category tabs filter, and `/blog` with no filter shows everything visible
- The hero is landscape on the post and cropped on the card, in both themes
- The product back-link appears only for products actually mentioned
- `/sitemap-blog.xml` parses and lists only visible posts

## Out of scope

- Comments — decided against
- Author bylines — decided against
- Tags alongside categories — one category per post
- RSS, newsletter, social auto-posting
- Multi-language — the site is French-only
- Fixing the product thumbnail-orphan bug (noted in §4)
- Sweeping images orphaned inside deleted post bodies (noted in §4)
