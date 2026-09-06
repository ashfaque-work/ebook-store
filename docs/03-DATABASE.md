# 03 — Database

Current schema, target schema, and every migration still to write.

---

## 1. Money: store integer paise

The single most important change in this document.

`orders.total` and `order_items.price` are `decimal(8,2)`. Razorpay, Cashfree and PhonePe all
take and return **integer paise**. Converting at the boundary (`* 100`, round, cast) is where
₹0.01 discrepancies come from, and a one-paisa discrepancy fails a signature comparison and
leaves a customer charged for an order your code thinks is unpaid.

```php
// migration
$table->unsignedBigInteger('total_paise');
$table->char('currency', 3)->default('INR');

// App\Services\Support\Money
final class Money
{
    private function __construct(public readonly int $paise) {}

    public static function fromPaise(int $p): self  { return new self($p); }
    public static function fromRupees(string $r): self { return new self((int) bcmul($r, '100')); }

    public function plus(Money $o): self { return new self($this->paise + $o->paise); }
    public function inr(): string { return '₹' . number_format($this->paise / 100, 2); }
}
```

Use `bcmul` on a **string**, never `(int) ($float * 100)` — `(int)(19.99 * 100)` is `1998` in
PHP. Admin forms accept rupees and convert once, on the way in.

`decimal(8,2)` also caps at ₹999,999.99, which is fine for books but not for a lifetime
revenue column later.

---

## 2. Existing tables

| Table | Notes |
|---|---|
| `users` | + `role` (`admin`\|`customer`), not fillable. Needs `state_code` for GST. |
| `authors` | `name`, `bio`, `photo_path`. Needs `slug` for author pages. |
| `genres` | `name`, `slug`. |
| `books` | `author_id`, `genre_id`, `title`, `slug`, `description`, `price`, `cover_image_path`, `file_path`. |
| `orders` | `user_id`, `order_number`, `status`, `total`, `payment_reference`, `paid_at`. |
| `order_items` | `order_id`, `book_id` (restrict on delete), `title` + `price` snapshot, unique per order. |

---

## 3. Migrations to write

Grouped by roadmap phase. Names are suggestions; keep them chronological.

### Phase A

**`add_publishing_fields_to_books`**
```php
$table->boolean('is_published')->default(false)->index();
$table->timestamp('published_at')->nullable();
$table->string('language', 12)->default('en');
$table->string('isbn', 20)->nullable();
$table->unsignedInteger('page_count')->nullable();
$table->unsignedBigInteger('file_size')->nullable();
$table->string('file_format', 8)->default('pdf');   // pdf | epub
$table->string('sample_path')->nullable();          // free preview, private disk
$table->boolean('is_featured')->default(false);
```
`is_published` is what makes A1 solvable — a sold book gets unpublished, never deleted.
Every public query gains `->where('is_published', true)`.

**`create_download_logs_table`** (A10)
```php
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->foreignId('book_id')->constrained()->cascadeOnDelete();
$table->string('ip', 45)->nullable();
$table->string('user_agent')->nullable();
$table->timestamp('created_at')->index();
```

**`add_idempotency_key_to_orders`** (A4) — `string('idempotency_key')->nullable()->unique()`.

### Phase B — payments

**`convert_money_columns_to_paise`**

Add the new columns, backfill, drop the old ones. Three steps in one migration, or three
migrations if you prefer to keep a rollback point:
```php
// up
$table->unsignedBigInteger('total_paise')->default(0)->after('status');
$table->char('currency', 3)->default('INR')->after('total_paise');
// then: DB::statement('UPDATE orders SET total_paise = ROUND(total * 100)');
// then: $table->dropColumn('total');
```
Same for `order_items.price` → `price_paise`, and `books.price` → `price_paise`.
Do the backfill in SQL, not PHP — it must not depend on the app booting.

**`add_gateway_fields_to_orders`**
```php
$table->string('gateway', 32)->nullable();            // razorpay
$table->string('gateway_order_id')->nullable()->index();
$table->string('gateway_payment_id')->nullable()->index();
$table->string('failure_reason')->nullable();
$table->timestamp('refunded_at')->nullable();
$table->unsignedBigInteger('refunded_paise')->default(0);
```

**`create_payments_table`** — the audit trail. One order can have several attempts.
```php
$table->foreignId('order_id')->constrained()->cascadeOnDelete();
$table->string('gateway', 32);
$table->string('gateway_payment_id')->unique();
$table->string('status', 32);                 // created|authorized|captured|failed|refunded
$table->unsignedBigInteger('amount_paise');
$table->string('method', 32)->nullable();     // upi|card|netbanking|wallet
$table->json('payload')->nullable();          // raw gateway response
$table->timestamps();
```

