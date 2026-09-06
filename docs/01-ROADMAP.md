# 01 — Roadmap

Six phases, dependency-ordered. Each phase ends with green tests and a commit.
Bug IDs (A1…A11) refer to the register in [00-AUDIT.md](00-AUDIT.md).

```
Phase A (stabilise) ──> Phase B (money) ──> Phase E (deploy free)
        │                     ▲                      ▲
        └──> Phase C (reader) ─┘                     │
        └──> Phase D (UI) ────────────────────────────┘
                                Phase F (growth) ── after launch
```

Phase A is complete. Phase B depended on A4, which is now fixed. Phases C and D are independent of each other and of B;
run whichever you have appetite for. ~~Do not deploy before A2 and A5 are resolved~~ — both are resolved.

---

## Phase A — Stabilise ✅ *(done 2026-09-06)*

*Nothing else was worth building on top of a store that eats its own files.*

- [x] **A1** Guard `Admin\BookController@destroy` behind `hasBeenPurchased()`; delete files only after the row is gone
- [x] **A2** `User implements MustVerifyEmail`; `verified` now gates `/admin` only — not checkout
- [x] **A3** `canRegister` shared from `HandleInertiaRequests`; the guest nav link works
- [x] **A4** Idempotent checkout: per-user cache lock, `orders.idempotency_key` unique index, already-paid short-circuit
- [x] **A5** `Book::COVER_DISK`; covers resolve through the public disk instead of the default one
- [x] **A6** Receipt mail wrapped in try/catch + `report()`
- [x] **A8** `CatalogSeeder` — 8 genres, 12 authors, ~26 books, a draft, a free book, a placeholder PDF
- [x] **A9** Library paginated (12/page)
- [x] **A10** `throttle:20,1` on `library.download`; `download_logs` table and model
- [x] **A11** Error pages (403/404/419/429/500/503); empty `show()` route dropped; trimmed `auth.user` payload
- [x] `BookPolicy` + `OrderPolicy`; inline `abort_unless` calls replaced with `$this->authorize()`
- [x] `App\Support\Money` (integer paise) and `resources/js/lib/money.js`; every price renders as ₹
- [x] Publish/draft state on books, enforced in the catalogue, book page and checkout
- [x] `BookCover` component — fallback, real `alt` text, lazy loading
- [x] `vendor/bin/pint` run across the codebase

**Deferred to Phase D by design:** A7 (admin sidebar on mobile) is folded into the
layout rebuild rather than patched twice.

**Result:** 73 tests, 278 assertions, green. New regression cover for A1, A4, A6,
A10, the draft/publish rules, the Money conversions and the seeder.

---

## Phase B — Real money 🔴

*One to two weeks, most of it waiting on gateway approval. Start the legal pages on day one —
they are the long pole.* Full detail in [04-PAYMENTS-INDIA.md](04-PAYMENTS-INDIA.md).

- [ ] Write and publish Terms, Privacy, Refund & Cancellation, Shipping/Delivery, Contact — see [09-SEO-LEGAL.md](09-SEO-LEGAL.md)
- [ ] Submit Razorpay KYC (PAN, bank account, the five live URLs above)
- [ ] Migrate money columns to **integer paise**; add `currency`, `gateway`, `gateway_order_id`, `gateway_payment_id` to `orders`
- [ ] Add the `payments` audit table
- [ ] Redesign the `PaymentGateway` contract for redirect-and-webhook gateways (`createSession` / `verifyCallback` / `verifyWebhook`)
- [ ] Implement `RazorpayPaymentGateway`; keep `FakePaymentGateway` bound in `testing` and `local`
- [ ] Add `POST /webhooks/razorpay` — CSRF-exempt, signature-verified, idempotent
- [ ] GST fields: buyer state for place-of-supply, invoice number series, tax breakdown on the receipt
- [ ] Refund flow in admin, writing back to `orders.status` and revoking library access

**Done when:** a real ₹1 payment completes on live keys, the webhook marks the order paid
with the browser closed mid-payment, and replaying the same webhook twice changes nothing.

---

## Phase C — The reader 🔴

