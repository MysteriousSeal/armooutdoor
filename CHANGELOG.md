# Changelog

All notable changes to this project since the initial commit are documented here, newest first.

## 2026-08-30 — v1.0.1 — build 0E41U4

### Storefront

- **A cookie banner, honestly earned.** Essential cookies — cart, login, theme — stay exempt from consent; the one processing that deserved a question is the session-id audience measurement, and that is precisely the question the banner asks. Declined or unanswered, the visit is still recorded without the identifier; accepted, it groups the pages of a single visit. Two real same-size buttons, a choice that holds for six months, and a "Cookies" footer link to change one's mind — the privacy policy tells the whole story.

- **The copy stops congratulating itself for being French.** The selection is no longer "written in French" and prices no longer proudly "stay in euros" — this is a French shop selling in France, those feats go without saying. In their place, the one claim that means something: prices are shown TTC. The airgun becomes the airsoft the catalogue actually sells (hunting stays, it earns its keep in search), the imaginary workshops close, and the em dashes in visitor-facing prose give way to commas and colons — tab-title separators and empty-cell dashes remain.

### Admin

- **The visitor's path draws itself.** Analytics gains an eight-step flow diagram — entrance to order, every drop-off draining into "Left the site". Sessions group by the real session id every visit now carries (two visitors behind one IP no longer blur together); older rows fall back to the IP-and-thirty-minute-gap approximation. Hovering a node dims everything not connected to it, a legend names the page groups, labels go quiet on nodes too thin to carry them, and the table view groups by step with amber-tinted leaks and share bars.

- **Abandoned carts get their page.** Sales → Carts lists what logged-in customers left sitting in their basket — items, quantities, value, last activity — searchable by name or email. A cart empties itself the instant its order completes, so every row is a purchase still hanging in the balance; guest carts live in their own browsers and never appear.

**Migration:** one, run with `php artisan migrate` — a nullable, indexed `session_id` column on `site_visits`. Existing rows stay null and keep working through the fallback.

## 2026-08-30 — v0.37.0 — build FQ83MN

### Admin

- **Analytics now knows which product and category got looked at.** Every recorded visit resolves its product and category from the route — a product page carries both, a category page carries its own — so the trend the last release added finally has a palmarès to match: Top products and Top categories sit beside Top pages and referrers, humans only, each name linking straight to its edit page. No extra query: the ids ride along on the same visit row, counted in the pass the other palmarès already runs.

**Migration:** one, run with `php artisan migrate` — two nullable `product_id`/`category_id` columns on `site_visits`, both null on delete. Existing rows stay null; only visits recorded from here on carry them.

## 2026-08-30 — v0.36.0 — build BSJ9T7

### Admin

- **Analytics tells when, and what.** The page had breakdowns and a raw log but no trend and no palmarès. A visits-over-time chart now leads — humans and bots stacked apart, bucketed by hour on 24 hours, by day on 7 and 30, by month on all time — drawn like the dashboard's revenue chart: colors from the theme, and a table twin rendered server-side so no value lives only in a tooltip. Top pages and top referrers get their panels, humans only, since a scraper inhaling the catalogue says nothing about what interests anyone; referrers keep external sites only. Everything is counted in the single pass the donut charts already made — the page runs no extra query for any of it.

- **The visit log can hide the bots.** "Bot" on this page is a verdict — user-agent signatures plus the request-burst heuristic — not a database column, so the toggle excludes exactly the rows that verdict flagged rather than approximating it in SQL. The page also reads in order now — trend, palmarès, composition, journal — the two donuts that duplicated the stat tiles are gone, and the header keeps only its live visitor chip instead of restating every tile in one dense line.

## 2026-08-30 — v0.35.0 — build WI1B7V

### Admin

- **The shop hears about its own orders.** Every path that makes an order real — the checkout, the Stripe finalizer, a manual creation, a validated draft — now also emails the address in `ORDER_NOTIFICATION_EMAIL` (blank meaning nobody). The full receipt, headed by the channel — website, manual, or the marketplace's name — and buttoned straight to the admin order page; the subject alone carries number and total, so the inbox list reads as a sales feed. Drafts stay silent until validated, and a mail outage logs rather than failing the order.

### Storefront

- **The legal pages caught up with the shop.** The CGV stop announcing themselves as a template, reserve regulated products (airsoft replicas, knives) to adults — what the product pages already promised — say delivery is mainland France only, and cite L612-1: the consumer mediator is named from two new company settings once the shop joins a scheme, with the EU dispute-resolution platform linked either way. The privacy policy owns up to the site's own audience measurement (visited pages and IP, cookieless, first-party) and names the actual processors — Stripe, the carriers, Sendcloud, the host. The withdrawal delay now runs from receipt by the customer or a designee. The wording still deserves a professional read before launch.

- **Nothing promises PayPal any more while the checkout has it disabled.** The FAQ — whose answers feed its search-result markup — the secure-payment page, the footer and the homepage badges all said card or PayPal; they now say card, PayPal soon. The payment page keeps the PayPal logo, greyed, wearing the checkout's own "Bientôt disponible" chip, and finally names who actually handles the card: Stripe, with 3-D Secure. The FAQ's contact answer points at the real contact form, and every delivery mention agrees on France métropolitaine.

**Migration:** one, run with `php artisan migrate` — two nullable mediator columns on company settings. Nothing is deleted.

## 2026-08-30 — v0.34.1 — build 3ZX692

### Under the hood

- **The back-office can leave `/admin`.** Its URL prefix now comes from the environment — set `ADMIN_PATH` in production and the admin moves to a path a scanner won't guess, without the secret ever entering git; route names and the token-protected `/api/admin` stay where they were. `robots.txt` only names the path while it's still the default, since a path renamed to be unfindable has no business printed in the first file every scanner reads — a `noindex` header covers the admin pages whatever they're called instead. The login throttle — five attempts a minute, counted before the password check — already existed; a test now pins it so it can't quietly disappear.

## 2026-08-30 — v0.34.0 — build 3D5HZM

### Storefront

- **A carrier can now carry a maximum weight.** Above it, the carrier stays visible at checkout but greyed and unselectable, its delivery estimate replaced by the reason — « votre commande dépasse le poids maximum de ce transporteur » — and the server refuses the order even posted by hand. The default selection skips it, a free-relay code can no longer claim to waive delivery on a carrier that won't take the parcel, and the cart's « à partir de » shipping estimate stops quoting the price of a choice the weight has already greyed out.

### Admin

- **The shipping settings page answers "what do we charge?" at a glance.** Each carrier is now a rate card — logo, a Home or Relay point chip, and a "Free above" badge when the free-shipping rule covers it, so the two panels finally read against each other. Below, a menu-style table with dotted leaders running from weight range to price: the default price becomes the first range, each tier bounds the one before it, and the new weight limit closes the last. The maximum weight is set in the same per-carrier modal as the tiers; a carrier without one says "No limit" rather than leaving a blank.

- **Package types read as tags**, each carrying how many orders used it — one grouped query for the whole page. The count matters most in the removal dialog, which now says what's at stake: "No order ever used it" reads differently from "12 orders used it and keep it on their tracking". The panel moved up beside Free shipping, in a left column that stacks its own panels so the tall carrier list opposite can't open a blank between them.

**Migration:** one, run with `php artisan migrate` — a nullable maximum-weight column on carriers. Nothing is deleted.

## 2026-08-30 — v0.33.0 — build OKGYB1

### Admin

- **An order can offer a thank-you code.** One click on the order page creates a 10 % code — a single use, for any customer, valid three months from the order's date — and remembers which order it was offered for, a different fact from which order later spends it. One per order: the panel then shows the code, its live state — valid until, used, expired — and hands it straight to the PDF card below. The code reads MERCI- plus six letters drawn without vowels or 0/O/1/I, so it retypes from a card without hesitation and spells no word. The customer's own order page never shows it — a code slipped into a parcel is handed over by the shop, not announced by the site — and a test holds that door shut.

- **A discount code prints as a 70 × 50 mm card**, from a PDF button on the codes list — only the code on it, to slip into a parcel. The type size is computed from the card's width and the code's length, worst-case letter and spacing included, rather than guessed by steps: the first guesses cropped an eight-character code, and a floor set too generously cropped the longest ones. A test now sweeps every admissible length against the same arithmetic.

