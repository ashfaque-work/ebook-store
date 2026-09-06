# 08 — Deployment

Free first, paid when there is revenue. The plan below costs ₹0/month and does not need
rewriting when you upgrade — only the hosting row changes.

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

`composer require league/flysystem-aws-s3-v3`.

Then point the model constants at it:

```php
class Book
{
    public const FILE_DISK  = 'r2';        // was 'local'
    public const COVER_DISK = 'r2-public'; // new — see A5
}
```

**Deliver private files with presigned URLs, not PHP streaming.** A 20 MB EPUB streamed
through a 512 MB container is how you take the site down:

```php
return redirect()->away(
    Storage::disk(Book::FILE_DISK)->temporaryUrl($path, now()->addMinutes(10))
);
```

The ownership check still happens in your controller — R2 only ever sees a URL your code
decided to issue, valid for ten minutes.

**Migrating existing local files:** write a one-off `php artisan books:migrate-storage` that
copies each `file_path` and `cover_image_path` to R2 and rewrites the column. Run it once,
verify a download, then remove the command.

---

## 4. Render setup

Deploy from the repo with a `Dockerfile` (more predictable than the native PHP runtime,
because you control the extensions and the Nginx config).

```dockerfile
FROM php:8.2-fpm-alpine
RUN apk add --no-cache nginx supervisor icu-dev oniguruma-dev libzip-dev \
 && docker-php-ext-install pdo_mysql bcmath intl zip opcache
# bcmath is required by the Money helper in 03-DATABASE.md

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www
COPY composer.* ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Build assets in a node stage and copy public/build across
COPY . .
RUN php artisan config:cache && php artisan route:cache && php artisan view:cache
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
```

Build assets in a separate `node:22-alpine` stage and copy `public/build` — do not ship
`node_modules` into the runtime image.

**Release command:** `php artisan migrate --force`. Never `migrate:fresh` in production.

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

Add a `SecurityHeaders` middleware in Phase E:

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

`blob:` in `img-src` is required by pdf.js. Get the CSP wrong and Razorpay's modal fails to
open with no visible error — test checkout immediately after adding headers.

---

## 7. Launch checklist

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
