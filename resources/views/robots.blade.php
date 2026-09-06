User-agent: *
Allow: /

# Nothing behind a login, and nothing that is one person's private state.
Disallow: /cart
Disallow: /checkout
Disallow: /library
Disallow: /orders
Disallow: /profile
Disallow: /admin
Disallow: /webhooks

# Samples are public on purpose and worth indexing; the full reader is not.
Disallow: /read/*/asset
Disallow: /read/*/progress

Sitemap: {{ route('sitemap') }}
