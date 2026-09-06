# 06 — Design System

Every screen today is unmodified Breeze scaffolding: grey-100 background, white rounded card,
blue-600 button. It is competent and anonymous. This document replaces it with a direction
that belongs to this store specifically.

---

## 1. The brief

**Subject.** A digital bookshop selling and hosting ebooks, run solo from India.
**Audience.** Indian readers, mostly on mid-range Android phones, price-sensitive, arriving
from WhatsApp and Instagram links rather than search.
**Primary job.** Let someone find a book, *open it*, and keep reading — with buying as a step
in the middle rather than the destination.

That last point drives everything below. Most bookstore UIs are catalogues that end at a
purchase. This one is a reading place with a till in it.

---

## 2. The concept: Ink and Paper

Two materials, two modes, one hard transition between them.

**The store is ink.** Deep indigo, dense, cover-forward — the feeling of a shelf in low light,
where the covers are the only bright things. Indigo rather than the expected cream because a
storefront's job is to make covers glow, and covers are designed against white; a cream page
flattens them.

**The reader is paper.** When you open a book the ink drains out of the interface and you are
on a warm white page with nothing else on it. Crossing from browsing into reading is a
*material* change, not a route change.

That contrast is the identity. It is also functional: it makes "am I shopping or am I
reading?" answerable at a glance, and it gives the reader an uncluttered surface without
inventing a separate visual language for it.

### What I deliberately did not do

- **Not cream paper with a terracotta accent.** It is the default any generative tool reaches for on a "books" brief, and it makes covers look muddy.
- **Not a grid of identical rounded cards with the same soft shadow.** Books are objects with thickness. The shadow belongs to the cover, not to a container around it.
- **Not all-caps tracked-out eyebrow labels** above every section. Genre names are proper nouns; set them as such.
- **Not `01 / 02 / 03` numbered markers.** Nothing here is a sequence.
- **Not a gradient hero with a big number.** A bookshop's most characteristic moment is opening a book, so the hero shows an actual excerpt you can read.

---

## 3. Colour

Six named values carry the whole system. Defined in oklch for Tailwind v4, hex given for reference.

| Token | Hex | Role |
|---|---|---|
| `ink` | `#12172B` | Store background. Deep indigo-black, warm enough not to read as pure charcoal. |
| `ink-raised` | `#1C2340` | Cards, sheets, sticky bars sitting above `ink`. |
| `ink-line` | `#2C3556` | Hairlines, dividers, input borders. Never a shadow where a line will do. |
| `paper` | `#FCFBF7` | The reading surface, and the light-mode base. Warm but not beige. |
| `marigold` | `#F0A830` | The accent. Price, primary action, current position. Used once per view. |
| `verdigris` | `#3E8E7E` | Owned, in-library, paid, success. The only other chromatic value. |

Text on ink: `#E9E7E2` primary, `#98A0B8` muted.
Text on paper: `#1A1D2B` primary, `#5C6274` muted.
Marigold as **text on paper** fails contrast — use `#8A5A00` for that case, and reserve pure
marigold for fills with ink text on top.

Reader themes (see §7) are separate surfaces, not palette variants:
Paper `#FCFBF7` / Sepia `#F4ECD8` / Night `#14161C`.

```css
/* resources/css/app.css — Tailwind v4 is CSS-first, there is no JS config */
@import "tailwindcss";

@theme {
  --color-ink:        oklch(0.21 0.045 273);
  --color-ink-raised: oklch(0.27 0.052 273);
  --color-ink-line:   oklch(0.35 0.050 273);
  --color-paper:      oklch(0.98 0.008 85);
  --color-marigold:   oklch(0.78 0.148 68);
  --color-verdigris:  oklch(0.60 0.070 175);

  --font-display: "Archivo", ui-sans-serif, system-ui, sans-serif;
  --font-reading: "Literata", ui-serif, Georgia, serif;

  --radius-cover: 2px;      /* books have crisp edges */
  --radius-ui:    10px;     /* controls are soft */
}

@custom-variant dark (&:where([data-theme="dark"], [data-theme="dark"] *));
```

Note the two radii. A single border-radius applied to everything is one of the clearest
tells of a templated design. Covers are near-square because paper is; buttons are rounded
because they are controls.

---

## 4. Typography

Two families, sharply distinct in job.

**Archivo** — display and interface. A grotesque with a genuine width axis, so section
headings can be set condensed the way a book *spine* is condensed, without a second font.
That is the typographic idea: the store speaks in spines, the book speaks in pages.

> **As built:** the width axis is not in use. Bunny Fonts serves plain weights
> reliably and variable axes less so, so headings lean on tight tracking
> instead. Restoring the axis means self-hosting the variable file.

**Literata** — body copy and all reading. Commissioned specifically for screen reading and
used by Google Play Books. Choosing it is not a style preference; it is the typeface that was
designed for the exact job this product exists to do.

