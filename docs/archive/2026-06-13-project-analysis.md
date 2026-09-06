> **SUPERSEDED — 2026-09-06.** This was the plan through Phase 5 of the original build.
> It is kept for history only. Do not plan from it; see [../README.md](../README.md) for the
> current document set, and [../00-AUDIT.md](../00-AUDIT.md) for the state of the code today.

# eBook Store — Project Analysis & Implementation Roadmap

> **Status:** Core store implemented. Roles, secured downloads, orders, mock checkout, and a customer library are now in place (Phases 1–5 done). Remaining: catalog pagination/search and production hardening (Phases 6–7).
> **Goal of this document:** give an accurate picture of the codebase and a concrete, ordered plan to take it to a *publishable* state.
> **Chosen direction:** Mock checkout (full order flow with a simulated payment step, no external payment account required, swappable for a real gateway later).
> **Last updated:** 2026-06-13
>
> ### Implementation log (2026-06-13)
> - **Phase 1 ✅** `role` column + `EnsureUserIsAdmin` middleware (`admin` alias); `/admin` now blocks non-admins (403); role-aware `/dashboard`; seeder creates `admin@example.com` + `customer@example.com` (password `password`). `role` is **not** mass-assignable.
> - **Phase 2 ✅** Ebook files moved to the **private** disk (`Book::FILE_DISK = 'local'`); `file_path` hidden from JSON; fixed a latent bug where covers were never deleted (accessor returned a URL — now uses `getRawOriginal()`).
> - **Phase 3 ✅** Real `orders` + new `order_items` tables; `Order`/`OrderItem` models; `User::hasPurchased()`.
> - **Phase 4 ✅** `PaymentGateway` contract + `FakePaymentGateway` (bound in `AppServiceProvider`); `CheckoutController` builds the order in a DB transaction, charges, marks paid, clears cart; real Checkout button + `Checkout/Success.vue`. Server recomputes totals; skips already-owned books.
> - **Phase 5 ✅** `LibraryController` (purchased list + **gated** `library.download` with `hasPurchased()` check); `Library/Index.vue`; book page shows Download when owned.
> - **⚠️ Requires a running MySQL** to `php artisan migrate --seed`. Could not run migrations/tests here (DB was offline). See §11.

---

## 1. Tech Stack

| Layer | Technology |
|-------|------------|
| Framework | Laravel 12 (PHP ^8.2) |
| Frontend | Inertia.js 2 + Vue 3 + Tailwind CSS 3 |
| Build | Vite 7, `laravel-vite-plugin` |
| Auth | Laravel Breeze (Inertia/Vue stack) + Sanctum |
| DB | MySQL (`ebook_store`) |
| Session / Cache / Queue | `database` driver |
| Notifications (UI) | `vue-toastification` via a `toast` shared prop |
| Routing helper | Ziggy |

Local tooling confirmed present: **PHP 8.2.12**, **Composer 2.5.8**, `public/storage` symlink already created.

---

## 2. Current Architecture

### Domain models
- **Author** — `name, bio, photo_path`; `hasMany(Book)`.
- **Genre** — `name, slug`; `hasMany(Book)`.
- **Book** — `author_id, genre_id, title, slug, description, price, cover_image_path, file_path`; `belongsTo` Author/Genre. A `coverImagePath` accessor rewrites the column into a public `Storage::url()`.
- **Order** — **empty stub** (`//` body, no fillable, no relations).
- **User** — default Breeze user. **No role/admin flag.**

### Request flow
- `GET /` → `HomeController` (invokable) → loads **all** books with author+genre → renders `Welcome.vue`.
- `GET /books/{book:slug}` → `BookController@show` → renders `Books/Show.vue`, passing an `isBookInCart` flag derived from the session cart.
- **Cart** (`CartController`) — session-array of book IDs: `index`, `store`, `destroy`, `clear`. No DB persistence.
- **Admin** (`/admin`, middleware `auth,verified`) — `Route::resource` for authors, genres, books. Book create/update handle cover + file uploads to the **public** disk.
- **Auth** — full Breeze routes in `routes/auth.php`.
- `GET /dashboard` → redirects **every** logged-in user to `admin.authors.index`.

### Shared Inertia props (`HandleInertiaRequests`)
`auth.user`, `toast` (flash), `cartCount`.

---

## 3. What Works Today ✅

- Public book catalog grid and single-book detail page.
- Session cart: add, remove single, clear all, with toast feedback and a live `cartCount` badge.
- Admin CRUD for Authors, Genres, Books — including image/file upload, slug generation, old-file cleanup on update/delete.
- Complete authentication: register, login, email verification, password reset, password confirmation, profile update/delete, dark-mode (`useTheme`).

