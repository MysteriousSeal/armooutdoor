# Admin API — Categories

Read and manage the category tree over HTTP. Four endpoints, JSON in and JSON out.

- **Base URL:** `https://<host>/api/admin`
- **Auth:** static bearer token — same token and rules as [products](products.md#authentication)
- **Content type:** `application/json` (send `Accept: application/json` on every request)

---

## Endpoints

| Method | Path | Purpose | Success |
|---|---|---|---|
| `GET` | `/categories` | Full tree with each category's products | `200` |
| `POST` | `/categories` | Create a category | `201` |
| `PATCH` | `/categories/{id}` | Partially update a category | `200` |
| `DELETE` | `/categories/{id}` | Delete an **empty** category | `204` |

`{id}` is the numeric primary key, not the slug.

---

## Fields

| Field | Notes |
|---|---|
| `name` | Required on create. French display name, max 120 chars. |
| `description` | Required on create. Plain text, max 2000 chars. |
| `guide` | Optional, nullable. The buying guide rendered under the category's product grid, max 30 000 chars. HTML, sanitised on save (h2, h3, p, ul/ol, li, strong, em, a survive; scripts, styles and event handlers are stripped). Send `null` or an empty string to clear it. |
| `slug` | Optional. Lowercase-and-dashes, max 80 chars. Omitted on create, it is derived from the name; a collision gets a `-2`, `-3`… suffix instead of a 422. |
| `parent_id` | Optional, nullable. **Only a root category can be a parent** — the tree is two levels deep, and a category that already has children cannot itself become a child. Both violations are 422s. |
| `sort_order` | Optional integer `0–9999`, defaults to `0` on create. |
| `image` | Optional path relative to `public/images` (or a full URL). Writable as a string; **uploading a file is the web admin's job**, which also produces the 1890×810 WebP hero crop. |

On `PATCH`, absent fields keep their value — send only what changes.

---

## Examples

Create:

```
POST /api/admin/categories
{"name": "Optiques de visée", "description": "Lunettes et points rouges.", "parent_id": 3}
```

Update:

```
PATCH /api/admin/categories/12
{"name": "Optiques", "sort_order": 4}
```

Set a buying guide:

```
PATCH /api/admin/categories/12
{"guide": "<h2>Bien choisir sa lunette</h2><p>Le grossissement se choisit d'après la distance…</p>"}
```

Success responses wrap the category in `data`:

```json
{"data": {"id": 12, "slug": "optiques", "name": "Optiques", "description": "…", "guide": null, "parent_id": 3, "sort_order": 4, "image": null}}
```

---

## Rules that will bite you

- **DELETE refuses a category that still has products or subcategories** (`422`). Move or retire its products first (`PATCH /products/{id}` with a new `category_id`), and delete or re-root its children. There is no force flag — the API will not orphan products.
- **Changing a slug changes the category's public URL immediately, with no redirect.** Unlike products, old category slugs are not remembered (only a static config map covers historic renames). Rename slugs sparingly.
- `name` and `description` are stored under the `fr` locale key, like the web admin does.