Both are on Google Fonts, and therefore on Bunny Fonts, which the app already uses.

```
Display / spine    Archivo Variable, wdth 88, wght 600, tracking -0.01em
Page heading       Archivo, wght 600, clamp(1.75rem, 1.2rem + 2vw, 2.75rem)
Section heading    Archivo, wght 600, 1.375rem
UI / controls      Archivo, wght 500, 0.9375rem
Book title (card)  Literata, wght 600, 1rem
Body / description Literata, wght 400, 1.0625rem / 1.65
Reading text       Literata, wght 400, 1.125rem / 1.75, max 66ch
Price              Archivo, wght 600, tabular-nums
Meta / muted       Archivo, wght 400, 0.8125rem
```

Scale is 1.25 on mobile widening to 1.333 above `md`, via `clamp()`.
Body measure caps at **66ch**; serif reading text gets the extra line-height it needs.
Numerals are tabular wherever prices align in a column.

**For Indian-language titles later:** Literata has no Devanagari. Pair with *Noto Serif
Devanagari* and scope it by `:lang(hi)`.

---

## 5. Layout

### The shelf

The organising device is a horizontal shelf, not a grid. Covers sit **on** a hairline rule
with their own drop shadow, scroll-snapped, sized so the next cover is half-visible — which
is what tells a person the row scrolls, better than any arrow button.

```
Genre name                                        See all →
┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐ ┌───
│      │ │      │ │      │ │      │ │      │ │
│cover │ │cover │ │cover │ │cover │ │cover │ │cov
│      │ │      │ │      │ │      │ │      │ │
└──────┘ └──────┘ └──────┘ └──────┘ └──────┘ └───
──────────────────────────────────────────────────  ← 1px ink-line
Title              Title      …
Author · ₹299      Author · ₹199
```

Native CSS `scroll-snap-type: x mandatory` — no carousel library. On desktop the same
component becomes a 5-up grid above `xl` where scrolling horizontally is worse than seeing
everything.

### Page frame

Content column maxes at `1200px`. The store is dense and left-aligned throughout; ragged
right. Only the reader justifies text, because that is what books do — and that difference is
deliberate, not an inconsistency.

### Responsive

Mobile-first and non-negotiable — this market is phones. Breakpoints `sm 640 / md 768 /
lg 1024 / xl 1280`. Every layout is designed at **360px first**, then allowed to expand.
The admin sidebar becomes an off-canvas drawer below `lg` (fixes A7).

---

## 6. Page specs

### Home

```
┌────────────────────────────────────────────────────────┐
│ ▣ Bookstore      Browse  Genres           ⌕  ♡  ⛉  [in]│
├────────────────────────────────────────────────────────┤
│                                                        │
│   ┌────────┐   "It was the hour of the unexpected      │
│   │        │    guest, and the rain had not stopped    │
│   │ cover  │    since morning…"                        │
│   │        │                                           │
│   │        │    The Unquiet House                      │
│   └────────┘    Anita Rau · Literary fiction           │
│                                                        │
│                 [ Read the first chapter ]   ₹299      │
│                                                        │
├────────────────────────────────────────────────────────┤
│  Continue reading            (signed in, has progress)  │
│  ┌────┐ Title ······················· 43% · 2h left    │
│  └────┘ ▓▓▓▓▓▓▓▓▓░░░░░░░░░░░░                          │
├────────────────────────────────────────────────────────┤
│  New this week                              See all →  │
│  [shelf]                                               │
│  Fiction                                    See all →  │
│  [shelf]                                               │
└────────────────────────────────────────────────────────┘
```

The hero is an **excerpt**, set in Literata at true reading size, with the cover beside it and
one action: read the first chapter. Not "Discover your next great read" over a gradient. The
store's argument for itself is the writing, so show the writing.

Below the fold: continue-reading (only when it applies), then genre shelves.

### Book detail

```
┌─────────────┬──────────────────────────────────────────┐
│             │  The Unquiet House                       │
│   cover     │  Anita Rau                               │
│   (sticky   │  ★★★★☆ 4.2 · 128 ratings · EPUB · 312pp  │
│    on lg)   │                                          │
│             │  ┌────────────────────────────────────┐  │
│             │  │ ₹299        [ Buy ]  [ Sample ]    │  │
│             │  └────────────────────────────────────┘  │
│             │                                          │
│             │  About this book …                       │
│             │  From the first chapter                  │
│             │  ┌──────────────────────────────────┐    │
│             │  │ two real paragraphs, readable    │    │
│             │  └──────────────────────────────────┘    │
│             │  Reviews · More by Anita Rau             │
└─────────────┴──────────────────────────────────────────┘
```

The excerpt block is on the page itself, not behind a modal. Buy panel is sticky on mobile as
a bottom bar. When owned, the panel collapses to a single `Read` button in verdigris.

### Library