- **"Available at supplier" flips from the product list.** A new At supplier column carries a clickable Yes/No chip, flipped in place without a reload; the row's availability reading redraws from the server's own verdict, since the flag can swing a product between "At supplier" and "Out of stock". A product sold in sizes shows a dash — the flag lives on each size — and one with no supplier a disabled chip, both refused server-side too.

- **The product list tightened by a column.** Supplier is gone — the name is one click away on the edit page, and the new chip already says what the list needs — and Status now wears the same pastille as GTIN and AI OK beside it, a green check or a red cross, still clickable to flip. On the way, the test guarding the variant panel's colspan turned out to count `<thead>` itself as a column: the panel had been declared one column wider than the table all along, silently clamped by browsers.

**Migration:** one, run with `php artisan migrate` — a nullable origin-order column on discount codes. Nothing is deleted.

## 2026-08-30 — v0.32.1 — build TCVQSU

### Storefront

- **Card is back at checkout.** It had been switched off, showing "Bientôt disponible" like PayPal still does, until the shop had a live Stripe account to actually take a payment against. That account is live now, so the card option is on again — PayPal stays off.

## 2026-08-30 — v0.32.0 — build O4MGCS

### Storefront

- **The order writes again when someone starts packing it.** A short note this time — "votre commande est en préparation", we'll tell you when it ships, a button to follow it — rather than a second receipt. Sent only on the actual move into preparation, so an admin re-clicking the status never writes the customer twice, and to the same audience as the confirmation: marketplace orders and hand-typed accounts hear nothing. The policy the two emails share now lives under one name on the order, ready for the shipping email when its turn comes.

## 2026-08-30 — v0.31.0 — build 2ESZMM

### Storefront

- **Ordering finally answers back.** The shop never sent an order confirmation — a customer checked out and heard nothing. Every real order now mails its receipt, dressed like the rest of the shop's email: the items in a table with the totals beneath, the carrier and delivery address — or the relay point — and a button to follow the order. When payment wasn't captured by card at checkout, a panel says so plainly: the order ships once the payment is confirmed.

  "Every real order" is a policy the code states in one place: orders from the site, card or not, wherever the Stripe finalizer runs; manual orders an admin places for a real customer, phone orders included, and drafts the moment they're validated. Marketplace orders stay silent — the marketplace already confirmed them — and the shadow accounts behind hand-typed customers are never written to, their address having been typed by an admin rather than verified by its owner.

### Under the hood

- **One way to send mail from a request.** The deferred, guarded send that conversations pioneered — after the response, a log line on failure, never a broken page — became `DeferredMail`, and every email the application owes a request now goes through it.

## 2026-08-30 — v0.30.0 — build UT2962

### Storefront

- **The emails put on the shop's own coat.** Every notification the shop sends — password resets, "we've answered you", the guest's private link — used to go out in Laravel's stock grey: its font, its near-black button, its rounded corners, its "© Laravel" heritage showing at the seams. The mail templates are now the shop's: the two-tone ArmoOutdoor wordmark as a text header no mail client can strip, the site's warm palette on a hairline-bordered square card, the action button in the shop's accent, and a footer that goes somewhere — la boutique, nous contacter. One theme file dresses them all, so the next email the shop learns to send is born wearing it.

### Admin

- **The test email now tells the truth.** It had its own hand-built design — prettier than what customers actually received, which defeated a test email's purpose. It renders through the shared theme now, panel and button included, so what lands in the inbox from Settings → Email test is exactly what every real email looks like.

## 2026-08-30 — v0.29.0 — build ENB06K

### Storefront

- **A guest can now hold a real conversation.** Writing through the contact form without an account used to be a message in a bottle — readable in the back office, unanswerable anywhere. It now mints a private link, emailed straight back ("votre message est bien arrivé"), opening a page where the whole exchange reads as one letter-like card: subject up top under a liseré in the shop's colour, the messages as bubbles on a softly recessed ground, and the reply box attached at the foot of the same sheet. Replies send in place with the usual spinner and toast, and each answer from the shop lands in the guest's inbox with the same link.

  The link is the whole key — 48 random characters, hidden from search engines, and a page that answers 404 to anything it doesn't recognise, expired links included. It works while the conversation is open and for thirty days after it closes; after that, old links in inboxes go quietly dead. A closed thread still reads during those thirty days, but no longer answers.

- **The account's conversation page wears the same card.** Same recessed ground, same accent edge, same moored composer — at the account page's own width, with its breadcrumbs and navigation untouched.

### Admin

- **Guest threads lost their dead-end.** The "no account, reply by email" note is gone: a guest conversation takes replies in the normal chat composer, with a line underneath saying where they land — the private emailed link, minted on the spot for threads that predate it, including threads whose customer account has since been deleted.

## 2026-08-29 — v0.28.0 — build 23WERS

### Admin

- **The shop now knows who's at the door.** Every public page view is written down — path, referrer, country and city, browser, device — and a new Analytics page under System reads it back: stat tiles for the chosen range, donut charts breaking traffic down by country, operating system, device, browser and logged-in versus guest, and a paginated log of every visit, each line linking a signed-in customer to their page. Bots are counted apart from people, caught two ways: by what they call themselves, and by how they behave — an IP firing twelve page loads inside a minute is automated no matter how honest its user agent looks, and gets flagged as such in the log. Four range tabs (24 hours, 7 days, 30 days, all time) frame everything on the page at once.

  The recording itself costs the visitor nothing: it runs after their page has already been sent, and the geo lookup — CDN headers first, then a cached lookup, a day per address — is skipped entirely for bots and never allowed to wait long. The same visit log is also served as JSON at `/api/admin/analytics`, behind the same token as the rest of the admin API.

- **A live pulse in the nav bar.** Next to the search field, a chip counts distinct visitors seen in the last two minutes, refreshing itself every ten seconds and glowing green when someone's actually there. It links to the Analytics page, where the same figure sits among the tiles, split into logged in, guests and bots.

## 2026-08-28 — v0.27.0 — build PT7XEW

### Storefront

- **The forgot-password form stops answering questions nobody should ask it.** It used to say "impossible d'envoyer" for an address without an account — and its cooldown only ever fired for addresses *with* one, so either message told a prober which emails are registered here. Unknown and known addresses now get the same neutral answer, and the one-per-minute cooldown is keyed on the address as typed rather than on the broker's table of real accounts, so the two cases are indistinguishable at every step. A retry inside the cooldown says a link is already on its way instead of pretending failure.

  The form also stopped reloading the page: it asks over AJAX with a small spinner in the button, confirms with the site's usual toast, shows cooldown and rate-limit answers inline under the field, and locks itself once the link is sent. If JavaScript is broken it falls back to the plain form post rather than dead-ending.

- **The reset form no longer knows your email.** The link in the email used to carry the address in its URL — lingering in browser history and server logs — and the form displayed it and sent it back with the new password, making the token only half the secret. The link now carries the token alone; the server works out whose it is by checking the hash against the broker's open resets, and the form shows nothing but two password fields.

## 2026-08-28 — v0.26.0 — build GFF5VT

### Admin

- **An email test bench, under Settings.** Owner only: type an address — your own is already filled in — press send, and a branded test email goes out, stamped with when it was sent and through what transport, so two tests can't be mistaken for one another. It's also the shop's first real HTML email, styled to the site's palette, so the test doubles as a look at what Armo Outdoor mail looks like in an inbox. Beside the form, the current mail configuration read straight from the environment — transport, host, port, encryption, whether the username and password are filled in but never their values, and the From identity. A dead SMTP server answers on the page as the error it raised, not a stack trace; the `log` transport gets an amber warning saying emails end in a file and nothing will be delivered. Encryption reads "auto (STARTTLS when offered)" rather than a false "none" when no scheme is forced — the transport upgrades on its own whenever the server allows. Every send lands in the activity log.

## 2026-08-28 — v0.25.1 — build PGMT88

### Under the hood

- **One test caught up with v0.25.0, no shipped code touched.** The test-order check still looked for the customer page's old "15,00 € spent" chip; the amount lives in the "Total spent" tile now, on its own line, and the test reads it there.

## 2026-08-28 — v0.25.0 — build 8IGRZ0

### Admin

