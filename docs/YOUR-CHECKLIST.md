# Your checklist

Everything left that needs a human, in the order it should happen. The code is
done; this is accounts, credentials and two things worth seeing with your own
eyes.

Tick these off in the file as you go.

---

## 0. Two things to verify locally (30 minutes, do these first)

Both are gaps I could not close: no browser and no MySQL were available.

- [ ] **Open a real book in the reader.** Put an actual `.epub` in `storage/app/private/books/`, attach it to a book through `/admin/books`, then open `/read/<slug>`. Check: pages turn, the table of contents works, the percentage moves, closing and reopening resumes in the same place. epub.js and pdf.js are wired but have never rendered a real file.
- [ ] **Run the migrations on MySQL.** `php artisan migrate:fresh --seed`. Ten migrations have only ever run against SQLite locally. CI covers MySQL, but you should see it once.

If either misbehaves, that is a real bug and worth fixing before anything below.

---

## 1. Fill in your details (15 minutes)

`.env` already has every key with a comment explaining it. A backup of your
previous file is at `.env.backup`.

- [ ] `STORE_LEGAL_NAME` — the name on your PAN/bank account
- [ ] `STORE_TRADING_NAME` — what customers see
- [ ] `STORE_SUPPORT_EMAIL` — an inbox you actually read
- [ ] `STORE_ADDRESS_LINE1`, `STORE_CITY`, `STORE_STATE`, `STORE_POSTCODE`
- [ ] `STORE_JURISDICTION` — the city whose courts govern disputes; it is printed in the Terms

Then check the pages read correctly: `/terms`, `/privacy`, `/refunds`,
`/delivery`, `/contact`. They render straight from these values.

**Leave `STORE_GST_ENABLED=false`.** An unregistered seller must not charge tax
or issue tax invoices.

---

## 2. Submit Razorpay KYC (start this on day one)

**The long pole: 2–7 working days.** Nothing else below is blocked by it, so
start it before the hosting work, not after.

- [ ] Deploy far enough that the five legal pages are reachable on a public URL (§4 gets you there), or point Razorpay at a temporary host
- [ ] Sign up at razorpay.com — a sole proprietorship with PAN and a bank account qualifies
- [ ] Give them the same business name and address you put in `.env` — a mismatch is the usual reason for rejection
- [ ] Submit the five policy URLs

While waiting, everything works on the mock gateway.

---

## 3. Talk to a CA (one hour, worth it)

- [ ] Whether you need GST registration at all, at your expected turnover
- [ ] The correct rate for your titles — ebooks are 5% where a print edition exists, 18% otherwise
- [ ] Whether your invoice series and place-of-supply handling are compliant

Only after that: set `STORE_GST_ENABLED=true`, `STORE_STATE_CODE` and your
`STORE_GSTIN`. Turning it on does not change what customers pay — only how the
invoice breaks the same amount down.

---

## 4. Provisioning (an afternoon)

Full detail and reasoning in [08-DEPLOYMENT.md](08-DEPLOYMENT.md). All free.

### Cloudflare R2 — do this before the first deploy

- [ ] Create a bucket, plus a public bucket (or public prefix) for covers
- [ ] Create an API token; fill `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_PUBLIC_BUCKET`, `R2_ENDPOINT`, `R2_PUBLIC_URL`
- [ ] `php artisan books:migrate-storage --dry-run`, then without the flag
- [ ] Set `PRIVATE_DISK=r2` and `PUBLIC_DISK=r2-public`, and verify a download still works

> Free hosting rebuilds the container on every deploy and takes `storage/app`
> with it. Skip this step and a routine redeploy deletes books people paid for.

### TiDB Serverless (25 GiB free)

- [ ] Create a cluster; fill `DB_HOST`, `DB_PORT=4000`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- [ ] Keep `MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt` — TiDB requires TLS

MySQL-compatible on purpose: Postgres would silently break catalogue search,
because its `LIKE` is case-sensitive.

### Brevo or Resend (email)

- [ ] Fill `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`
- [ ] Add SPF and DKIM records, or receipts land in spam

### Render

- [ ] Create a service from the committed `render.yaml`
- [ ] Paste in every secret marked `sync: false`
- [ ] Confirm the health check at `/up` passes
- [ ] Point UptimeRobot at `/up` every 10 minutes — the free plan sleeps after 15 minutes idle and a cold visitor otherwise waits ~50 seconds

---

## 5. Go live with payments

- [ ] Once KYC clears, set `RAZORPAY_KEY`, `RAZORPAY_SECRET` (live keys start `rzp_live_`)
- [ ] Register `https://<your-domain>/webhooks/razorpay` in the Razorpay dashboard for `payment.captured`, `payment.failed` and `refund.processed`
- [ ] Set `RAZORPAY_WEBHOOK_SECRET` to the secret you chose there
- [ ] **Buy something for ₹1 with a real card or UPI.** Confirm: the book appears in the library, a receipt arrives, the order shows an invoice number
- [ ] **Refund that ₹1 from `/admin/orders`.** Confirm the book disappears from the library
- [ ] Test checkout once more right after any change to the CSP — a wrong `script-src` makes Razorpay's modal fail to open with nothing in the console explaining why

---

## 6. Before you tell anyone about it

- [ ] `APP_DEBUG=false` and a real `APP_URL` in production
- [ ] Create your admin account with `php artisan tinker`, not the seeder's `password`
- [ ] Do **not** run `DatabaseSeeder` in production — it creates demo accounts
- [ ] Paste a book link into WhatsApp and check the preview shows the cover, title and price
- [ ] Open the store on your own phone and buy something
- [ ] Sentry DSN, if you want errors reported
- [ ] Schedule a database backup, and restore it once to prove it works

---

## Then

[07-FEATURES.md](07-FEATURES.md) has the growth backlog ranked by expected
return. The first three worth doing: **reviews**, **abandoned-cart email**, and
an **admin sales dashboard**.

[SECURITY-NOTES.md](SECURITY-NOTES.md) has one accepted risk to revisit if you
ever let someone other than an admin upload an EPUB.
