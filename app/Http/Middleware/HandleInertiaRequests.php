<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            // An explicit shape, so adding a column to `users` never leaks it
            // to the frontend by accident.
            'auth' => [
                'user' => $request->user()?->toInertiaArray(),
            ],
            'appName' => config('app.name'),
            // Business identity and policy parameters, so the legal pages and
            // receipts read from one place instead of hardcoded prose.
            'store' => [
                'legalName' => config('store.legal_name'),
                'tradingName' => config('store.trading_name'),
                'supportEmail' => config('store.support_email'),
                'supportPhone' => config('store.support_phone'),
                'address' => config('store.address'),
                'jurisdiction' => config('store.jurisdiction'),
                'gstin' => config('store.gstin'),
                'refundWindowDays' => config('store.refund_window_days'),
                'refundMaxReadPercent' => config('store.refund_max_read_percent'),
                'refundProcessingDays' => config('store.refund_processing_days'),
                'supportResponseHours' => config('store.support_response_hours'),
            ],
            'canRegister' => Route::has('register'),
            'toast' => fn () => $request->session()->get('toast'),
            'cartCount' => fn () => count(Session::get('cart', [])),
        ]);
    }
}