- **A customer can be banned.** Owner only, from their page, behind the usual confirmation dialog — and reversible from the same spot. The ban closes every door at once: the login form refuses the account with its own message rather than a misleading wrong-password error, sessions already open are cut on their very next request, remember-me cookies included, and database-held sessions are purged the moment the ban lands. What the customer already did — orders, reviews, messages — stays untouched; a ban stops the future, it doesn't rewrite the past. Banned accounts leave the regular customer tabs and counts entirely and gather on their own Banned tab, kept apart at the far right of the row. Admin accounts can't be banned, and every ban and unban is written to the activity log.

- **The customer page reads like a dossier now.** The flat run of chips under the name becomes four tiles — total spent, orders, average order, customer since — each carrying its footnote, the way the dashboard already counts. Three things the page only counted, or didn't mention at all, now show themselves: the reviews the customer posted, their contact conversations — matched by account *and* by email, so messages sent before signing up surface too — and the wishlist's actual products with thumbnails and prices. The email gained a copy button, order lines carry their total right beside the number instead of a View button at the far end, and "Create manual order" opens the form with the customer already picked. The initials avatar squares up to the full height of the name-and-email block beside it.

## 2026-08-28 — v0.24.2 — build 3OZJC4

### Under the hood

- **Two test repairs, no shipped code touched.** The authorization sweep — the test that walks every admin route as a guest and expects the login door — was never given a review to build the delete-review URL with, so it knocked on a literal `{review}` and read the 404 as an unguarded route; it now carries a manual review among its fixtures. And the order-lifecycle test only passed while the clock stood still: it appended `orderBy('id')` to a relation that already orders newest-first, which merely tiebreaks — so a run crossing a second boundary flipped the history it was asserting. It now replaces the ordering instead of appending to it.

## 2026-08-28 — v0.24.1 — build 1D4MMA

### Storefront

- **Fixed:** in the rating distribution under "Avis clients", a full bar drew longer than the empty rails beside it — a rating holding every review ran past where the other lines end and touched its count. The rails are painted with an inset at each end, and the cell was meant to carry matching padding so the filled bars sit exactly on them; a blanket cell rule with higher specificity was silently zeroing that padding. The padding rule now out-specifies it, and every bar — full, partial or empty — ends on the same line.

## 2026-08-28 — v0.24.0 — build 3WGTAI

### Admin

- **A review posted elsewhere can be copied home.** The shop sells through marketplaces too, and a review left there was stranded — invisible to anyone reading the product page here. The Reviews page now opens a form in a dialog: pick the product by name or reference through the same search the discount form uses, then the customer's name as the marketplace showed it, the stars picked by clicking them, and the review itself. Optionally, which marketplace it came from and the date it was posted there — a backdated review sorts among the others as if it had always been here.

  On the shop it reads like any other review; only the admin list marks it with a quiet chip naming its marketplace, or "Added manually". Under the hood a review no longer requires a customer account and an order — the two are optional now, filled only when a review was really posted here, and the imported name travels in its own column.

## 2026-08-28 — v0.23.1 — build A3119V

### Admin

- **A review can be read where the customers read it.** Each line of the Reviews page now carries a "View in shop" button next to Delete, opening the product's storefront page in a new tab, landed directly on its reviews section rather than the top of the page. It shows for every admin, not just the owner — reading the shop isn't a destructive act — and steps aside when the review's product has since been deleted, there being nothing left to view.

## 2026-08-28 — v0.23.0 — build 1YH5FE

### Admin

- **Reviews get their own page, under Catalog.** Until now a review could only be read where it was written — on its product's page, one product at a time. The new page reads the shop's whole voice at once: every review newest first, each carrying its product, its customer, its order and its date, all linking to their own admin pages. A strip up top gives the average rating, the total, and how the stars spread from five down to one; subtabs narrow the list to a single rating, and a search finds reviews by product name, reference, customer name or email.

  A review that shouldn't stay can be deleted — owner only, like the rest of what destroys data — behind the admin's usual confirmation dialog, and the deletion lands in the activity log. It disappears from the product page for good; the customer isn't notified.

## 2026-08-28 — v0.22.0 — build JMRP3F

### Storefront

- **The mobile header carries only what fits.** Below 640px, the top bar used to repeat itself — a cart button up top, a second one lower down, a full name and a "Déconnexion" button squeezed into a row of their own. All of it now folds into five equal icon buttons next to the hamburger: cart, account, contact and theme, each the same fixed width so the row reads as one control strip rather than five different-sized boxes. A cart carrying items, or an account with unread messages, is the one exception — those two widen just enough for their badge rather than clipping the number.

  Nouveautés, Promotions, Meilleures ventes and Blog moved out of that bar entirely and into the opened category menu, at its top, two per row above the categories themselves. Only Contact stays in the bar's icon row, since the storefront's mailbox is a text form, not phone support — it reads as an envelope now, not a headset.

- **The opened category menu no longer traps itself off-screen.** It used to cap its own height and lock the page behind it from scrolling, which meant a menu opened before the page had scrolled — subheader not yet stuck to the top — could render its bottom edge past the visible viewport with no way left to reach it. It now measures where the subheader actually sits the moment it opens and pins itself to the viewport instead, so the whole menu is always reachable and scrolls on its own.

- **The account hub page ends on a logout button** instead of leaving the header as the only way out. Styled deliberately quieter than the navigation cards above it — a plain bordered button behind a hairline, not another destination to click into.

- **A pretty maintenance page.** `/503` used to fall back to Laravel's bare default. It now matches the site's own 404 and 500 pages — except it deliberately doesn't share their layout, since that layout queries the database for its category menu, and a 503 fires exactly when the database might be mid-migration or unreachable. Verified with a test that it renders zero database queries.

### Under the hood

- **`deploy.sh` no longer serves a half-updated site while it deploys.** It now wraps the risky window — pulling code, installing dependencies, migrating — in Laravel's maintenance mode, and checks the site actually responds before taking it back out of maintenance; a failed health check leaves the maintenance page up rather than exposing a broken one. PHP-FPM is reloaded rather than restarted, so in-flight requests aren't dropped, and the code is now pulled with `fetch` + `reset --hard` instead of `pull`, closing off the chance of a surprise merge commit on the server.

- **`robots.txt` is generated instead of shipped as a static file.** The static one was still pointing at a local development URL, which would have gone dead the day the site actually launched. It's now built from the live `sitemap.xml` route, and disallows `/admin`, `/cart` and `/checkout`.

- **Every response now carries a baseline of security headers** — CSP, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, and HSTS once a request is actually over HTTPS.

- Fixed a CSS cascade bug hit twice this cycle: two rules setting the same property for overlapping `max-width` breakpoints resolve to whichever one is written later in the file, not the narrower one. Both the category menu's column count and the mobile cart button's default visibility had fallen on the wrong side of that, in different files, before the fix.

## 2026-08-28 — v0.21.0 — build EKT5MZ

### Storefront

- **Card and PayPal are both off at checkout for now.** Neither the live Stripe account nor PayPal is configured yet; each option stays visible with a "Bientôt disponible" badge rather than disappearing, so the choice is still legible while nothing can actually be selected. Checkout cannot be completed until at least one comes back.

- **The product-card grid gives ground below 640px.** The homepage's category grid drops to one per row, a card's price and stock chip stop crowding side by side and stack instead, and the low-stock, supplier-availability and restocking chips swap to a shorter label rather than wrapping.

- `robots.txt` is now generated from the current `sitemap.xml` route instead of shipped as a static file — it was still pointing at a local dev URL, which would have gone dead the day the site actually shipped. `/admin`, `/cart` and `/checkout` are disallowed too.

### Admin

- **A month's accounts can carry the supplier's own paperwork.** A purchase line takes its invoice as a PDF, held on the private disk and served through the same owner-only door as the rest of the accounts — nothing under `public/` for anyone to guess at. The file opens named after the supplier and the invoice number, uploading again replaces what was there, and a PNG renamed `.pdf` is turned away because the check reads the file rather than its name. The month list now says how many of its lines are still owed a PDF, and detaching one asks first, in the same dialog the rest of the admin already uses.

