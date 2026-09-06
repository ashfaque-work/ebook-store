<?php

namespace App\Http\Controllers;

use App\Support\Meta;
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
        'terms' => [
            'component' => 'Legal/Terms',
            'title' => 'Terms of Service',
            'description' => 'What you get when you buy a book here, and what we ask of you in return.',
        ],
        'privacy' => [
            'component' => 'Legal/Privacy',
            'title' => 'Privacy Policy',
            'description' => 'What we collect, why, and what you can do about it. Your payment details never reach our servers.',
        ],
        'refunds' => [
            'component' => 'Legal/Refunds',
            'title' => 'Refunds & Cancellations',
            'description' => 'A refund policy we can actually apply and check fairly, rather than a blanket refusal.',
        ],
        'delivery' => [
            'component' => 'Legal/Delivery',
            'title' => 'Delivery',
            'description' => 'Everything here is a digital book. Nothing is shipped, and delivery is immediate.',
        ],
        'contact' => [
            'component' => 'Legal/Contact',
            'title' => 'Contact Us',
            'description' => 'One person reads this inbox. Write in plain words and you will get a real reply.',
        ],
    ];

    public function show(string $page): Response
    {
        abort_unless(isset(self::PAGES[$page]), 404);

        return Inertia::render(self::PAGES[$page]['component'], [
            'meta' => Meta::make(
                title: self::PAGES[$page]['title'],
                description: self::PAGES[$page]['description'],
                canonical: route('legal', $page),
            )->toArray(),
            'lastUpdated' => '6 September 2026',
        ]);
    }
}
