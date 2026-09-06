# 07 — Feature Backlog

Priority: **P0** blocks launch · **P1** launch-shaping · **P2** growth · **P3** later.
Effort: **S** ≤1 day · **M** 2–4 days · **L** ~1 week · **XL** 2+ weeks.
Phase refers to [01-ROADMAP.md](01-ROADMAP.md).

---

## Reading

| Feature | P | E | Phase | Notes |
|---|---|---|---|---|
| In-browser EPUB reader | P0 | L | C | epub.js. The product. |
| In-browser PDF reader | P0 | M | C | pdf.js on the same shell. |
| Reading progress + resume | P0 | M | C | Cross-device. Powers "Continue reading". |
| **Free sample chapter** | P0 | M | C | Highest-converting feature in this list. No login wall. |
| Reader themes + type controls | P1 | S | C | Paper / Sepia / Night, per user. |
| Bookmarks | P1 | S | C | |
| Highlights + notes | P2 | M | C | |
| PDF watermarking on download | P2 | M | C | Attribution beats encryption. |
| Time-remaining estimate | P2 | S | C | More useful than a percentage. |
| Offline reading (service worker) | P3 | L | — | Real value for Indian connectivity; large surface. |
| Text-to-speech | P3 | M | — | Web Speech API. |

## Payments and money

| Feature | P | E | Phase | Notes |
|---|---|---|---|---|
| Razorpay integration + webhooks | P0 | L | B | [04-PAYMENTS-INDIA.md](04-PAYMENTS-INDIA.md) |
| Integer paise migration | P0 | M | B | Do it before the gateway, not after. |
| Idempotent checkout (A4) | P0 | S | A | Blocks B. |
| GST fields + invoice series | P0 | M | B | Talk to a CA. |
| Refund flow (admin) | P0 | M | B | Must revoke library access. |
| Order receipt PDF | P1 | S | B | Attach to the confirmation email. |
| Coupons / discount codes | P2 | M | F | Schema in [03-DATABASE.md](03-DATABASE.md). |
| Gifting a book | P3 | M | F | |
| Merchant-of-record for global sales | P3 | L | F | Paddle / Lemon Squeezy, second gateway behind the same contract. |

## Catalogue and discovery

| Feature | P | E | Phase | Notes |
|---|---|---|---|---|
| Publish / draft state | P0 | S | A | Makes A1 solvable — unpublish instead of delete. |
| Seeded demo catalogue | P0 | S | A | A8. Fresh installs are empty today. |
| Sorting (new, price, rating) | P1 | S | D | |
| Featured / bestseller shelves | P1 | M | D | Needs `is_featured` + a sales count. |
| Reviews + ratings | P1 | M | F | Verified purchase only. Denormalise the average. |
| Author landing pages | P2 | S | F | Needs `authors.slug`. |
| Tags (many-to-many) | P2 | M | F | One `genre_id` is too coarse. |
| Series / collections | P2 | M | F | |
| Wishlist | P2 | S | F | |
| Related books | P2 | S | D | Same genre and author to start. |
| Full-text search | P2 | M | F | MySQL fulltext; Meilisearch when there is budget. |
| Price-range filter | P3 | S | F | |
| Bundles | P3 | M | F | |

## Storefront experience

| Feature | P | E | Phase | Notes |
|---|---|---|---|---|
| Design system + token layer | P0 | L | D | [06-DESIGN-SYSTEM.md](06-DESIGN-SYSTEM.md) |
| Responsive rebuild of both layouts | P0 | M | D | A7. |
| ₹ formatting everywhere | P0 | S | A | Hardcoded `$` today. |
| SEO metadata + OG tags | P0 | S | D | WhatsApp links render bare today — this market shares on WhatsApp. |
| Custom 404 / 500 | P1 | S | A | |
| Infinite scroll (`WhenVisible`) | P1 | S | D | |
| Prefetch on hover | P1 | S | D | |
| Skeletons and empty states | P1 | M | D | |
| Accessibility pass | P1 | M | D | Target ≥95. |
| Sitemap + robots.txt | P1 | S | D | |
| JSON-LD `Book` schema | P2 | S | F | Rich results in Google. |
| PWA install | P3 | M | — | |

## Accounts

| Feature | P | E | Phase | Notes |
|---|---|---|---|---|
| Email verification decision (A2) | P0 | S | A | Currently a no-op that the README claims works. |
| Persistent DB cart | P1 | M | F | Session cart dies on logout. |
| Social login (Google) | P2 | S | F | Meaningful signup-friction reduction here. |
| 2FA on admin accounts | P2 | M | F | |
| Account deletion export | P3 | S | — | |

## Admin

| Feature | P | E | Phase | Notes |
|---|---|---|---|---|
| Guard book deletion (A1) | P0 | S | A | Data-loss bug. |
| Orders list + detail | P0 | M | B | You cannot run a store without seeing orders. |
| Refunds | P0 | M | B | |
| Sales dashboard | P1 | M | F | Revenue, top titles, conversion. |
| User management | P2 | M | F | |
| Bulk CSV import | P2 | M | F | Matters once the catalogue passes ~100. |
| Sales / GST export | P2 | S | F | Your CA will ask. |
| Audit log | P3 | M | F | |

## Marketing

| Feature | P | E | Phase | Notes |
|---|---|---|---|---|
| Abandoned-cart email | P2 | M | F | Best measurable revenue lift post-launch. |
| Newsletter capture | P2 | S | F | |
| "New from an author you read" | P3 | M | F | Uses purchase history you already have. |
| Referral codes | P3 | M | F | |

## Operations

| Feature | P | E | Phase | Notes |
|---|---|---|---|---|
| Object storage (R2) | P0 | M | E | Free hosts have ephemeral disks — uploads vanish on redeploy. |
| Production env + caching | P0 | S | E | |
| Error tracking (Sentry) | P1 | S | E | |
| CI (Pest + Pint) | P1 | S | E | [10-TESTING-CI.md](10-TESTING-CI.md) |
| Security headers + CSP | P1 | S | E | Must allow `checkout.razorpay.com`. |
| Download throttle + logs (A10) | P1 | S | A | |
| Automated DB backups | P1 | S | E | |
| Uptime monitoring | P2 | S | E | |

---

## If you only build ten things

1. Fix A1 — stop destroying purchased files
2. Fix A4 — idempotent checkout
3. Legal pages
4. Razorpay + webhooks
5. Paise migration
6. Free sample chapters
7. EPUB reader + progress
8. Design system and responsive rebuild
9. SEO and OG tags
10. Deploy to the free stack

That is a real store people can find, trust, buy from, and read in.