- **The shop can back itself up from the admin.** One archive holding the database, the product photographs and the other private files — the code itself stays out, since it already lives in git. The button says what it's about to do, then says it again while it works, and can't be pressed twice; the archive lives outside `public/`, and its own directory is excluded so a backup never nests the ones before it.

- **An empty category can be deleted.** Products cascade with their category, and a category with children would have dropped them back to root, so deletion only opens up once a category holds nothing — no products, no subcategories. A category still in use shows the site's usual disabled-button tooltip instead.

- **A variant's own wording sits under it on the printed label**, not just its reference — two sizes of the same product used to print identical words, and the reference alone is easy to misread on a shelf. "Respirant — M" now reads directly, or the full wording where a variant names more than one thing.

- **The cover photo can be pulled as a JPEG.** The shop stores WebP, which a marketplace form, a supplier or a printer won't take. The Images panel now offers the cover full size, converted on the way out and flattened onto white first since a JPEG has no transparency to keep. Nothing stored is touched — the copy is made for the download and thrown away after.

- **Admin users can now be listed and edited over the same JSON API used for the catalogue** — name, email, role and password — with the same guardrails as the web admin: the last remaining owner can't be demoted, and every change is written to the activity log.

### Under the hood

- **Every web response now carries a baseline of security headers** — CSP, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, and HSTS once the request is actually over HTTPS.

- **Nine admin areas that were only reachable through their activity-log row — or not reachable by a test at all — are now covered directly**: settings that store euros as cents, a carrier-tier grid that replaces rather than appends, a checkbox that has to be coerced because an untouched one sends nothing, and six more guards, each checked by reverting the line it protects and watching the test fail.

- `deploy.sh` added: pull, install dependencies, build assets, migrate, recache config/routes/views, restart PHP-FPM. Points at `armooutdoor.fr`, the domain the site is shipping under.

- The backup tests were deleting the site's own real backups — the suite swaps the database for one in memory but never moved the storage path, so every run wiped the real archive directory. Where archives live now comes from config, like the rest of the backup code already did, and a test asserts the real path and the test path are never the same.

- `.env.example` no longer lists three NaturaBuy keys the real `.env` doesn't set — they still work through their own defaults in `config/services.php` if ever needed, so nothing changes; the example file just stops overpromising.

### Catalogue

- Several products gained real photographs.

## 2026-08-26 — v0.20.0 — build 49Y3T8

### Admin

- **Every article can be given a printed label.** An article is a product without sizes, or one size of a product that has them: each carries its own reference and its own barcode, and each is one sheet. The label prints the name, the line under it, the reference, an EAN-13 barcode, the importer, the origin, the batch date, and optionally what the article is made of and a warning to show.

- **A page under Catalog lists every article that could wear one**, with its thumbnail, its size, its two codes and a button to print. The wording is typed on the line it is read on, saved without leaving the page, and the button switches on the moment an article has everything it needs. Products taken off the shop, and sizes withdrawn from products still on it, are left out: a retired article needs no label.

- **Tabs sort the work.** Ready and Incomplete for a sweep, then one tab per missing piece — title, subtitle, reference, barcode — because a list of what has no barcode is a different job from a list of everything unfinished. Every count speaks for the whole catalogue rather than the page in hand.

- The name and the line under it are stored in capitals, so a label reads the same whoever typed it.

### Under the hood

- The barcode is drawn from the encoding itself, ninety-five bars and their three guards, rather than by pulling in a library to paint rectangles. A twelve-digit code is padded to thirteen; a length belonging to another symbology prints its digits alone rather than bars a scanner would misread.

- The label's wording lives in its own table rather than as four columns on every product, and a label emptied of every field is deleted: the row's existence is what "this product has wording" means.

- "Catalogue" in the admin menu is spelled Catalog, and the small heading over a field is named for what it labels rather than for the filter bar it was first written in.

## 2026-08-26 — v0.19.0 — build VEL512

### Admin

- **The shop keeps its own accounts.** A new Accounting section, owner only, holds two halves — sales and purchases — each listing every month from January 2026 onwards. A month appears by itself on the first, with nothing to create, and each card says how many lines it holds.

- **A month of sales shows what came in**, line by line: the date, the invoice number, the client, the channel it was sold through, the kind of sale, the total, the fees withheld and what is actually perceived once they are. The shop's own orders and anything typed by hand sit in the same table, sorted by date, since an entry written by hand is a line of the accounts and not an appendix to them. Refunded orders are listed struck through but join no total: the money went back out.

- **A month of purchases shows what went out**, entered as a supplier's invoice reads — the total paid and the rate charged — with the amount before tax and the tax itself worked back from those.

- **Either month prints as a journal for the accounting book.** One page per month, landscape, in French, carrying the company's letterhead and a blank block to date and sign. The sheet prints exactly the lines the screen shows.

- **A month cannot be printed while it is still running**, nor when it holds nothing: an accounting sheet that would say something different a fortnight later is worse than none, and an empty one is not a document. The button says which of the two it is.

- **Every copy taken out is written down** — which month, by whom, at what time — and each download keeps a fingerprint of what the sheet said. A month whose figures have moved since then says so and asks to be printed again, while a change the journal never prints, an internal note or a tracking number, says nothing. The list of months marks at a glance what has been filed, what has moved, and what is still waiting.

## 2026-08-26 — v0.18.0 — build XI4OWN

### Storefront

- **The product page finally tells search engines what it is selling.** The home page, the categories, the blog and the FAQ all emitted structured data; the product page, the one that matters most, emitted none. It now carries its price, its availability, its average rating and its reviews, plus a breadcrumb trail.

- **The four figures that decide a purchase sit next to the price**: quantity, size, material, colour. The full specification table still says everything, in two columns rather than one long fall — sixteen rows in a single column asked for a page of scrolling where every row looked equally important.

- **The stars were drawn and never said.** Every rating now carries its value in words for anyone using a screen reader, the rating at the top of the page leads to the reviews at the bottom, and the average shows the shape of its sample: three and a half out of two opinions is not a verdict, and a distribution says so better than an average alone.

- **The page says what the shop already enforced**, that only a customer whose order has shipped may leave a review, and marks each review as a verified purchase. It is a guarantee few shops can give and the page was keeping it to itself.

- "Accueil" leaves the main menu. The logo already led home, and the row of tabs is for the places that are not one click away anyway.

### Admin

- **A purchase order prints a receipt sheet to tick while unpacking.** It lists the lines with the quantity ordered, a box to write the count actually found, and two boxes to tick: Received and Handled. No prices — that is not what is checked with a crate open. The boxes print empty even on an order the system already counts as received, since a box ticked in advance invites trust in the paper rather than a look in the carton.

- Both the purchase order and the receipt sheet end with a signature block for ArmoOutdoor: name, date, and room for a real signature, all left blank.

## 2026-08-26 — v0.17.0 — build 8DISR4

### Storefront

- **A product's old address keeps working.** Every slug a product has ever carried is now kept, the current one marked active, and visiting a retired one answers with a permanent redirect to the page's current URL. Renaming a product used to break every link already in circulation — shared, indexed by search engines, or printed on a marketplace listing. A retired slug also belongs to that product for good: it can never be handed to another one, since the redirect would quietly start pointing at the wrong item. A product may always return to one of its own former addresses.

### Admin

- **The product list carries the slug and a review flag.** The slug sits under the name and reference in small monospaced type, cut with an ellipsis and shown in full on hover, since it is an address read character by character rather than a name skimmed. A new "AI OK" column shows a tick or a cross for `ai_validated`, a field that records whether a page has been reviewed and passed; it changes nothing on the storefront. The ID column no longer claims more width than a few digits need.

- The products API can now write the `slug` and the new `ai_validated` field. Uniqueness is checked against the whole slug history, so a generated slug skips past a retired one rather than stealing a live redirect.

### Documentation

- **How a product page is brought up to standard is written down**, in `docs/admin/make-products-ok.md`: what counts as finished, the order to work in, and the two steps that get skipped first and cost the most — looking at the photographs before writing about the product, and reading the rendered page before marking it validated. The products API reference gained the slug and review-flag rules alongside it.

### Catalogue

- Several products gained real photographs.

## 2026-08-25 — v0.16.0 — build K2GHJS

### Admin

