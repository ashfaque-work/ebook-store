# 08 — Deployment

Free first, paid when there is revenue. The plan below costs ₹0/month and does not need
rewriting when you upgrade — only the hosting row changes.

> **Built and committed:** [`Dockerfile`](../Dockerfile), [`docker/`](../docker/),
> [`render.yaml`](../render.yaml), [`.env.production.example`](../.env.production.example),
> the `r2` / `r2-public` disks, the `SecurityHeaders` middleware with the CSP, and
> `php artisan books:migrate-storage`. What is left is provisioning the accounts and
> pasting in the keys — §7 is the order to do it in.

---

## 1. The trap to design around

Laravel needs persistent PHP, a database, **and persistent file storage**. Almost every free
tier gives you an ephemeral disk: the container is rebuilt on every deploy and every restart,
and `storage/app` goes with it. Uploaded covers and ebooks disappear.

This is not a problem to solve later. Move files to object storage **before** the first
deploy, or you will lose customer purchases to a routine redeploy.

---

## 2. Free stack

| Piece | Service | Free tier | Catch |
|---|---|---|---|
| App | **Render** web service | 512 MB, sleeps after 15 min idle | ~50 s cold start |
| Database | **TiDB Serverless** | 25 GiB | MySQL-compatible — no code changes |
| Files | **Cloudflare R2** | 10 GB, zero egress | S3-compatible |
| Email | **Brevo** | 300/day | or Resend, 3k/month |
| Errors | **Sentry** | 5k events/month | |
| Uptime | **UptimeRobot** | 50 monitors | also keeps Render awake |

**Why TiDB and not Neon/Supabase Postgres.** Postgres `LIKE` is case-sensitive. The catalogue
search in [`HomeController`](../app/Http/Controllers/HomeController.php) would silently return
nothing for `harry` against a title of `Harry`, and you would have to rewrite every `LIKE` to
`ILIKE`. TiDB speaks the MySQL wire protocol; nothing in the app changes. Alternatives if TiDB
does not suit: Aiven or Clever Cloud free MySQL (smaller quotas).

**Cold starts.** Render's free tier sleeps. A first visitor waits ~50 s, which is fatal for a
storefront. Point UptimeRobot at `/up` (the health route already exists in
[bootstrap/app.php](../bootstrap/app.php)) every 10 minutes to keep it warm. This is a
workaround, not a fix — it is the main reason to move to the $7/month paid tier once anything
sells.

---

## 3. Storage on R2

Two buckets, or one bucket with two prefixes:

- `covers/` — public, cached hard, served from R2's public URL
- `books/` — **private**, never public, accessed only via presigned URLs

```php
// config/filesystems.php
'r2' => [
    'driver' => 's3',
    'key' => env('R2_ACCESS_KEY_ID'),
    'secret' => env('R2_SECRET_ACCESS_KEY'),
    'region' => 'auto',
    'bucket' => env('R2_BUCKET'),
    'endpoint' => env('R2_ENDPOINT'),          // https://<account>.r2.cloudflarestorage.com
    'use_path_style_endpoint' => true,
    'url' => env('R2_PUBLIC_URL'),             // public dev URL or your custom domain
    'visibility' => 'private',
    'throw' => true,
],
```

The `league/flysystem-aws-s3-v3` adapter is already a dependency.

The disks are chosen by environment, not by editing code:

```
PRIVATE_DISK=r2
PUBLIC_DISK=r2-public
```

`Book::fileDisk()` and `Book::coverDisk()` read those, so the same image runs
locally on the filesystem and in production on R2.

**Deliver private files with presigned URLs, not PHP streaming.** A 20 MB EPUB streamed
through a 512 MB container is how you take the site down:

```php
return redirect()->away(
    Storage::disk(Book::fileDisk())->temporaryUrl($path, now()->addMinutes(10))
);
```

Both the download route and the reader's asset route already do this when the configured
disk uses the `s3` driver, and stream from the filesystem otherwise.

The ownership check still happens in your controller — R2 only ever sees a URL your code
decided to issue, valid for ten minutes.

**Migrating existing local files:** `php artisan books:migrate-storage --dry-run` lists what
would move; without the flag it streams each file across. It copies rather than moves and
leaves the stored paths untouched, so a failure halfway through leaves the store working on
the old disk. Verify a download before deleting anything.

---

## 4. Render setup

Deploy from the repo with the committed [`Dockerfile`](../Dockerfile) — more predictable than
a native PHP runtime, because you control the extensions and the nginx config.

It builds in three stages so `node_modules` and dev dependencies never reach the runtime
image: assets in `node:22-alpine`, vendor in `composer:2`, then a `php:8.2-fpm-alpine` runtime
running nginx and php-fpm under supervisor. `bcmath` is not optional — the money layer needs
it so rupees convert to paise exactly.

