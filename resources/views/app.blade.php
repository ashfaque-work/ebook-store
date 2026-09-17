@php
    $meta = $page['props']['meta'] ?? [];
    $siteName = config('store.trading_name') ?: config('app.name');
    $title = ($meta['title'] ?? null) ? $meta['title'] . ' · ' . $siteName : $siteName;
    $description = $meta['description'] ?? 'Digital books you can read in your browser, delivered the moment you buy them.';
    $canonical = $meta['canonical'] ?? url()->current();
    $image = $meta['image'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Inertia keeps the <title> in sync after the first load; these are
             what a crawler and a link preview see, so they are rendered
             server-side rather than by the client. --}}
        <title inertia>{{ $title }}</title>
        <meta name="description" content="{{ $description }}">
        <link rel="canonical" href="{{ $canonical }}">
        @if (! empty($meta['noindex']))
            <meta name="robots" content="noindex, nofollow">
        @endif

        {{-- A book link pasted into a WhatsApp group is how most people will
             meet this store. Without these it renders as a bare URL. --}}
        <meta property="og:site_name" content="{{ $siteName }}">
        <meta property="og:type" content="{{ $meta['type'] ?? 'website' }}">
        <meta property="og:title" content="{{ $meta['title'] ?? $siteName }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $canonical }}">
        @if ($image)
            <meta property="og:image" content="{{ $image }}">
        @endif
        <meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
        <meta name="twitter:title" content="{{ $meta['title'] ?? $siteName }}">
        <meta name="twitter:description" content="{{ $description }}">
        @if ($image)
            <meta name="twitter:image" content="{{ $image }}">
        @endif

        @if (! empty($meta['jsonLd']))
            <script type="application/ld+json">{!! json_encode($meta['jsonLd'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif

        <meta name="theme-color" content="#12172B">

        {{-- Browsers ask for /favicon.ico on their own, but the SVG stays crisp
             on high-density screens and the touch icon is what iOS saves. --}}
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=archivo:400,500,600,700|literata:400,400i,600,700&display=swap"
            rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