- **A purchase order downloads as a PDF to send to the supplier.** It carries the lines ordered, the unit costs, shipping, any discount or additional costs, the VAT and the total to pay. What has already been received is deliberately absent: that figure keeps moving after the order is sent, and the supplier reads what is being ordered, not the state of our receipts. The document is dressed like the delivery slip, since both come from the same house — the designation takes the full width with the variant and supplier reference in small type underneath, rather than half-empty columns of their own.

- **The navigation is five entries instead of thirteen.** Sales, Catalogue and System gather what belongs together and open on a click, like the Actions menus in the lists. A folded group still carries the sum of the counts it hides, so putting Orders away does not hide the number that is looked at all day. System sits at the right edge: what is set once a month has no business in the middle of the bar.

- Two arrows next to an order number step to the order before and after it by date, whatever search or filter led there.

### Under the hood

- **Every PDF was embedding full-size product photographs.** A thousand-pixel-square picture was decoded whole and written into the document to print a 36-pixel square, which made one purchase order of eleven lines take six and a half seconds to build. Printed images are now shrunk once and kept aside, keyed on the source file's date so a replaced photograph regenerates its own copy. That order now takes two tenths of a second, and the file carries 66 kB of image data instead of 903 kB. The delivery slip and the invoice were doing exactly the same thing.

## 2026-08-25 — v0.15.0 — build YLJ6SS

### Storefront

- **The shop has a blog.** Articles carry a cover image, a category, a summary and their own meta title and description, and only show once they are published and their date has passed, so an article can be written today and appear on Monday. Categories have their own URLs. Bodies may contain images, but only ones served by this site: an article pulling a picture from somewhere else would break the day that somewhere else changes its mind. A cover can name its photographer, always printed as "Photo ©" so the wording never drifts from one article to the next.

- **An order that is on its way but not yet delivered says so.** "Shipped" covered both the parcel handed to the carrier this morning and the one out for delivery tonight. A new "In transit" status sits between shipped and delivered, in its own colour — the first one chosen looked like delivered at a glance, which defeats the point of a status.

- **A product waiting on a delivery says "Approvisionnement en cours"** instead of "Rupture de stock" or "Dispo fournisseur". Stock already ordered from a supplier is not stock you can promise: the product can no longer be bought against supplier availability while a purchase order for it is open, since a customer ordering it would be waiting on the same delivery, twice over.

- The contact page no longer prints a postal address, and the email address is now contact@armooutdoor.fr.

### Admin

- **The order list is split by status, the way the product list already was.** Every status has its own tab with its count, and Archived and Test sit apart at the far end since they are not stages of an order's life but places it is filed away.

- **The seven figures above the order list are now three cards** and hold on one line. They were laid out on a six-column grid, so the seventh dropped to a line of its own on every screen.

- **NaturaBuy listings are visible from the admin**, under a new Marketplaces section. The listing is compared with the catalogue by reference, falling back to a prefix when a listing covers several sizes sold here as separate variants, and tabs single out what disagrees: what is listed but not in the catalogue, what is in the catalogue but not listed, and where quantities or names differ between the two. Closed listings are left out. Everything can be resynced from the page.

- **An order number and each of its references copy on a click**, with a toast to confirm. Two arrows next to the order number step to the order before and after it by date, whatever search or filter led there — staying among orders of the same kind, since drafts, test orders and archived ones each have their own page and count differently everywhere else.

- Products on an open purchase order are marked as being restocked in the product list and on the product form, in their own colour, so it is clear the shelf is empty on purpose.

- The admin product API accepts everything the product form does, and both it and the new blog API are documented for whoever, or whatever, calls them.

### Catalogue

- The Walther PPK/S, the Beretta Px4 Storm spring replica, the Mechanix M-Pact Woodland gloves and the two M-Pact mitaine colourways are on sale. Several existing products gained real photographs.

### Under the hood

- The description editor destroyed every bulleted list it was asked to load. It wrote the stored HTML straight into the page instead of handing it to the editor's own parser, and the first save after that wrote back what was left. One article had lost seventeen blocks this way before it was caught.

- Relative dates in the admin were printed in French on an otherwise English back office.

- The shared pager was styled in a stylesheet the admin does not load, so it appeared unstyled on nine admin pages.

## 2026-08-24 — v0.14.0 — build UXT3NP

### Admin

- **The dashboard answers "how is this week going?" instead of "how has it gone since the beginning?"** Every figure on it was lifetime — lifetime revenue, lifetime orders, all-time best sellers that barely moved month to month. One period selector now frames the whole page, from today to the last ninety days, and each number carries how it compares with the previous stretch of the same length, named rather than left to guess at.

  Work to do and things to look at were drawn as the same card, so "three parcels without a tracking number" sat among figures nobody acts on. What needs attention now has its own strip at the top, and that strip disappears entirely when nothing is wrong — one that is always full teaches you to skip it. Purchase orders waiting to be received, unread messages, best customers and recent stock movements are on the dashboard for the first time.

  Revenue is drawn as a line against the previous period rather than seven bars, and every chart is doubled by a table underneath it: the figures stay readable when a chart cannot be, and no value is reachable only by hovering.

- **A purchase order can carry a discount and additional costs.** Both are typed the way the supplier writes them and follow the order's VAT rate, exactly like the shipping line already did — a rebate comes off the total, customs or a surcharge goes on. They appear on the order only when they are actually set, since most orders carry neither.

- **A product's average purchase cost now accounts for those discounts and costs**, shared across the order's lines in proportion to how much each one received: a line of nineteen pieces absorbs nineteen times what a line of one does. The "Average paid" figure on a product opens a page showing the arithmetic — one row per delivery received, and how the average was reached from them.

### Under the hood

- The dashboard's best-sellers query read every order line ever sold into memory and then fetched a product for each distinct reference, before keeping the top five; the database does the counting now. It groups by reference rather than by product, because a deleted product leaves its sales behind and they have to stay readable under the name recorded at the time of the sale.

- The daily revenue figures used a date function only SQLite understands, which would have failed outright on any other database. Three columns the dashboard and the order list filter on constantly — status, archive date, creation date — were not indexed.

- Charts are drawn with Chart.js 4.4.7, committed into the repository rather than loaded from a CDN, which would otherwise have been the only outside thing this site needs at runtime to work.

## 2026-08-24 — v0.13.0 — build JDO5AZ

### Admin

- **An order can be invoiced as soon as it's being prepared, not only once it ships.** The order was already confirmed and paid at that point; a customer still waits for the parcel, but the back office had no reason to wait too. The two audiences now read separate rules — a customer's own invoice still waits for shipping.

  Downloading that early can still be missing what the shipping label will carry — carrier, tracking number, package type — so the download now opens a warning listing exactly what's absent before it goes ahead. The download itself is never blocked: the warning is a courtesy, not a lock, and it works without JavaScript.

- **An invoice names the carrier that actually shipped the order**, not the one chosen at checkout. The two can differ — the correcting field already existed for exactly this, the invoice just wasn't reading it.

- **A product's edit page shows what it actually costs to buy**, averaged from its received purchase-order history and weighted by quantity — a nineteen-unit delivery counts for more than a one-unit one. A line ordered but never received doesn't enter the figure, since a promised price isn't a paid one.

- **The orders list prices each order's margin.** Two new columns, P. costs and Profit, and a KPI card summing profit across every order in scope. Both go blank the moment one line can't be priced — a deleted product, or one with no purchase history — rather than quietly summing only the lines that can; the KPI card says how many orders that leaves out of the total.

- **The Channel column shows the marketplace logo alone**, its name moved to a tooltip; a manual order with no marketplace reads as a dash instead of the word "Manuelle". Three column headers shortened to fit: Free del., Costs, Perceived.

## 2026-08-24 — v0.12.0 — build ZZJLBX

### Admin

- **Stock now comes from suppliers on purpose.** Restocking was a bare quantity field with no record of what was ordered, from whom, or at what cost. Purchase orders give it a lifecycle instead — draft, sent, received, possibly across several deliveries — and every receipt is written to a timeline naming who received what. Stock moves in exactly one place, at receiving, under row locks: a deleted product's receipt is booked without moving stock, and a product that gained variants since the order was raised is never credited directly, since the next reconcile would erase it.

  Prices are typed as the supplier shows them and stored excl. VAT; the rate itself is kept on the order, so reopening a draft shows the figures the way they were written rather than silently converting them a second time. VAT applies to the line total rather than to the unit price — rounding the unit first and multiplying was repeating the rounding error once per unit.

