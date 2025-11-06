<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Interswitch AutoPay Credentials
    |--------------------------------------------------------------------------
    |
    | These are your Interswitch AutoPay API credentials.
    |
    */
    'client_id' => env('AUTOPAY_CLIENT_ID'),
    'client_secret' => env('AUTOPAY_CLIENT_SECRET'),
    'terminal_id' => env('AUTOPAY_TERMINAL_ID', '3PSA0001'),

    /*
    |--------------------------------------------------------------------------
    | AutoPay API Endpoints
    |--------------------------------------------------------------------------
    |
    | The URLs for authentication and payment requests.
    |
    */
    'auth_url' => env('AUTOPAY_AUTH_URL', 'https://saturn.interswitchng.com/v2/authenticate?wsdl'),
    'request_url' => env('AUTOPAY_REQUEST_URL', 'https://saturn.interswitchng.com/v3/autopayservice?wsdl'),

    /*
    |--------------------------------------------------------------------------
    | Interswitch Processing Charge
    |--------------------------------------------------------------------------
    |
    | The switching/processing charge per transaction (in Naira).
    |
    */
    'switching_charge' => env('AUTOPAY_SWITCHING_CHARGE', 0),

    /*
    |--------------------------------------------------------------------------
    | Database Table Names
    |--------------------------------------------------------------------------
    |
    | Customize the table names used by the package.
    |
    */
    'tables' => [
        'transactions' => 'autopay_transactions',
        'transaction_details' => 'autopay_transaction_details',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable detailed logging of API requests and responses.
    |
    */
    'logging' => [
        'enabled' => env('AUTOPAY_LOGGING', true),
        'channel' => env('AUTOPAY_LOG_CHANNEL', 'stack'),
    ],
];
