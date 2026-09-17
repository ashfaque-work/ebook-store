# 📚 eBook Store

A digital bookshop built with **Laravel 12**, **Inertia 2** and **Vue 3**. Readers browse a
catalogue, read a free first chapter without an account, buy in ₹ through **Razorpay**, and
read the whole book in the browser — on any device, picking up where they left off.

Built for the Indian market: rupee-denominated, GST-aware, mobile-first, and deployable on
free hosting.

## Live demo

**[ebook.ashfaqueahmad.com](https://ebook.ashfaqueahmad.com)**

Hosted on Render's free tier, so the first request after a quiet spell takes a few seconds
to wake the container.

---

## What it does

**Reading is the product.** EPUB and PDF render in the browser with a table of contents,
three themes, adjustable type, bookmarks and highlights. Progress follows you between
devices. Every book can carry a free sample that anyone can open — no account, no wall.

**Payments are real.** Razorpay with webhooks as the source of truth, so a customer who pays
and closes the tab is still served. Money is stored as integer paise. GST is calculated
inclusive of the displayed price, with place-of-supply and a gapless per-financial-year
invoice series. Refunds are idempotent and revoke library access.

**The catalogue is a shelf, not a grid.** The home page opens a book rather than selling
one: the hero is an actual excerpt, set at reading size, with one action — read the first
chapter.

| Area | Detail |
|---|---|
| Reader | epub.js / pdf.js, lazy-loaded; progress, bookmarks, highlights, free samples |
| Payments | Razorpay REST + signature-verified webhooks; mock gateway for local and CI |
| Money | Integer paise end to end; `Money` value object; GST and invoice numbering |
| Catalogue | Publish/draft state, search, genre shelves, deferred loading, prefetch |
| Library | Owned books, continue-reading, gated downloads, throttled and logged |
| Admin | Books, authors, genres, orders, revenue tiles, full and partial refunds |
| Auth | Register, login, verification, password reset, profile, roles |
| Legal | Terms, Privacy, Refunds, Delivery, Contact — required for gateway approval |
| SEO | Per-page metadata, Open Graph, JSON-LD, generated sitemap and robots.txt |

---

## Stack

| Layer | Tech |
|---|---|
| Backend | Laravel 12, PHP 8.2 |
| Frontend | Inertia 2, Vue 3, Tailwind CSS 4, reka-ui, lucide |
| Reader | epub.js, pdf.js |
| Build | Vite 7, Prettier |
| Database | PostgreSQL (MySQL and SQLite also supported; CI runs all three) |
| Payments | Razorpay |
| Storage | Local disk, or any S3-compatible bucket (Cloudflare R2) |
| Tests | Pest — 256 tests, 1,004 assertions, green on Postgres, MySQL and SQLite |

---

## Local setup

Requires PHP 8.2+ with `bcmath` and `pdo_pgsql`, Composer, Node 22+, and PostgreSQL 14+.
(MySQL and SQLite work too — `DB_CONNECTION` is all that changes.)

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# Create the database named in .env, then:
php artisan migrate --seed
php artisan storage:link

composer dev          # server + queue listener + Vite
```

Visit <http://localhost:8000>.

Seeding creates a catalogue of ~26 books with free samples, one draft and one free title, so
every path is visible immediately.

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `password` |
| Customer | `customer@example.com` | `password` |

**Payments run on a simulated gateway** until `RAZORPAY_KEY` and `RAZORPAY_SECRET` are set,
so the whole store can be run and demoed before KYC clears. It implements the same four
steps as Razorpay and signs its callbacks with real HMAC, and the pay page lets you choose
what happens:

| Outcome | What it rehearses |
|---|---|
| Payment succeeds | Signed callback, verified against the gateway, order fulfilled |
| Bank declines the card | Order marked failed, cart preserved for a retry |
| Customer closes the window | Nothing charged, order still payable |
| Paid, browser never returns | Fulfilment by **webhook alone** — the path that saves a closed tab |

The last one is the reason this exists: it is the hardest thing to test against live keys
and the most expensive to get wrong. Every outcome drives the real controllers, signature
checks and fulfilment — none of them shortcut anything.

The simulated gateway is **refused in production**. A deploy that forgets the keys fails
loudly at checkout instead of quietly giving books away.

### Commands

```bash
php artisan test                  # the suite
vendor/bin/pint                   # PHP formatting
npm run format                    # JS/Vue formatting
npm run build                     # production assets
php artisan books:migrate-storage # move files onto object storage
php artisan store:preflight       # check the config before going live
```

---

## How it fits together

- **Ownership is derived, never stored.** `User::hasPurchased()` walks paid orders, so a refund revokes access without separate bookkeeping that could drift.
- **Fulfilment happens in one place.** `FulfilOrder` is called by both the browser callback and the webhook, and is idempotent — one paid order, one payment row, one invoice number, one receipt.
- **The client never sees a file path.** `file_path` and `sample_path` are hidden on the model; assets are streamed or presigned only after an ownership check.
- **Money never touches a float.** Rupees convert to paise with `bcmul` on the string, because `(int) (19.99 * 100)` is 1998.
- **Sold books are never deleted.** They are unpublished, so nobody loses something they paid for.

Architecture in [docs/02-ARCHITECTURE.md](docs/02-ARCHITECTURE.md).

---

## Deploying

Everything needed to deploy is committed: a three-stage `Dockerfile`, nginx/php-fpm/supervisor
config, `render.yaml`, `.env.production.example`, S3-compatible disks and a CSP.

What remains is provisioning accounts — object storage, database, SMTP, host — and submitting
Razorpay KYC, which takes 2–7 working days and needs the five legal pages this repo already
serves.

Your remaining tasks, in order: **[docs/YOUR-CHECKLIST.md](docs/YOUR-CHECKLIST.md)**.
Step-by-step deployment detail: [docs/08-DEPLOYMENT.md](docs/08-DEPLOYMENT.md).

> **Storage matters more than it looks.** Free hosting rebuilds the container on every deploy
> and takes `storage/app` with it. Move files to object storage *before* the first deploy or
> you will lose customer purchases to a routine redeploy.

---

## Documentation

Planning and specification docs live in [`docs/`](docs/). Start with
[docs/README.md](docs/README.md), then [docs/01-ROADMAP.md](docs/01-ROADMAP.md) for what is
done and what is left. [docs/SECURITY-NOTES.md](docs/SECURITY-NOTES.md) records known
accepted risks.

---

## Verified in a browser

The reader, the purchase flow and the responsive layouts were driven end to end in
Chromium on 2026-09-12: a real EPUB renders and paginates, the table of contents and the
three themes work, reading position survives a reload, a PDF renders, and a book was bought
through the mock gateway and read. Migrations apply, seed and roll back cleanly on MySQL.

That pass found four real bugs, all fixed — see the commit history.

## Not yet done

Recorded honestly rather than omitted:

- **PDF text selection and highlighting.** PDFs render to canvas; highlights are EPUB-only.
- **Auto-generated samples.** Admins upload a sample file per book.
- **PDF watermarking on download**, infinite scroll, and a Lighthouse pass — see [docs/01-ROADMAP.md](docs/01-ROADMAP.md).

---

## License

MIT.