- **Every stock figure now says why it changed.** A number that looked wrong had no history to check — the activity feed is a free-text log, populated inconsistently, with no before-or-after. A page per product now lists every movement: when, how much, what it went to or came from, and who did it. Nothing calls a logger for this; an observer on the product and its variants writes the row whenever a quantity changes, which is what makes the ledger impossible to bypass. A change that declares no reason is still recorded, as Unattributed, rather than silently missed.

  A `stock:backfill-history` command reaches back over past orders and purchase-order receipts to fill the ledger in, without moving a single quantity — the balances are walked backwards from what's known today, so they land exactly on the current stock, though they describe a consistent past rather than a verified one.

- **A refunded line can be put back on the shelf.** Refunding an order only ever changed its status, since the refund and the physical return are different events — nothing declared the second one. Each line on a refunded order now carries a quantity field, capped at what's left to restock, and its own reason in the ledger. It can be restocked in more than one pass, the same way a purchase-order line can be received in several deliveries.

## 2026-08-23 — v0.11.1 — build Y6PLK4

### Admin

- **The product list gives its space to what is worth reading.** The barcode column printed thirteen digits on every row to answer a single question — is it set? A tick answers it now, and the count appears only when a product sold in several sizes is missing one, so the detail takes room where it earns it.

  The weight keeps its figure, since it reads at a glance and decides the price of a parcel. Its absence is what needed marking: two hundred and eleven active products carry none, and a dash there looks no different from an empty cell.

## 2026-08-23 — v0.11.0 — build X8RTM6

### Admin

- **The product list now says what its stock figures mean.** A number alone left the reading to you: zero could mean nothing left, or nothing here but the supplier can fetch it, and forty units looked no different from one. A new Availability column names the four states — in stock, last pieces, at supplier, out of stock — in the same colours the shop already uses for them, so a state does not change appearance between the storefront and the back office.

  A product sold in several sizes is judged on its sizes rather than on its total, since what matters is whether a customer can buy something on that page.

- **Out of stock now means out of stock.** Thirty of the fifty-one products it listed could still be ordered from the supplier, which buried the ones that genuinely needed restocking. Two tabs join it — In stock and At supplier — and the three now cover the active catalogue exactly once each. With eight tabs in the bar, a rule opens each family and Disabled sits apart at the end.

- **Products can be found by their reference.** The search read the name and the slug only, though the reference is what is printed on the article and what a marketplace sends back. It now covers the references carried by a product's sizes too: forty-six products hold none of their own, so the number on the item was the one way of finding them that did not work.

- **Missing SKU stops listing products that are done.** A product sold in several sizes carries its reference on each size, not on itself, so fifteen of the thirty-five rows had nothing left to fill in.

**Fixed:** the panel that opens under a product with sizes stopped one column short of the table it sits in.

## 2026-08-23 — v0.10.3 — build W7NKD5

### Storefront

- **An article available only from the supplier now says so plainly.** Its badge wore the same amber as low stock, so one colour meant two different things — almost none left, and none here but we can order it. The second state has its own slate blue now, on the listing cards, the product page and each size of a product sold in several.

  The product page also carries a short note above the delay: we do not have this article in stock, our supplier does, and we can order it for you. It appears and disappears with the size you pick, in step with the delay it explains.

## 2026-08-23 — v0.10.2 — build V4LQC8

### Storefront

**Fixed:** a double click on "add to cart" could leave the same article in the basket twice, each line carrying its own quantity. The rule meant to prevent it did not cover products sold without variants, because of how databases compare an absent value. It does now, and a second click quietly updates the line already there.

### Admin

**Fixed:** the "view in Stripe" links always pointed at Stripe's test data. The first real payment would have opened a page showing nothing — which reads as a missing payment rather than a wrong link. The link now follows the key the shop is configured with, and falls back to the test view when that key is anything unexpected.

**Fixed:** entering the same product on two lines of a manual order let stock fall below zero — each line was checked against the whole shelf, then both were taken from it. The two lines are now added together before any stock moves, so the order is either served in full or refused outright.

### Behind the scenes

- The rule for taking stock — sell it, put it on backorder, or refuse the line — lived in three separate copies that had already drifted apart. It now lives in one place, with the difference between a customer order and a manual one kept as a deliberate setting rather than an accident.

## 2026-08-23 — v0.10.1 — build T9YBQ4

### Admin

- **A manual order now records its pickup point.** Choosing a point from the carrier's list filled the shipping address and then forgot which point it was, so a relay order was saved without knowing where its parcel gets collected — the address carries the shop's name, not its identity as a pickup point. The point has its own fields now, filled by the picker and editable afterwards, because a marketplace imposes its own relay and that one appears in no list of ours.

  A relay delivery must name its pickup point before the order can be finalized. A draft may still be saved without one: a draft is work in progress, and the pickup point is often the last thing known.

## 2026-08-22 — v0.10.0 — build R2VKD7

### Admin

- **The changelog page was showing less than the changelog file.** It understood a release, a section and a bullet with its sub-bullets, and quietly discarded everything else — nineteen paragraphs in all. Every "Fixed" note closing a section had never been readable here, nor had the second paragraph of any longer entry. Both now appear: a continuation sits under the bullet it belongs to, and a closing note is set off by a rule against the section it ends.

  A test now walks every line of the file and checks it reaches the page, so the next piece of formatting the page cannot read will fail rather than disappear.

## 2026-08-22 — v0.9.9 — build N8QWJ2

### Admin

- **A draft order can be validated from its own page.** Turning a draft into a real order meant reopening the whole edit form just to press save-and-finalize, re-reading every line on the way. A Validate draft button now sits beside Edit draft and does the same work in one click: it takes the stock, sets the order to placed and opens its status history. A confirmation names what is about to happen, down to how many units leave the shelf.

  Stock is allowed to go negative rather than blocking the validation — a marketplace sale happened whatever the shelf says, and refusing would only stop the shop from recording it. Clicking twice cannot take the stock twice.

## 2026-08-21 — v0.9.8 — build M4XPZW

### Admin

- **An order address can now name ten countries instead of four.** Marketplace sales travel further than the shop's own checkout does, and a country missing from the list could not be saved at all — a Portuguese order already on file could not have its address corrected. Germany, Spain, Ireland, Italy, the Netherlands and Portugal join France, Belgium, Switzerland and Luxembourg. The dropdown reads France first, then alphabetically by name.

  The shop's own checkout is unchanged and still ships to France only: opening it further is a question of shipping rates, not of a list.

## 2026-08-21 — v0.9.7 — build K7VQD3

### Storefront

- **The homepage hero became a carousel of four panels.** One image and one message held the top of the page, and nothing else could be said beside it. The original hero is now the first of four: new arrivals, promotions and best sellers follow, each linking to the aisle it names. Panels alternate sides — text left, then text right — so a slide reads as a new one rather than the same block redrawn, and the mirroring is dropped on narrow screens where there is no image left for the text to sit against.

  It rotates every six seconds and stops the moment anyone shows interest: a pointer on the panel, a focused link inside it, or a tab moved to the background. Arrows, dots and the left and right arrow keys all move it by hand. Nothing rotates at all for a visitor whose system asks for reduced motion.

  All four panels are in the page as served, so a reader without JavaScript still gets the whole message; the arrows and dots are the reverse, shipped hidden and revealed by the script, since a control that does nothing is worse than no control. Panels waiting off-screen cannot be reached by keyboard.

  The three new panels borrow the existing hero image for now.

## 2026-08-21 — v0.9.6 — build H3KQ7M

### Admin

- **The orders list can be navigated again.** Past the first page there was only Previous and Next, no page numbers, no total, and no way to jump: reaching an order from three months ago meant clicking Next until it appeared. The list now numbers its pages, shows a sliding window of them around the current one with the first and last always reachable, and announces where you are — "Showing 21–40 of 83". Filters survive the jump. The other admin lists share the same pager and gain the numbers too.

- **The order total is broken down by status.** Next to the total count, three chips now give the shipped, delivered and refunded counts, each in the colour that status already wears in the table below. They cover the same orders as the total — archived ones included, test orders and drafts left out — so the figures can be read against one another.

