# 02 — Architecture

## Stack

| Layer | Now | After the roadmap |
|---|---|---|
| Framework | Laravel 12, PHP 8.2 | unchanged |
| Frontend | Inertia 2 + Vue 3 + Tailwind 3 | Tailwind 4, reka-ui, lucide, VueUse |
| Build | Vite 7 | unchanged |
| Auth | Breeze + Sanctum | + `MustVerifyEmail`, 2FA on admin |
| Database | MySQL 8 | MySQL-compatible (TiDB Serverless in prod) |
| Session / cache / queue | `database` | `database`; queue `sync` on free tier |
| Files | `public` disk + private `local` disk | Cloudflare R2 (`s3` driver), two buckets |
| Payments | `FakePaymentGateway` | Razorpay + webhooks |
| Reader | — | epub.js / pdf.js |

---

## Request flow today

```
GET  /                     HomeController          catalogue, paginated, search + genre filter
GET  /books/{book:slug}    BookController@show     detail + in-cart + owned flags
GET  /cart                 CartController@index    session array of book IDs → re-fetched books
POST /cart/{book}          CartController@store
POST /checkout             CheckoutController      order + items + charge, in a transaction
GET  /library              LibraryController       books joined through paid orders
GET  /library/{book}/download                      ownership check → stream from private disk
GET  /orders, /orders/{order}                      history + receipt
/admin/{authors,genres,books}                      Route::resource, behind auth + admin
```

Shared Inertia props ([`HandleInertiaRequests`](../app/Http/Middleware/HandleInertiaRequests.php)):
`auth.user` (whole model — trim it), `toast`, `cartCount`.

### What is good and should be preserved

- The cart carries **book IDs only**. Prices are read from the database at checkout. Keep this invariant forever.
- Ebook files are on a private disk and reachable only through an ownership-checked route.
- `role` is outside `$fillable`, so registration cannot escalate.
- Order line items snapshot `title` and `price`, so catalogue edits never rewrite history.
- Payment is behind an interface, so the gateway swap does not touch checkout logic.

### What has to change structurally

**1. The payment contract is the wrong shape.** `charge(Order): PaymentResult` is synchronous.
Razorpay is create-session → client modal → callback → webhook. See [04-PAYMENTS-INDIA.md](04-PAYMENTS-INDIA.md).

**2. Money is `decimal(8,2)`.** Every Indian gateway speaks integer paise. Converting at the
boundary invites ₹0.01 mismatches that fail signature checks. Store paise. See [03-DATABASE.md](03-DATABASE.md).

**3. Authorization is inline.** `abort_unless($order->user_id === auth()->id(), 403)` is repeated
in two controllers and will be repeated in five more once the reader lands. Move to policies.

**4. Storage is local-disk-shaped.** `Storage::url()` on the default disk (A5) and streaming
downloads through PHP both break on a free host. Named disks + presigned URLs.

**5. There is no service layer.** `CheckoutController@store` is 70 lines of orchestration.
Once payments become asynchronous it will double. Extract `App\Services\Checkout\PlaceOrder`
and `FulfilOrder`, callable from both the HTTP callback and the webhook — they must produce
identical results.

---

## Target structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/            authors, genres, books, orders, dashboard, users
│   │   ├── Reader/           ReaderController, ProgressController, BookmarkController
│   │   ├── Webhooks/         RazorpayWebhookController
│   │   └── …                 storefront controllers
│   ├── Middleware/           EnsureUserIsAdmin, VerifyRazorpaySignature
│   ├── Requests/             form requests per write action
│   └── Resources/            BookResource, OrderResource — stop leaking whole models
├── Models/                   + Review, Coupon, ReadingProgress, Bookmark, Highlight, Payment
├── Policies/                 BookPolicy, OrderPolicy, ReviewPolicy
├── Services/
│   ├── Checkout/             PlaceOrder, FulfilOrder, CartService
│   ├── Payments/             PaymentGateway, Razorpay…, Fake…, PaymentSession, PaymentResult
│   ├── Reader/               AssetStreamer, ProgressSync, PdfWatermarker
│   └── Support/              Money
└── Jobs/                     SendReceipt, WatermarkPdf, GenerateInvoice
```

### Two boundaries worth naming

**Fulfilment is idempotent and single-sourced.** `FulfilOrder` marks the order paid, grants
library access and queues the receipt. It is called from the browser callback *and* the
webhook, may run twice, and must be a no-op the second time. Every payment bug in a store
like this comes from having two code paths that disagree about what "paid" means.

**The reader never sees a file path.** It asks for `/read/{book}/asset`, the server checks
ownership (or sample eligibility), and streams or presigns. No book identifier the client
holds can be turned into a file location.

---

## Data flow after Phase B

```
Cart (session, IDs only)
   │
   ▼
PlaceOrder ──────────► orders (pending, paise)  ──► Razorpay Orders API
   │                    order_items (snapshot)         │
   │                                                   ▼
   │                                          Razorpay Checkout modal
   │                                                   │
   │                        ┌──────────────────────────┴────────────┐
   │                        ▼                                       ▼
   │              POST /checkout/verify              POST /webhooks/razorpay
   │              (HMAC of order|payment)            (HMAC of raw body)
   │                        │                                       │
   └────────────────────────┴──────────► FulfilOrder ◄──────────────┘
                                          (idempotent)
                                              │
                              orders.status = paid, paid_at
                              payments row, receipt queued
                                              │
                                              ▼
                              Library + Reader access granted
```

Ownership is derived — `User::hasPurchased()` walks paid orders. Do not add a
`user_books` table; a derived check cannot drift out of sync with what was actually paid for.
The one exception is admin-granted access (comps, refund reversals), which needs an explicit
`entitlements` table if you build it — keep it separate rather than faking an order.

---

## Conventions

- **Controllers orchestrate, services decide.** No business rule lives in a controller.
- **Form requests validate.** No `$request->validate()` in controller bodies for new code.
- **Resources serialise.** Never pass an Eloquent model straight to Inertia once it has fields the client should not see.
- **Money is paise, everywhere**, until the last formatting step. `Money::fromPaise()` in, `->inr()` out.
- **Every webhook and callback is idempotent.** Assume it fires twice.
