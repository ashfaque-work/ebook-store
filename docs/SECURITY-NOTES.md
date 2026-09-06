# Security notes

Known, accepted risks and the reasoning behind them. Revisit when the
circumstances in each entry change.

---

## Accepted: two advisories in `epubjs`

**Status:** open · **Reviewed:** 2026-09-07

`npm audit` reports two findings that both resolve only by upgrading `epubjs`
from `0.3.93` to `0.4.x`, a major version of the EPUB renderer:

| Package | Severity | Advisory |
|---|---|---|
| `@xmldom/xmldom` | high | XML injection via unsafe CDATA serialization |
| `epubjs` | moderate | transitive, same root cause |

**Why it is accepted for now.**

The attack needs a hostile EPUB file. Nothing in this application lets a
customer supply one: books and samples are uploaded only through
`/admin/books`, behind the `admin` middleware, by the store owner. There is no
user-upload path, and the reader renders chapters in a sandboxed iframe under
a CSP that forbids remote script.

So the realistic exposure is the store owner uploading a malicious book to
their own store — which is not a boundary worth a blind major upgrade of the
one library the entire reading experience depends on, on a reader that has not
yet been verified in a real browser.

**What would change this.**

- **Any feature that accepts an EPUB from someone other than an admin** — author self-publishing, bulk import from an untrusted source, user-supplied files of any kind. Then this becomes urgent, not accepted.
- Once the reader has been exercised against real EPUBs in a browser, upgrade to `epubjs` 0.4.x deliberately and re-test pagination, locations, theming and CFI positions.

**Not a mitigation, but worth knowing:** `docker/php.ini` and the CSP in
`app/Http/Middleware/SecurityHeaders.php` already constrain what injected
markup could do.

---

## Standing checks

- CI runs `composer audit` and `npm audit` on every push ([`.github/workflows/ci.yml`](../.github/workflows/ci.yml)). The job is informational — a new advisory should be visible the day it lands, but should not block a deploy that fixes something else.
- `npm audit --audit-level=critical` in CI so the two entries above do not mask a genuinely critical finding.
- Re-run `composer audit` and `npm audit` before any deploy that follows a gap in work.

## History

- **2026-09-07** — The PHP tree was a year stale, with 42 advisories across 13 packages including Guzzle. All resolved by `composer update` within existing constraints; no code changes were needed. 10 of 12 npm findings resolved by `npm audit fix`.