**Fixed:** the coloured chips in list headers had no colour in light mode, and the muted chip on a customer's page was never dashed or greyed as intended. The variant rules were written before the rule they were meant to override, which at equal weight means they lost; only the dark theme, being more specific, came through.

## 2026-08-21 — v0.9.5 — build TR0RAN

### Orders

- **A cost of zero no longer looks like a cost never entered.** Commission and shipping paid both showed an em dash whether they had been checked and found to be nil or simply left blank, so an order already dealt with looked identical to one nobody had touched. A recorded zero now prints as 0,00 €; the dash is kept for the case it actually means.

**Fixed:** a marketplace order created before its platform had an invoice note printed without the legal mention about platform-collected fees — and would have kept doing so forever, since the note is copied onto the order at creation. The invoice now falls back to the platform's current note when the order carries none, while an order that already has one keeps its own wording, so invoices already issued cannot change retroactively.

### Admin

- Logos added for the Vinted and eBay marketplaces.

## 2026-08-21 — v0.9.4 — build 8XG4PS

### Admin

- **Top products now shows a thumbnail**, matching the Stock alerts panel beside it — the two sat side by side and only one showed what the product looks like. The tile links to the product, and shows the product's current image, so swapping a photo shows through straight away.
- A product with no image, or one since deleted, keeps a tile of the same size rather than losing it, so the text column stays lined up with the rows above.

## 2026-08-21 — v0.9.3 — build A7UMDY

### Orders

- **Orders can now be marked as delivered.** A shipped order stopped there and stayed until it was refunded or forgotten; delivered closes the useful part of its life. Set from the order page, one order at a time, behind the same confirmation as the other status changes.
- The status wears blue — the one colour the row had left, and far enough from the green of "shipped" that the two do not blur together in a long list. The customer sees "Livrée" on their own order.
- A delivered order can still be refunded. Returns and complaints arrive after delivery, so that is precisely when refunds happen.

**Fixed:** two things the new status would have broken quietly. The right to review a product required the order to be exactly "shipped", so marking it delivered would have removed that right at the moment the customer finally had the product in hand. Best-sellers ranked on placed, preparing and shipped, so delivered orders would have dropped out of the sales counts and demoted the products that sell best.

## 2026-08-21 — v0.9.2 — build K9VCB1

### Orders

- **A tracking number is now a link.** The shipping block on an order opens the carrier's own tracking page in a new tab, instead of leaving a number to copy into a carrier site by hand. Colissimo and Lettre suivie go to La Poste, both Chronopost offerings to Chronopost, and the rest to Mondial Relay — who want the recipient's postcode alongside the number, taken from the delivery address and falling back to the relay point.
- A carrier with no known tracking page keeps a plain, selectable number. So does a Mondial Relay parcel with no postcode on file: a link to an empty search form is worse than a number to copy.

**Known:** the link is admin-only for now. A customer looking at their own order still sees the number as plain text.

## 2026-08-21 — v0.9.1 — build TBSXWI

### Storefront

- **Category pages are paginated, twenty products a page.** Vêtements listed 119 products in one go, and every image on it loaded before a visitor could reach the second row. The pager shows a sliding window of five page numbers with first, last and ellipses, so a large category does not print thirty numbers in a row, and a line underneath says where you are — "Produits 21 à 40 sur 119".
- Filters and the sort still apply to the whole category, not just the page on screen, and changing either returns to the first page. A page number typed beyond the last falls back to the last rather than showing an empty grid.

**Fixed:** the product count in the category header counted what was on screen. Once paginated it would have announced twenty pieces for a category of 119.

## 2026-08-21 — v0.9.0 — build 1TX1B2

### Admin

- **A product now records what it costs and what it should sell for.** Two fields in the supplier panel: the purchase price excluding VAT, and the markup wanted on it. Neither is ever shown to a customer.
- Below them, the resulting **recommended price** — purchase plus 20% VAT plus markup, rounded up to the next ,49 or ,99. Never down: rounding down would eat the margin just asked for. It recalculates as either field is typed, and the rule is written out under the figure so the number can be checked rather than taken on trust.
- An **Apply** button fills the Price field, confirming first when a different price is already there. Nothing is saved until Save changes, and the modal says so. The Price field flashes so it is clear where the value landed.
- The supplier panel can now be **saved on its own**, behind a confirmation and a toast. Noting a purchase price should not republish the description, the photos and every variant row.

**Fixed:** creating an empty category took the homepage down with a 500 — and so did deactivating the last product in a category, which is the same fault arriving by an unrelated route. The homepage's featured-product logic returned a collection its own callers could not use as soon as one root category had nothing to show.

### Under the hood

- Three tests fetched the homepage and all three stayed green through the outage above. Every fixture in the suite builds categories with products already in them, so a category with none was unreachable from test data. The homepage is now exercised against thirteen catalogue shapes an admin can actually create; five of them fail against the old code.
- Purchase price and markup are stored as integers — cents and basis points — because this schema has no decimal columns and a float has no business holding money. Basis points also keep a 32,5 % markup exact.
- The recommended price is capped at the maximum the Price field accepts. Above it, applying would have written an out-of-range value and the browser would have refused to submit without saying why, leaving the Save button apparently dead.

## 2026-08-21 — v0.8.2 — build RT4R3N

### Storefront

- **The product reference is now on the product page.** Every product carried a SKU that no customer could read. It sits under the title as a quiet "Réf." line — small muted label, monospace value, selectable in one click so it can be pasted into an email or an order without retyping. It follows the selected variant, the way the price and the lead time already do, since that is the reference actually going in the cart. A product without a reference shows no line rather than an empty label.

**Fixed:** every multi-paragraph product had a broken meta description. Plain-text extraction ran the end of one paragraph straight into the start of the next — "…et chargeur.Le DLV36 reprend…" — because tags were stripped without leaving a space behind. Block boundaries now become spaces; inline tags still close up, so a bold word gains nothing.

### Catalog

- Seven airsoft replicas added: the Classic Army Nemesis LS-12, the ASG DLV36 pack, the Walther PPQ and Ruger P345 pistols, the ASG Urban Sniper and the Black Ops Kar 98K, each with full characteristics, supplier reference and package weight. Placeholder images stand in until the real ones arrive.
- The airsoft section was reorganised into **Répliques de poing**, **Répliques longues** and **Répliques sniper**. The Browning 1911 had been filed under Revolvers.
- Characteristics across the airsoft range now follow one schema and one order, so two fiches can be read side by side. Manufacturer references and EAN codes were dropped from the public specs.
- Six target listings rewritten against their own photographs. Several were wrong: one described colours it does not have, one was sold as reactive when it is a plain scoring target, and one had "test" as its entire description — which was also its Google snippet. Specifications that had been dumped into the description as paragraphs are now proper characteristics, with filters and package weights.

## 2026-08-21 — v0.8.1 — build TKBHYX

### Storefront

- **The free-shipping threshold now leads the homepage.** A quiet strip above the logo, with the amount carrying the weight. It was buried halfway down the page, below the categories, where someone deciding whether to add one more item never saw it. Homepage only, and it appears only when there is a real figure behind it.

**Fixed:** the homepage worked its free-shipping figure out from the threshold alone, but free shipping is granted per carrier. A threshold set with no carrier flagged had the homepage advertising a discount checkout would never apply. Both the new strip and the existing mid-page banner now require a carrier behind the number.

### Under the hood

- Nothing asserted that saving a draft order leaves stock alone, which is the whole reason deleting a draft is safe. Reserving stock at draft time would have made deletion quietly lose inventory with the suite still green. Three tests now cover it: a saved draft moves no stock, finalizing moves it exactly once, and deleting the draft takes nothing with it.

## 2026-08-20 — v0.8.0 — build LSPEJ6

### Orders

- **Orders placed while testing can be marked as such instead of deleted.** The record is kept in full, but the order leaves every figure in the admin: revenue, order counts, the charts, the marketplace breakdown, top products and the customer's lifetime spend. Marked orders move to a fourth tab of their own and carry a Test chip wherever they surface, including admin search — which stays the way to find one and unmark it.
- Marking is owner-only, like refunding rather than like archiving: archiving hides an order, this moves money out of the figures. It is reversible, singly or in a batch, and the activity log records both directions.
- **Nothing is undone by marking.** The stock the order took and the invoice number it used are not given back, and the confirmation says so plainly, so nobody marks a batch expecting their stock to return. Nothing about it reaches the customer.