**`create_webhook_events_table`** — idempotency for webhooks. Insert before processing; a
duplicate `event_id` means you have already handled it.
```php
$table->string('gateway', 32);
$table->string('event_id')->unique();
$table->string('event_type');
$table->json('payload');
$table->timestamp('processed_at')->nullable();
$table->timestamps();
```

**`add_gst_fields`**
```php
// users
$table->string('state_code', 2)->nullable();     // place of supply
// orders
$table->string('invoice_number')->nullable()->unique();
$table->string('buyer_state_code', 2)->nullable();
$table->unsignedBigInteger('subtotal_paise')->default(0);
$table->unsignedBigInteger('tax_paise')->default(0);
$table->decimal('tax_rate', 5, 2)->default(0);   // a rate, not money — decimal is right here
$table->string('tax_type', 8)->nullable();       // cgst_sgst | igst
```

### Phase C — reader

**`create_reading_progress_table`**
```php
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->foreignId('book_id')->constrained()->cascadeOnDelete();
$table->string('location', 512)->nullable();     // EPUB CFI, or PDF page number
$table->unsignedTinyInteger('percent')->default(0);
$table->timestamp('last_read_at')->nullable();
$table->timestamps();
$table->unique(['user_id', 'book_id']);
```
The `unique` is what makes progress sync an `updateOrCreate` rather than a race.

**`create_bookmarks_table`** — `user_id`, `book_id`, `location`, `label`, `timestamps`.

**`create_highlights_table`** — `user_id`, `book_id`, `location_start`, `location_end`,
`text` (the excerpt), `note` (nullable), `color` (8 chars), `timestamps`.

### Phase F — growth

**`create_reviews_table`**
```php
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->foreignId('book_id')->constrained()->cascadeOnDelete();
$table->unsignedTinyInteger('rating');            // 1..5
$table->string('title')->nullable();
$table->text('body')->nullable();
$table->boolean('is_approved')->default(false);
$table->timestamps();
$table->unique(['user_id', 'book_id']);
```
Only allow a review from a user with a paid order for that book — a verified-purchase store
is worth more than volume.

Denormalise onto `books`: `rating_avg decimal(3,2)`, `rating_count unsignedInteger`,
recalculated on review write. Never `AVG()` in a catalogue query.

**`create_book_tag_tables`** — `tags` (`name`, `slug`) + `book_tag` pivot. The single
`genre_id` is too coarse; a book is "fiction" *and* "translated" *and* "Booker longlist".
Keep `genre_id` as the primary shelf, add tags alongside it.

**`create_wishlists_table`** — `user_id`, `book_id`, unique pair.

**`create_coupons_table`**
```php
$table->string('code')->unique();
$table->string('type', 12);                       // percent | fixed
$table->unsignedInteger('value');                 // percent points, or paise
$table->unsignedBigInteger('min_order_paise')->default(0);
$table->unsignedInteger('max_redemptions')->nullable();
$table->unsignedInteger('redemptions')->default(0);
$table->timestamp('starts_at')->nullable();
$table->timestamp('expires_at')->nullable();
$table->boolean('is_active')->default(true);
```
Plus `coupon_order` or `orders.coupon_id` + `discount_paise` to record what was actually applied.

**`create_carts_table`** — replaces the session array so a cart survives logout and
follows the user across devices. `user_id` nullable + `session_id` for guests, merged on login.

---

## 4. Indexes

Add these when the catalogue passes a few hundred rows:

```php
$table->index(['is_published', 'created_at']);          // the default catalogue query
$table->index(['genre_id', 'is_published']);            // genre filter
$table->fullText(['title', 'description']);             // replaces LIKE %…%
// orders
$table->index(['user_id', 'status']);                   // hasPurchased(), library
```

`hasPurchased()` runs a `whereHas` on every book page and every reader request. Once the
reader ships it is the hottest query in the app — index `orders(user_id, status)` and
`order_items(order_id, book_id)` (the existing unique constraint covers the second).

---

## 5. Portability warning

If you are ever tempted by a free Postgres tier (Neon, Supabase) instead of a
MySQL-compatible one: **Postgres `LIKE` is case-sensitive.** The catalogue search in
[`HomeController`](../app/Http/Controllers/HomeController.php) silently returns nothing for
`harry` when the title is `Harry`. You would need `ILIKE` or `whereRaw('lower(title) like ?')`
throughout. [08-DEPLOYMENT.md](08-DEPLOYMENT.md) recommends TiDB Serverless specifically to
avoid this whole class of problem.
