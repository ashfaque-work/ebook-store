<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    {{-- Deliberately self-contained: an error page must render even when the
         asset build is the thing that is broken. --}}
    <style>
        :root {
            --ink: #12172B;
            --ink-raised: #1C2340;
            --ink-line: #2C3556;
            --paper: #FCFBF7;
            --marigold: #F0A830;
            --muted: #98A0B8;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 2rem 1.5rem;
            background: var(--ink);
            color: #E9E7E2;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            line-height: 1.6;
        }
        main { max-width: 34rem; }
        .code {
            display: inline-block;
            font-size: 0.8125rem;
            letter-spacing: 0.04em;
            color: var(--marigold);
            border: 1px solid var(--ink-line);
            border-radius: 999px;
            padding: 0.25rem 0.75rem;
            margin-bottom: 1.25rem;
        }
        h1 {
            font-size: clamp(1.75rem, 1.2rem + 2vw, 2.5rem);
            font-weight: 600;
            letter-spacing: -0.02em;
            margin: 0 0 0.75rem;
        }
        p { color: var(--muted); margin: 0 0 2rem; max-width: 44ch; }
        .actions { display: flex; flex-wrap: wrap; gap: 0.75rem; }
        a {
            display: inline-block;
            padding: 0.625rem 1.125rem;
            border-radius: 10px;
            font-size: 0.9375rem;
            font-weight: 500;
            text-decoration: none;
            transition: background-color .15s ease, border-color .15s ease;
        }
        a.primary { background: var(--marigold); color: var(--ink); }
        a.primary:hover { background: #FFC257; }
        a.secondary { border: 1px solid var(--ink-line); color: #E9E7E2; }
        a.secondary:hover { background: var(--ink-raised); }
        a:focus-visible { outline: 2px solid var(--marigold); outline-offset: 2px; }
        @media (prefers-reduced-motion: reduce) { a { transition: none; } }
    </style>
</head>
<body>
    <main>
        <span class="code">@yield('code')</span>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            <a class="primary" href="{{ url('/') }}">Browse the shelves</a>
            @yield('actions')
        </div>
    </main>
</body>
</html>
