# Admin API — Blog

Read and write blog posts over HTTP. Six endpoints, JSON in and JSON out.

- **Base URL:** `https://<host>/api/admin`
- **Auth:** static bearer token — the same one the [Products API](products.md) uses
- **Content type:** `application/json` (send `Accept: application/json` on every request)

> **For AI agents:** every rule in this document is enforced by the server. Sections marked **MUST** / **MUST NOT** describe hard 422 failures, not style advice. Read [Rules that will bite you](#rules-that-will-bite-you) before writing any request.

---

## Endpoints

| Method | Path | Purpose | Success |
|---|---|---|---|
| `GET` | `/blog/categories` | List the four categories | `200` |
| `GET` | `/blog/posts` | List posts, filtered and paginated | `200` |
| `POST` | `/blog/posts` | Create a post | `201` |
| `GET` | `/blog/posts/{id}` | Fetch one post | `200` |
| `PATCH` | `/blog/posts/{id}` | Partially update a post | `200` |
| `DELETE` | `/blog/posts/{id}` | Delete a post | `204` |

`{id}` is the numeric primary key, not the slug.

> **`DELETE` exists here but not on products.** A product is referenced by order history, so it is retired with `is_active: false`; a post is referenced by nothing and can genuinely go away. To take a post offline without destroying it, `PATCH {"status": "draft"}` instead.

## Authentication and rate limiting

Identical to the Products API: `Authorization: Bearer <ADMIN_API_TOKEN>`, **120 requests per minute keyed by IP**, counted before the token is checked. `401` on a bad token, `429` with `Retry-After` when the limit is hit. See [products.md](products.md#authentication) for the details.

## Response envelope

Single post (`GET /blog/posts/{id}`, `POST`, `PATCH`):

```json
{ "data": { "id": 1, "slug": "…", … } }
```

Lists add Laravel's pagination blocks — `data`, `links`, and `meta` holding `current_page`, `from`, `to`, `last_page`, `per_page`, `total`, `path`, `links`.

`DELETE` returns `204` with an empty body.

---

## The post object

```json
{
  "id": 1,
  "slug": "comment-choisir-sa-premiere-replique",
  "title": "Comment choisir sa première réplique",
  "excerpt": "Ressort, CO2 ou électrique.",
  "body": "<p>Le texte.</p>",
  "blog_category_id": 1,
  "category": { "id": 1, "slug": "conseils", "name": "Conseils" },
  "status": "published",
  "published_at": "2026-08-23T18:37:14+00:00",
  "is_visible": true,
  "is_scheduled": false,
  "meta_title": null,
  "meta_description": null,
  "image": "blog/guide-abc123.webp",
  "url": "https://armooutdoor.fr/blog/comment-choisir-sa-premiere-replique",
  "products": [
    { "id": 1, "slug": "pistolet-walther-ppks-…", "name": "Pistolet Walther PPK/S …" }
  ],
  "created_at": "2026-08-24T18:37:14+00:00",
  "updated_at": "2026-08-24T18:37:14+00:00"
}
```

### The three states

`status` holds only `draft` or `published`, but a post is in one of **three** states, because a published post can be dated in the future:

| State | `status` | `published_at` | `is_visible` | `is_scheduled` |
|---|---|---|---|---|
| Draft | `draft` | anything | `false` | `false` |
| Scheduled | `published` | in the future | `false` | `true` |
| Live | `published` | in the past | `true` | `false` |

**Read `is_visible`; do not recompute it.** It is the same three-condition rule the storefront, the sitemap and the product back-link all use, and it is deliberately computed in one place. `url` is `null` for anything not visible, for the same reason.

### Field notes

| Field | Notes |
|---|---|
| `slug` | Generated from the title on create, deduplicated with `-2`, `-3`. **Never changes afterwards**, and cannot be set. |
| `body` | Sanitised HTML. See [Images in the body](#images-in-the-body). |
| `excerpt` | Card text, and the meta description when `meta_description` is empty. |
| `image` | Cover path relative to `public/images/`. Writable as a path; **uploading a file is the web admin's job**. |
| `url` | The public URL, or `null` when the post is not visible. |
| `products` | The "Produits mentionnés" block, in display order. |
| `category` | Present when loaded; `blog_category_id` is always there. |

---

## `GET /blog/categories`

The four categories, with a count of **visible** posts in each — drafts and scheduled posts are not counted.

```json
{ "data": [
  { "id": 1, "slug": "conseils", "name": "Conseils",
    "description": "Bien choisir, bien entretenir, bien débuter.",
    "sort_order": 0, "posts_count": 1 }
] }
```

Use this to get a valid `blog_category_id`. Categories are seeded, not creatable over the API.

## `GET /blog/posts`

Returns **every** post — drafts and scheduled included. This is the admin view, not the public one.

| Param | Type | Effect |
|---|---|---|
| `search` | string | Partial match on title or slug |
| `slug` | string | Exact match |
| `category_id` | int | Exact match |
| `category` | string | Match by category **slug** (`conseils`, `actualites`, `essais`, `reglementation`) |
| `status` | string | `draft` · `published` · `visible` · `scheduled` — see below |
| `updated_since` | date/datetime | Posts with `updated_at >=` this value |
| `per_page` | int | Default `25`, **capped at 100** (higher values are clamped, not rejected) |
| `page` | int | 1-based |

`status` takes two values that are not stored columns:

- `published` — every post whose status is `published`, **including scheduled ones**
- `visible` — only what a reader can actually see right now
- `scheduled` — published, but dated in the future or missing a date

Ordered by `published_at` descending, then `id` descending.

```bash
curl -s -H "Authorization: Bearer $ADMIN_API_TOKEN" -H "Accept: application/json" \
     "https://example.com/api/admin/blog/posts?status=scheduled"
```

## `POST /blog/posts`

**Required:** `title`, `body`, `blog_category_id`. Everything else is optional; `status` defaults to `draft`.

```bash
curl -s -X POST \
  -H "Authorization: Bearer $ADMIN_API_TOKEN" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{
        "title": "Comment choisir sa première réplique",
        "body": "<p>Ressort, CO2 ou électrique : ce qui change vraiment.</p>",
        "blog_category_id": 1,
        "excerpt": "Un guide court pour débuter.",
        "status": "published",
        "published_at": "2026-09-01T09:00:00+02:00",
        "product_ids": [338, 342]
      }' \
  "https://example.com/api/admin/blog/posts"
```

## `PATCH /blog/posts/{id}`

Partial update — **only the keys you send are touched**.

```bash
curl -s -X PATCH \
  -H "Authorization: Bearer $ADMIN_API_TOKEN" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"status": "published", "published_at": "2026-09-01T09:00:00+02:00"}' \
  "https://example.com/api/admin/blog/posts/12"
```

---

## Writable fields

Same set for `POST` and `PATCH`.

| Field | Type | Constraints |
|---|---|---|
| `title` | string | ≤ 180 chars. Sets the slug on create only |
| `body` | string | ≤ 200 000 chars, HTML, **sanitised server-side** |
| `blog_category_id` | int | must exist |
| `excerpt` | string\|null | ≤ 300 chars |
| `status` | string | `draft` or `published` |
| `published_at` | date\|null | Any parseable date. A future value means scheduled |
| `meta_title` | string\|null | ≤ 180. Falls back to `title` |
| `meta_description` | string\|null | ≤ 300. Falls back to `excerpt` |
| `image` | string\|null | ≤ 2048, path relative to `public/images/` |
| `product_ids` | int[] | Each must exist. **Order is kept** as the display order |

Not writable: `slug`, `is_visible`, `is_scheduled`, `url`, timestamps.

---

## Rules that will bite you

**1. A published post MUST have a date.**
`status: "published"` without `published_at` is a `422`. This holds on `PATCH` too — flipping a dateless draft to published is refused:

```json
{ "errors": { "published_at": ["A published post needs a publication date. Send published_at, or keep the status as draft."] } }
```

Without the date the post could never satisfy the visibility rule: it would read as published in the admin and be permanently invisible to readers.

**2. The slug is fixed at creation.**
Sending `title` on `PATCH` renames the post but **not** its URL. That is deliberate — a shared or indexed link must keep working. There is no way to change a slug over the API; do it in the database if you truly must.

**3. `product_ids` replaces the whole list, but only when sent.**
Omit the key and the attachments are left alone. Send `[]` and they are all removed. Order in the array is the order on the page.

**4. A future date is not an error.**
It creates a scheduled post: `is_visible: false`, `is_scheduled: true`, `url: null`. It becomes visible on its own when the clock passes. Nothing needs to run.

**5. Drafts appear in this API.**
`GET /blog/posts` is the admin list. If you are mirroring the public blog, filter with `?status=visible` — otherwise you will publish drafts somewhere else.

### Images in the body

`body` is cleaned server-side. Allowed tags: `p`, `br`, `strong`, `b`, `em`, `i`, `u`, `s`, `strike`, `a`, `ul`, `ol`, `li`, `h2`, `h3`, `h4`, `blockquote`, `span`, `div`, `pre`, `code`, plus `img`, `figure` and `figcaption`.

**An `img` MUST be same-origin.** Two shapes are accepted:

1. A root-relative path — starts with a single `/`, and **not** `//`
2. An absolute `http`/`https` URL whose host matches this site's

Anything else — another host, `data:`, `javascript:`, or a protocol-relative `//host/x.jpg` — has the whole `<img>` **removed**, not just its `src`. A bare `<img>` would render as a broken-image icon.

Since external images are dropped, the file has to be on this site first. **The API has no upload endpoint** — use the web admin's editor, which uploads and inserts the path for you, or set `image` to a path that already exists.

`<script>`, `<style>` and `<iframe>` never survive, with or without images.

---

## Errors

| Status | Meaning |
|---|---|
| `401` | Missing, malformed or wrong token |
| `403` | Reached a handler without passing the token middleware (should not occur) |
| `404` | No post with that id |
| `422` | Validation failed |
| `429` | Rate limit exceeded — honour `Retry-After` |

```json
{
  "message": "Le champ title est obligatoire. (and 3 more errors)",
  "errors": {
    "title": ["Le champ title est obligatoire."],
    "body": ["Le champ body est obligatoire."],
    "blog_category_id": ["Le champ blog category id est obligatoire."],
    "published_at": ["A published post needs a publication date. Send published_at, or keep the status as draft."]
  }
}
```

> **Messages are French** — the application locale is `fr` — except the custom ones above, which are English. Agents **MUST** branch on the `errors` object keys and the HTTP status, never on message text.

---

## Worked example: schedule a guide for next Monday

```bash
# 1. Which category?
CATEGORY=$(curl -s -H "Authorization: Bearer $ADMIN_API_TOKEN" -H "Accept: application/json" \
  "https://example.com/api/admin/blog/categories" | jq -r '.data[] | select(.slug=="conseils") | .id')

# 2. Write it, dated ahead. It stays private until then.
curl -s -X POST \
  -H "Authorization: Bearer $ADMIN_API_TOKEN" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d "{
        \"title\": \"Quelles billes pour quelle réplique ?\",
        \"body\": \"<p>Le poids de bille change tout.</p>\",
        \"blog_category_id\": $CATEGORY,
        \"excerpt\": \"0,20 g, 0,25 g, 0,28 g : ce que cela change.\",
        \"status\": \"published\",
        \"published_at\": \"2026-09-07T09:00:00+02:00\",
        \"product_ids\": [338]
      }" \
  "https://example.com/api/admin/blog/posts" | jq '.data | {id, slug, is_scheduled, url}'

# → { "id": 13, "slug": "quelles-billes-pour-quelle-replique",
#     "is_scheduled": true, "url": null }
```

---

## Agent checklist

1. `Accept: application/json` and `Authorization: Bearer …` set.
2. `PATCH` carries **only** the fields being changed.
3. Setting `status: "published"` always accompanies a `published_at`.
4. Read `is_visible` — never recompute it from `status` and `published_at`.
5. `product_ids` omitted means "leave alone"; `[]` means "detach all".
6. Body images are same-origin paths already hosted here.
7. Mirroring the public blog means `?status=visible`, not the bare list.
8. On `429`, wait for `Retry-After`. On `422`, read `errors` keys — never the message text.

## Related

- [Products API](products.md) — same token, same envelope, same limiter
- Web admin → Blog — the only place to upload cover and in-body images
