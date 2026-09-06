# 📚 eBook Store

A digital ebook storefront built with **Laravel 12**, **Inertia.js 2**, and **Vue 3**. Customers browse a catalogue, add books to a cart, check out, and download their purchased files from a personal library. Administrators manage authors, genres, and books from a protected admin panel.

> Payments currently run through a **mock gateway** (no external account needed). The payment layer is isolated behind a `PaymentGateway` contract so a real provider (e.g. Stripe) can be dropped in without touching the checkout flow.

---

## Features

- **Catalogue** with search (title/author), genre filter, and pagination.
- **Session cart** — add, remove, clear.
- **Checkout** — creates an order, charges via the (mock) gateway, records line items, and emails a receipt. Totals are always recomputed server-side; books already owned are skipped.
- **My Library** — customers download purchased ebooks through a **gated** route; files live on a **private** disk and are never publicly reachable.
- **Role-based admin** — only users with the `admin` role can reach `/admin`; everyone else gets a 403.
- **Auth** — registration, login, email verification, password reset, profile management (Laravel Breeze).

## Tech stack

| Layer | Tech |
|------|------|
| Backend | Laravel 12, PHP 8.2 |
| Frontend | Inertia.js 2 + Vue 3 + Tailwind CSS |
| Build | Vite |
| Auth | Laravel Breeze + Sanctum |
| Database | MySQL (dev/prod), SQLite in-memory (tests) |
| Tests | Pest |

---

## Local setup

### Requirements
- PHP 8.2+, Composer
- Node.js 18+ and npm
- MySQL 8+ (or MariaDB)

### Steps

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Configure the database in .env (DB_DATABASE=ebook_store, etc.),
#    create the schema, then:
php artisan migrate --seed

# 4. Link the public storage disk (for cover images)
php artisan storage:link

# 5. Run everything (server + queue worker + Vite)
composer dev
```

`composer dev` runs the PHP server, a queue listener (needed for the receipt email), and Vite concurrently. Visit **http://localhost:8000**.

### Seeded accounts

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@example.com` | `password` |
| Customer | `customer@example.com` | `password` |

---

## How it fits together

- **Roles** — `users.role` (`admin` \| `customer`). The column is **not** mass-assignable, so it can't be set via registration. The `admin` middleware alias (`EnsureUserIsAdmin`) guards the admin route group.
- **File storage** — cover images go to the **public** disk; ebook files go to the **private** `local` disk (`storage/app/private`, see `Book::FILE_DISK`). Downloads are served only by `LibraryController@download` after a `User::hasPurchased()` check.
- **Orders** — `orders` + `order_items` (with a price/title snapshot at purchase time). `OrderItem` keeps a `unique(order_id, book_id)` constraint.
- **Payments** — `App\Services\Payments\PaymentGateway` is bound to `FakePaymentGateway` in `AppServiceProvider`. To go live, implement the contract for your provider and re-bind it there.

---

## Tests

```bash
php artisan test
```

Tests run against SQLite in-memory and cover admin access control, mass-assignment safety, the checkout flow, and download gating.

---

## Going to production

Before deploying:

1. Set in `.env`: `APP_ENV=production`, `APP_DEBUG=false`, a real `APP_NAME` and `APP_URL`, and `APP_KEY` (via `php artisan key:generate`).
2. Configure a real mailer (`MAIL_MAILER=smtp`, …) so order receipts are actually delivered.
3. Run a queue worker (`php artisan queue:work`) — the receipt email is queued.
4. Build assets: `npm run build`.
5. Cache config/routes/views: `php artisan config:cache route:cache view:cache`.
6. Swap `FakePaymentGateway` for a real payment integration.

---

## Documentation

Planning and specification docs live in [`docs/`](docs/). Start with
[docs/README.md](docs/README.md) for the index, then
[docs/00-AUDIT.md](docs/00-AUDIT.md) (current state and bug register) and
[docs/01-ROADMAP.md](docs/01-ROADMAP.md) (what to build, in order).

---

## License

MIT.