Continue-reading first as one wide card, then owned books as a shelf with progress rings on
the covers. Empty state is an invitation, not an apology: "Nothing here yet. Browse the
shelves →".

### Cart / checkout

Digital goods: no quantity steppers, no shipping fields. Line items, total in ₹, one primary
action. Replace both `confirm()` calls with the existing `Modal` component.

### Admin

Same tokens, denser. Data tables with sticky headers, `tabular-nums`, right-aligned money.
Off-canvas nav below `lg`.

---

## 7. Reader

The one place the system inverts. Chrome fades after 3s of stillness and returns on pointer
move or tap. Nothing on screen but the page.

```
        ┌──────────────────────────────────────┐
        │  ‹        Chapter Four          Aa ⛭ │   ← fades away
        │                                      │
        │      Body text, Literata, 66ch,      │
        │      justified, generous leading.    │
        │                                      │
        │                                      │
        │  ▓▓▓▓▓▓▓▓░░░░░░░  43% · 14 min left  │   ← fades away
        └──────────────────────────────────────┘
```

Themes Paper / Sepia / Night; font size, line height, margin width; TOC drawer; tap-thirds,
swipe, arrow keys. All three themes clear 4.5:1 on body text. Full behaviour in
[05-READER.md](05-READER.md) §6.

---

## 8. Motion

**One orchestrated moment:** opening a book. The cover morphs into the first page via the
View Transitions API — `view-transition-name` on the cover image, matched on the reader page.
It is the moment the concept is about, so it is the only place motion is spent.

Everything else is response-only: a pressed button, an opening drawer, a toast. No
fade-and-slide-up on scroll, no hover lift on every card. `transform: scale(1.05)` on card
hover, currently in [Welcome.vue](../resources/js/Pages/Welcome.vue), goes.

All motion behind `@media (prefers-reduced-motion: no-preference)`.

---

## 9. Stack changes

| Add | Why |
|---|---|
| **Tailwind CSS v4** | CSS-first `@theme`, oklch, container queries. `@tailwindcss/vite` is *already in package.json* and unused — the v3 config and v4 plugin currently coexist (A11). |
| **reka-ui** | Accessible headless primitives for Vue (dialog, popover, tabs, tooltip). Keyboard and focus handling you should not write by hand. |
| **lucide-vue-next** | Replaces the hand-pasted SVG paths in both layouts. |
| **@vueuse/core** | `useDark`, `useStorage`, `useIntersectionObserver`, `useSwipe`. Deletes most of `useTheme.js`. |

**Inertia 2 features not yet used, all worth adopting in Phase D:**
- `Deferred` props — load reviews and related books after first paint.
- `prefetch` on book links — hover on desktop, viewport on mobile. The catalogue feels instant.
- `<WhenVisible>` — infinite scroll on the catalogue instead of numbered pagination.
- `useForm().processing` — fixes the double-submit surface of A4.

**Fonts:** self-host Archivo and Literata as `woff2` with `font-display: swap` and a
`preload` on the two used weights. The Bunny CDN call in
[app.blade.php](../resources/views/app.blade.php) currently loads Figtree, which after this
change nothing uses.

---

## 10. Component inventory

Build these once, in `resources/js/Components/`:

`BookCover` (aspect-locked, spine shadow, fallback for missing image, `view-transition-name`) ·
`BookCard` · `Shelf` (snap-scrolling row + heading + see-all) · `PriceTag` (₹, tabular) ·
`RatingStars` · `ProgressRing` · `Button` (primary/secondary/ghost/danger) · `Field` (label +
input + error, replacing the three separate Breeze components) · `Sheet` (reka-ui dialog:
drawer on mobile, modal on desktop) · `Toast` · `EmptyState` · `Skeleton` · `Pagination` ·
`DataTable` (admin).

Retire: `PrimaryButton`, `SecondaryButton`, `DangerButton`, `InputLabel`, `InputError`,
`TextInput` — six components that are one `Button` and one `Field`.

---

## 11. Quality floor

Not features — the baseline every screen meets before it is called done.

- Renders correctly at **360px**. Check the admin tables and the cart first; they break first.
- Visible keyboard focus on every interactive element. `:focus-visible`, marigold ring, 2px offset.
- Real `alt` text: `alt="Cover of The Unquiet House by Anita Rau"`, never `alt="Book Cover"`.
- Landmarks: one `<main>`, `<nav aria-label>`, headings in order.
- Contrast ≥ 4.5:1 body, ≥ 3:1 large text and UI borders — in **both** themes and all three reader themes.
- `prefers-reduced-motion` respected.
- Every image has explicit `width`/`height` to prevent layout shift.
- Covers lazy-loaded below the fold; the hero cover is `fetchpriority="high"`.
- Empty, loading and error states designed for every list. Errors say what happened and what to do; they do not apologise.

**Target:** Lighthouse ≥ 95 accessibility, ≥ 90 performance on a simulated mid-tier Android.
That last one is the real test for this audience.