---

## 4. Critical Gaps 🔴 (block "publishable")

### 4.1 No checkout / order system — *the store doesn't sell anything*
The cart's **Checkout** button in [`Cart/Index.vue`](../resources/js/Pages/Cart/Index.vue) is a dead `<a href="#">`. The `orders` table is only `id + timestamps`, the `Order` model is empty, and there is no `OrderController`, no `order_items`, no concept of a completed purchase. Nothing is ever recorded as sold.

### 4.2 No role-based access control — *anyone can be admin*
There is no `is_admin`/`role` column. The admin group is gated only by `auth,verified`, so **any** registered, email-verified user can open `/admin` and create/edit/delete books, authors, and genres. `/dashboard` even funnels every user straight into the admin panel.

### 4.3 Paid ebooks are publicly downloadable — *no DRM/gating at all*
Uploaded ebook files go to `storage/app/public/books` (the **public** disk) and are addressable via `Storage::url()`. Anyone who learns or guesses the path downloads the paid file for free. There is no purchase check and no protected download route.

### 4.4 No payment step
No gateway and no simulated payment. Even the mock flow that we plan does not exist yet.

### 4.5 No customer "My Library"
Buyers have nowhere to see or download what they purchased. Post-purchase value delivery is missing entirely.

---

## 5. Secondary Gaps 🟠 / Polish 🟡

- **Catalog doesn't scale (🟠).** `HomeController` and admin `index` use `->get()` — no pagination, search, genre/author filter, or sorting. Fine for 10 books, broken for 1,000.
- **No customer landing vs. admin split (🟠).** Logged-in customers have no meaningful dashboard; everyone is shoved into admin.
- **Digital-goods wording (🟡).** Cart says "Shipping and taxes calculated at checkout" — irrelevant for downloads.
- **Production config (🟡).** `.env` has `APP_ENV=local`, `APP_DEBUG=true`, `APP_NAME=Laravel`, `MAIL_MAILER=log`. README is the stock Laravel readme.
- **Order confirmation email (🟡).** No receipt/notification after purchase.
- **Tests (🟡).** Pest is installed but there are no feature tests for cart, checkout, downloads, or admin authorization.
- **Minor:** in `Admin/BookController@update`, `$updateData = $validated` carries the `cover_image`/`book_file` keys into `update()`; harmless today (not fillable, not columns) but easy to tidy.

---

## 6. Target Architecture (after roadmap)

### New / changed database
```
users
  + role            ENUM('admin','customer') DEFAULT 'customer'   (or boolean is_admin)

orders                          (rebuilt)
  id, user_id → users
  order_number   (unique, public-facing)
  status        ENUM('pending','paid','failed','refunded')
  total         DECIMAL(8,2)
  payment_ref   (mock/gateway id, nullable)
  paid_at       (nullable)
  timestamps

order_items                     (new)
  id, order_id → orders, book_id → books
  title          (snapshot of book title at purchase)
  price          (snapshot DECIMAL(8,2))
  timestamps

  -- prevents a user buying the same book twice; powers "My Library"
  unique(order_id, book_id)
```