*Two to three weeks. This is the product.* Full spec in [05-READER.md](05-READER.md).

- [ ] `reading_progress`, `bookmarks`, `highlights` tables
- [ ] Gated asset-streaming route for EPUB/PDF, ownership-checked, `Range`-aware
- [ ] EPUB reader on `epub.js`; PDF reader on `pdf.js`
- [ ] Reader chrome: TOC, font size, line height, three themes, keyboard navigation
- [ ] Progress sync — resume on any device; "Continue reading" on the library
- [ ] **Free samples**: `sample_path` per book, readable without purchase, with a buy prompt at the end
- [ ] Watermark the buyer's email into downloaded PDFs
- [ ] Bookmarks and highlights UI

**Done when:** a book can be read start to finish on a phone without downloading anything,
progress survives a device switch, and a guest can read chapter one.

---

## Phase D — Modern UI 🟠

*One to two weeks.* Full spec in [06-DESIGN-SYSTEM.md](06-DESIGN-SYSTEM.md).

- [ ] Migrate Tailwind v3 → v4 (CSS-first `@theme`, `@tailwindcss/vite`); drop the dead v3 config
- [ ] Add `reka-ui`, `lucide-vue-next`, `@vueuse/core`; delete hand-pasted SVG paths
- [ ] Build the token layer: ink/paper palette, Archivo + Literata type scale, spacing, radii
- [ ] Rebuild `StoreLayout` (guest) and `AppLayout` (authenticated) — responsive, off-canvas below `lg`, one shared shell
- [ ] Homepage: excerpt-led hero, genre shelves, no card grid
- [ ] Book page: sample-first, sticky buy panel, related books
- [ ] Cart, checkout, library, orders redesigned; replace `confirm()` with the `Modal` component
- [ ] Inertia v2: deferred props, prefetch on hover, `<WhenVisible>` infinite scroll on the catalogue
- [ ] Accessibility pass: focus rings, real `alt` text, landmarks, `prefers-reduced-motion`
- [ ] ₹ formatting everywhere via the Phase A money helper

**Done when:** every page renders correctly at 360px, Lighthouse accessibility is ≥95, and
no screen still looks like Breeze.

---

## Phase E — Deploy free 🟠

*Two or three days.* Full detail in [08-DEPLOYMENT.md](08-DEPLOYMENT.md).

- [ ] Move covers and ebooks to Cloudflare R2 (`s3` driver) — free hosts have ephemeral disks
- [ ] Provision the database (TiDB Serverless, MySQL-compatible — avoids a Postgres `LIKE` rewrite)
- [ ] SMTP via Brevo or Resend
- [ ] Deploy to Render; `QUEUE_CONNECTION=sync` until a worker is affordable
- [ ] Production `.env`: `APP_DEBUG=false`, real `APP_NAME`/`APP_URL`, cached config and routes
- [ ] Sentry, uptime monitoring, `/up` health check wired to the platform
- [ ] Security headers and a CSP that allows `checkout.razorpay.com`

**Done when:** a stranger on a phone can find a book, read the sample, pay ₹ and read the
whole thing — on a URL you did not pay for.

---

## Phase F — Growth 🟡

Post-launch, ordered by expected return. See [07-FEATURES.md](07-FEATURES.md).

- [ ] Reviews and ratings; featured and bestseller rails
- [ ] Wishlist; series and collections; many-to-many tags; author landing pages
- [ ] Coupons and discount codes; gifting
- [ ] Admin dashboard: revenue, top titles, conversion, refunds, user management
- [ ] Abandoned-cart email; "new from an author you read" notifications
- [ ] Persistent database cart replacing the session array
- [ ] Full-text search (MySQL fulltext, or Meilisearch when there is budget)
- [ ] International sales through a merchant-of-record (Paddle / Lemon Squeezy)

---

## Sequencing advice

If time is short, the highest-leverage cut is **A → B → E**: a plain-looking store that
takes real money beats a beautiful one that cannot. But the reader (C) is what makes anyone
come back, and it is the only part of this product that Amazon does not already do better.
Do not ship without at least free samples from Phase C.
