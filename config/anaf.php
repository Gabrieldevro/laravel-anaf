<?php

return [
    // ANAF public "PlatitorTvaRest" endpoint (no account or key needed).
    'endpoint' => env('ANAF_ENDPOINT', 'https://webservicesp.anaf.ro/api/PlatitorTvaRest/v9/tva'),

    'timeout' => (int) env('ANAF_TIMEOUT', 10),

    // Company data changes rarely; cache each CUI to stay far below ANAF's 1 request/second limit.
    // Set to 0 to disable caching.
    'cache_ttl' => (int) env('ANAF_CACHE_TTL', 60 * 60 * 24),

    'cache_store' => env('ANAF_CACHE_STORE'),

    // Optional JSON endpoint for billing-form autocomplete: GET /{prefix}/{cui}.
    // Disabled when null. It is throttled; protect it further if you expose it publicly.
    'route' => [
        'prefix' => env('ANAF_ROUTE_PREFIX'),
        'middleware' => ['api', 'throttle:20,1'],
    ],
];
