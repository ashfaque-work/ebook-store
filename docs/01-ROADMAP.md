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

## Phase B — Real money ✅ *(code done 2026-09-06; KYC is yours to submit)*

Full detail in [04-PAYMENTS-INDIA.md](04-PAYMENTS-INDIA.md).

- [x] Terms, Privacy, Refunds, Delivery and Contact pages, live and linked from the footer
- [ ] **Submit Razorpay KYC** — PAN, bank account, and the five URLs above. *Only you can do this; it takes 2–7 working days and nothing else in this phase is blocked by it.*
- [x] Money migrated to **integer paise**; `currency`, `gateway`, `gateway_order_id`, `gateway_payment_id` on `orders`
- [x] `payments` audit table, `webhook_events` table, `refunds` table
- [x] `PaymentGateway` contract redesigned for redirect-and-webhook gateways (`createSession` / `verifyCallback` / `verifyWebhook` / `refund`)
- [x] `RazorpayPaymentGateway` over the REST API; `FakePaymentGateway` implements the same four steps and signs with real HMAC
- [x] `POST /webhooks/razorpay` — CSRF-exempt, signature-verified against the raw body, idempotent by event id
- [x] GST: per-book rates, tax-inclusive pricing, place-of-supply, gapless per-financial-year invoice series
- [x] Admin orders list with revenue tiles, filters and search; full and partial refunds that revoke library access

**Done when:** a real ₹1 payment completes on live keys, the webhook marks the
order paid with the browser closed mid-payment, and replaying the same webhook
twice changes nothing. *The last two are covered by tests; the first needs live
keys after KYC.*

**Result:** 144 tests, 573 assertions. The mock gateway runs the same four-step
flow as Razorpay, so nothing here is only exercised in production.

### To go live once KYC clears

1. Set `RAZORPAY_KEY`, `RAZORPAY_SECRET`, `RAZORPAY_WEBHOOK_SECRET`. Leaving them blank keeps the mock gateway.
2. Register `https://<your-domain>/webhooks/razorpay` in the Razorpay dashboard for `payment.captured`, `payment.failed` and `refund.processed`.
3. Fill in the `STORE_*` values in `.env` — the legal pages and the KYC application must agree.
4. Turn on `STORE_GST_ENABLED` **only** once you hold a GSTIN, and set `STORE_STATE_CODE`.
5. Make a real ₹1 purchase, then refund it from the admin orders page.

---

## Phase C — The reader ✅ *(2026-09-06)*

Full spec in [05-READER.md](05-READER.md).

- [x] `reading_progress`, `bookmarks`, `highlights` tables
- [x] Gated asset route, ownership-checked, `Range`-aware locally and presigned on object storage
- [x] EPUB reader on `epub.js`; PDF reader on `pdf.js`; both lazy-loaded
- [x] Reader chrome: TOC, font size, line height, page width, three themes, keyboard navigation, tap-thirds and swipe
- [x] Progress sync — throttled, flushed on unload, resumes on any device
- [x] **Free samples** — `sample_path` per book, readable with no account, buy panel at the end
- [x] "Continue reading" leads the library
- [x] Bookmarks; highlights with notes (EPUB)

**Deliberately not done, and why:**

- **PDF text selection and highlighting.** PDFs render to canvas, so there is no
  selectable text layer. Highlights work in EPUB, where the format has real
  ranges (CFI). A half-working PDF text layer is worse than an honest absence;
  this is a follow-up, not a gap in the flow.
- **Auto-generated samples.** Admins upload a sample file per book. Slicing the
  first 10% out of an arbitrary EPUB or PDF server-side is a project of its own
  and the manual path gets the conversion benefit today.
- **PDF watermarking on download** (P2 in [07-FEATURES.md](07-FEATURES.md)).
  Attribution beats encryption, but it needs a PDF manipulation library and
  careful testing; it is not on the path to launch.

**Result:** 166 tests, 690 assertions. epub.js (352 KB) and pdf.js (438 KB)
build as separate chunks and stay out of the 265 KB storefront bundle.

---

## Phase D — Modern UI ✅ *(2026-09-07)*

Full spec in [06-DESIGN-SYSTEM.md](06-DESIGN-SYSTEM.md).

- [x] Tailwind v3 → v4 (CSS-first `@theme`, oklch, `@tailwindcss/vite`); the dead v3 config and the unused v4 plugin are now one stack
- [x] Token layer: ink/paper palette, Archivo + Literata, two radii, focus rings defined once in base
- [x] `reka-ui` and `lucide-vue-next`; hand-pasted SVG paths deleted
- [x] Both layouts rebuilt — **A7 fixed**: a real off-canvas drawer below `lg` instead of a fixed `w-64` aside
- [x] Home page: excerpt-led hero, genre shelves, results mode for search — no card grid
- [x] Book page: excerpt on the page, sticky buy panel (bottom bar on mobile), related books
- [x] Cart, checkout, library, orders rebuilt; `window.confirm()` replaced with a real dialog
- [x] Inertia 2 deferred props (genre shelves, related books) and prefetch on book links
- [x] Accessibility: skip links, visible focus, `aria-current`, real `alt` text, landmarks, `prefers-reduced-motion`
- [x] Prettier added, plus a route smoke test covering every page

**Deliberately not done, and why:**

- **`<WhenVisible>` infinite scroll.** Numbered pagination is kept. Infinite
  scroll needs merged props and a scroll-position story, and it makes the
  footer — which carries the policy links a payment gateway checks —
  unreachable. Worth revisiting, not worth rushing.
- **A width-axis display face.** The design doc wanted Archivo's `wdth` axis
  for spine-like headings. Bunny serves plain weights reliably and variable
  axes less so, so headings use tight tracking instead. A real deviation from
  the spec, recorded rather than hidden.
- **Lighthouse verification.** Targets of ≥95 accessibility and ≥90
  performance are in the spec and still unmeasured — the browser pass on
  2026-09-12 checked behaviour and layout, not scores. What it did confirm:
  no horizontal overflow at 360px, the admin drawer works, and the console is
  clean on every page exercised.

**Result:** 205 tests, 765 assertions, including a smoke test that renders
every route for the role that should see it.

---

## Phase E — Deploy free 🟠 *(code complete; accounts are yours)*

Everything that can be built without an account exists. Full detail in
[08-DEPLOYMENT.md](08-DEPLOYMENT.md).

**Done:**

- [x] `r2` / `r2-public` disks; `PRIVATE_DISK` and `PUBLIC_DISK` choose them by environment
- [x] `php artisan books:migrate-storage` — streams existing files across, `--dry-run` first
- [x] Downloads and reader assets presign on object storage instead of streaming through PHP
- [x] `SecurityHeaders` middleware and a CSP that allows Razorpay, the reader and the font host
- [x] `Dockerfile`, `docker/` (nginx, php-fpm, supervisor, entrypoint), `render.yaml`, `.dockerignore`
- [x] `.env.production.example` with every value the free stack needs
- [x] CI (moved forward to Phase D)

**Yours, because they need accounts or money:**

- [ ] Cloudflare R2 bucket → fill in `R2_*`, then run `books:migrate-storage`
- [ ] TiDB Serverless database → fill in `DB_*` (MySQL-compatible: nothing in the app changes)
- [ ] Brevo or Resend SMTP → fill in `MAIL_*`
- [ ] Create the Render service from `render.yaml` and paste the secrets in
- [ ] **Submit Razorpay KYC** — the long pole at 2–7 working days; the five pages it needs are live
- [ ] Sentry DSN, and an uptime monitor pointed at `/up` every 10 minutes to mask the free tier's cold start

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
