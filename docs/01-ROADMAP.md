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

Phase B depends on A4 being fixed. Phases C and D are independent of each other and of B;
run whichever you have appetite for. **Do not deploy before A2 and A5 are resolved** —
both fail differently in production than they do locally.

---

## Phase A — Stabilise 🔴

*Roughly a week. Nothing else is worth building on top of a store that eats its own files.*

- [ ] **A1** Guard `Admin\BookController@destroy` behind `orderItems()->exists()`; delete files only after the row is gone
- [ ] **A2** Decide on email verification; implement `MustVerifyEmail` and gate `/admin` only, or remove the `'verified'` middleware
- [ ] **A3** Fix or remove `canRegister` so guests can reach `/register`
- [ ] **A4** Idempotent checkout: cache lock, idempotency key on `orders`, `processing` on the button
- [ ] **A5** `Storage::disk('public')->url()` via a new `Book::COVER_DISK` constant
- [ ] **A6** Wrap the receipt mail in try/catch and `report()`
- [ ] **A8** Seed genres, authors, books and sample EPUBs
- [ ] **A9** Paginate the library
- [ ] **A10** Throttle `library.download`; add a `download_logs` table
- [ ] **A11** Custom `404`/`500` pages; drop the empty `show()` route; share a trimmed auth user
- [ ] Introduce `BookPolicy` and `OrderPolicy`; replace inline `abort_unless` calls
- [ ] Add a `Money` value object / `->inr()` helper so no template ever formats a price by hand

**Done when:** a purchased book cannot be deleted, `php artisan test` is green with new
regression tests for A1 and A4, and a fresh `migrate:fresh --seed` gives a populated store.

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

- [ ] Reviews and ratings; publish/draft state; featured and bestseller rails
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
