<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The policy pages.
 *
 * These are not decoration: Razorpay, Cashfree and every other Indian gateway
 * require Terms, Privacy, Refund, Delivery and Contact pages to be live and
 * reachable before they will activate an account. See docs/09-SEO-LEGAL.md.
 */
class LegalController extends Controller
{
    /** The pages, in the order they appear in the footer. */
    public const PAGES = [
        'terms' => ['component' => 'Legal/Terms', 'title' => 'Terms of Service'],
        'privacy' => ['component' => 'Legal/Privacy', 'title' => 'Privacy Policy'],
        'refunds' => ['component' => 'Legal/Refunds', 'title' => 'Refunds & Cancellations'],
        'delivery' => ['component' => 'Legal/Delivery', 'title' => 'Delivery'],
        'contact' => ['component' => 'Legal/Contact', 'title' => 'Contact Us'],
    ];

    public function show(string $page): Response
    {
        abort_unless(isset(self::PAGES[$page]), 404);

        return Inertia::render(self::PAGES[$page]['component'], [
            'lastUpdated' => '6 September 2026',
        ]);
    }
}
