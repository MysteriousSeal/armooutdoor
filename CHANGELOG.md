# Changelog

All notable changes to this project since the initial commit are documented here, newest first.

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
