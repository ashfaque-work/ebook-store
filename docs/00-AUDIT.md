# 00 — Project Audit

**Date:** 2026-09-06 · **Commit:** `d01394e` · **Method:** full read of `app/`, `routes/`, `resources/js/`, `database/`, `config/`

---

## 1. Verdict

The foundations are sound. Security decisions that are usually wrong in a first-pass store are right here: `role` is not mass-assignable, ebook files live on the private disk and are served only after an ownership check, checkout recomputes totals server-side and runs in a transaction, and the payment layer sits behind an interface.

What is missing is everything that turns a working prototype into a business: real money, a reading experience, a designed interface, and the legal and operational surface a live store needs. Call it **40% complete**.

---

## 2. What works

| Area | State |
|---|---|
| Catalogue | Paginated, search by title/author, genre filter — [HomeController.php](../app/Http/Controllers/HomeController.php) |
| Book detail | Cover, description, price, cart/download state — [Books/Show.vue](../resources/js/Pages/Books/Show.vue) |
| Cart | Session array of book IDs, add/remove/clear, live count badge |
| Checkout | Transactional order + line items, mock gateway, skips already-owned books, queued receipt email |
| Library | Purchased list + gated streaming download with `hasPurchased()` check |
| Orders | Customer order history and receipt pages, paginated |
| Admin | CRUD for authors, genres, books; upload handling; slug uniqueness; old-file cleanup |
| Auth | Breeze — register, login, password reset, profile, dark-mode toggle |
| Access control | `EnsureUserIsAdmin` on the `/admin` group; non-admins get 403 |
| Tests | 7 feature test files covering admin access, catalogue, checkout, downloads, order history |

---

## 3. Bug register

Ranked by damage. IDs are referenced from [01-ROADMAP.md](01-ROADMAP.md).

### A1 — Deleting a purchased book destroys the file and leaves the row 🔴