### Changed

- **Archived orders now count towards the money.** Archiving tidies the working list; it does not unmake a sale. The euro KPIs, the order counts behind them, net revenue, the seven-day chart, the marketplace breakdown, top products and customer lifetime spend all cover archived orders again. What is left to prepare and what is missing tracking still exclude them, because archiving is exactly how you say you are done with an order.
- A customer's admin profile now lists their archived orders, with a badge. Their spend counts those orders, and a total covering rows the page does not show cannot be checked by anyone.
- **Drafts are deleted rather than archived.** A draft records nothing that happened — nothing charged, nothing shipped, no invoice number taken — so there is nothing to file away. Owners delete them from the row menu, the draft's own page or the Drafts tab in a batch; everything past draft refuses deletion just as firmly. Deleting is the one irreversible action here, so it is the only one wearing red.
- Drafts also refuse being archived or marked as test, so deletion is the only way one leaves the Drafts tab.

**Fixed:** the Orders tab count plus the Archived tab count came to more than the total the KPI above them reported, with nothing on the page to explain the gap. Archived was the only tab sweeping up archived drafts as well. Drafts now sit apart wherever they are, and the tab counts add up to the KPI.

**Migration:** two, run with `php artisan migrate`. One adds the column, one clears the archived flag from drafts that were archived while that was still allowed. Neither deletes anything.

## 2026-08-20 — v0.7.1 — build GQODHO

### Storefront

- The account section for discount codes is now called **Mes codes de réduction** rather than "Mes réductions", which read as money off — what a discount does, not what the page holds. Singular "réduction" to match the wording already used at the cart and checkout.
- The account nav tab now carries a **count of the codes the customer can use**, so a code reserved for someone is visible from any account page rather than only once they think to look. Tinted rather than filled, unlike the unread-messages badge beside it: an unread reply is something to act on, a standing count of codes is a fact.
- The hub card now reads that same count instead of working it out again. Both showed the same figure by running the same filter twice, which is a disagreement waiting to happen.

### Changed

- The figure on each account hub card — the email, the address count, the order count — sat in the same muted grey as the description right above it and read as a third line of blurb. Each card now closes on its figure: pushed to the foot on a hairline, in the body colour, with tabular figures so "0 commande" and "12 commandes" do not shift the line. The rules land at the same height across a row, which the ragged spacing had been hiding.
- The numbering on those cards sat over a centimetre from the title it labels, because the kicker carries a bottom margin sized for the homepage that landed on top of the card's own gap. Number and title now read as one heading.

## 2026-08-20 — v0.7.0 — build 9S4EA5

### Storefront

- **Customers can now see the discount codes reserved for them.** A new "Mes réductions" page in the account, listed as vouchers with the amount, the code and how many uses are left. Only codes tied to that customer appear; public codes stay as they are distributed rather than becoming a coupon directory for anyone who logs in.
- Whether a code is usable is decided by the same method checkout uses, so an expired, sold-out or already-used code drops off the page on its own. The one thing the account cannot judge is a free-relay code on a cart where relay delivery is already free, since that depends on the cart.
- Each voucher carries a **Copier le code** button rather than a link to the cart, with a toast confirming the copy and a fallback that selects the code where the clipboard is unavailable.
- Codes with a deadline show a **live countdown** that ticks down to the second, switching to the warning palette inside the last 48 hours. It renders server-side first, so it is right before any script runs and still shows a value without JavaScript. Seconds drop away past a week out.
- When a countdown reaches zero the voucher withdraws itself — struck through, dimmed, copy button disabled — so the page stops offering a code the checkout has already started refusing.
- The list is ordered by how soon each code lapses, with undated codes last. It was newest-first, which buried a code expiring tomorrow under one valid for three years.
- Deadlines falling in the middle of the day now show the time. The admin form defaults to 23:59, so existing codes read exactly as before, but a code ending at 09:00 no longer reads as valid all day.

### Under the hood

- Listing a customer's codes cost roughly three queries per code, and the account hub repeated the whole thing for its count. Twelve codes ran 41 queries; the listing query now aggregates each code's usage in one pass and the page stays under a dozen regardless of how many codes there are.
- Both surfaces share `User::usableDiscountCodes()` instead of repeating the eligibility filter, so the page and the hub's count cannot drift apart.

**Fixed:** the countdown showed "3j 03h" for a code with exactly three days and four hours left — `ends_at` is stored to the second while `now()` carries microseconds, so truncating the difference dropped a whole unit. Both the server and the browser now round up, which also keeps them agreeing at the handover to the first tick.

## 2026-08-20 — v0.6.2 — build NFYJQY

### Orders

- Marking an order as being prepared, shipped or refunded no longer reloads the page. The badge, the action buttons, the invoice and delivery-slip links, the status history and the confirmation dialogs all update in place, and a toast confirms the change.
- Anything unexpected falls back to the ordinary page submit, so a refusal — a staff member attempting a refund, say — is still shown in full. Without JavaScript the page behaves exactly as before.

**Known:** the count on the Orders nav item still reflects the old status until the next page load.

## 2026-08-20 — v0.6.1 — build Y0S29P

### Storefront

- The contact page now leads with a hero, matching the nouveautés, meilleures ventes and catégories pages. No background image yet — drop a `contact-hero.webp` into `public/images/pages/` and it picks one up.
- It also headed itself with an `<h2>` rather than an `<h1>`, unlike every other page; the hero corrects that.

## 2026-08-20 — v0.6.0 — build BSEIPM

### Orders

- **Select several orders and archive them in one go.** A checkbox per row with a select-all for the page, a bar that appears once something is ticked, and a confirmation naming the count. Shift-click selects a range. The Archived tab offers Unarchive instead.
- Selection covers the current page only, so the request carries the orders you actually ticked — the server never re-runs the list's filters, and cannot act on an order that arrived after the page was loaded.
- Orders archived by someone else in the meantime are skipped rather than failing the batch, and the message counts what actually changed. Each order still gets its own activity-log entry, so "who archived this order" keeps working.

### Admin

- Validation errors now appear as a banner at the top of admin pages. Previously only success messages were shown, so a form with no field to attach an error to — a bulk action — redirected back looking as though nothing had happened. Forms with inline field errors now show both, which is the usual error-summary pattern.

## 2026-08-20 — v0.5.2 — build 5KRG63

### Fixed

- **Card orders using a free relay-delivery code recorded the wrong total.** The customer was charged correctly, but the order was saved with the full delivery charge added back — on a 79,00 € basket, Stripe took 79,00 € while the order said 82,90 €. Card payments only; PayPal was unaffected. Because the waiver was not recorded, those orders also showed "No" under Free delivery, printed "Réduction −0,00 €" on the invoice, and exported a row that did not add up.
- On the same path, a code could be consumed without being worth anything.

**Existing card orders placed with such a code carry the wrong total and need correcting by hand — the fix only applies to new orders.** The affected ones are card orders whose discount code was a free relay-delivery one; their total is overstated by exactly the delivery charge.

## 2026-08-20 — v0.5.1 — build D1SSUL

Follow-ups to the free relay-delivery code added in v0.5.0.

### Fixed

- **The invoice didn't add up when a code waived delivery.** The waived amount appeared nowhere, so sous-total − réduction + livraison came to more than the Total TTC printed below it, by exactly the waived shipping. There is now a "Livraison offerte" line under Livraison, and "Réduction" only prints when something actually came off the goods. **Invoices already downloaded for such orders have the wrong figures baked in — regenerate them from the admin.**
- The orders list showed "No" under Free delivery for code-waived orders. A code waives the charge without changing the shipping figure, which keeps the carrier's real price for the invoice. The column now reads "Yes (code)" for a waiver, plain "Yes" when the cart reached the free-shipping threshold.
- Order pages, in the admin and the customer's account, showed "-0,00 €" beside the code instead of naming it.

### Changed

- The orders CSV export gains a "Free delivery" column, so a code-waived row reconciles from its own figures. **Total moves from column 12 to 13** — check anything reading that file by position rather than by header.

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