Config, route and view caches are built in [`docker/entrypoint.sh`](../docker/entrypoint.sh)
at boot rather than at image build time, because they bake in environment values and the
environment is not known until the container starts on the platform.

[`render.yaml`](../render.yaml) declares the service, the health check, the pre-deploy
migration and every environment variable. Anything marked `sync: false` is a secret you paste
into the dashboard.

**Pre-deploy command:** `php artisan migrate --force`. Never `migrate:fresh` in production.

**Health check path:** `/up`.

---

## 5. Production environment

```
APP_NAME="<your store name>"
APP_ENV=production
APP_DEBUG=false                      # A stack trace on a payment error leaks your keys
APP_URL=https://yourstore.onrender.com

DB_CONNECTION=mysql                  # TiDB
DB_HOST=gateway01.<region>.prod.aws.tidbcloud.com
DB_PORT=4000
MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt   # TiDB requires TLS

FILESYSTEM_DISK=r2
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=
R2_ENDPOINT=
R2_PUBLIC_URL=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync                # no worker on the free tier — see below

MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_FROM_ADDRESS=orders@yourdomain.com

RAZORPAY_KEY=rzp_live_...
RAZORPAY_SECRET=
RAZORPAY_WEBHOOK_SECRET=

SENTRY_LARAVEL_DSN=
```

**`QUEUE_CONNECTION=sync` on the free tier.** There is no second process to run
`queue:work`, so a queued receipt email would sit in the table forever. `sync` sends it
inline. The cost is a slower checkout response; the alternative is silently undelivered
receipts. Move to `database` + a worker the moment you are on a paid plan.

This is also why A6 matters: with `sync`, a mailer failure happens *inside* the checkout
request. Wrap it in try/catch or a Brevo outage becomes a checkout outage.

---

## 6. Security headers

[`app/Http/Middleware/SecurityHeaders.php`](../app/Http/Middleware/SecurityHeaders.php)
applies these on every response. The CSP is production-only, because locally the debug page
is worth more than the policy:

```
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
Content-Security-Policy:
  default-src 'self';
  script-src 'self' https://checkout.razorpay.com;
  frame-src https://api.razorpay.com;
  img-src 'self' data: blob: https://<your-r2-domain>;
  connect-src 'self' https://api.razorpay.com https://<your-r2-domain>;
```

`blob:` is required by pdf.js and by the EPUB reader's iframe; `R2_PUBLIC_URL` is folded in
automatically as an asset origin. Get the CSP wrong and Razorpay's modal fails to open with
no visible error — `DeploymentReadinessTest` asserts the gateway and reader origins survive,
but **test a real checkout immediately after changing it** anyway.

---

## 7. Launch checklist

Order matters: the storage move has to happen before the first deploy that uses R2, and
Razorpay KYC has to be submitted long before you need the live keys.

- [ ] `APP_DEBUG=false`, real `APP_KEY`, real `APP_NAME`/`APP_URL`
- [ ] `php artisan config:cache route:cache view:cache` in the image
- [ ] `npm run build`, assets served from `public/build`
- [ ] Migrations run; **seeders not run** in production except genres
- [ ] An admin user created via tinker, not the seeder's `password`
- [ ] Files on R2; a purchased download verified end-to-end
- [ ] A real ₹1 purchase on live keys, then refunded
- [ ] Webhook URL registered in the Razorpay dashboard and verified
- [ ] The five legal pages live — see [09-SEO-LEGAL.md](09-SEO-LEGAL.md)
- [ ] Transactional email arriving, not in spam (SPF + DKIM on the domain)
- [ ] Sentry receiving events; UptimeRobot pinging `/up`
- [ ] `robots.txt` and `sitemap.xml` reachable
- [ ] Database backup scheduled and one restore rehearsed

---

## 8. Upgrade path

When something sells:

| Step | Cost | Buys you |
|---|---|---|
| Render Starter | ~$7/mo | No cold starts. Do this first — it is the biggest UX gain per rupee. |
| Custom domain + Cloudflare | ~₹1,000/yr | Trust, and Razorpay prefers a real domain. |
| Render worker | ~$7/mo | Real queues; back to `QUEUE_CONNECTION=database`. |
| Managed MySQL (PlanetScale/RDS) | ~$15+/mo | Only when TiDB's free quota binds. |
| Redis | ~$10/mo | Cache and sessions off the database. Not before a few thousand daily users. |

Do not buy any of these before launch. The free stack genuinely serves your first customers;
the only thing it handles badly is the cold start, and UptimeRobot masks that.