[`Admin/BookController.php:150-160`](../app/Http/Controllers/Admin/BookController.php#L150-L160)

`destroy()` deletes the cover and ebook from disk **before** calling `$book->delete()`. Because `order_items.book_id` is `restrictOnDelete`, the delete throws a `QueryException` — after the files are already gone. Result: every customer who bought that book permanently loses their download, the catalogue row survives pointing at a missing file, and the admin sees a 500 with no explanation.

**Fix:** guard first, delete files last.

```php
if ($book->orderItems()->exists()) {
    return back()->with('toast', ['type' => 'error',
        'message' => 'This book has been purchased and cannot be deleted. Unpublish it instead.']);
}

$cover = $book->getRawOriginal('cover_image_path');
$file  = $book->getRawOriginal('file_path');

$book->delete();   // only now are we sure it succeeded

Storage::disk('public')->delete($cover);
Storage::disk(Book::FILE_DISK)->delete($file);
```

Pairs with the `is_published` flag in [03-DATABASE.md](03-DATABASE.md) — "unpublish" is the correct action for a sold book, not delete.

### A2 — Email verification is silently disabled 🔴

[`app/Models/User.php:12`](../app/Models/User.php#L12)

`MustVerifyEmail` is imported but commented out, and the class does not implement it. Laravel's `EnsureEmailIsVerified` middleware only enforces on models implementing that interface, so **every `'verified'` middleware in [routes/web.php](../routes/web.php) is a no-op** — on `/dashboard`, on checkout, and on the entire admin group. The README claims verification works. It does not.

**Fix:** decide deliberately. Either implement the interface, or strip `'verified'` from the route groups so the code stops implying a guarantee it does not make.

Recommended: implement it, but gate only `/admin` on it — not checkout. Blocking a paying customer behind an email round-trip costs conversions, and with `MAIL_MAILER=log` on a fresh deploy it would block *every* purchase.

### A3 — The Register link never renders 🔴

[`GuestLayout.vue:62`](../resources/js/Layouts/GuestLayout.vue#L62)

`$page.props.canRegister` is never shared by [`HandleInertiaRequests`](../app/Http/Middleware/HandleInertiaRequests.php), so it is always `undefined` and the `v-if` never passes. **Guests cannot find the sign-up page from the storefront.** Anyone who registered did it by typing `/register` directly.

**Fix:** the guest nav is rebuilt in Phase D anyway; until then remove the `v-if`. If you keep the prop, share it: `'canRegister' => Route::has('register')`.

### A4 — Double-submit creates duplicate orders 🔴

[`CheckoutController.php:26`](../app/Http/Controllers/CheckoutController.php#L26)

No idempotency key, no lock, no client-side disable. Two fast clicks produce two orders. Harmless against `FakePaymentGateway`; against Razorpay it is a double charge and a refund request. Must be fixed **before** the gateway goes in, not after.

**Fix:** a `Cache::lock("checkout:{$user->id}", 10)` around the flow, an idempotency key derived from user plus sorted cart contents stored on the order, and a button disabled on `useForm().processing`.

### A5 — Cover URLs resolve by accident 🟠

[`app/Models/Book.php:88`](../app/Models/Book.php#L88)

`Storage::url($value)` uses the **default** disk, which is `local` (private) per `FILESYSTEM_DISK=local`. It only produces working URLs because the `public/storage` symlink happens to intercept the path. The moment covers move to S3/R2 — which [08-DEPLOYMENT.md](08-DEPLOYMENT.md) requires, because free hosts have ephemeral disks — every cover 404s.

**Fix:** `Storage::disk('public')->url($value)`. Better, add a `Book::COVER_DISK` constant next to the existing `FILE_DISK` so both disks are named in one place.

### A6 — Mail failure after successful payment returns a 500 🟠

[`CheckoutController.php:91`](../app/Http/Controllers/CheckoutController.php#L91)

`Mail::to()` runs outside the transaction and unguarded. A misconfigured mailer means the customer's money is taken and they are shown an error page. The order is fine in the database, but they have no way to know that.

**Fix:** wrap in `try { … } catch (\Throwable $e) { report($e); }`. Delivery of a receipt is never a reason to fail the request.

### A7 — Admin layout is broken on mobile 🟠

[`AuthenticatedLayout.vue:33`](../resources/js/Layouts/AuthenticatedLayout.vue#L33)

Fixed `w-64` sidebar with no responsive collapse; `showingNavigationDropdown` is declared and never used. On a phone the content column is crushed to unusable width. Given that the Indian market is overwhelmingly mobile, this also predicts how the storefront behaves as it grows.

**Fix:** covered by the layout rebuild in Phase D — off-canvas drawer below `lg`.

### A8 — Fresh install shows an empty store 🟡

[`DatabaseSeeder.php`](../database/seeders/DatabaseSeeder.php) creates users only — no authors, genres, or books. A new clone, and every free-tier redeploy against an ephemeral database, lands on a blank catalogue.

**Fix:** seed ~8 genres, ~12 authors, ~40 books with realistic titles and ₹ prices. Ship two or three public-domain EPUBs so the reader has something to open in development.

### A9 — Library index is unpaginated 🟡

[`LibraryController.php:26`](../app/Http/Controllers/LibraryController.php#L26) uses `->get()`. Fine at 10 books, a slow page at 500.

### A10 — Download route is unthrottled and unlogged 🟡

[`routes/web.php`](../routes/web.php) — `library.download` has no rate limit and no audit trail. A shared account can pull unlimited copies and you would never know.

**Fix:** `->middleware('throttle:20,1')` plus a `download_logs` row per hit.

### A11 — Smaller items 🟡

- `package.json` carries both `tailwindcss@^3.2.1` and an unused `@tailwindcss/vite@^4.0.0`. Dead dependency; resolved by the Tailwind v4 migration in Phase D.
- No `resources/views/errors/` — 404 and 500 are stock Laravel pages.
- No `app/Policies/` — authorization is inline `abort_unless` calls. Fine now, will not scale past the next three features.
- [`Admin\BookController@show()`](../app/Http/Controllers/Admin/BookController.php) is an empty method still bound to a route.
- `Modal.vue` exists and is used once; the cart uses browser `confirm()` dialogs instead.
- Prices render unformatted — `$9.5` rather than `₹9.50`. The `$` symbol is hardcoded throughout, in a store selling to India.
- Footer is hardcoded `© 2025`.
- `alt="Book Cover"` on every cover image — useless to a screen reader.
- `HandleInertiaRequests` shares the full `User` model on every response, including `email`. Share a trimmed array instead.

---

## 4. Missing entirely

Not bugs — features that were never built. Detailed in [07-FEATURES.md](07-FEATURES.md).

- **Reading.** No reader, no progress, no bookmarks, no samples. The stated goal is a place people come to read; today the product is buy-then-download. See [05-READER.md](05-READER.md).
- **Real payments.** See [04-PAYMENTS-INDIA.md](04-PAYMENTS-INDIA.md).
- **Legal pages.** Terms, Privacy, Refunds, Contact. Razorpay will not approve an account without them. Hard blocker on revenue.
- **Discovery.** No publish/draft state, no featured rails, no ratings, no reviews, no wishlist, no series, no tags, no sorting, no author pages.
- **Designed UI.** Everything is unmodified Breeze scaffolding. No hero, no brand, no SEO metadata, no OG tags — links shared on WhatsApp render as bare URLs.
- **Admin depth.** No dashboard, no orders view, no refunds, no user management, no coupons, no exports.
- **Ops.** No CI, no error tracking, no security headers, no backups.
