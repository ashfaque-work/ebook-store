# 09 — SEO and Legal Pages

Two unrelated things in one document because they are both "content the site is missing" and
both block launch — the legal pages hard-block Razorpay approval, and the metadata gap makes
every shared link worthless in the channel this audience actually uses.

---

## Part 1 — Legal pages

### These are a payment blocker, not polish

Razorpay (and Cashfree, and PhonePe) will not activate an account without five reachable URLs.
Write them **on day one of Phase B**, before any integration code, because approval takes
2–7 working days and you cannot compress that.

| Page | Route | Must contain |
|---|---|---|
| Terms & Conditions | `/terms` | What is sold, licence granted (personal non-transferable), acceptable use, account termination, liability limits, governing law and jurisdiction (your city) |
| Privacy Policy | `/privacy` | Data collected, why, who it is shared with (Razorpay, Brevo, Sentry, Cloudflare), retention, user rights, cookies, contact for requests |
| Refund & Cancellation | `/refunds` | Window, conditions, how to request, processing time (5–7 working days), what is non-refundable |
| Shipping & Delivery | `/delivery` | For digital goods: delivered instantly to the library after payment, no physical shipment, access is permanent |
| Contact Us | `/contact` | Real email, real postal address, response time. A form alone is not enough — gateways look for an address. |

Add a footer link group to all five, plus the business name. Ship them as simple Inertia pages
with content in Blade or Markdown; do not build a CMS for this.

### Content notes specific to this store

**Licence, not sale.** Say plainly that the customer buys a personal, non-transferable licence
to read, not the right to redistribute. This is what makes watermarking
([05-READER.md](05-READER.md) §7) defensible.

**Refunds.** A blanket "no refunds on digital goods" reads badly and gateways dislike it. A
fair, enforceable policy: full refund within 7 days if the book has not been downloaded and
reading progress is under ~10%. You have `download_logs` and `reading_progress` to check
this objectively, which most stores cannot do.

**Access permanence.** State what happens to purchased books if the store closes. The honest
answer — download your files, they are yours — is better than silence, and it is a reason to
keep downloads enabled alongside the reader.

**Jurisdiction.** Name your city. Consult a CA or lawyer before launch; the GST and
consumer-protection surface in India is not something to improvise. This document is not
legal advice.

---

## Part 2 — SEO and sharing

### The actual problem

This audience arrives from **WhatsApp and Instagram**, not Google. A book link pasted into a
WhatsApp group currently renders as a bare URL with no image, no title, no price. That is the
single highest-impact fix in this document — higher than anything about search rankings.

### Per-page metadata

Nothing is set today except a `<title>` in
[app.blade.php](../resources/views/app.blade.php). Add to the Inertia root view, driven by
shared props:

```blade
<title inertia>{{ $page['props']['meta']['title'] ?? config('app.name') }}</title>
<meta name="description" content="{{ $meta['description'] }}">
<link rel="canonical" href="{{ $meta['canonical'] }}">

<meta property="og:type"        content="{{ $meta['type'] ?? 'website' }}">
<meta property="og:title"       content="{{ $meta['title'] }}">
<meta property="og:description" content="{{ $meta['description'] }}">
<meta property="og:image"       content="{{ $meta['image'] }}">
<meta property="og:url"         content="{{ $meta['canonical'] }}">
<meta name="twitter:card"       content="summary_large_image">
```

Share a `meta` prop from each controller. For a book:

```php
'meta' => [
    'title'       => "{$book->title} by {$book->author->name}",
    'description' => Str::limit(strip_tags($book->description), 155),
    'image'       => $book->og_image_url,          // 1200×630, see below
    'canonical'   => route('books.show', $book),
    'type'        => 'book',
],
```

**OG images.** A raw book cover is 2:3 and gets cropped badly to WhatsApp's 1.91:1. Generate a
1200×630 card per book — cover on the left, title, author and price on the right, on the `ink`
background from [06-DESIGN-SYSTEM.md](06-DESIGN-SYSTEM.md). A queued job on book publish,
cached to R2. This is a few hours of work and it changes how every shared link looks.

### Structured data

JSON-LD on book pages earns rich results:

```json
{
  "@context": "https://schema.org",
  "@type": "Book",
  "name": "…", "author": {"@type": "Person", "name": "…"},
  "bookFormat": "https://schema.org/EBook",
  "isbn": "…", "numberOfPages": 312, "inLanguage": "en",
  "aggregateRating": {"@type": "AggregateRating", "ratingValue": "4.2", "ratingCount": 128},
  "offers": {"@type": "Offer", "price": "299.00", "priceCurrency": "INR",
             "availability": "https://schema.org/InStock"}
}
```

Only emit `aggregateRating` once reviews exist — fabricated ratings are a manual-action risk.
Add `Organization` on the home page and `BreadcrumbList` on book pages.

### Crawlability

- **`sitemap.xml`** — generated, covering home, books, genres, authors, and the legal pages. Regenerate on publish.
- **`robots.txt`** — allow the catalogue; disallow `/cart`, `/checkout`, `/library`, `/orders`, `/admin`, `/read`.
- **Slugs** are already clean (`/books/{slug}`). Keep them stable; a slug change on an edit breaks every shared link. Consider storing old slugs and 301-ing.
- **Only published books** should be indexable — another argument for the `is_published` flag in [03-DATABASE.md](03-DATABASE.md).
- **`hreflang`** if you add Indian-language titles later.

### Content that earns traffic

Ranking for "buy ebooks india" is not winnable. Ranking for a specific title, author, or a
niche subject is. What works for a store this size:

- **Sample chapters are indexable content.** Each one is a real page of real prose on your domain. This is the strongest SEO asset you have and it doubles as the conversion lever from [05-READER.md](05-READER.md) §5.
- **Author pages** with a bio and their catalogue.
- **Genre pages** with a real description, not just a filtered grid.
- Reviews add unique text to book pages over time.

### Performance is SEO here

Core Web Vitals matter more than metadata on mobile connections. The relevant work is already
in [06-DESIGN-SYSTEM.md](06-DESIGN-SYSTEM.md) §11: explicit image dimensions, lazy-loading
below the fold, `fetchpriority="high"` on the hero cover, and keeping epub.js and pdf.js out
of the storefront bundle.

---

## Checklist

**Legal (blocks Razorpay)**
- [ ] Terms, Privacy, Refunds, Delivery, Contact written and routed
- [ ] Footer links to all five, with the business name and address
- [ ] Reviewed by a CA or lawyer

**SEO (blocks nothing, costs conversions)**
- [ ] `meta` prop shared from every public controller
- [ ] OG and Twitter tags in the root view
- [ ] Generated 1200×630 OG cards per book
- [ ] JSON-LD on book pages
- [ ] `sitemap.xml` and `robots.txt`
- [ ] Verified with WhatsApp, Facebook Sharing Debugger, and Google Rich Results Test
