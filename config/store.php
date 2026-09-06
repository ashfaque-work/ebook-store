<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Business identity
    |--------------------------------------------------------------------------
    |
    | These appear on the legal pages, on receipts, and in the payment
    | gateway's KYC review. Razorpay checks that the name and contact details
    | on your site match the ones on your application, so fill these in before
    | you submit. Every value is read from the environment so the placeholders
    | below never reach production by accident.
    |
    */

    'legal_name' => env('STORE_LEGAL_NAME', 'Your Registered Business Name'),
    'trading_name' => env('STORE_TRADING_NAME', env('APP_NAME', 'eBook Store')),

    'support_email' => env('STORE_SUPPORT_EMAIL', 'support@example.com'),
    'support_phone' => env('STORE_SUPPORT_PHONE', ''),

    'address' => [
        'line1' => env('STORE_ADDRESS_LINE1', 'Street address'),
        'line2' => env('STORE_ADDRESS_LINE2', ''),
        'city' => env('STORE_CITY', 'City'),
        'state' => env('STORE_STATE', 'State'),
        'postcode' => env('STORE_POSTCODE', '000000'),
        'country' => env('STORE_COUNTRY', 'India'),
    ],

    // Courts of this city govern disputes. Name where you actually operate.
    'jurisdiction' => env('STORE_JURISDICTION', 'City'),

    'gstin' => env('STORE_GSTIN', ''),

    /*
    |--------------------------------------------------------------------------
    | Policy parameters
    |--------------------------------------------------------------------------
    |
    | The refund policy is enforced in code, not just described in prose:
    | download_logs and reading_progress make the conditions below objectively
    | checkable, which is unusual for a digital store and worth keeping honest.
    |
    */

    'refund_window_days' => (int) env('STORE_REFUND_WINDOW_DAYS', 7),
    'refund_max_read_percent' => (int) env('STORE_REFUND_MAX_READ_PERCENT', 10),
    'refund_processing_days' => env('STORE_REFUND_PROCESSING_DAYS', '5-7 working days'),

    'support_response_hours' => (int) env('STORE_SUPPORT_RESPONSE_HOURS', 48),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */

    'currency' => env('STORE_CURRENCY', 'INR'),

];
