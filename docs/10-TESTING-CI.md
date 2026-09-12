# 10 — Testing and CI

---

## 1. Where things stand

Pest 3 is installed and configured against SQLite in-memory. Seven feature test files exist:

| File | Covers |
|---|---|
| `AdminAccessTest` | Non-admins get 403; admins pass; `role` is not mass-assignable |
| `CatalogTest` | Pagination, search, genre filter |
| `CheckoutTest` | Happy path, empty cart, already-owned skip, totals |
| `LibraryDownloadTest` | Ownership gating on downloads |
| `OrderHistoryTest` | A user sees only their own orders |
| `Auth/*`, `ProfileTest` | Breeze defaults |

That is a genuinely decent base — the security-critical paths are covered. The gaps are the
things that are about to be built, plus two regression tests the audit demands.

**Caveat worth knowing:** tests run on SQLite while production is MySQL. They will not catch
MySQL-specific issues — collation, strict-mode rejections, `ONLY_FULL_GROUP_BY`. CI should run
both (see §4).

---

## 2. Tests to write

### Phase A — regressions for the audit bugs

```php
it('refuses to delete a book that has been purchased', function () {
    $book = Book::factory()->create();
    // …a paid order containing $book…

    $this->actingAs($admin)->delete(route('admin.books.destroy', $book))
        ->assertRedirect();

    expect(Book::find($book->id))->not->toBeNull()
        ->and(Storage::disk(Book::FILE_DISK)->exists($book->getRawOriginal('file_path')))->toBeTrue();
});
```

That second assertion is the important one. A1 is not that the delete fails — it is that the
**file is gone** by the time it fails.

Also for Phase A:
- Checkout submitted twice concurrently creates exactly one order (A4)
- Cover URLs resolve against the public disk, not the default (A5)
- A mailer exception does not fail the checkout request (A6)
- Download route rate-limits after 20 requests in a minute (A10)
- A guest can reach `/register` from the storefront (A3)

### Phase B — payments

The highest-value tests in the project. Money bugs are the only ones that cost you money.

- Signature verification accepts a known-good payload and rejects a tampered one
- **`FulfilOrder` called twice produces one paid order, one payment row, one receipt**
- A webhook replayed with the same event id is a no-op
- A gateway amount that disagrees with `total_paise` is rejected and the order is not fulfilled
- A refund revokes library and reader access
- Money conversion: `Money::fromRupees('19.99')->paise === 1999` — the `(int)(19.99 * 100) === 1998` trap
- The full flow with `FakePaymentGateway`, no network

### Phase C — reader

- A non-owner gets 403 on `/read/{book}/asset`
- A sample is readable while logged out
- Progress round-trips: save, reload, resume at the same location
- `updateOrCreate` on `reading_progress` never creates a duplicate row for a user/book pair
- Presigned URLs expire

### Phase D — UI

Do not chase component unit tests. Two things are worth automating:

- **Smoke tests**: every route returns 200 for the right role and does not blow up Inertia
- **Accessibility**: axe-core against the six main pages in CI

Manual pass at 360px before each release, on the pages that break first: cart, admin tables,
book detail.

---

## 3. Conventions

- Feature tests over unit tests. This is a CRUD-and-money app; integration is where bugs live.
- Factories for everything. `BookFactory` exists; add `OrderFactory`, `OrderItemFactory`, `ReviewFactory`, and a `paid()` state.
- `Storage::fake()` in any test touching uploads or downloads.
- `Mail::fake()`, `Queue::fake()`, `Http::fake()` for gateway calls. **No test hits Razorpay.**
- `RefreshDatabase` throughout.
- One behaviour per test; the name states the behaviour, not the method.

Add to `Pest.php` as the suite grows:

```php
function actingAsAdmin(): TestCase {
    return test()->actingAs(User::factory()->admin()->create());
}

function purchasedBook(User $user): Book { /* paid order + item */ }
```

`purchasedBook()` will be used by nearly every reader and library test — build it early.

---

## 4. CI

**Now at [`.github/workflows/ci.yml`](../.github/workflows/ci.yml).** It runs the suite on
SQLite *and* MySQL, checks Pint and Prettier, runs `npm ci` (a peer-dependency conflict
fails there and nowhere else until a deploy), builds the assets, and proves the migrations
roll back on MySQL. Sketch it was built from:

```yaml
# .github/workflows/ci.yml
name: CI
on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        db: [sqlite, mysql]
    services:
      mysql:
        image: mysql:8
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: ebook_store_test
        ports: ['3306:3306']
        options: >-
          --health-cmd="mysqladmin ping" --health-interval=10s
          --health-timeout=5s --health-retries=5
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, pdo_mysql, bcmath, intl, zip
          coverage: none
      - run: composer install --prefer-dist --no-interaction
      - run: cp .env.example .env && php artisan key:generate
      - run: php artisan test

  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.2' }
      - run: composer install --prefer-dist --no-interaction
      - run: vendor/bin/pint --test

  assets:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: '22', cache: npm }
      - run: npm ci && npm run build
```

The `assets` job matters more than it looks: a Vue template error is invisible until build
time, and on Render a failed build is a failed deploy.

Pint is already a dev dependency and has never been run. Run `vendor/bin/pint` once in Phase A
so the first CI run is not a thousand-line diff.

---

## 5. Quality gates

**Before every commit:** `vendor/bin/pint` · `php artisan test`

**Before every deploy:** CI green · `npm run build` clean · migrations run on a copy of
production data · a manual purchase in Razorpay test mode

**Before launch:** the checklist in [08-DEPLOYMENT.md](08-DEPLOYMENT.md) §7, plus a real ₹1
purchase on live keys followed by a real refund.

---

## 5a. Two blind spots worth knowing about

Both were found by driving the app in a browser on 2026-09-12, while 225 tests were green.

**Deferred props run no code on the first response.** `Inertia::defer()` closures are only
evaluated on the follow-up partial reload, so a broken query inside one is invisible to an
ordinary page assertion. The genre shelves threw a 500 for days behind a passing suite. Use
the `inertiaPartial()` helper in `tests/Pest.php`, and give **every** deferred prop a test
that actually asks for it.

**SQLite forgives what MySQL does not.** Two `down()` methods dropped a composite index that
InnoDB was using to satisfy a foreign key; MySQL refuses, SQLite does not care. The matrix
job catches this only because it now asserts how many tables survive a reset — checking the
exit code alone was not enough.

The lesson for both: a green suite proves the code paths the suite exercises, and deferred
props and rollbacks are easy to leave unexercised while looking covered.

---

## 6. What not to test

Time is finite and this is a solo project. Skip: Breeze's own auth internals (already covered
upstream), Vue component rendering in isolation, admin CRUD happy paths beyond one smoke test
each, styling.

Spend the effort on **money and ownership**: who paid, what they got, and whether they can
reach a file they did not buy. Every other bug is embarrassing; those two are expensive.
