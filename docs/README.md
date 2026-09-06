# eBook Store — Documentation

Working documentation for the eBook Store project. These docs are the plan of record;
when code and docs disagree, fix one of them in the same commit.

**Last reviewed:** 2026-09-06 · **Branch:** `main` · **Stage:** pre-launch

---

## Read in this order

| # | Doc | What it answers |
|---|-----|-----------------|
| — | [00-AUDIT.md](00-AUDIT.md) | What is built, what is broken, ranked bug register |
| — | [01-ROADMAP.md](01-ROADMAP.md) | What to build, in what order, with acceptance criteria |
| — | [02-ARCHITECTURE.md](02-ARCHITECTURE.md) | How the system is wired now and where it is going |
| — | [03-DATABASE.md](03-DATABASE.md) | Target schema, every migration still to write |
| — | [04-PAYMENTS-INDIA.md](04-PAYMENTS-INDIA.md) | Gateway choice, Razorpay integration, GST, refunds |
| — | [05-READER.md](05-READER.md) | The in-browser reader — the product differentiator |
| — | [06-DESIGN-SYSTEM.md](06-DESIGN-SYSTEM.md) | Visual direction, tokens, components, page specs |
| — | [07-FEATURES.md](07-FEATURES.md) | Full feature backlog with priority and effort |
| — | [08-DEPLOYMENT.md](08-DEPLOYMENT.md) | Free-tier hosting plan, then the paid upgrade path |
| — | [09-SEO-LEGAL.md](09-SEO-LEGAL.md) | SEO, metadata, and the legal pages payments depend on |
| — | [10-TESTING-CI.md](10-TESTING-CI.md) | Test strategy, CI pipeline, quality gates |

`archive/` holds superseded documents, kept for history. Do not plan from them.

---

## The one-paragraph version

The store sells and delivers digital books today: catalogue, cart, mock checkout, gated
downloads, admin CRUD, role-based access. It cannot take real money, has no reading
experience, and its UI is unmodified Breeze scaffolding. The three things standing between
this repo and a live store are **a real payment gateway** (Razorpay — Stripe is not a
practical option for a domestic Indian business), **the legal pages that gateway approval
requires**, and **an in-browser reader**, without which this is a file locker rather than a
place people come to read.

## Working agreements

- Every phase in the roadmap ends with green tests. No phase is "done" while `php artisan test` is red.
- Money is stored as **integer paise**, never a float or decimal. See [03-DATABASE.md](03-DATABASE.md).
- Prices, totals and ownership are decided **server-side only**. The client sends book IDs, nothing else.
- The ebook file never becomes a public URL. Delivery is always gated by an ownership check.
- New UI follows [06-DESIGN-SYSTEM.md](06-DESIGN-SYSTEM.md). Do not add another Breeze-default screen.
