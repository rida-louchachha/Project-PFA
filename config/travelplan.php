<?php

/*
|--------------------------------------------------------------------------
| TravelPlan application settings
|--------------------------------------------------------------------------
|
| Exposed to the Angular app through GET /api/settings (public, read-only).
|
*/

return [

    'currency' => [
        // ISO 4217 code stored in logements.devise (used for data / payments)
        'code' => env('CURRENCY_CODE', 'MAD'),
        // How amounts are displayed to users, e.g. "650.00 DH"
        'symbol' => env('CURRENCY_SYMBOL', 'DH'),
    ],

];