### File storage change
- Ebook files move from the **public** disk to a **private** disk (`local`/`books`), never exposed by `Storage::url()`.
- Covers stay public (they're marketing images).
- Downloads go through a gated route: `GET /library/{book}/download` → policy check "user has a paid order containing this book" → `Storage::disk('local')->download(...)`.

### Routes added
```
POST   /checkout                 CheckoutController@store   (auth, verified)   create order from cart → mock-pay → mark paid → clear cart
GET    /checkout/success/{order} CheckoutController@success (auth)
GET    /library                  LibraryController@index    (auth)             list purchased books
GET    /library/{book}/download  LibraryController@download (auth)             gated file stream
```

### Authorization
- `EnsureUserIsAdmin` middleware (or a Gate/`AdminMiddleware`) applied to the `/admin` group.
- A `BookPolicy@download` (or explicit query) backing the library download route.
- `/dashboard` becomes role-aware: admins → admin panel, customers → `/library`.

---

## 7. Implementation Roadmap (ordered, mock-checkout)

> Designed so each phase is independently testable. The payment piece is isolated behind a tiny service so a real Stripe integration later is a drop-in replacement.

### Phase 1 — Roles & admin lockdown 🔴
1. Migration: add `role` (or `is_admin`) to `users`; default `customer`.
2. `User`: cast/helper `isAdmin()`; expose `role` to the frontend via shared props.
3. `EnsureUserIsAdmin` middleware; register it and apply to the `/admin` group.
4. Make `/dashboard` role-aware (admin → admin panel, customer → `/library`).
5. Seeder: create one **admin** user + a handful of **customer** users.
6. Tests: customer is `403` on `/admin`; admin passes.

### Phase 2 — Secure the ebook files 🔴
1. Switch book-file uploads in `Admin/BookController` (store + update) to the **private** disk.
2. Stop exposing `file_path` via any public URL; keep the cover accessor as-is.
3. Add the gated `GET /library/{book}/download` route + controller that authorizes ownership before streaming.
4. Data note: existing files under `public/books` must be migrated to the private disk (one-off artisan command or manual move) — document in README.

### Phase 3 — Orders domain 🔴
1. Rewrite the `orders` migration (fields above) + new `order_items` migration.
2. Flesh out `Order` (fillable, `belongsTo(User)`, `hasMany(OrderItem)`, status helpers) and add `OrderItem` model.
3. `User hasMany(Order)`; helper `hasPurchased(Book)` used by the download policy and the book page ("Already in your library").

### Phase 4 — Checkout (mock payment) 🔴
1. `PaymentService` interface with a `FakePaymentGateway` implementation (always succeeds, returns a fake `payment_ref`). Bind in a service provider so Stripe can replace it later.
2. `CheckoutController@store`: validate cart non-empty → build `Order` + `OrderItem`s inside a DB transaction → call `PaymentService` → on success mark `paid`, set `paid_at`, clear the session cart → redirect to success page.
3. Guard against re-buying already-owned books; recompute totals server-side (never trust client prices).
4. Wire the real **Checkout** button in `Cart/Index.vue`; add `Checkout/Success.vue`.
5. Tests: full happy path; empty-cart guard; duplicate-purchase guard; totals integrity.

### Phase 5 — My Library 🟠
1. `LibraryController@index` → `Library/Index.vue` listing purchased books with download buttons.
2. Book detail page: show "Download" / "In your library" when owned, instead of "Add to cart".
3. Nav link to Library for authenticated customers.

### Phase 6 — Catalog UX 🟠
1. Paginate the public catalog; add search (title/author) and genre filter.
2. Paginate admin index pages.
3. Fix digital-goods copy ("Shipping and taxes…" → remove).

### Phase 7 — Production hardening 🟡
1. Real `.env` story: `APP_ENV=production`, `APP_DEBUG=false`, real `APP_NAME`, app key, real mailer.
2. Order confirmation email (queued) with the receipt + library link.
3. Project README (setup, seeding, admin credentials, build/deploy, env vars).
4. Custom error pages; `config:cache`, `route:cache`, `npm run build` deploy steps.
5. Backfill Pest feature tests for the flows above; run `pint`.

---

## 8. Suggested Build Order (dependency-aware)

```
Phase 1 (roles) ─┬─> Phase 2 (secure files) ─┐
                 │                            ├─> Phase 3 (orders) ─> Phase 4 (checkout) ─> Phase 5 (library)
                 └────────────────────────────┘
Phase 6 (catalog UX)  — independent, any time
Phase 7 (hardening)   — last
```

Phases 1–5 deliver a **functionally complete, sellable store**. Phases 6–7 make it pleasant and production-ready.

---

## 9. Risks & Notes

- **Existing public ebook files** (if any were uploaded) are already exposed; moving to the private disk is necessary but means re-pathing existing `file_path` values.
- **Mock payment is not real money.** The `PaymentService` seam keeps the swap to Stripe Checkout small, but PCI/refund/webhook concerns are deferred until a real gateway is chosen.
- **Prices/totals** must always be recomputed on the server at checkout; the cart only carries book IDs today, which is good — keep it that way.
- **Slugs** are generated from titles without a uniqueness guard beyond the DB unique index; two same-titled books will collide. Worth a uniqueness suffix during Phase 6.

---

## 10. Quick File Map

| Concern | File(s) |
|---|---|
| Public catalog | `app/Http/Controllers/HomeController.php`, `resources/js/Pages/Welcome.vue` |
| Book detail | `app/Http/Controllers/BookController.php`, `resources/js/Pages/Books/Show.vue` |
| Cart | `app/Http/Controllers/CartController.php`, `resources/js/Pages/Cart/Index.vue` |
| Admin | `app/Http/Controllers/Admin/*`, `resources/js/Pages/Admin/*` |
| Models | `app/Models/{Book,Author,Genre,Order,User}.php` |
| Migrations | `database/migrations/*` |
| Shared props | `app/Http/Middleware/HandleInertiaRequests.php` |
| Routes | `routes/web.php`, `routes/auth.php` |
```
