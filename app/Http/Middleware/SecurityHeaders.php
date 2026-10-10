<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers, including the content security policy.
 *
 * The CSP is the one to be careful with: get `script-src` wrong and Razorpay's
 * checkout silently fails to open with nothing in the console that explains
 * why. Test a payment immediately after changing anything here.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Let the framework's own error pages keep their inline styles in
        // development, where the debug page is worth more than the policy.
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), interest-cohort=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        if (config('app.env') === 'production') {
            $headers['Content-Security-Policy'] = $this->policy();
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    private function policy(): string
    {
        $assets = array_filter([
            "'self'",
            config('store.asset_origin'),   // the R2 public URL, when there is one
        ]);

        $assets = implode(' ', $assets);

        return implode('; ', [
            "default-src 'self'",
            // Razorpay's checkout script, and Vite's inlined module preloads.
            "script-src 'self' 'unsafe-inline' https://checkout.razorpay.com",
            // The gateway renders its own checkout in an iframe.
            'frame-src https://api.razorpay.com https://checkout.razorpay.com',
            // blob: is the EPUB's own stylesheets. epub.js rewrites each
            // chapter's <link> to a blob URL built from the zip, so without it
            // every book renders with its typography stripped — centred title
            // pages left-aligned, drop caps gone — and the console fills with
            // one refusal per chapter. The sheets run inside a sandboxed
            // iframe with scripting off, and this policy still governs what
            // they may load.
            "style-src 'self' 'unsafe-inline' blob: https://fonts.bunny.net",
            'font-src \'self\' https://fonts.bunny.net data: blob:',
            // blob: is required by pdf.js; data: by the cover fallbacks.
            "img-src {$assets} data: blob:",
            "media-src {$assets} blob:",
            "connect-src {$assets} https://api.razorpay.com https://lumberjack.razorpay.com",
            // The reader renders EPUB chapters in a sandboxed iframe.
            "child-src 'self' blob:",
            "worker-src 'self' blob:",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            'upgrade-insecure-requests',
        ]);
    }
}
