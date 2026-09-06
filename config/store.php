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

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Which disks hold the goods. Locally these are the filesystem; in
    | production they are object storage, because free hosting rebuilds the
    | container on every deploy and takes storage/app with it — which would
    | mean losing books people have paid for to a routine redeploy.
    |
    | Set PRIVATE_DISK=r2 and PUBLIC_DISK=r2-public once the bucket exists,
    | then run `php artisan books:migrate-storage`.
    |
    */

    'disks' => [
        'private' => env('PRIVATE_DISK', 'local'),
        'public' => env('PUBLIC_DISK', 'public'),
    ],

    // Where covers are served from, so the content security policy can allow
    // it. Blank while everything is served from our own origin.
    'asset_origin' => env('R2_PUBLIC_URL'),

    /*
    |--------------------------------------------------------------------------
    | GST
    |--------------------------------------------------------------------------
    |
    | Off by default: below the registration threshold you must NOT charge GST,
    | and an unregistered seller issuing tax invoices is a real problem. Turn it
    | on only once you hold a GSTIN.
    |
    | Prices are stored and displayed inclusive of tax, as Indian consumer
    | pricing expects, so enabling GST does not change what anyone pays - it
    | changes how the same amount is broken down on the invoice.
    |
    | Rates are per book (books.tax_rate): 5% where a printed edition of the
    | title exists, 18% otherwise. Confirm your own position with a CA.
    |
    */

    'gst_enabled' => filter_var(env('STORE_GST_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    // Our own state code, for place-of-supply. Same state as the buyer means
    // CGST + SGST; a different state means IGST.
    'state_code' => env('STORE_STATE_CODE', ''),

    'default_tax_rate' => (float) env('STORE_DEFAULT_TAX_RATE', 18.0),

];
